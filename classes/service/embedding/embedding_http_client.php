<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

class embedding_http_client {
    public function post(string $url, array $payload, array $headers): ?array {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            curl_close($curl);
            return null;
        }

        $headers = array_merge(['Content-Type: application/json'], array_values(array_filter($headers)));

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator RAG',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($raw === false || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string)$raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}