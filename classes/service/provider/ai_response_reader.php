<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

// Reads provider responses without leaking transport details.
class ai_response_reader {
    public function answer(array $response, string $format): string {
        if (!$response['ok']) {
            return $this->error($response);
        }

        $body = $response['body'];
        $answer = $format === 'ollama'
            ? trim((string) ($body['message']['content'] ?? ''))
            : trim((string) ($body['choices'][0]['message']['content'] ?? ''));

        return $answer !== '' ? $answer : 'Errore AI API: risposta valida ma contenuto mancante.';
    }

    private function error(array $response): string {
        $body = $response['body'] ?? null;
        if (!is_array($body)) {
            return 'Errore AI API: risposta non JSON. HTTP status: ' . ($response['status'] ?? 0);
        }

        $message = $body['error'] ?? $body['message'] ?? json_encode($body, JSON_UNESCAPED_UNICODE);
        return 'Errore AI API HTTP ' . ($response['status'] ?? 0) . ': ' . $message;
    }
}
