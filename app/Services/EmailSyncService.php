<?php

namespace App\Services;

use App\Models\User;
use App\Models\Email;
use App\Models\Attachment;
use App\Models\SyncJob;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing Gmail messages into the local database.
 *
 * Parses raw Gmail API message objects, extracts headers, body parts,
 * and attachment metadata, and stores everything using Eloquent models.
 */
class EmailSyncService
{
    private GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Sync emails for a user for the given number of days.
     *
     * @param User $user The authenticated user
     * @param int  $days  Number of days to look back
     * @param SyncJob|null $syncJob Optional sync job model to update progress
     * @return int Number of emails synced
     */
    public function syncForUser(User $user, int $days, ?SyncJob $syncJob = null): int
    {
        // Configure Gmail client with user's tokens
        $this->gmailService->setAccessToken($user->access_token, $user->refresh_token);

        // Clear existing emails for this user so only the selected range is shown.
        // Attachments are deleted via cascade (foreign key constraint).
        \App\Models\Attachment::whereHas('email', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->delete();
        Email::where('user_id', $user->id)->delete();

        // Fetch message list from Gmail for the specified date range
        $messages = $this->gmailService->listMessages($days);
        $totalMessages = count($messages);
        $syncedCount = 0;

        if ($syncJob) {
            $syncJob->update(['total_messages' => $totalMessages]);
        }

        foreach ($messages as $messageStub) {
            try {
                $messageId = $messageStub->getId();

                // Fetch full message details and store
                $fullMessage = $this->gmailService->getMessage($messageId);
                $this->storeMessage($user, $fullMessage);
                $syncedCount++;

                if ($syncJob && $syncedCount % 10 === 0) {
                    $syncJob->update(['processed_messages' => $syncedCount]);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to sync message {$messageStub->getId()}: " . $e->getMessage());
            }
        }

        return $syncedCount;
    }

    /**
     * Parse and store a full Gmail message into the database.
     */
    public function storeMessage(User $user, $message): \App\Models\Email
    {
        $headers = $this->extractHeaders($message->getPayload()->getHeaders());

        // Simple categorization based on subject
        $subject = strtolower($headers['subject'] ?? '');
        $category = 'General';
        if (str_contains($subject, 'receipt') || str_contains($subject, 'invoice') || str_contains($subject, 'payment')) {
            $category = 'Finance';
        } elseif (str_contains($subject, 'meeting') || str_contains($subject, 'invite')) {
            $category = 'Meetings';
        } elseif (str_contains($subject, 'newsletter') || str_contains($subject, 'unsubscribe')) {
            $category = 'Newsletters';
        }

        $rawLabels = $message->getLabelIds() ?? [];
        $rawLabels[] = "CATEGORY_$category"; // Append custom category

        $email = Email::create([
            'user_id' => $user->id,
            'gmail_message_id' => $message->getId(),
            'thread_id' => $message->getThreadId(),
            'sender' => $headers['from'] ?? '',
            'receiver' => $headers['to'] ?? '',
            'subject' => $headers['subject'] ?? '',
            'snippet' => $message->getSnippet(),
            'labels' => json_encode($rawLabels),
            'body_html' => $this->extractBody($message->getPayload(), 'text/html'),
            'body_text' => $this->extractBody($message->getPayload(), 'text/plain'),
            'date' => isset($headers['date']) ? \Carbon\Carbon::parse($headers['date']) : null,
            'has_attachment' => $this->hasAttachments($message->getPayload()),
        ]);

        // Store attachment metadata
        $this->storeAttachments($email, $message->getPayload());

        return $email;
    }

    /**
     * Extract header values by name into a keyed array.
     */
    private function extractHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            $name = strtolower($header->getName());
            if (in_array($name, ['from', 'to', 'subject', 'date'])) {
                $result[$name] = $header->getValue();
            }
        }
        return $result;
    }

    /**
     * Recursively extract body content from message payload parts.
     *
     * Gmail messages can have nested multipart structures. This method
     * walks the part tree to find the body matching the requested MIME type.
     *
     * @param mixed  $payload  Gmail message payload
     * @param string $mimeType Target MIME type (text/html or text/plain)
     * @return string|null Decoded body content
     */
    private function extractBody($payload, string $mimeType): ?string
    {
        // Check direct body on the payload
        if ($payload->getMimeType() === $mimeType) {
            $data = $payload->getBody()->getData();
            if ($data) {
                return base64_decode(strtr($data, '-_', '+/'));
            }
        }

        // Recurse into parts (multipart/alternative, multipart/mixed, etc.)
        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                $body = $this->extractBody($part, $mimeType);
                if ($body) {
                    return $body;
                }
            }
        }

        return null;
    }

    /**
     * Check if the payload contains any file attachments.
     */
    private function hasAttachments($payload): bool
    {
        $parts = $payload->getParts();
        if (!$parts)
            return false;

        foreach ($parts as $part) {
            if ($part->getFilename()) {
                return true;
            }
            // Recurse into nested multiparts
            if ($this->hasAttachments($part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store attachment metadata from the message payload.
     */
    private function storeAttachments(Email $email, $payload): void
    {
        $parts = $payload->getParts();
        if (!$parts)
            return;

        foreach ($parts as $part) {
            if ($part->getFilename()) {
                Attachment::create([
                    'email_id' => $email->id,
                    'file_name' => $part->getFilename(),
                    'mime_type' => $part->getMimeType(),
                    'file_path' => null, // Attachments are stored as metadata only
                ]);
            }

            // Recurse into nested multiparts
            $this->storeAttachments($email, $part);
        }
    }
}
