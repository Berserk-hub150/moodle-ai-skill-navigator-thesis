<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared HTTP/cURL behaviour for concrete AI providers.
 */
abstract class abstract_curl_ai_provider implements ai_provider_interface {

    protected string $endpoint;
    protected string $model;
    protected string $apikey;

    public function __construct(string $endpoint, string $model, string $apikey = '') {
        $this->endpoint = rtrim(trim($endpoint), '/');
        $this->model = trim($model);
        $this->apikey = trim($apikey);
    }

    protected function post_json_and_extract_answer(string $url, array $payload, array $headers, string $format): string {
        $curl = curl_init($url);

        if ($curl === false) {
            return 'Errore inizializzazione cURL.';
        }

        $jsonpayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonpayload === false) {
            curl_close($curl);
            return 'Errore creazione JSON per la richiesta AI.';
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonpayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator',
        ]);

        $raw = curl_exec($curl);

        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return 'Errore chiamata AI API: ' . $error;
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return 'Errore AI API: risposta non JSON. HTTP status: ' . $status . '. Risposta: ' . substr((string) $raw, 0, 500);
        }

        if ($status >= 400) {
            $message = $decoded['error'] ?? $decoded['message'] ?? json_encode($decoded, JSON_UNESCAPED_UNICODE);
            return 'Errore AI API HTTP ' . $status . ': ' . $message;
        }

        if ($format === 'ollama') {
            $answer = trim((string) ($decoded['message']['content'] ?? ''));
        } else {
            $answer = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        }

        if ($answer === '') {
            return 'Errore AI API: risposta valida ma contenuto mancante.';
        }

        return $this->clean_model_output($answer);
    }

    protected function clean_model_output(string $answer): string {
        $answer = trim($answer);

        if (str_starts_with($answer, '```json')) {
            $answer = trim(substr($answer, 7));
        } else if (str_starts_with($answer, '```')) {
            $answer = trim(substr($answer, 3));
        }

        if (str_ends_with($answer, '```')) {
            $answer = trim(substr($answer, 0, -3));
        }

        return trim($answer);
    }

    protected function default_system_prompt(): string {
        return 'You are a precise educational assistant integrated into Moodle. Follow the requested output format exactly.';
    }
}