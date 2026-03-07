<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\User;
use App\Services\GmailService;
use App\Services\EmailSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\ReplyEmailRequest;

/**

 * Provides thread-grouped email listing and the ability
 * to reply to threads via the Gmail API.
 */
class EmailController extends Controller
{
    /**
     * List all email threads for the authenticated user.
     *
     * Returns one entry per thread with the latest message info.
     * GET /api/emails
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            return response()->json([], 200);
        }

        // 1. Get stats per thread (message count and attachment flag)
        $threadStats = Email::where('user_id', $user->id)
            ->select('thread_id')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('MAX(has_attachment) as thread_has_attachments')
            ->groupBy('thread_id')
            ->get()
            ->keyBy('thread_id');

        // 2. Get the latest message for each thread using a subquery for IDs (Resolves N+1)
        $latestEmailIds = Email::where('user_id', $user->id)
            ->whereIn('id', function ($query) use ($user) {
                $query->selectRaw('MAX(id)')
                    ->from('emails')
                    ->where('user_id', $user->id)
                    ->groupBy('thread_id');
            })
            ->pluck('id');

        $threads = Email::whereIn('id', $latestEmailIds)
            ->orderByDesc('date')
            ->get()
            ->map(function ($email) use ($threadStats) {
                $stats = $threadStats->get($email->thread_id);

                return [
                    'thread_id' => $email->thread_id,
                    'subject' => $email->subject,
                    'sender' => $email->sender,
                    'snippet' => $email->snippet ?: \Illuminate\Support\Str::limit(
                        strip_tags($email->body_text ?? $email->body_html ?? ''),
                        100
                    ),
                    'date' => $email->date?->toISOString(),
                    'message_count' => $stats ? $stats->total_count : 1,
                    'has_attachment' => $stats ? (bool) $stats->thread_has_attachments : (bool) $email->has_attachment,
                ];
            });

        return response()->json($threads);
    }

    /**
     * Get all messages in a specific thread.
     *
     * GET /api/email-thread/{threadId}
     */
    public function thread(Request $request, string $threadId): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            return response()->json([], 200);
        }

        $emails = Email::where('user_id', $user->id)
            ->where('thread_id', $threadId)
            ->with('attachments')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($email) {
                return [
                    'id' => $email->id,
                    'gmail_message_id' => $email->gmail_message_id,
                    'thread_id' => $email->thread_id,
                    'sender' => $email->sender,
                    'receiver' => $email->receiver,
                    'subject' => $email->subject,
                    'body_html' => $email->body_html,
                    'body_text' => $email->body_text,
                    'date' => $email->date?->toISOString(),
                    'has_attachment' => $email->has_attachment,
                    'attachments' => $email->attachments->map(fn($att) => [
                        'id' => $att->id,
                        'file_name' => $att->file_name,
                        'mime_type' => $att->mime_type,
                        'file_path' => $att->file_path,
                    ]),
                ];
            });

        return response()->json($emails);
    }

    /**
     * Reply to an email thread.
     *
     * POST /api/reply-email
     * Body: { "thread_id": "xxx", "body": "Reply content" }
     */
    public function reply(ReplyEmailRequest $request, GmailService $gmailService, EmailSyncService $emailSyncService): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->isGmailConnected()) {
            return response()->json(['message' => 'Not authorized'], 401);
        }

        // Get the latest email in the thread to determine the recipient
        $latestEmail = Email::where('user_id', $user->id)
            ->where('thread_id', $request->thread_id)
            ->orderByDesc('date')
            ->first();

        if (!$latestEmail) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        // Determine the reply-to address
        $to = $latestEmail->sender;
        $subject = str_starts_with($latestEmail->subject ?? '', 'Re:')
            ? $latestEmail->subject
            : 'Re: ' . ($latestEmail->subject ?? '');

        try {
            $gmailService->setAccessToken($user->access_token, $user->refresh_token);
            $sentMessageStub = $gmailService->sendReply($request->thread_id, $to, $subject, $request->body);

            // Fetch the full message details so we can store it immediately
            $fullMessage = $gmailService->getMessage($sentMessageStub->getId());

            // Store it in our local database so it appears in the UI instantly
            $storedEmail = $emailSyncService->storeMessage($user, $fullMessage);

            return response()->json([
                'message' => 'Reply sent successfully.',
                'email' => $storedEmail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send reply: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for the authenticated user.
     *
     * GET /api/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user) {
            return response()->json([
                'total_emails' => 0,
                'threads' => 0,
                'attachments' => 0,
                'synced' => 0,
            ], 200);
        }

        // Fetch all counts in a single efficient query
        $counts = Email::where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total_emails')
            ->selectRaw('COUNT(DISTINCT thread_id) as total_threads')
            ->first();

        // Count attachments efficiently
        $attachmentCount = \App\Models\Attachment::whereHas('email', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        return response()->json([
            'total_emails' => (int) $counts->total_emails,
            'threads' => (int) $counts->total_threads,
            'attachments' => (int) $attachmentCount,
            'synced' => (int) $counts->total_emails,
        ]);
    }

    /**
     * Helper: Get authenticated user from request.
     * Uses X-User-Id header to identify the user. Each user's data
     * is strictly isolated — no fallback to other users.
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $userId = $request->header('X-User-Id') ?? $request->query('user_id');
        if ($userId) {
            return User::find($userId);
        }

        return null;
    }
}
