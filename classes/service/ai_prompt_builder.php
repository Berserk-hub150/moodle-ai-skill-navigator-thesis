<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds prompts for AI workflows.
 */
class ai_prompt_builder {

    public function tutor_prompt(string $question): string {
        return "Sei un tutor universitario integrato in Moodle.\n"
            . "Rispondi in italiano, in modo chiaro, didattico e sintetico.\n"
            . "Puoi rispondere su qualsiasi argomento richiesto dallo studente.\n"
            . "Se non sei sicuro di un dettaglio specifico, dichiaralo chiaramente invece di inventare.\n\n"
            . "Domanda dello studente:\n"
            . trim($question);
    }

    public function tutor_with_materials_prompt(string $question, array $materials): string {
        return "Sei un tutor AI integrato in Moodle.\n"
            . "Devi rispondere alla domanda dello studente usando SOLO i materiali del docente forniti sotto.\n"
            . "Se i materiali non bastano, dillo chiaramente.\n"
            . "Non inventare informazioni esterne ai materiali.\n"
            . "Rispondi in italiano.\n"
            . "Organizza la risposta con sezioni brevi e leggibili.\n"
            . "Alla fine aggiungi una sezione 'Fonti usate' con i titoli dei materiali usati.\n\n"
            . "MATERIALI DEL DOCENTE:\n"
            . $this->material_context($materials, 2200)
            . "DOMANDA DELLO STUDENTE:\n"
            . trim($question);
    }

    public function tutor_with_rag_prompt(string $question, string $ragcontext): string {
        return "Sei un tutor AI integrato in Moodle con accesso a una Knowledge Base indicizzata.\n"
            . "I materiali sotto sono stati selezionati semanticamente come i piÃ¹ rilevanti per la domanda.\n"
            . "Rispondi SOLO usando questi materiali. Se non bastano, dillo chiaramente.\n"
            . "Non inventare informazioni esterne ai materiali.\n"
            . "Rispondi in italiano.\n"
            . "Organizza la risposta con sezioni brevi e leggibili.\n"
            . "Alla fine aggiungi una sezione 'Fonti usate' con i titoli dei materiali citati.\n\n"
            . "MATERIALI RILEVANTI (recuperati via RAG):\n"
            . $ragcontext
            . "\n\nDOMANDA DELLO STUDENTE:\n"
            . trim($question);
    }

