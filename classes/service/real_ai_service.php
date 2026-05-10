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

        return $this->generate($prompt, 1000);
    }

    public function generate_quiz(string $topic, string $difficulty): string {
        $prompt = "Genera un micro-test universitario in italiano per Moodle.\n\n"
            . "Argomento: {$topic}\n"
            . "Difficoltà: {$difficulty}\n\n"
            . "REGOLE OBBLIGATORIE:\n"
            . "Rispondi SOLO con JSON valido.\n"
            . "Non usare Markdown.\n"
            . "Non usare blocchi ```.\n"
            . "Non scrivere testo prima o dopo il JSON.\n"
            . "Genera ESATTAMENTE 3 domande.\n"
            . "Ogni domanda deve avere ESATTAMENTE 4 opzioni.\n"
            . "Le spiegazioni devono essere brevi, massimo 180 caratteri.\n\n"
            . "Formato obbligatorio:\n"
            . "{\n"
            . "\"title\":\"Titolo del test\",\n"
            . "\"topic\":\"{$topic}\",\n"
            . "\"difficulty\":\"{$difficulty}\",\n"
            . "\"questions\":[\n"
            . "{\n"
            . "\"question\":\"Testo domanda\",\n"
            . "\"options\":[\"Opzione A\",\"Opzione B\",\"Opzione C\",\"Opzione D\"],\n"
            . "\"correct_index\":0,\n"
            . "\"explanation\":\"Spiegazione breve\",\n"
            . "\"skill\":\"Competenza valutata\"\n"
            . "}\n"
            . "]\n"
            . "}";

        return $this->generate($prompt, 2200);
    }

    public function generate_mindmap(string $topic): string {
        $prompt = "Genera una mappa mentale didattica semplice e interattiva in italiano.\n\n"
            . "Argomento centrale: {$topic}\n\n"
            . "REGOLE OBBLIGATORIE:\n"
            . "Rispondi SOLO con JSON valido.\n"
            . "Non usare Markdown.\n"
            . "Non usare blocchi ```.\n"
            . "Non scrivere testo prima o dopo il JSON.\n"
            . "Genera ESATTAMENTE 4 rami principali.\n"
            . "Ogni ramo deve avere ESATTAMENTE 2 sotto-nodi.\n"
            . "Ogni titolo deve essere corto: massimo 4 parole.\n"
            . "Ogni descrizione deve essere chiara: massimo 180 caratteri.\n"
            . "La mappa deve essere utile per studiare e ripassare.\n\n"
            . "Formato JSON obbligatorio:\n"
            . "{\n"
            . "\"title\":\"Titolo corto\",\n"
            . "\"central_topic\":\"{$topic}\",\n"
            . "\"summary\":\"Sintesi breve dell'argomento\",\n"
            . "\"central_description\":\"Spiegazione del concetto centrale\",\n"
            . "\"branches\":[\n"
            . "{\n"
            . "\"title\":\"Ramo principale\",\n"
            . "\"description\":\"Spiegazione del ramo principale\",\n"
            . "\"children\":[\n"
            . "{\"title\":\"Sotto nodo 1\",\"description\":\"Spiegazione del sotto nodo 1\"},\n"
            . "{\"title\":\"Sotto nodo 2\",\"description\":\"Spiegazione del sotto nodo 2\"}\n"
            . "]\n"
            . "}\n"
            . "]\n"
            . "}";

        return $this->generate($prompt, 1500);
    }

    public function generate_xr_scenario(string $topic, string $environment): string {
        $prompt = "Genera uno scenario formativo per Virtual Worlds in italiano.\n\n"
            . "Argomento: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n"
            . "Usa queste sezioni: titolo, obiettivo didattico, ambiente, storia, task studente, criteri di valutazione, competenze coinvolte.";

        return $this->generate($prompt, 1400);
    }

    private function generate(string $prompt, int $maxtokens = 1200): string {
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
                    'content' => 'You are a precise educational assistant integrated into Moodle. Follow the requested output format exactly. If JSON is requested, output valid JSON only.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => $maxtokens,
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