<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Facade for AI workflows used by Moodle pages.
 */
class ai_workflow_facade {

    private ai_provider_interface $provider;
    private ai_prompt_builder $prompts;

    public function __construct(ai_provider_interface $provider, ai_prompt_builder $prompts) {
        $this->provider = $provider;
        $this->prompts = $prompts;
    }

    public function ask_tutor(string $question): string {
        $question = trim($question);

        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor AI.';
        }

        return $this->provider->generate($this->prompts->tutor_prompt($question), 1000);
    }

    public function ask_with_course_materials(string $question, array $materials): string {
        $question = trim($question);

        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        if (empty($materials)) {
            return 'Non sono stati trovati materiali del docente rilevanti per rispondere alla domanda.';
        }

        return $this->provider->generate($this->prompts->tutor_with_materials_prompt($question, $materials), 1400);
    }

    public function ask_with_rag_context(string $question, string $ragcontext): string {
        $question = trim($question);

        if ($question === '') {
            return 'Scrivi una domanda prima di inviarla al tutor del corso.';
        }

        if (trim($ragcontext) === '') {
            return 'Non sono stati trovati materiali rilevanti nel RAG index per rispondere alla domanda.';
        }

        return $this->provider->generate($this->prompts->tutor_with_rag_prompt($question, $ragcontext), 1400);
    }

    public function generate_quiz(string $topic, string $difficulty): string {
        return $this->provider->generate($this->prompts->quiz_prompt($topic, $difficulty), 2200);
    }

    public function generate_quiz_from_course_materials(string $focus, string $difficulty, array $materials): string {
        if (empty($materials)) {
            return $this->generate_quiz(trim($focus) !== '' ? $focus : 'Course materials', $difficulty);
        }

        return $this->provider->generate($this->prompts->quiz_from_materials_prompt($focus, $difficulty, $materials), 2400);
    }

    public function generate_quiz_with_rag_context(string $focus, string $difficulty, string $ragcontext): string {
        if (trim($ragcontext) === '') {
            return $this->generate_quiz(trim($focus) !== '' ? $focus : 'Course materials', $difficulty);
        }

        return $this->provider->generate($this->prompts->quiz_with_rag_prompt($focus, $difficulty, $ragcontext), 2400);
    }

    public function generate_mindmap(string $topic): string {
        return $this->provider->generate($this->prompts->mindmap_prompt($topic), 1500);
    }

    public function generate_mindmap_from_course_materials(string $focus, array $materials): string {
        if (empty($materials)) {
            return $this->generate_mindmap(trim($focus) !== '' ? $focus : 'Course materials');
        }

        return $this->provider->generate($this->prompts->mindmap_from_materials_prompt($focus, $materials), 1800);
    }

    public function generate_mindmap_with_rag_context(string $focus, string $ragcontext): string {
        if (trim($ragcontext) === '') {
            return $this->generate_mindmap(trim($focus) !== '' ? $focus : 'Course materials');
        }

        return $this->provider->generate($this->prompts->mindmap_with_rag_prompt($focus, $ragcontext), 1800);
    }

    public function generate_xr_scenario(string $topic, string $environment): string {
        return $this->provider->generate($this->prompts->xr_scenario_prompt($topic, $environment), 2800);
    }

    public function generate_xr_scenario_from_course_materials(string $focus, string $environment, array $materials): string {
        if (empty($materials)) {
            return $this->generate_xr_scenario(trim($focus) !== '' ? $focus : 'Digital Twin and IoT', $environment);
        }

        return $this->provider->generate($this->prompts->xr_scenario_from_materials_prompt($focus, $environment, $materials), 3000);
    }

    public function generate_xr_scenario_with_rag_context(string $focus, string $environment, string $ragcontext): string {
        if (trim($ragcontext) === '') {
            return $this->generate_xr_scenario(trim($focus) !== '' ? $focus : 'Digital Twin and IoT', $environment);
        }

        return $this->provider->generate($this->prompts->xr_scenario_with_rag_prompt($focus, $environment, $ragcontext), 3000);
    }

    public function summarize_course_materials(string $focus, array $materials): string {
        if (empty($materials)) {
            return 'Non sono stati trovati materiali leggibili del docente da riassumere.';
        }

        return $this->provider->generate($this->prompts->summarize_materials_prompt($focus, $materials), 1600);
    }

    public function summarize_with_rag_context(string $focus, string $ragcontext): string {
        if (trim($ragcontext) === '') {
            return 'Non sono stati trovati materiali rilevanti nel RAG index da riassumere.';
        }

        return $this->provider->generate($this->prompts->summarize_rag_prompt($focus, $ragcontext), 1600);
    }
}