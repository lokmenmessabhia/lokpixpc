<?php
require 'vendor/autoload.php';

use OpenAI\Laravel\Facades\OpenAI;

header('Content-Type: application/json');

function getChatGptResponse($userInput) {
    $apiKey = 'YOUR_OPENAI_API_KEY'; // Replace with your OpenAI API key

    try {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo', // Or 'gpt-4'
            'messages' => [
                ['role' => 'user', 'content' => $userInput],
            ],
        ]);

        return $response['choices'][0]['message']['content'];
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['message'])) {
        $userMessage = $input['message'];
        $aiResponse = getChatGptResponse($userMessage);
        echo json_encode(['response' => $aiResponse]);
    } else {
        echo json_encode(['error' => 'No message provided']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>