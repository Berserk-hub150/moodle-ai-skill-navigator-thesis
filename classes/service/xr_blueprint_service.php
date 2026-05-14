<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class xr_blueprint_service {

    private string $provider;
    private string $endpoint;
    private string $model;
    private string $apikey;

    public function __construct() {
        $provider = trim((string) get_config('local_aiskillnavigator', 'provider'));
        $endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $model = trim((string) get_config('local_aiskillnavigator', 'model'));
        $apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));

        $this->provider = $provider !== '' ? $provider : 'ollama';
        $this->endpoint = $endpoint !== '' ? $endpoint : 'http://host.docker.internal:11434';
        $this->model = $model !== '' ? $model : 'qwen2.5:3b';
        $this->apikey = $apikey;
    }

    public function generate_blueprint(string $topic, string $environment): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->generate($this->build_prompt($topic, $environment, ''), 4200);
    }

    public function generate_blueprint_from_course_materials(string $focus, string $environment, array $materials): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        if (empty($materials)) {
            return $this->generate_blueprint($focus, $environment);
        }

        $context = $this->build_material_context($materials, 3200);

        return $this->generate($this->build_prompt($focus, $environment, $context), 4500);
    }

    public function generate_blueprint_with_rag_context(string $focus, string $environment, string $ragcontext): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->generate($this->build_prompt($focus, $environment, $ragcontext), 4500);
    }

    private function build_prompt(string $topic, string $environment, string $context): string {
        $prompt = "Sei un generatore di blueprint XR per un plugin Moodle universitario.\n"
            . "Devi generare un JSON strutturato esportabile verso Virtual Worlds, A-Frame, Unity o Mozilla Hubs.\n\n"
            . "Topic/focus: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n"
            . "OBIETTIVO:\n"
            . "Non generare un mondo 3D vero. Genera un blueprint 2D/strutturale con coordinate, oggetti, punti di interesse, task, checkpoint, trigger, dialoghi e obiettivi didattici.\n\n";

        if (trim($context) !== '') {
            $prompt .= "CONTESTO MATERIALI/RAG:\n"
                . $context
                . "\nUsa SOLO i concetti presenti nel contesto. Non inventare informazioni esterne ai materiali.\n\n";
        }

        $prompt .= "REGOLE OBBLIGATORIE:\n"
            . "- Rispondi SOLO con JSON valido.\n"
            . "- Non usare Markdown.\n"
            . "- Non usare blocchi ```.\n"
            . "- Non scrivere testo prima o dopo il JSON.\n"
            . "- Usa coordinate x/y numeriche da 0 a 100.\n"
            . "- Distribuisci bene gli elementi sulla mappa: non ammassare tutto al centro.\n"
            . "- Usa preferibilmente coordinate tra 10 e 90 sia per x che per y.\n"
            . "- Genera almeno 5 objects.\n"
            . "- Genera almeno 4 points_of_interest.\n"
            . "- Genera almeno 5 tasks.\n"
            . "- Genera almeno 4 checkpoints.\n"
            . "- Genera almeno 4 triggers.\n"
            . "- Genera almeno 4 dialogs.\n"
            . "- Ogni id deve essere breve, lowercase, senza spazi.\n"
            . "- I target_id dei task devono riferirsi a id esistenti in objects, points_of_interest o checkpoints.\n"
            . "- source_id dei trigger deve riferirsi a un id esistente.\n"
            . "- related_object_id dei dialoghi deve riferirsi a un id esistente quando possibile.\n\n"
            . "FORMATO JSON OBBLIGATORIO:\n"
            . "{\n"
            . "  \"title\": \"Titolo dello scenario\",\n"
            . "  \"topic\": \"{$topic}\",\n"
            . "  \"environment\": \"{$environment}\",\n"
            . "  \"description\": \"Descrizione sintetica dello scenario\",\n"
            . "  \"learning_objectives\": [\"Obiettivo 1\", \"Obiettivo 2\", \"Obiettivo 3\", \"Obiettivo 4\"],\n"
            . "  \"map\": {\"width\": 100, \"height\": 100, \"unit\": \"percent\", \"layout_description\": \"Descrizione top-down della mappa\"},\n"
            . "  \"objects\": [\n"
            . "    {\"id\": \"obj_sensor_1\", \"name\": \"Nome oggetto\", \"type\": \"machine\", \"x\": 50, \"y\": 50, \"description\": \"Ruolo didattico\", \"interaction\": \"Interazione studente\"}\n"
            . "  ],\n"
            . "  \"points_of_interest\": [\n"
            . "    {\"id\": \"poi_control_room\", \"name\": \"Nome punto\", \"x\": 20, \"y\": 35, \"description\": \"Importanza nella simulazione\"}\n"
            . "  ],\n"
            . "  \"tasks\": [\n"
            . "    {\"id\": \"task_1\", \"title\": \"Titolo task\", \"description\": \"Cosa deve fare lo studente\", \"target_id\": \"obj_sensor_1\", \"success_condition\": \"Condizione di completamento\"}\n"
            . "  ],\n"
            . "  \"checkpoints\": [\n"
            . "    {\"id\": \"cp_1\", \"name\": \"Nome checkpoint\", \"x\": 70, \"y\": 60, \"description\": \"Cosa viene verificato\", \"required_task_ids\": [\"task_1\"]}\n"
            . "  ],\n"
            . "  \"triggers\": [\n"
            . "    {\"id\": \"tr_1\", \"name\": \"Nome trigger\", \"type\": \"on_click\", \"source_id\": \"obj_sensor_1\", \"condition\": \"Quando si attiva\", \"effect\": \"Effetto nella simulazione\"}\n"
            . "  ],\n"
            . "  \"dialogs\": [\n"
            . "    {\"id\": \"dialog_1\", \"speaker\": \"Tutor AI\", \"text\": \"Testo del dialogo\", \"related_object_id\": \"obj_sensor_1\"}\n"
            . "  ],\n"
            . "  \"assessment\": [\"Criterio 1\", \"Criterio 2\", \"Criterio 3\", \"Criterio 4\"],\n"
            . "  \"export_targets\": [\"A-Frame\", \"Unity\", \"Mozilla Hubs\"],\n"
            . "  \"sources_used\": []\n"
            . "}\n";

        return $prompt;
    }

    private function build_material_context(array $materials, int $limitpermaterial): string {
        $context = '';

        foreach ($materials as $index => $material) {
            $number = $index + 1;
            $title = trim((string) ($material->title ?? 'Materiale senza titolo'));
            $type = trim((string) ($material->materialtype ?? 'text'));
            $content = trim((string) ($material->content ?? ''));

            $content = trim((string) preg_replace('/\s+/u', ' ', $content));

            if ($content === '') {
                continue;
            }

            if (function_exists('mb_strlen') && mb_strlen($content) > $limitpermaterial) {
                $content = mb_substr($content, 0, $limitpermaterial) . '...';
            } else if (strlen($content) > $limitpermaterial) {
                $content = substr($content, 0, $limitpermaterial) . '...';
            }

            $context .= "FONTE {$number}\n";
            $context .= "Titolo: {$title}\n";
            $context .= "Tipo: {$type}\n";
            $context .= "Contenuto: {$content}\n\n";
        }

        return $context;
    }

    private function generate(string $prompt, int $maxtokens): string {
        if ($this->provider === 'ollama') {
            return $this->generate_with_ollama($prompt, $maxtokens);
        }

        return $this->generate_with_openai_compatible_api($prompt, $maxtokens);
    }

    private function generate_with_ollama(string $prompt, int $maxtokens): string {
        $endpoint = rtrim($this->endpoint, '/');
        $url = str_ends_with($endpoint, '/api/chat') ? $endpoint : $endpoint . '/api/chat';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a strict JSON generator for educational XR blueprints. Return only valid JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false,
            'options' => [
                'temperature' => 0.15,
                'num_predict' => $maxtokens,
            ],
        ];

        return $this->post_json_and_extract_answer($url, $payload, ['Content-Type: application/json'], 'ollama');
    }

    private function generate_with_openai_compatible_api(string $prompt, int $maxtokens): string {
        $endpoint = rtrim($this->endpoint, '/');
        $url = str_ends_with($endpoint, '/chat/completions') ? $endpoint : $endpoint . '/chat/completions';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a strict JSON generator for educational XR blueprints. Return only valid JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.15,
            'max_tokens' => $maxtokens,
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        if ($this->apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        }

        return $this->post_json_and_extract_answer($url, $payload, $headers, 'openai');
    }

    private function post_json_and_extract_answer(string $url, array $payload, array $headers, string $format): string {
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
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator XR Blueprint',
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

    private function clean_model_output(string $answer): string {
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
}