<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class custom_http_ai_provider implements ai_provider_interface {
    private string $endpoint;
    private string $model;
    private string $apikey;
    private string $requesttemplate;
    private string $headersjson;
    private string $responsepath;

    public function __construct(
        string $endpoint,
        string $model,
        string $apikey,
        string $requesttemplate,
        string $headersjson,
        string $responsepath
    ) {
        $this->endpoint = trim($endpoint);
        $this->model = trim($model);
        $this->apikey = trim($apikey);
        $this->requesttemplate = trim($requesttemplate);
        $this->headersjson = trim($headersjson);
        $this->responsepath = trim($responsepath);
    }

    public function get_name(): string {
        return 'custom_http';
    }

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        if ($this->endpoint === '') {
            return 'Errore Custom HTTP API: endpoint mancante.';
        }

        $systemprompt = $systemprompt !== '' ? $systemprompt : 'You are a Moodle tutor. Follow the requested format exactly.';
        $template = $this->requesttemplate !== '' ? $this->requesttemplate : $this->default_template();

        $json = $this->render_template($template, [
            'model' => $this->model !== '' ? $this->model : 'default',
            'system' => $systemprompt,
            'prompt' => $prompt,
            'max_tokens' => (string) $maxtokens,
            'apikey' => $this->apikey,
        ]);

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            return 'Errore Custom HTTP API: request template non produce JSON valido. JSON error: ' . json_last_error_msg();
        }

        $headers = $this->build_headers();

        $curl = curl_init($this->endpoint);

        if ($curl === false) {
            return 'Errore Custom HTTP API: impossibile inizializzare cURL.';
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator custom_http',
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            return 'Errore Custom HTTP API cURL: ' . $curlerror;
        }

        if ($status >= 400) {
            return 'Errore Custom HTTP API HTTP ' . $status . ': ' . substr((string) $raw, 0, 800);
        }

        if ($this->responsepath === '_raw') {
            return trim((string) $raw);
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return trim((string) $raw);
        }

        $answer = $this->value_by_path($decoded, $this->responsepath);

        if (is_array($answer)) {
            return json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $answer = trim((string) $answer);

        return $answer !== '' ? $answer : 'Errore Custom HTTP API: response path vuoto o non trovato.';
    }

    private function default_template(): string {
        return '{
          "model": "{{model}}",
          "messages": [
            {"role": "system", "content": "{{system}}"},
            {"role": "user", "content": "{{prompt}}"}
          ],
          "temperature": 0.2,
          "max_tokens": {{max_tokens}}
        }';
    }

    private function render_template(string $template, array $values): string {
        foreach ($values as $key => $value) {
            $replacement = $key === 'max_tokens'
                ? (string) ((int) $value)
                : $this->escape_json_string((string) $value);

            $template = str_replace('{{' . $key . '}}', $replacement, $template);
        }

        return $template;
    }

    private function escape_json_string(string $value): string {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return '';
        }

        return substr($encoded, 1, -1);
    }

    private function build_headers(): array {
        $headers = [];
        $decoded = json_decode($this->headersjson, true);

        if (is_array($decoded)) {
            foreach ($decoded as $name => $value) {
                $name = trim((string) $name);
                $value = str_replace('{{apikey}}', $this->apikey, (string) $value);

                if ($name !== '' && $value !== '') {
                    $headers[] = $name . ': ' . $value;
                }
            }
        }

        if (empty($headers)) {
            $headers[] = 'Content-Type: application/json';

            if ($this->apikey !== '') {
                $headers[] = 'Authorization: Bearer ' . $this->apikey;
            }
        }

        return $headers;
    }

    private function value_by_path(array $data, string $path) {
        $path = trim($path);

        if ($path === '') {
            $path = 'choices.0.message.content';
        }

        $current = $data;
        $parts = explode('.', $path);

        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
                continue;
            }

            if (is_array($current) && ctype_digit($part) && array_key_exists((int) $part, $current)) {
                $current = $current[(int) $part];
                continue;
            }

            return null;
        }

        return $current;
    }
}