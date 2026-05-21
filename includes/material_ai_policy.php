<?php

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_current_ai_is_local(): bool {
    $provider = strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
    $endpoint = strtolower(trim((string)get_config('local_aiskillnavigator', 'endpoint')));

    if ($provider === '' || $provider === 'prototype') {
        return true;
    }

    if (in_array($provider, ['ollama', 'local', 'local_ollama'], true)) {
        return true;
    }

    if (
        str_contains($endpoint, 'localhost') ||
        str_contains($endpoint, '127.0.0.1') ||
        str_contains($endpoint, 'host.docker.internal') ||
        str_contains($endpoint, '::1')
    ) {
        return true;
    }

    return false;
}

function local_aiskillnavigator_material_external_allowed(stdClass $material): bool {
    if (isset($material->externalaiallowed)) {
        return ((int)$material->externalaiallowed) === 1;
    }

    if (isset($material->aipolicy)) {
        return ((string)$material->aipolicy) === 'external_allowed';
    }

    return false;
}

function local_aiskillnavigator_material_can_be_sent_to_current_ai(stdClass $material): bool {
    if (local_aiskillnavigator_current_ai_is_local()) {
        return true;
    }

    return local_aiskillnavigator_material_external_allowed($material);
}

function local_aiskillnavigator_filter_materials_for_current_ai(array $materials): array {
    $filtered = [];

    foreach ($materials as $key => $material) {
        if (local_aiskillnavigator_material_can_be_sent_to_current_ai($material)) {
            $filtered[$key] = $material;
        }
    }

    return $filtered;
}

function local_aiskillnavigator_ai_policy_label(stdClass $material): string {
    return local_aiskillnavigator_material_external_allowed($material)
        ? 'Allowed for external AI'
        : 'Local AI only';
}

function local_aiskillnavigator_ai_policy_badge_class(stdClass $material): string {
    return local_aiskillnavigator_material_external_allowed($material)
        ? 'badge badge-success'
        : 'badge badge-secondary';
}

function local_aiskillnavigator_set_material_ai_policy(int $materialid, int $courseid, bool $externalallowed): bool {
    global $DB;

    $material = $DB->get_record('local_aiskillnav_material', [
        'id' => $materialid,
        'courseid' => $courseid,
    ]);

    if (!$material) {
        return false;
    }

    $material->externalaiallowed = $externalallowed ? 1 : 0;
    $material->aipolicy = $externalallowed ? 'external_allowed' : 'local_only';
    $material->timemodified = time();

    $DB->update_record('local_aiskillnav_material', $material);

    return true;
}

function local_aiskillnavigator_provider_privacy_notice(): string {
    if (local_aiskillnavigator_current_ai_is_local()) {
        return 'Current AI provider is local/prototype: course materials can be used without sending them to an external provider.';
    }

    return 'Current AI provider is external: only materials explicitly allowed by the teacher can be sent to the AI provider.';
}