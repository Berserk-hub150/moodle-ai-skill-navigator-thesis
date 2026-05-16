<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Factory Method for AI provider creation.
 */
class ai_provider_factory {

    public static function create_from_config(): ai_provider_interface {
        $provider = strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
        $endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $model = trim((string) get_config('local_aiskillnavigator', 'model'));
        $apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));

        if ($provider === '') {
            $provider = 'ollama';
        }

        if ($endpoint === '') {
            $endpoint = 'http://host.docker.internal:11434';
        }

        if ($model === '') {
            $model = 'qwen2.5:3b';
        }

        if (in_array($provider, ['prototype', 'mock', 'demo'], true)) {
            return new prototype_ai_provider();
        }

        if (in_array($provider, ['openai', 'openai_compatible', 'openrouter', 'groq', 'deepseek'], true)) {
            return new openai_compatible_ai_provider($endpoint, $model, $apikey);
        }

        return new ollama_ai_provider($endpoint, $model, $apikey);
    }
}