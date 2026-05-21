<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/material_ai_policy.php');

if (!function_exists('local_aiskillnavigator_current_ai_is_local')) {
    function local_aiskillnavigator_current_ai_is_local(): bool {
        $provider = strtolower(trim((string)get_config('local_aiskillnavigator', 'provider')));
        $endpoint = strtolower(trim((string)get_config('local_aiskillnavigator', 'endpoint')));

        if ($provider === '' || $provider === 'prototype') {
            return true;
        }

        if (in_array($provider, ['ollama', 'local', 'local_ollama'], true)) {
            return true;
        }

        return str_contains($endpoint, 'localhost')
            || str_contains($endpoint, '127.0.0.1')
            || str_contains($endpoint, 'host.docker.internal')
            || str_contains($endpoint, '::1');
    }
}

if (!function_exists('local_aiskillnavigator_material_external_allowed')) {
    function local_aiskillnavigator_material_external_allowed(stdClass $material): bool {
        if (isset($material->externalaiallowed)) {
            return ((int)$material->externalaiallowed) === 1;
        }

        if (isset($material->aipolicy)) {
            return ((string)$material->aipolicy) === 'external_allowed';
        }

        return false;
    }
}

if (!function_exists('local_aiskillnavigator_material_can_be_sent_to_current_ai')) {
    function local_aiskillnavigator_material_can_be_sent_to_current_ai(stdClass $material): bool {
        return local_aiskillnavigator_current_ai_is_local()
            || local_aiskillnavigator_material_external_allowed($material);
    }
}

if (!function_exists('local_aiskillnavigator_ai_policy_label')) {
    function local_aiskillnavigator_ai_policy_label(stdClass $material): string {
        return local_aiskillnavigator_material_external_allowed($material)
            ? 'Allowed for external AI'
            : 'Local AI only';
    }
}

if (!function_exists('local_aiskillnavigator_ai_policy_badge_class')) {
    function local_aiskillnavigator_ai_policy_badge_class(stdClass $material): string {
        return local_aiskillnavigator_material_external_allowed($material)
            ? 'badge badge-success'
            : 'badge badge-secondary';
    }
}

function local_aiskillnavigator_material_source_mode_from_request(int $defaultmaterialid = -1): string {
    $mode = optional_param('sourcemode', '', PARAM_ALPHA);

    if ($mode === '') {
        $mode = optional_param('source_mode', '', PARAM_ALPHA);
    }

    if ($mode === '') {
        $legacy = optional_param('materialid', $defaultmaterialid, PARAM_INT);

        if ($legacy === 0) {
            $mode = 'all';
        } else if ($legacy > 0) {
            $mode = 'selected';
        } else {
            $mode = 'manual';
        }
    }

    return in_array($mode, ['manual', 'all', 'selected'], true) ? $mode : 'manual';
}

function local_aiskillnavigator_material_source_get_readable_materials(int $courseid, bool $includeall = true): array {
    global $DB;

    if (!$DB->get_manager()->table_exists(new xmldb_table('local_aiskillnav_material'))) {
        return [];
    }

    $records = $DB->get_records(
        'local_aiskillnav_material',
        ['courseid' => $courseid],
        'timemodified DESC, timecreated DESC'
    );

    $readable = [];

    foreach ($records as $record) {
        if (trim((string)($record->content ?? '')) === '') {
            continue;
        }

        $readable[(int)$record->id] = $record;
    }

    return $readable;
}

function local_aiskillnavigator_material_source_selected_ids_from_request(array $readablematerials): array {
    $ids = optional_param_array('materialids', [], PARAM_INT);
    $legacy = optional_param('materialid', -1, PARAM_INT);

    if ($legacy > 0) {
        $ids[] = $legacy;
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));

    return array_values(array_filter($ids, function($id) use ($readablematerials) {
        return isset($readablematerials[$id]);
    }));
}

function local_aiskillnavigator_material_source_selected_materials(array $readablematerials, string $sourcemode, array $selectedmaterialids): array {
    if ($sourcemode === 'manual') {
        return [];
    }

    $selected = [];

    if ($sourcemode === 'all') {
        $selected = array_values($readablematerials);
    } else {
        foreach ($selectedmaterialids as $id) {
            $id = (int)$id;

            if (isset($readablematerials[$id])) {
                $selected[] = $readablematerials[$id];
            }
        }
    }

    return array_values(array_filter($selected, function($material) {
        return local_aiskillnavigator_material_can_be_sent_to_current_ai($material);
    }));
}

function local_aiskillnavigator_material_source_legacy_materialid(string $sourcemode, array $selectedmaterialids): int {
    if ($sourcemode === 'all') {
        return 0;
    }

    if ($sourcemode === 'selected' && !empty($selectedmaterialids)) {
        return (int)reset($selectedmaterialids);
    }

    return -1;
}

function local_aiskillnavigator_material_source_clean_title(stdClass $material): string {
    $title = trim((string)($material->title ?? 'Course material'));
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/', '', $title);
    return trim($title) !== '' ? trim($title) : 'Course material';
}

