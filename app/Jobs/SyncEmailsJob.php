<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\SyncJob;

/**
 * Background job to sync Gmail messages for a user.
 *
 * This job is dispatched when the user triggers a sync from the dashboard.
 * It runs in the background (via queue worker) so the API response returns
 * immediately while emails are being fetched and stored.
 */
class SyncEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum attempts before marking as failed.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds for large syncs.
     */
    public int $timeout = 300;

    public function __construct(
        private User $user,
        private int $days
    ) {
    }

    public function handle(EmailSyncService $emailSyncService): void
    {
        // 1. Create the sync job record
        $syncJob = SyncJob::create([
            'user_id' => $this->user->id,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            Log::info("Starting email sync for user {$this->user->id}, last {$this->days} days");

            // 2. We pass the syncJob in down to track progress if needed, 
            // but for now we just get the total successfully synced count.
            $count = $emailSyncService->syncForUser($this->user, $this->days, $syncJob);

            // 3. Mark completed
            $syncJob->update([
                'status' => 'completed',
                'processed_messages' => $count,
                'completed_at' => now(),
            ]);

            Log::info("Completed email sync for user {$this->user->id}: {$count} emails synced");

        } catch (\Exception $e) {
            $syncJob->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error("Email sync failed for user {$this->user->id}: " . $e->getMessage());

            if (config('queue.default') !== 'sync') {
                throw $e;
            }
        }
    }
}
