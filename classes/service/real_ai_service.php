<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class real_ai_service {

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

    public function ask_tutor(string $question): string {
        $question = trim($question);

        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor AI.';
        }

        $prompt = "Sei un tutor universitario integrato in Moodle.\n"
            . "Rispondi in italiano, in modo chiaro, didattico e sintetico.\n"
            . "Puoi rispondere su qualsiasi argomento richiesto dallo studente.\n"
            . "Se non sei sicuro di un dettaglio specifico, dichiaralo chiaramente invece di inventare.\n\n"
            . "Domanda dello studente:\n"
            . $question;

        return $this->generate($prompt, 1000);
    }

    public function ask_with_course_materials(string $question, array $materials): string {
        $question = trim($question);

        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        if (empty($materials)) {
            return 'Non sono stati trovati materiali del docente rilevanti per rispondere alla domanda. Chiedi al docente di caricare slide, appunti o dispense nella Knowledge Base del corso.';
        }

        $context = $this->build_material_context($materials, 2200);

        $prompt = "Sei un tutor AI integrato in Moodle.\n"
            . "Devi rispondere alla domanda dello studente usando SOLO i materiali del docente forniti sotto.\n"
            . "Se i materiali non bastano, dillo chiaramente.\n"
            . "Non inventare informazioni esterne ai materiali.\n"
            . "Rispondi in italiano.\n"
            . "Organizza la risposta con sezioni brevi e leggibili.\n"
            . "Alla fine aggiungi una sezione 'Fonti usate' con i titoli dei materiali usati.\n\n"
            . "MATERIALI DEL DOCENTE:\n"
            . $context
            . "DOMANDA DELLO STUDENTE:\n"
            . $question;

        return $this->generate($prompt, 1400);
    }

    public function generate_quiz(string $topic, string $difficulty): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

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
            . "QUALITÀ E AFFIDABILITÀ:\n"
            . "Le domande devono essere specifiche, non banali e coerenti con la difficoltà scelta.\n"
            . "Se la difficoltà è hard, evita domande semplici di memoria come nomi, colori, titoli o protagonisti.\n"
            . "Se la difficoltà è hard, crea domande che richiedono ragionamento, confronto, interpretazione o applicazione.\n"
            . "Le opzioni sbagliate devono essere plausibili e vicine alla risposta corretta.\n"
            . "Non usare opzioni ridicole o palesemente false.\n"
            . "Non inventare poteri, eventi, relazioni, date, citazioni o motivazioni non confermate.\n"
            . "Se non sei sicuro di un dettaglio specifico, evita quella domanda e scegli un aspetto più noto del topic.\n"
            . "Per serie TV, anime, videogiochi, film o lore, genera domande su eventi, temi, personaggi e conflitti chiaramente riconosciuti.\n\n"
            . $this->quiz_json_format($topic, $difficulty);

        return $this->generate($prompt, 2200);
    }

    public function generate_quiz_from_course_materials(string $focus, string $difficulty, array $materials): string {
        $focus = trim($focus);
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        if (empty($materials)) {
            return $this->generate_quiz($focus !== '' ? $focus : 'Course materials', $difficulty);
        }

        $context = $this->build_material_context($materials, 2600);
        $topic = $focus !== '' ? $focus : 'Materiali del docente';

        $prompt = "Genera un micro-test universitario in italiano per Moodle usando SOLO i materiali del docente forniti sotto.\n"
            . "Le domande devono verificare concetti realmente presenti nei materiali.\n"
            . "Non inventare concetti non presenti.\n"
            . "Se il focus è specificato, usa il focus solo se compatibile con i materiali.\n\n"
            . "Focus richiesto dallo studente: {$topic}\n"
            . "Difficoltà: {$difficulty}\n\n"
            . "MATERIALI DEL DOCENTE:\n"
            . $context
            . "\nREGOLE OBBLIGATORIE:\n"
            . "Rispondi SOLO con JSON valido.\n"
            . "Non usare Markdown.\n"
            . "Non usare blocchi ```.\n"
            . "Non scrivere testo prima o dopo il JSON.\n"
            . "Genera ESATTAMENTE 3 domande.\n"
            . "Ogni domanda deve avere ESATTAMENTE 4 opzioni.\n"
            . "Le spiegazioni devono essere brevi, massimo 180 caratteri.\n"
            . "Nel campo skill indica la competenza o concetto del materiale valutato.\n\n"
            . "QUALITÀ DELLE DOMANDE:\n"
            . "Le domande devono essere specifiche e basate su dettagli realmente presenti nei materiali.\n"
            . "Se la difficoltà è hard, genera domande di applicazione, confronto o ragionamento, non semplici definizioni.\n"
            . "Le opzioni sbagliate devono essere plausibili e vicine alla risposta corretta.\n"
            . "Non usare concetti esterni ai materiali del docente.\n\n"
            . $this->quiz_json_format($topic, $difficulty);

        return $this->generate($prompt, 2400);
    }

    public function generate_mindmap(string $topic): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';

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
            . "Non inventare dettagli troppo specifici se non sei sicuro.\n\n"
            . $this->mindmap_json_format($topic);

        return $this->generate($prompt, 1500);
    }

    public function generate_mindmap_from_course_materials(string $focus, array $materials): string {
        $focus = trim($focus);

        if (empty($materials)) {
            return $this->generate_mindmap($focus !== '' ? $focus : 'Course materials');
        }

        $context = $this->build_material_context($materials, 2600);
        $topic = $focus !== '' ? $focus : 'Materiali del docente';

        $prompt = "Genera una mappa mentale didattica semplice e interattiva in italiano usando SOLO i materiali del docente forniti sotto.\n"
            . "La mappa deve rappresentare i concetti realmente presenti nei materiali.\n"
            . "Non inventare argomenti non presenti.\n"
            . "Se il focus è specificato, usalo solo se compatibile con i materiali.\n\n"
            . "Focus richiesto dallo studente: {$topic}\n\n"
            . "MATERIALI DEL DOCENTE:\n"
            . $context
            . "\nREGOLE OBBLIGATORIE:\n"
            . "Rispondi SOLO con JSON valido.\n"
            . "Non usare Markdown.\n"
            . "Non usare blocchi ```.\n"
            . "Non scrivere testo prima o dopo il JSON.\n"
            . "Genera ESATTAMENTE 4 rami principali.\n"
            . "Ogni ramo deve avere ESATTAMENTE 2 sotto-nodi.\n"
            . "Ogni titolo deve essere corto: massimo 4 parole.\n"
            . "Ogni descrizione deve essere chiara: massimo 180 caratteri.\n\n"
            . $this->mindmap_json_format($topic);

        return $this->generate($prompt, 1800);
    }

    public function summarize_course_materials(string $focus, array $materials): string {
        $focus = trim($focus);

        if (empty($materials)) {
            return 'Non sono stati trovati materiali leggibili del docente da riassumere.';
        }

        $context = $this->build_material_context($materials, 3000);

        $prompt = "Riassumi in italiano i materiali del docente forniti sotto.\n"
            . "Usa SOLO questi materiali.\n"
            . "Non inventare contenuti esterni.\n"
            . "Organizza il riassunto con sezioni brevi, elenco dei concetti principali, parole chiave e cosa studiare prima del test.\n";

        if ($focus !== '') {
            $prompt .= "Focus richiesto: {$focus}\n";
        }

        $prompt .= "\nMATERIALI DEL DOCENTE:\n" . $context;

        return $this->generate($prompt, 1600);
    }

    public function generate_xr_scenario(string $topic, string $environment): string {
    $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
    $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

    $prompt = "Genera uno scenario formativo completo per Virtual Worlds in italiano.\n\n"
        . "Argomento: {$topic}\n"
        . "Ambiente virtuale: {$environment}\n\n"
        . "REGOLE OBBLIGATORIE:\n"
        . "- Genera uno scenario lungo, concreto e usabile in una demo Moodle.\n"
        . "- Non fermarti al titolo.\n"
        . "- Usa ESATTAMENTE le sezioni indicate sotto.\n"
        . "- Ogni sezione deve avere contenuto reale, non solo una frase generica.\n"
        . "- I task studente devono essere almeno 5.\n"
        . "- I criteri di valutazione devono essere almeno 4.\n"
        . "- Le competenze coinvolte devono essere almeno 4.\n"
        . "- Se non sei sicuro di dettagli specifici del topic, resta su concetti generali verificabili.\n\n"
        . "FORMATO OBBLIGATORIO:\n"
        . "# Titolo\n"
        . "Scrivi un titolo specifico.\n\n"
        . "## Obiettivo didattico\n"
        . "Spiega cosa deve imparare lo studente.\n\n"
        . "## Ambiente virtuale\n"
        . "Descrivi l'ambiente, gli oggetti interattivi, i dati visibili e il contesto.\n\n"
        . "## Storia dello scenario\n"
        . "Descrivi la situazione iniziale, il problema e la missione dello studente.\n\n"
        . "## Task dello studente\n"
        . "Elenco numerato con almeno 5 task operativi.\n\n"
        . "## Criteri di valutazione\n"
        . "Elenco puntato con almeno 4 criteri.\n\n"
        . "## Competenze coinvolte\n"
        . "Elenco puntato con almeno 4 competenze.\n\n"
        . "## Estensioni possibili\n"
        . "Proponi almeno 3 miglioramenti futuri dello scenario.";

    return $this->generate($prompt, 2800);
}

