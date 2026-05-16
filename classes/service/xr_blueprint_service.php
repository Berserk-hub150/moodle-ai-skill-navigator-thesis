<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates exportable XR blueprints.
 */
class xr_blueprint_service {

    private ai_provider_interface $provider;

    public function __construct(?ai_provider_interface $provider = null) {
        $this->provider = $provider ?? ai_provider_factory::create_from_config();
    }

    public function generate_blueprint(string $topic, string $environment): string {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->provider->generate($this->build_prompt($topic, $environment, ''), 4200, $this->system_prompt());
    }

    public function generate_blueprint_from_course_materials(string $focus, string $environment, array $materials): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        if (empty($materials)) {
            return $this->generate_blueprint($focus, $environment);
        }

        return $this->provider->generate(
            $this->build_prompt($focus, $environment, $this->build_material_context($materials, 3200)),
            4500,
            $this->system_prompt()
        );
    }

    public function generate_blueprint_with_rag_context(string $focus, string $environment, string $ragcontext): string {
        $focus = trim($focus) !== '' ? trim($focus) : 'Materiali del docente';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return $this->provider->generate($this->build_prompt($focus, $environment, $ragcontext), 4500, $this->system_prompt());
    }

    private function system_prompt(): string {
        return 'You are a strict JSON generator for educational XR blueprints. Return only valid JSON.';
    }

    private function build_prompt(string $topic, string $environment, string $context): string {
        $prompt = "Sei un generatore di blueprint XR per un plugin Moodle universitario.\n"
            . "Devi generare un JSON strutturato esportabile verso Virtual Worlds, A-Frame, Unity o Mozilla Hubs.\n\n"
            . "Topic/focus: {$topic}\n"
            . "Ambiente virtuale: {$environment}\n\n"
            . "Genera coordinate, oggetti, punti di interesse, task, checkpoint, trigger, dialoghi e obiettivi didattici.\n\n";

        if (trim($context) !== '') {
            $prompt .= "CONTESTO MATERIALI/RAG:\n" . $context . "\nUsa SOLO i concetti presenti nel contesto.\n\n";
        }

        return $prompt
            . "Rispondi SOLO con JSON valido, senza Markdown.\n"
            . "Genera almeno 5 objects, 4 points_of_interest, 5 tasks, 4 checkpoints, 4 triggers e 4 dialogs.\n"
            . "Usa coordinate x/y numeriche da 0 a 100.\n";
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

            $context .= "FONTE {$number}\nTitolo: {$title}\nTipo: {$type}\nContenuto: {$content}\n\n";
        }

        return $context;
    }
}