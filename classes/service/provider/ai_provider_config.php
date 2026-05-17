<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

// Reads provider settings from Moodle config.
class ai_provider_config {
    public string $provider;
    public string $endpoint;
    public string $model;
    public string $apikey;

    public function __construct() {
        $this->provider = strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
        $this->endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $this->model = trim((string) get_config('local_aiskillnavigator', 'model'));
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));

        if ($this->provider === '') {
            $this->provider = 'deepseek';
        }
    }
}
