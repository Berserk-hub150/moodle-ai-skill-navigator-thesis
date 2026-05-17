<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Creates embeddings through Ollama or an OpenAI compatible endpoint.
class embedding_client {
    private embedding_config $config;

    public function __construct(embedding_config $config) {
        $this->config = $config;
    }

    public function generate(string $text): ?array {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return $this->config->provider === 'ollama'
            ? $this->ollama($text)
            : $this->openai($text);
    }

    private function ollama(string $text): ?array {
        $url = rtrim($this->config->endpoint, '/') . '/api/embeddings';
        $body = (new embedding_http_client())->post($url, ['model' => $this->config->model, 'prompt' => $text], []);

        return isset($body['embedding']) && is_array($body['embedding']) ? $body['embedding'] : null;
    }

    private function openai(string $text): ?array {
        $endpoint = preg_replace('#/v1$#', '', preg_replace('#/chat/completions$#', '', rtrim($this->config->endpoint, '/')));
        $headers = $this->config->apikey !== '' ? ['Authorization: Bearer ' . $this->config->apikey] : [];
        $body = (new embedding_http_client())->post($endpoint . '/v1/embeddings', ['model' => $this->config->model, 'input' => $text], $headers);

        return isset($body['data'][0]['embedding']) && is_array($body['data'][0]['embedding'])
            ? $body['data'][0]['embedding']
            : null;
    }
}
