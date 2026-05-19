<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

class ai_provider_config {
    public string $provider;
    public string $endpoint;
    public string $model;
    public string $apikey;
    public string $customrequesttemplate;
    public string $customheadersjson;
    public string $customresponsepath;

    public function __construct() {
        $this->provider = strtolower(trim((string) get_config('local_aiskillnavigator', 'provider')));
        $this->endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $this->model = trim((string) get_config('local_aiskillnavigator', 'model'));
        $this->apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));
        $this->customrequesttemplate = trim((string) get_config('local_aiskillnavigator', 'customrequesttemplate'));
        $this->customheadersjson = trim((string) get_config('local_aiskillnavigator', 'customheadersjson'));
        $this->customresponsepath = trim((string) get_config('local_aiskillnavigator', 'customresponsepath'));

        if ($this->provider === '') {
            $this->provider = 'ollama';
        }

        if ($this->customresponsepath === '') {
            $this->customresponsepath = 'choices.0.message.content';
        }
    }
}