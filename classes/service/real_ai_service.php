<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Backward-compatible facade used by existing Moodle pages.
 */
class real_ai_service {

    private ai_workflow_facade $facade;

    public function __construct(?ai_provider_interface $provider = null, ?ai_prompt_builder $prompts = null) {
        $this->facade = new ai_workflow_facade(
            $provider ?? ai_provider_factory::create_from_config(),
            $prompts ?? new ai_prompt_builder()
        );
    }

    public function ask_tutor(string $question): string {
        return $this->facade->ask_tutor($question);
    }

    public function ask_with_course_materials(string $question, array $materials): string {
        return $this->facade->ask_with_course_materials($question, $materials);
    }

    public function ask_with_rag_context(string $question, string $ragcontext): string {
        return $this->facade->ask_with_rag_context($question, $ragcontext);
    }

    public function generate_quiz(string $topic, string $difficulty): string {
        return $this->facade->generate_quiz($topic, $difficulty);
    }

    public function generate_quiz_from_course_materials(string $focus, string $difficulty, array $materials): string {
        return $this->facade->generate_quiz_from_course_materials($focus, $difficulty, $materials);
    }

    public function generate_quiz_with_rag_context(string $focus, string $difficulty, string $ragcontext): string {
        return $this->facade->generate_quiz_with_rag_context($focus, $difficulty, $ragcontext);
    }

    public function generate_mindmap(string $topic): string {
        return $this->facade->generate_mindmap($topic);
    }

    public function generate_mindmap_from_course_materials(string $focus, array $materials): string {
        return $this->facade->generate_mindmap_from_course_materials($focus, $materials);
    }

    public function generate_mindmap_with_rag_context(string $focus, string $ragcontext): string {
        return $this->facade->generate_mindmap_with_rag_context($focus, $ragcontext);
    }

    public function generate_xr_scenario(string $topic, string $environment): string {
        return $this->facade->generate_xr_scenario($topic, $environment);
    }

    public function generate_xr_scenario_from_course_materials(string $focus, string $environment, array $materials): string {
        return $this->facade->generate_xr_scenario_from_course_materials($focus, $environment, $materials);
    }

    public function generate_xr_scenario_with_rag_context(string $focus, string $environment, string $ragcontext): string {
        return $this->facade->generate_xr_scenario_with_rag_context($focus, $environment, $ragcontext);
    }

    public function summarize_course_materials(string $focus, array $materials): string {
        return $this->facade->summarize_course_materials($focus, $materials);
    }

    public function summarize_with_rag_context(string $focus, string $ragcontext): string {
        return $this->facade->summarize_with_rag_context($focus, $ragcontext);
    }
}