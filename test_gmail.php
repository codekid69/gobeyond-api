<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}

$service = app(App\Services\GmailService::class);
$service->setAccessToken($user->access_token, $user->refresh_token);

$days = 30; // Check last 30 days
$after = now()->subDays($days)->format('Y/m/d');
echo "Querying after: {$after}\n";

try {
    $gmail = $service->getGmailService();
    $params = [
        'q' => "after:{$after}",
        'maxResults' => 10,
    ];

    $response = $gmail->users_messages->listUsersMessages('me', $params);
    $messages = $response->getMessages() ?? [];
    echo "Found " . count($messages) . " messages.\n";

    // Test without query
    $params2 = [
        'maxResults' => 10,
    ];
    $response2 = $gmail->users_messages->listUsersMessages('me', $params2);
    $messages2 = $response2->getMessages() ?? [];
    echo "Found " . count($messages2) . " messages without date filter.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
