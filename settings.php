<?php

defined('MOODLE_INTERNAL') || die();

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
    'Configure the AI provider used by the plugin. Prototype mode works without external API keys and is recommended for first installation tests.'
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/provider',
    'Provider',
    'Choose where AI requests are sent.',
    'prototype',
    [
        'prototype' => 'Prototype/demo provider - no external calls',
        'gemini' => 'Google Gemini API',
        'openrouter' => 'OpenRouter multi-LLM gateway',
        'ollama' => 'Local Ollama',
        'openai' => 'OpenAI API',
        'openai_compatible' => 'Generic OpenAI-compatible API',
        'deepseek' => 'DeepSeek API',
        'groq' => 'Groq API',
        'mistral' => 'Mistral API',
        'custom_http' => 'Custom HTTP JSON',
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/endpoint',
    'Endpoint',
    'Leave empty to use the default endpoint for Gemini/OpenRouter/OpenAI/Groq/DeepSeek/Ollama. Required for custom HTTP or generic OpenAI-compatible providers.',
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/model',
    'Model',
    'Examples: gemini-1.5-flash, deepseek/deepseek-chat, gpt-4o-mini, qwen2.5:3b.',
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/apikey',
    'API key',
    'Store the key only in Moodle settings. Leave empty for local/prototype providers.',
    ''
));

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/customheading',
    'Custom HTTP provider',
    'Use this only if your provider is not directly supported.'
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/customrequesttemplate',
    'Custom request JSON template',
    'Available placeholders: {{model}}, {{system}}, {{prompt}}, {{max_tokens}}, {{apikey}}.',
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/customheadersjson',
    'Custom headers JSON',
    'Example: {"Authorization":"Bearer {{apikey}}","Content-Type":"application/json"}',
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/customresponsepath',
    'Custom response path',
    'Example: choices.0.message.content, candidates.0.content.parts.0.text, or _raw.',
    'choices.0.message.content',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/embeddingheading',
    'RAG embeddings',
    'Configure embeddings used by the Course materials / RAG feature. Leave fields empty to use safe defaults.'
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/embeddingprovider',
    'Embedding provider',
    '',
    'same_as_chat',
    [
        'same_as_chat' => 'Same family as chat provider',
        'ollama' => 'Local Ollama embeddings',
        'openai' => 'OpenAI-compatible embeddings',
        'custom_http' => 'Custom HTTP embeddings',
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingendpoint',
    'Embedding endpoint',
    'Optional. For Ollama use http://host.docker.internal:11434. For OpenAI-compatible APIs use the base endpoint.',
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingmodel',
    'Embedding model',
    'Examples: nomic-embed-text, text-embedding-3-small.',
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/embeddingapikey',
    'Embedding API key',
    'Optional. If empty, the main API key is reused.',
    ''
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/embeddingrequesttemplate',
    'Custom embedding request template',
    'Available placeholders: {{model}}, {{input}}, {{apikey}}.',
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtextarea(
    'local_aiskillnavigator/embeddingheadersjson',
    'Custom embedding headers JSON',
    '',
    '',
    PARAM_RAW
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/embeddingresponsepath',
    'Custom embedding response path',
    'Default: data.0.embedding',
    'data.0.embedding',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_heading(
    'local_aiskillnavigator/searchheading',
    'Live web search',
    'Optional Search API used by Simulator Finder to verify online simulators/tools. Leave disabled if you do not have a Search API key.'
));

$settings->add(new admin_setting_configselect(
    'local_aiskillnavigator/searchprovider',
    'Search provider',
    'Use Tavily for AI-oriented web search, Brave for independent search, or SerpAPI for Google-style results.',
    'none',
    [
        'none' => 'Disabled',
        'tavily' => 'Tavily Search API',
        'brave' => 'Brave Search API',
        'serpapi' => 'SerpAPI Google Search',
    ]
));

$settings->add(new admin_setting_configtext(
    'local_aiskillnavigator/searchendpoint',
    'Search endpoint',
    'Optional. Leave empty for default endpoint of the selected provider.',
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_aiskillnavigator/searchapikey',
    'Search API key',
    'Do not put this key in code or GitHub. Store it only in Moodle settings.',
    ''
));