    public function quiz_prompt(string $topic, string $difficulty): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        return "Genera un micro-test universitario in italiano per Moodle.\n\n"
            . "Argomento: {$topic}\n"
            . "DifficoltÃ : {$difficulty}\n\n"
            . $this->quiz_rules()
            . "\n"
            . $this->quiz_json_format($topic, $difficulty);
    }

    public function quiz_from_materials_prompt(string $focus, string $difficulty, array $materials): string {
        $topic = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        return "Genera un micro-test universitario in italiano per Moodle usando SOLO i materiali del docente forniti sotto.\n"
            . "Le domande devono verificare concetti realmente presenti nei materiali.\n"
            . "Non inventare concetti non presenti.\n"
            . "Focus richiesto: {$topic}\n"
            . "DifficoltÃ : {$difficulty}\n\n"
            . "MATERIALI DEL DOCENTE:\n"
            . $this->material_context($materials, 2600)
            . "\n"
            . $this->quiz_rules()
            . "\nNel campo skill indica la competenza o concetto del materiale valutato.\n\n"
            . $this->quiz_json_format($topic, $difficulty);
    }

    public function quiz_with_rag_prompt(string $focus, string $difficulty, string $ragcontext): string {
        $topic = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        return "Genera un micro-test universitario in italiano per Moodle usando SOLO i materiali forniti sotto.\n"
            . "Focus richiesto: {$topic}\n"
            . "DifficoltÃ : {$difficulty}\n\n"
            . "MATERIALI RILEVANTI (recuperati via RAG):\n"
            . $ragcontext
            . "\n"
            . $this->quiz_rules()
            . "\nNel campo skill indica la competenza o concetto del materiale valutato.\n\n"
            . $this->quiz_json_format($topic, $difficulty);
    }

    public function mindmap_prompt(string $topic): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';

        return "Genera una mappa mentale didattica semplice e interattiva in italiano.\n\n"
            . "Argomento centrale: {$topic}\n\n"
            . $this->mindmap_rules()
            . "\n"
            . $this->mindmap_json_format($topic);
    }

    public function mindmap_from_materials_prompt(string $focus, array $materials): string {
        $topic = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';

        return "Genera una mappa mentale didattica semplice e interattiva in italiano usando SOLO i materiali del docente forniti sotto.\n"
            . "Focus richiesto: {$topic}\n\n"
            . "MATERIALI DEL DOCENTE:\n"
            . $this->material_context($materials, 2600)
            . "\n"
            . $this->mindmap_rules()
            . "\n"
            . $this->mindmap_json_format($topic);
    }

    public function mindmap_with_rag_prompt(string $focus, string $ragcontext): string {
        $topic = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';

        return "Genera una mappa mentale didattica in italiano usando SOLO i materiali forniti sotto.\n"
            . "Focus richiesto: {$topic}\n\n"
            . "MATERIALI RILEVANTI (recuperati via RAG):\n"
            . $ragcontext
            . "\n"
            . $this->mindmap_rules()
            . "\n"
            . $this->mindmap_json_format($topic);
    }

    public function xr_scenario_prompt(string $topic, string $environment): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->xr_intro($topic, $environment, '')
            . $this->xr_rules(false)
            . $this->xr_markdown_format(false);
    }

    public function xr_scenario_from_materials_prompt(string $focus, string $environment, array $materials): string {
        $topic = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->xr_intro($topic, $environment, $this->material_context($materials, 3200))
            . $this->xr_rules(true)
            . $this->xr_markdown_format(true);
    }

    public function xr_scenario_with_rag_prompt(string $focus, string $environment, string $ragcontext): string {
        $topic = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->xr_intro($topic, $environment, $ragcontext)
            . $this->xr_rules(true)
            . $this->xr_markdown_format(true);
    }

    public function summarize_materials_prompt(string $focus, array $materials): string {
        $prompt = "Riassumi in italiano i materiali del docente forniti sotto.\n"
            . "Usa SOLO questi materiali.\n"
            . "Non inventare contenuti esterni.\n";

        if (trim($focus) !== '') {
            $prompt .= "Focus richiesto: " . trim($focus) . "\n";
        }

        return $prompt . "\nMATERIALI DEL DOCENTE:\n" . $this->material_context($materials, 3000);
    }

    public function summarize_rag_prompt(string $focus, string $ragcontext): string {
        $prompt = "Riassumi in italiano i materiali forniti sotto.\n"
            . "Usa SOLO questi materiali. Non inventare contenuti esterni.\n";

        if (trim($focus) !== '') {
            $prompt .= "Focus richiesto: " . trim($focus) . "\n";
        }

        return $prompt . "\nMATERIALI RILEVANTI:\n" . $ragcontext;
    }

    private function quiz_rules(): string {
        return "REGOLE OBBLIGATORIE:\n"
            . "Rispondi SOLO con JSON valido.\n"
            . "Non usare Markdown.\n"
            . "Non usare blocchi ```.\n"
            . "Non scrivere testo prima o dopo il JSON.\n"
            . "Genera ESATTAMENTE 3 domande.\n"
            . "Ogni domanda deve avere ESATTAMENTE 4 opzioni.\n"
            . "Le spiegazioni devono essere brevi, massimo 180 caratteri.\n"
            . "Le domande devono richiedere ragionamento, confronto o applicazione.";
    }

    private function mindmap_rules(): string {
        return "REGOLE OBBLIGATORIE:\n"
            . "Rispondi SOLO con JSON valido.\n"
            . "Genera ESATTAMENTE 4 rami principali.\n"
            . "Ogni ramo deve avere ESATTAMENTE 2 sotto-nodi.\n"
            . "Ogni titolo deve essere corto: massimo 4 parole.";
    }

    private function quiz_json_format(string $topic, string $difficulty): string {
        return "Formato obbligatorio:\n"
            . "{\n"
            . "\"title\":\"Titolo del test\",\n"
            . "\"topic\":\"{$topic}\",\n"
            . "\"difficulty\":\"{$difficulty}\",\n"
            . "\"questions\":[{\"question\":\"Testo domanda\",\"options\":[\"A\",\"B\",\"C\",\"D\"],\"correct_index\":0,\"explanation\":\"Spiegazione\",\"skill\":\"Competenza\"}]\n"
            . "}";
    }

    private function mindmap_json_format(string $topic): string {
        return "Formato JSON obbligatorio:\n"
            . "{\n"
            . "\"title\":\"Titolo corto\",\n"
            . "\"central_topic\":\"{$topic}\",\n"
            . "\"summary\":\"Sintesi breve\",\n"
            . "\"central_description\":\"Descrizione centrale\",\n"
            . "\"branches\":[{\"title\":\"Ramo\",\"description\":\"Descrizione\",\"children\":[{\"title\":\"Nodo 1\",\"description\":\"Descrizione\"},{\"title\":\"Nodo 2\",\"description\":\"Descrizione\"}]}]\n"
            . "}";
    }

    private function xr_intro(string $topic, string $environment, string $context): string {
        $prompt = "Genera uno scenario formativo completo per Virtual Worlds in italiano.\n\n"
            . "Argomento/focus: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n";

        if (trim($context) !== '') {
            $prompt .= "MATERIALI/RAG:\n" . $context . "\nUsa SOLO questi materiali.\n\n";
        }

        return $prompt;
    }

    private function xr_rules(bool $usesources): string {
        $rules = "REGOLE OBBLIGATORIE:\n"
            . "- Genera uno scenario lungo e concreto.\n"
            . "- I task studente devono essere almeno 5.\n"
            . "- I criteri di valutazione devono essere almeno 4.\n";

        if ($usesources) {
            $rules .= "- Alla fine indica le fonti usate.\n";
        }

        return $rules . "\n";
    }

    private function xr_markdown_format(bool $usesources): string {
        $format = "# Titolo\n## Obiettivo didattico\n## Ambiente virtuale\n## Storia dello scenario\n## Task dello studente\n## Criteri di valutazione\n## Competenze coinvolte\n";

        return $usesources ? $format . "## Fonti usate\n" : $format . "## Estensioni possibili\n";
    }

    private function material_context(array $materials, int $limitpermaterial): string {
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

            $context .= "FONTE {$number}\nTitolo: {$title}\nTipo: {$type}\nContenuto: {$content}\n\n";
        }

        return $context;
    }
}