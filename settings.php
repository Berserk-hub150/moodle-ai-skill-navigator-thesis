<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

/** @var admin_root $ADMIN */
/** @var bool $hassiteconfig */
global $ADMIN;

if (empty($hassiteconfig)) {
    return;
}

$settings = new admin_settingpage(
    'local_aiskillnavigator',
    get_string('settings', 'local_aiskillnavigator')
);

$ADMIN->add('localplugins', $settings);

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/provider',
    get_string('provider', 'local_aiskillnavigator'),
    get_string('provider_desc', 'local_aiskillnavigator'),
    'ollama',
    [
        'ollama' => 'Ollama',
        'openrouter' => 'OpenRouter',
        'openai' => 'OpenAI / compatible API',
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/endpoint',
    'AI endpoint',
    'For Ollama via Colab tunnel use your Cloudflare URL, for example https://xxxx.trycloudflare.com. For local Ollama from Docker use http://host.docker.internal:11434.',
    'http://host.docker.internal:11434',
    PARAM_URL
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/model',
    'AI model',
    'Example for Ollama: qwen2.5:3b. Example for OpenAI: gpt-4o-mini.',
    'qwen2.5:3b',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingmodel',
    'Embedding model',
    'Model used for RAG embeddings. For Ollama: nomic-embed-text (recommended). For OpenAI: text-embedding-3-small.',
    'nomic-embed-text',
    PARAM_TEXT
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/apikey',
    get_string('apikey', 'local_aiskillnavigator'),
    'Only required for OpenRouter/OpenAI-compatible APIs. Leave empty for Ollama.',
    ''
));
