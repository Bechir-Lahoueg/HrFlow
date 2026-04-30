<?php
require 'vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$apiKey = 'AIzaSyBp-rk_2YuLS7XLcorytHyGvebHwoQ8BIE';
$model = 'gemini-1.5-flash-latest';

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$client = HttpClient::create();

try {
    $response = $client->request('POST', $url, [
        'json' => [
            'contents' => [
                ['parts' => [['text' => 'Hello, are you working?']]]
            ]
        ]
    ]);

    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Response: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
