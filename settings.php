<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
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
            'ollama' => 'Ollama local model',
            'openai' => 'OpenAI / compatible API',
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiskillnavigator/endpoint',
        'AI endpoint',
        'For Ollama use http://host.docker.internal:11434. For OpenAI use https://api.openai.com/v1.',
        'http://host.docker.internal:11434',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiskillnavigator/model',
        'AI model',
        'Example: llama3.2:1b for Ollama, or gpt-4o-mini for OpenAI.',
        'llama3.2:1b',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiskillnavigator/apikey',
        get_string('apikey', 'local_aiskillnavigator'),
        get_string('apikey_desc', 'local_aiskillnavigator'),
        ''
    ));
}