<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Gmail;

/**
 * Service for Google OAuth and Gmail API interactions.
 *
 * Handles: OAuth URL generation, token exchange, token refresh,
 * and raw Gmail API calls (list messages, get message, send message).
 */
class GmailService
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->addScope(Gmail::GMAIL_READONLY);
        $this->client->addScope(Gmail::GMAIL_SEND);
        $this->client->addScope('openid');
        $this->client->addScope('email');
        $this->client->addScope('profile');
    }

    /**
     * Generate the Google OAuth consent URL.
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     */
    public function exchangeCode(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth error: ' . $token['error_description']);
        }

        return $token;
    }

    /**
     * Set the access token on the client. Automatically refreshes if expired.
     */
    public function setAccessToken(string $accessToken, ?string $refreshToken = null): void
    {
        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired() && $refreshToken) {
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            $this->client->setAccessToken($newToken);
        }
    }

    /**
     * Get the Gmail service instance.
     */
    public function getGmailService(): Gmail
    {
        return new Gmail($this->client);
    }

    /**
     * List message IDs from Gmail for a given time period.
     *
     * @param int $days Number of days to look back
     * @return array List of message objects with 'id' and 'threadId'
     */
    public function listMessages(int $days): array
    {
        $gmail = $this->getGmailService();

        \Illuminate\Support\Facades\Log::info("Fetching Gmail with custom ranges (subDays: {$days})");

        $messages = [];

        if ($days === 7) {
            // 7 days range: show up to 20 emails
            $params = [
                'q' => "newer_than:7d",
                'maxResults' => 20,
            ];
            $response = $gmail->users_messages->listUsersMessages('me', $params);
            $messages = array_merge($messages, $response->getMessages() ?? []);
        } elseif ($days === 15) {
            // 15 days range: 10 older emails and 10 newer emails to show 15 days old mails
            $oldParams = [
                'q' => "older_than:7d newer_than:15d",
                'maxResults' => 10,
            ];
            $responseOld = $gmail->users_messages->listUsersMessages('me', $oldParams);
            $messages = array_merge($messages, $responseOld->getMessages() ?? []);

            $newParams = [
                'q' => "newer_than:7d",
                'maxResults' => 10,
            ];
            $responseNew = $gmail->users_messages->listUsersMessages('me', $newParams);
            $messages = array_merge($messages, $responseNew->getMessages() ?? []);
        } elseif ($days === 30) {
            // 30 days range: preserve backend exhaustion and db perfectly (fetch 10 older, 10 newer)
            $oldParams = [
                'q' => "older_than:15d newer_than:30d",
                'maxResults' => 10,
            ];
            $responseOld = $gmail->users_messages->listUsersMessages('me', $oldParams);
            $messages = array_merge($messages, $responseOld->getMessages() ?? []);

            $newParams = [
                'q' => "newer_than:15d",
                'maxResults' => 10,
            ];
            $responseNew = $gmail->users_messages->listUsersMessages('me', $newParams);
            $messages = array_merge($messages, $responseNew->getMessages() ?? []);
        } else {
            // Fallback
            $params = [
                'q' => "newer_than:{$days}d",
                'maxResults' => 20,
            ];
            $response = $gmail->users_messages->listUsersMessages('me', $params);
            $messages = array_merge($messages, $response->getMessages() ?? []);
        }

        // Deduplicate
        $uniqueMessages = [];
        $seen = [];
        foreach ($messages as $msg) {
            if (!isset($seen[$msg->getId()])) {
                $seen[$msg->getId()] = true;
                $uniqueMessages[] = $msg;
            }
        }

        return $uniqueMessages;
    }

    /**
     * Get full message details by ID.
     *
     * @param string $messageId Gmail message ID
     * @return \Google\Service\Gmail\Message
     */
    public function getMessage(string $messageId)
    {
        $gmail = $this->getGmailService();
        return $gmail->users_messages->get('me', $messageId, ['format' => 'full']);
    }

    /**
     * Send a reply message within an existing thread.
     *
     * @param string $threadId Gmail thread ID
     * @param string $to Recipient email
     * @param string $subject Email subject (should include Re: prefix)
     * @param string $body HTML body content
     */
    public function sendReply(string $threadId, string $to, string $subject, string $body): \Google\Service\Gmail\Message
    {
        $gmail = $this->getGmailService();

        // Build raw MIME message
        $rawMessage = "To: {$to}\r\n";
        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $rawMessage .= $body;

        $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $message = new \Google\Service\Gmail\Message();
        $message->setRaw($encodedMessage);
        $message->setThreadId($threadId);

        return $gmail->users_messages->send('me', $message);
    }

    /**
     * Get the underlying Google Client (for user info, etc.)
     */
    public function getClient(): GoogleClient
    {
        return $this->client;
    }
}
