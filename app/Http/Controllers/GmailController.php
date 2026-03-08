<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SyncJob;
use App\Services\GmailService;
use App\Jobs\SyncEmailsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Handles Google OAuth flow and email sync triggering.
 *
 * Flow:
 * 1. POST /connect-gmail → returns Google OAuth consent URL
 * 2. GET  /gmail-callback → exchanges auth code for tokens, stores them
 * 3. POST /sync-emails → dispatches background sync job
 * 4. GET  /sync-status → returns current sync progress
 */
class GmailController extends Controller
{
    public function __construct(
        private GmailService $gmailService
    ) {
    }

    /**
     * Generate and return the Google OAuth consent URL.
     *
     * POST /api/connect-gmail
     */
    public function connect(): JsonResponse
    {
        $url = $this->gmailService->getAuthUrl();

        return response()->json([
            'url' => $url,
        ]);
    }

    /**
     * Handle the OAuth callback from Google.
     * Exchanges the authorization code for tokens and stores them on the user.
     *
     * GET /api/gmail-callback?code=xxx
     */
    public function callback(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            // Exchange the authorization code for tokens
            $token = $this->gmailService->exchangeCode($request->code);

            // Set the full token on the client so API calls work
            $this->gmailService->getClient()->setAccessToken($token);
            $oauth2 = new \Google\Service\Oauth2($this->gmailService->getClient());
            $googleUser = $oauth2->userinfo->get();

            // Find or create the user
            $user = User::where('email', $googleUser->email)->first();

            $updateData = [
                'name' => $googleUser->name ?? $googleUser->email,
                'google_id' => $googleUser->id,
                'access_token' => $token['access_token'],
            ];

            // Only update refresh token if Google provided a new one
            if (isset($token['refresh_token'])) {
                $updateData['refresh_token'] = $token['refresh_token'];
            }

            if ($user) {
                $user->update($updateData);
            } else {
                $updateData['email'] = $googleUser->email;
                $user = User::create($updateData);
            }

            // Redirect to frontend with success flag
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect("{$frontendUrl}/integrations?connected=true&user_id={$user->id}");

        } catch (\Exception $e) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect("{$frontendUrl}/integrations?error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Trigger an email sync for the authenticated user.
     *
     * POST /api/sync-emails
     * Body: { "days": 7 | 15 | 30 }
     */
    public function syncEmails(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'required|integer|in:7,15,30',
        ]);

        // In a real app, get the authenticated user from the session/token
        // For this demo, we'll use the user_id parameter or the first connected user
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->isGmailConnected()) {
            return response()->json([
                'message' => 'Gmail not connected. Please connect your Gmail first.',
                'status' => 'error',
            ], 400);
        }

        // Rate limiting and active sync prevention
        $lastJob = SyncJob::where('user_id', $user->id)->latest()->first();
        if ($lastJob && $lastJob->status === 'processing') {
            return response()->json([
                'message' => 'A sync is already in progress.',
                'status' => 'error',
            ], 429);
        }

        if ($lastJob && $lastJob->status === 'completed' && $lastJob->started_at && $lastJob->started_at->addMinutes(5)->isFuture()) {
            return response()->json([
                'message' => 'Please wait 5 minutes between sync requests.',
                'status' => 'error',
            ], 429);
        }

        // Dispatch background sync job
        SyncEmailsJob::dispatch($user, $request->days);

        return response()->json([
            'message' => "Sync started for the last {$request->days} days.",
            'status' => 'syncing',
        ]);
    }

    /**
     * Get the current sync status.
     *
     * GET /api/sync-status
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated']);
        }

        $job = SyncJob::where('user_id', $user->id)->latest()->first();

        if (!$job) {
            return response()->json([
                'status' => 'idle',
                'message' => 'No sync in progress.',
            ]);
        }

        $message = "Waiting to start...";
        if ($job->status === 'processing') {
            $message = "Processed {$job->processed_messages} of {$job->total_messages} messages...";
        } elseif ($job->status === 'completed') {
            $message = "Successfully synced {$job->processed_messages} messages.";
            // If they hit our artificial limits (10 for 7d, 20 for 15d, 30 for 30d) notify them
            if (in_array($job->total_messages, [10, 20, 30])) {
                $message .= " (Free Tier: Showing the most recent {$job->total_messages} emails)";
            }
        } elseif ($job->status === 'failed') {
            $message = "Sync failed: {$job->error}";
        }

        return response()->json([
            'status' => $job->status === 'failed' ? 'error' : ($job->status === 'processing' ? 'syncing' : $job->status),
            'message' => $message,
            'synced_count' => $job->processed_messages,
            'total_messages' => $job->total_messages,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
    }

    /**
     * Get the full sync history for the user.
     *
     * GET /api/sync-history
     */
    public function syncHistory(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        $jobs = SyncJob::where('user_id', $user->id)->latest()->take(10)->get();

        return response()->json(['data' => $jobs]);
    }

    /**
     * Get the authentication status of the current user.
     *
     * GET /api/auth-status
     */
    public function authStatus(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            return response()->json([
                'is_connected' => false,
                'message' => 'Not authenticated',
            ]);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'google_id' => $user->google_id,
            'is_connected' => $user->isGmailConnected(),
        ]);
    }

    /**
     * Disconnect the current user's Gmail integration.
     *
     * POST /api/disconnect-gmail
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            return response()->json(['message' => 'Not authenticated'], 401);
        }

        $user->update([
            'access_token' => null,
            'refresh_token' => null,
        ]);

        return response()->json(['message' => 'Successfully disconnected']);
    }

    /**
     * Helper: Get authenticated user from request.
     * Uses X-User-Id header to identify the user. Each user's data
     * is strictly isolated — no fallback to other users.
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        // Check for Sanctum auth first
        if ($request->user()) {
            return $request->user();
        }

        // Identify user by X-User-Id header (set by frontend)
        $userId = $request->header('X-User-Id') ?? $request->query('user_id');
        if ($userId) {
            return User::find($userId);
        }

        return null;
    }
}