public function generate_xr_scenario_from_course_materials(string $focus, string $environment, array $materials): string {
    $focus = trim($focus);
    $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

    if (empty($materials)) {
        return $this->generate_xr_scenario($focus !== '' ? $focus : 'Digital Twin and IoT', $environment);
    }

    $context = $this->build_material_context($materials, 3200);
    $topic = $focus !== '' ? $focus : 'Materiali del docente';

    $prompt = "Genera uno scenario formativo completo per Virtual Worlds in italiano usando SOLO i materiali del docente forniti sotto.\n"
        . "Non inventare concetti non presenti nei materiali.\n"
        . "Se il focus è specificato, usalo solo se compatibile con i materiali.\n\n"
        . "Focus richiesto: {$topic}\n"
        . "Ambiente virtuale: {$environment}\n\n"
        . "MATERIALI DEL DOCENTE:\n"
        . $context
        . "\nREGOLE OBBLIGATORIE:\n"
        . "- Genera uno scenario lungo, concreto e usabile in una demo Moodle.\n"
        . "- Non fermarti al titolo.\n"
        . "- Usa ESATTAMENTE le sezioni indicate sotto.\n"
        . "- Ogni sezione deve avere contenuto reale, non solo una frase generica.\n"
        . "- I task studente devono essere almeno 5.\n"
        . "- I criteri di valutazione devono essere almeno 4.\n"
        . "- Le competenze coinvolte devono essere almeno 4.\n"
        . "- Alla fine indica le fonti usate con i titoli dei materiali.\n\n"
        . "FORMATO OBBLIGATORIO:\n"
        . "# Titolo\n"
        . "Scrivi un titolo specifico.\n\n"
        . "## Obiettivo didattico\n"
        . "Spiega cosa deve imparare lo studente.\n\n"
        . "## Ambiente virtuale\n"
        . "Descrivi l'ambiente, gli oggetti interattivi, i dati visibili e il contesto.\n\n"
        . "## Storia dello scenario\n"
        . "Descrivi la situazione iniziale, il problema e la missione dello studente.\n\n"
        . "## Task dello studente\n"
        . "Elenco numerato con almeno 5 task operativi.\n\n"
        . "## Criteri di valutazione\n"
        . "Elenco puntato con almeno 4 criteri.\n\n"
        . "## Competenze coinvolte\n"
        . "Elenco puntato con almeno 4 competenze.\n\n"
        . "## Fonti usate\n"
        . "Elenca i titoli dei materiali usati.";

    return $this->generate($prompt, 3000);
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

    private function quiz_json_format(string $topic, string $difficulty): string {
        return "Formato obbligatorio:\n"
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
    }

    private function mindmap_json_format(string $topic): string {
        return "Formato JSON obbligatorio:\n"
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
    }

    private function generate(string $prompt, int $maxtokens = 1200): string {
        if ($this->provider === 'ollama') {
            return $this->generate_with_ollama($prompt, $maxtokens);
        }

        return $this->generate_with_openai_compatible_api($prompt, $maxtokens);
    }

    private function generate_with_ollama(string $prompt, int $maxtokens): string {
        $endpoint = rtrim($this->endpoint, '/');

        if (str_ends_with($endpoint, '/api/chat')) {
            $url = $endpoint;
        } else {
            $url = $endpoint . '/api/chat';
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise educational assistant integrated into Moodle. Follow the requested output format exactly.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false,
            'options' => [
                'temperature' => 0.2,
                'num_predict' => $maxtokens,
            ],
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        return $this->post_json_and_extract_answer($url, $payload, $headers, 'ollama');
    }

    private function generate_with_openai_compatible_api(string $prompt, int $maxtokens): string {
        $endpoint = rtrim($this->endpoint, '/');

        if (str_ends_with($endpoint, '/chat/completions')) {
            $url = $endpoint;
        } else {
            $url = $endpoint . '/chat/completions';
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a precise educational assistant integrated into Moodle. Follow the requested output format exactly.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.2,
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

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return 'Errore AI API: risposta non JSON. HTTP status: ' . $status . '. Risposta: ' . substr($raw, 0, 500);
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