<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

class http_json_client {
    public function post(string $url, array $payload, array $headers, int $timeout = 75): array {
        $url = trim($url);

        if ($url === '') {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'Endpoint vuoto.'];
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'Errore inizializzazione cURL.'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            curl_close($curl);
            return ['ok' => false, 'status' => 0, 'body' => null, 'raw' => '', 'error' => 'Errore creazione JSON: ' . json_last_error_msg()];
        }

        $headers = $this->normalise_headers($headers);
        $timeout = max(10, min($timeout, 120));

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => null,
                'raw' => '',
                'error' => $curlerror !== '' ? $curlerror : 'Errore cURL sconosciuto.',
            ];
        }

        $decoded = json_decode((string)$raw, true);
        $jsonok = is_array($decoded);

        return [
            'ok' => $status >= 200 && $status < 300 && $jsonok,
            'status' => $status,
            'body' => $jsonok ? $decoded : null,
            'raw' => (string)$raw,
            'error' => $jsonok ? '' : 'Risposta non JSON: ' . substr((string)$raw, 0, 500),
        ];
    }

    private function normalise_headers(array $headers): array {
        $out = [];
        $hascontenttype = false;

        foreach ($headers as $header) {
            $header = trim((string)$header);

            if ($header === '') {
                continue;
            }

            if (stripos($header, 'Content-Type:') === 0) {
                $hascontenttype = true;
            }

            $out[] = $header;
        }

        if (!$hascontenttype) {
            array_unshift($out, 'Content-Type: application/json');
        }

        return $out;
    }
}