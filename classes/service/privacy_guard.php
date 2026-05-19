<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy guard for teacher materials.
 *
 * Manual topic generation can use external providers.
 * Teacher materials/RAG are allowed only with a local Ollama endpoint.
 */
class privacy_guard {
    public static function provider(): string {
        return strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
    }

    public static function endpoint(): string {
        $endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));

        return $endpoint !== '' ? $endpoint : 'http://host.docker.internal:11434';
    }

    public static function is_local_endpoint(string $endpoint): bool {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return false;
        }

        $parts = parse_url($endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        return in_array($host, [
            'localhost',
            '127.0.0.1',
            '::1',
            'host.docker.internal',
            'ollama',
        ], true);
    }

    public static function is_local_ollama(): bool {
        return self::provider() === 'ollama' && self::is_local_endpoint(self::endpoint());
    }

    public static function can_use_teacher_materials_with_current_provider(): bool {
        return self::is_local_ollama();
    }

    public static function safe_embedding_endpoint(): string {
        $endpoint = self::endpoint();

        if (self::provider() === 'ollama' && self::is_local_endpoint($endpoint)) {
            return $endpoint;
        }

        return 'http://host.docker.internal:11434';
    }

    public static function teacher_materials_external_block_message(): string {
        return 'Privacy protection active: teacher materials and RAG context are not sent to external AI providers. '
            . 'Use Manual topic only with external providers such as DeepSeek/OpenAI/OpenRouter, or configure a local Ollama endpoint '
            . '(http://host.docker.internal:11434, http://localhost:11434 or http://127.0.0.1:11434) to use teacher materials.';
    }
}