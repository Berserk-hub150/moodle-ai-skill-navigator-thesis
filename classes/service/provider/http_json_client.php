<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

// Sends JSON requests and returns decoded responses.
class http_json_client {
    public function post(string $url, array $payload, array $headers, int $timeout = 180): array {
        $curl = curl_init($url);
        if ($curl === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'Errore inizializzazione cURL.'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            curl_close($curl);
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'Errore creazione JSON.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator',
        ]);

        $raw = curl_exec($curl);
        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => $error];
        }

        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $body = json_decode((string) $raw, true);

        return ['ok' => $status < 400 && is_array($body), 'status' => $status, 'raw' => $raw, 'body' => $body];
    }
}
