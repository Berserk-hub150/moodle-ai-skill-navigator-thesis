<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

// Backward compatible service used by existing Moodle pages.
class real_ai_service {
    private ai_workflow_facade $facade;

    public function __construct(?ai_provider_interface $provider = null, ?ai_prompt_builder $prompts = null) {
        $this->facade = new ai_workflow_facade($provider ?? ai_provider_factory::create_from_config(), $prompts ?? new ai_prompt_builder());
    }

    public function ask_tutor(string $q): string { return $this->facade->ask_tutor($q); }
    public function ask_with_course_materials(string $q, array $m): string { return $this->facade->ask_with_course_materials($q, $m); }
    public function ask_with_rag_context(string $q, string $c): string { return $this->facade->ask_with_rag_context($q, $c); }
    public function generate_quiz(string $t, string $d): string { return $this->facade->generate_quiz($t, $d); }
    public function generate_quiz_from_course_materials(string $f, string $d, array $m): string { return $this->facade->generate_quiz_from_course_materials($f, $d, $m); }
    public function generate_quiz_with_rag_context(string $f, string $d, string $c): string { return $this->facade->generate_quiz_with_rag_context($f, $d, $c); }
    public function generate_mindmap(string $t): string { return $this->facade->generate_mindmap($t); }
    public function generate_mindmap_from_course_materials(string $f, array $m): string { return $this->facade->generate_mindmap_from_course_materials($f, $m); }
    public function generate_mindmap_with_rag_context(string $f, string $c): string { return $this->facade->generate_mindmap_with_rag_context($f, $c); }
    public function generate_xr_scenario(string $t, string $e): string { return $this->facade->generate_xr_scenario($t, $e); }
    public function generate_xr_scenario_from_course_materials(string $f, string $e, array $m): string { return $this->facade->generate_xr_scenario_from_course_materials($f, $e, $m); }
    public function generate_xr_scenario_with_rag_context(string $f, string $e, string $c): string { return $this->facade->generate_xr_scenario_with_rag_context($f, $e, $c); }
    public function summarize_course_materials(string $f, array $m): string { return $this->facade->summarize_course_materials($f, $m); }
    public function summarize_with_rag_context(string $f, string $c): string { return $this->facade->summarize_with_rag_context($f, $c); }
}
