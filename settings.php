<?php

defined('MOODLE_INTERNAL') || die();

global $ADMIN, $PAGE;

if (empty($hassiteconfig)) {
    return;
}

$settings = new admin_settingpage(
    'local_aiskillnavigator',
    'AI Skill Navigator'
);

$ADMIN->add('localplugins', $settings);

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/mainheading',
    'AI provider configuration',
    'Configure the AI provider used by the plugin.'
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/provider',
    'Provider',
    '',
    'openrouter',
    [
        'openrouter' => 'Multi-LLM API gateway',
        'ollama' => 'Local LLM',
        'openai_compatible' => 'OpenAI-compatible API',
        'deepseek' => 'Direct provider API',
        'custom_http' => 'Custom HTTP JSON',
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/model',
    'Model',
    '',
    'deepseek/deepseek-chat',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/apikey',
    'API key',
    '',
    ''
));

if (!(defined('CLI_SCRIPT') && CLI_SCRIPT)) {
    $PAGE->requires->js_init_code("
(function() {
    function clean() {
        var style = document.createElement('style');
        style.innerHTML = `
            .form-defaultinfo,
            .form-shortname,
            .form-description,
            .adminsettings .form-item .form-label .text-muted,
            .adminsettings .form-item .form-setting .text-muted {
                display: none !important;
            }

            .adminsettings .form-item {
                margin-bottom: 8px !important;
                padding-bottom: 0 !important;
            }

            .adminsettings .form-label {
                width: 150px !important;
                min-width: 150px !important;
                font-weight: 600 !important;
                font-size: 15px !important;
            }

            .adminsettings input[type='text'],
            .adminsettings input[type='password'],
            .adminsettings select {
                max-width: 390px !important;
                width: 390px !important;
            }

            .adminsettings .settingsform {
                max-width: 700px !important;
            }
        `;
        document.head.appendChild(style);

        document.querySelectorAll('.form-defaultinfo').forEach(function(e) { e.remove(); });
        document.querySelectorAll('.form-shortname').forEach(function(e) { e.remove(); });
    }

    window.addEventListener('load', clean);
    setTimeout(clean, 300);
})();
");
}