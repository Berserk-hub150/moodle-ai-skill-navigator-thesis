<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Reads embedding settings from Moodle config.
class embedding_config {
    public string $provider;
    public string $endpoint;
    public string $model;
    public string $apikey;

    public function __construct() {
        $provider = trim((string) get_config('local_aiskillnavigator', 'provider'));
        $endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $model = trim((string) get_config('local_aiskillnavigator', 'embeddingmodel'));

        $this->provider = $provider !== '' ? $provider : 'ollama';
        $this->endpoint = $endpoint !== '' ? $endpoint : 'http://host.docker.internal:11434';
        $this->model = $model !== '' ? $model : 'nomic-embed-text';
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));
    }
}