function local_aiskillnavigator_material_source_short_title(stdClass $material): string {
    return local_aiskillnavigator_material_source_clean_title($material) . ' (' . strlen((string)($material->content ?? '')) . ' chars)';
}

function local_aiskillnavigator_material_source_excerpt(string $text, int $limit = 170): string {
    if (function_exists('local_aiskillnavigator_fix_mojibake')) {
        $text = local_aiskillnavigator_fix_mojibake($text);
    }

    $text = trim((string)preg_replace('/\s+/u', ' ', $text));

    if (core_text::strlen($text) > $limit) {
        return core_text::substr($text, 0, $limit) . '...';
    }

    return $text;
}

function local_aiskillnavigator_material_source_search($embeddingservice, string $query, int $courseid, int $limit, string $sourcemode, array $selectedmaterialids): array {
    if ($sourcemode === 'manual') {
        return [];
    }

    $results = [];

    try {
        if (method_exists($embeddingservice, 'search')) {
            if ($sourcemode === 'all') {
                $results = $embeddingservice->search($query, $courseid, $limit);
            } else {
                $merged = [];

                foreach ($selectedmaterialids as $materialid) {
                    $partial = $embeddingservice->search($query, $courseid, $limit, (int)$materialid);

                    foreach ($partial as $result) {
                        $key = (int)($result->id ?? 0);

                        if ($key <= 0) {
                            $key = crc32(($result->title ?? '') . ($result->chunkindex ?? '') . ($result->chunktext ?? ''));
                        }

                        $merged[$key] = $result;
                    }
                }

                $results = array_values($merged);
            }
        } else if (method_exists($embeddingservice, 'semantic_search')) {
            $results = $embeddingservice->semantic_search($courseid, $query, $limit);
        } else if (method_exists($embeddingservice, 'retrieve')) {
            $results = $embeddingservice->retrieve($courseid, $query, $limit);
        }
    } catch (Throwable $e) {
        debugging('AI Skill Navigator material source search skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }

    if (empty($results)) {
        return [];
    }

    if ($sourcemode === 'selected' && !empty($selectedmaterialids)) {
        $selected = array_map('intval', $selectedmaterialids);

        $results = array_values(array_filter($results, function($result) use ($selected) {
            $materialid = isset($result->materialid) ? (int)$result->materialid : 0;
            return in_array($materialid, $selected, true);
        }));
    }

    usort($results, function($a, $b) {
        return ((float)($b->similarity ?? 0)) <=> ((float)($a->similarity ?? 0));
    });

    return array_slice($results, 0, $limit);
}

function local_aiskillnavigator_material_source_search_rag(
    $embeddingservice,
    string $query,
    int $courseid,
    string $sourcemode,
    array $materialids,
    int $topk
): array {
    return local_aiskillnavigator_material_source_search(
        $embeddingservice,
        $query,
        $courseid,
        $topk,
        $sourcemode,
        $materialids
    );
}

function local_aiskillnavigator_material_source_hidden_fields(string $sourcemode, array $materialids): string {
    $html = '';

    $html .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sourcemode',
        'value' => $sourcemode,
    ]);

    foreach ($materialids as $materialid) {
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'materialids[]',
            'value' => (int)$materialid,
        ]);
    }

    return $html;
}

function local_aiskillnavigator_material_source_hidden_inputs(string $sourcemode, array $materialids): string {
    return local_aiskillnavigator_material_source_hidden_fields($sourcemode, $materialids);
}

