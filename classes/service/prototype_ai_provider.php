<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Strategy implementation for demos without external AI.
 */
class prototype_ai_provider implements ai_provider_interface {

    public function get_name(): string {
        return 'prototype';
    }

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string {
        $lower = strtolower($prompt);

        if (str_contains($lower, '"questions"') || str_contains($lower, 'micro-test')) {
            return json_encode([
                'title' => 'Micro-test dimostrativo',
                'topic' => 'AI, IoT e Digital Twin',
                'difficulty' => 'medium',
                'questions' => [
                    [
                        'question' => 'Qual Ã¨ il ruolo principale dellâ€™IoT in un Digital Twin?',
                        'options' => [
                            'Fornire dati dal sistema fisico',
                            'Sostituire il docente',
                            'Eliminare la necessitÃ  di sensori',
                            'Trasformare Moodle in un simulatore 3D',
                        ],
                        'correct_index' => 0,
                        'explanation' => 'I sensori IoT mantengono aggiornato il modello digitale.',
                        'skill' => 'IoT e Digital Twin',
                    ],
                    [
                        'question' => 'PerchÃ© un tutor AI puÃ² essere utile in un LMS?',
                        'options' => [
                            'Per supportare studio e recupero personalizzato',
                            'Per eliminare tutti i quiz',
                            'Per sostituire il database',
                            'Per impedire lâ€™accesso ai materiali',
                        ],
                        'correct_index' => 0,
                        'explanation' => 'Il tutor puÃ² guidare lo studente usando materiali e risultati.',
                        'skill' => 'AI per apprendimento',
                    ],
                    [
                        'question' => 'Che vantaggio dÃ  il RAG rispetto a una risposta generica?',
                        'options' => [
                            'Usa materiali del corso come contesto',
                            'Ignora il corso',
                            'Produce solo immagini',
                            'Disabilita la valutazione',
                        ],
                        'correct_index' => 0,
                        'explanation' => 'Il RAG collega la risposta ai materiali recuperati.',
                        'skill' => 'RAG',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (str_contains($lower, '"branches"') || str_contains($lower, 'mappa mentale')) {
            return json_encode([
                'title' => 'Mappa AI Skill Navigator',
                'central_topic' => 'AI Skill Navigator',
                'summary' => 'Sintesi dei concetti principali del plugin Moodle.',
                'central_description' => 'Plugin Moodle per supportare apprendimento, quiz, RAG e scenari XR.',
                'branches' => [
                    [
                        'title' => 'Tutor AI',
                        'description' => 'Supporta domande dello studente.',
                        'children' => [
                            ['title' => 'Risposte', 'description' => 'Genera spiegazioni didattiche.'],
                            ['title' => 'RAG', 'description' => 'Usa materiali del docente.'],
                        ],
                    ],
                    [
                        'title' => 'Quiz',
                        'description' => 'Produce micro-test formativi.',
                        'children' => [
                            ['title' => 'Domande', 'description' => 'Crea quesiti a scelta multipla.'],
                            ['title' => 'Feedback', 'description' => 'Spiega la risposta corretta.'],
                        ],
                    ],
                    [
                        'title' => 'Mind Map',
                        'description' => 'Visualizza relazioni tra concetti.',
                        'children' => [
                            ['title' => 'Nodi', 'description' => 'Rappresentano concetti principali.'],
                            ['title' => 'Studio', 'description' => 'Aiuta il ripasso.'],
                        ],
                    ],
                    [
                        'title' => 'XR',
                        'description' => 'Genera scenari per mondi virtuali.',
                        'children' => [
                            ['title' => 'Task', 'description' => 'Definisce attivitÃ  operative.'],
                            ['title' => 'Valutazione', 'description' => 'Descrive criteri di completamento.'],
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (str_contains($lower, 'scenario') || str_contains($lower, 'virtual worlds')) {
            return "# Scenario dimostrativo\n\n"
                . "## Obiettivo didattico\nComprendere il rapporto tra IoT, dati e Digital Twin.\n\n"
                . "## Ambiente virtuale\nSmart factory con sensori, dashboard e macchine interattive.\n\n"
                . "## Task dello studente\n1. Identificare i sensori.\n2. Analizzare i dati.\n3. Trovare lâ€™anomalia.\n4. Aggiornare il Digital Twin.\n5. Rispondere al quiz finale.\n\n"
                . "## Criteri di valutazione\n- Correttezza dellâ€™analisi.\n- Uso dei dati.\n- Motivazione della scelta.\n- Comprensione del modello digitale.";
        }

        return 'Risposta dimostrativa: il sistema Ã¨ attivo in modalitÃ  prototype. Configura Ollama o un provider OpenAI-compatible per ottenere risposte generate da un modello reale.';
    }
}