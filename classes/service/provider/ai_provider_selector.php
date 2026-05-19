<?php

namespace local_aiskillnavigator\service\provider;

use local_aiskillnavigator\service\custom_http_ai_provider;
use local_aiskillnavigator\service\deepseek_ai_provider;
use local_aiskillnavigator\service\ollama_ai_provider;
use local_aiskillnavigator\service\openai_compatible_ai_provider;
use local_aiskillnavigator\service\prototype_ai_provider;
use local_aiskillnavigator\service\ai_provider_interface;

defined('MOODLE_INTERNAL') || die();

class ai_provider_selector {
    public function create(ai_provider_config $config): ai_provider_interface {
        if ($config->provider === 'custom_http') {
            return new custom_http_ai_provider(
                $config->endpoint,
                $config->model ?: 'default',
                $config->apikey,
                $config->customrequesttemplate,
                $config->customheadersjson,
                $config->customresponsepath
            );
        }

        if ($config->provider === 'deepseek') {
            return new deepseek_ai_provider(
                $config->endpoint ?: 'https://api.deepseek.com',
                $config->model ?: 'deepseek-chat',
                $config->apikey
            );
        }

        if (in_array($config->provider, ['openai', 'openai_compatible', 'openrouter', 'groq'], true)) {
            return $config->endpoint === ''
                ? new prototype_ai_provider()
                : new openai_compatible_ai_provider($config->endpoint, $config->model ?: 'default', $config->apikey);
        }

        if ($config->provider === 'ollama') {
            return new ollama_ai_provider(
                $config->endpoint ?: 'http://host.docker.internal:11434',
                $config->model ?: 'qwen2.5:3b',
                $config->apikey
            );
        }

        return new prototype_ai_provider();
    }
}