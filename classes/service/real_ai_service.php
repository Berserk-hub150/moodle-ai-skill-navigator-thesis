<?php
namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class real_ai_service {

    private string $provider;
    private string $apikey;
    private string $endpoint;
    private string $model;

    public function __construct() {
        $this->provider = (string) get_config('local_aiskillnavigator', 'provider');
        $this->apikey = (string) get_config('local_aiskillnavigator', 'apikey');
        $this->endpoint = (string) get_config('local_aiskillnavigator', 'endpoint');
        $this->model = (string) get_config('local_aiskillnavigator', 'model');

        if ($this->provider === '') {
            $this->provider = 'openrouter';
        }

        if ($this->endpoint === '') {
            $this->endpoint = 'https://openrouter.ai/api/v1';
        }

        if ($this->model === '') {
            $this->model = 'openrouter/free';
        }
    }

    public function ask_tutor(string $question): string {
        $prompt = "Sei un tutor universitario integrato in Moodle per un corso su AI, IoT, Digital Twin e Virtual Worlds.\n"
            . "Rispondi in italiano, in modo chiaro, didattico e sintetico.\n\n"
            . "Domanda dello studente:\n"
            . $question;

        return $this->generate($prompt);
    }

    public function generate_quiz(string $topic, string $difficulty): string {
        $prompt = "Genera un quiz didattico in italiano per Moodle.\n\n"
            . "Argomento: {$topic}\n"
            . "Difficoltà: {$difficulty}\n\n"
            . "Genera tre domande a risposta multipla con quattro opzioni, risposta corretta e breve spiegazione.";

        return $this->generate($prompt);
    }

    public function generate_xr_scenario(string $topic, string $environment): string {
        $prompt = "Genera uno scenario formativo per Virtual Worlds in italiano.\n\n"
            . "Argomento: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n"
            . "Usa queste sezioni: titolo, obiettivo didattico, ambiente, storia, task studente, criteri di valutazione, competenze coinvolte.";

        return $this->generate($prompt);
    }

    private function generate(string $prompt): string {
        if ($this->provider !== 'openrouter') {
            return 'Provider non configurato correttamente. Imposta provider = openrouter.';
        }

        if ($this->apikey === '') {
            return 'API key OpenRouter mancante. Configura la chiave API nelle impostazioni del plugin.';
        }

        $url = rtrim($this->endpoint, '/') . '/chat/completions';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful educational AI assistant integrated into Moodle. Always answer in Italian.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.4,
            'max_tokens' => 900,
            'stream' => false,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
            'HTTP-Referer: http://localhost:8000',
            'X-Title: AI Skill Navigator Moodle',
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);

        $raw = curl_exec($curl);

        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return 'Errore chiamata OpenRouter API: ' . $error;
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return 'Errore OpenRouter API: risposta non JSON. HTTP status: ' . $status . '. Risposta: ' . substr($raw, 0, 500);
        }

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? json_encode($decoded);
            return 'Errore OpenRouter API HTTP ' . $status . ': ' . $message;
        }

        return $decoded['choices'][0]['message']['content']
            ?? 'Errore OpenRouter API: risposta valida ma contenuto mancante.';
    }
}