function local_aiskillnavigator_material_source_selector_html(
    array $readablematerials,
    $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $selectedmaterialids,
    string $label = 'Source',
    string $help = ''
): string {
    $sourcemode = in_array($sourcemode, ['manual', 'all', 'selected'], true) ? $sourcemode : 'manual';

    if ($sourcemode === 'all') {
        $sourcemode = 'selected';
    }

    $html = '';

    $html .= html_writer::tag('style', '
.aisn-material.is-disabled {
    opacity: .62;
    cursor: not-allowed;
    background: #f8fafc;
}
.aisn-material.is-disabled:hover {
    transform: none;
    border-color: #e5e7eb;
    box-shadow: none;
}
.aisn-material-note {
    display: block;
    margin-top: 8px;
    color: #92400e;
    font-size: 12px;
    font-weight: 700;
}
');

    $html .= html_writer::start_div('aisn-source-box');

    $html .= html_writer::tag('label', s($label), ['class' => 'font-weight-bold']);

    $html .= html_writer::start_div('aisn-choice-row');

    $html .= html_writer::start_tag('label', ['class' => 'aisn-choice']);
    $html .= html_writer::empty_tag('input', [
        'type' => 'radio',
        'name' => 'sourcemode',
        'value' => 'manual',
        'checked' => $sourcemode === 'manual' ? 'checked' : null,
    ]);
    $html .= html_writer::span('Question/topic only', 'aisn-choice-title');
    $html .= html_writer::span('Use 0 course materials.', 'aisn-choice-text');
    $html .= html_writer::end_tag('label');

    $html .= html_writer::start_tag('label', ['class' => 'aisn-choice']);
    $html .= html_writer::empty_tag('input', [
        'type' => 'radio',
        'name' => 'sourcemode',
        'value' => 'selected',
        'checked' => $sourcemode === 'selected' ? 'checked' : null,
    ]);
    $html .= html_writer::span('Use course materials', 'aisn-choice-title');
    $html .= html_writer::span('Choose one or more allowed materials below.', 'aisn-choice-text');
    $html .= html_writer::end_tag('label');

    $html .= html_writer::end_div();

    $panelclass = $sourcemode === 'manual' ? 'aisn-material-panel aisn-hidden' : 'aisn-material-panel';

    $html .= html_writer::start_div($panelclass, ['data-aisn-material-panel' => '1']);

    if (empty($readablematerials)) {
        $html .= html_writer::div(
            'No course materials found yet. Add a Moodle File, Page, Label, Folder, URL or Book resource to this course.',
            'aisn-empty'
        );
    } else {
        $html .= html_writer::tag('p', 'Select one or more course materials.', ['class' => 'aisn-muted']);
        $html .= html_writer::start_div('aisn-material-grid');

        foreach ($readablematerials as $material) {
            $id = (int)$material->id;
            $allowed = local_aiskillnavigator_material_can_be_sent_to_current_ai($material);
            $checked = $allowed && in_array($id, $selectedmaterialids, true) && $sourcemode === 'selected';

            $class = $allowed ? 'aisn-material' : 'aisn-material is-disabled';

            $html .= html_writer::start_tag('label', ['class' => $class]);

            $inputattrs = [
                'type' => 'checkbox',
                'name' => 'materialids[]',
                'value' => $id,
                'checked' => $checked ? 'checked' : null,
            ];

            if (!$allowed) {
                $inputattrs['disabled'] = 'disabled';
            }

            $html .= html_writer::empty_tag('input', $inputattrs);

            $html .= html_writer::span(s(local_aiskillnavigator_material_source_clean_title($material)), 'aisn-material-title');
            $html .= html_writer::empty_tag('br');
            $html .= html_writer::span(strlen((string)$material->content) . ' chars', 'aisn-badge');
            $html .= ' ';
            $html .= html_writer::span(s(local_aiskillnavigator_ai_policy_label($material)), local_aiskillnavigator_ai_policy_badge_class($material));

            if (!$allowed) {
                $html .= html_writer::span('Not selectable with the current external provider.', 'aisn-material-note');
            }

            $html .= html_writer::div(
                s(local_aiskillnavigator_material_source_excerpt((string)$material->content)),
                'aisn-excerpt'
            );

            $html .= html_writer::end_tag('label');
        }

        $html .= html_writer::end_div();
    }

    $html .= html_writer::end_div();

    $html .= html_writer::tag('script', '
(function() {
    const radios = document.querySelectorAll("input[name=\"sourcemode\"], input[name=\"source_mode\"]");
    const panels = document.querySelectorAll("[data-aisn-material-panel=\"1\"], #materials-panel");

    function refresh() {
        const selected = document.querySelector("input[name=\"sourcemode\"]:checked") ||
                         document.querySelector("input[name=\"source_mode\"]:checked");

        panels.forEach(function(panel) {
            const boxes = panel.querySelectorAll("input[type=\"checkbox\"]");

            if (!selected || selected.value === "manual") {
                panel.classList.add("aisn-hidden");
                boxes.forEach(function(box) {
                    box.checked = false;
                    if (!box.dataset.forceDisabled) {
                        box.disabled = true;
                    }
                });
            } else {
                panel.classList.remove("aisn-hidden");
                boxes.forEach(function(box) {
                    if (!box.dataset.forceDisabled) {
                        box.disabled = false;
                    }
                });
            }
        });
    }

    document.querySelectorAll(".aisn-material.is-disabled input[type=\"checkbox\"]").forEach(function(box) {
        box.dataset.forceDisabled = "1";
        box.disabled = true;
        box.checked = false;
    });

    radios.forEach(function(radio) {
        radio.addEventListener("change", refresh);
    });

    refresh();
})();
');

    $html .= html_writer::end_div();

    return $html;
}

function local_aiskillnavigator_material_source_render_controls(
    array $readablematerials,
    $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $materialids,
    string $label,
    string $manualtext,
    string $alltext,
    string $selectedtext,
    string $helptext
): string {
    return local_aiskillnavigator_material_source_selector_html(
        $readablematerials,
        $embeddingservice,
        $courseid,
        $sourcemode,
        $materialids,
        $label,
        $helptext
    );
}

function local_aiskillnavigator_material_source_footer_script(): string {
    return '';
}

function local_aiskillnavigator_material_source_count_chunks(
    $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $materialids
): int {
    if ($sourcemode === 'manual') {
        return 0;
    }

    if ($sourcemode === 'all' && method_exists($embeddingservice, 'count_indexed_chunks')) {
        return $embeddingservice->count_indexed_chunks($courseid);
    }

    $total = 0;

    foreach ($materialids as $materialid) {
        if (method_exists($embeddingservice, 'count_indexed_chunks')) {
            $total += (int)$embeddingservice->count_indexed_chunks($courseid, (int)$materialid);
        }
    }

    return $total;
}