<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/material_ai_policy.php');

function local_aiskillnavigator_material_source_mode_from_request(int $defaultmaterialid = -1): string {
    $mode = optional_param('sourcemode', '', PARAM_ALPHA);

    if ($mode === '') {
        $mode = optional_param('source_mode', '', PARAM_ALPHA);
    }

    if ($mode === '') {
        $legacy = optional_param('materialid', $defaultmaterialid, PARAM_INT);
        $mode = $legacy > 0 ? 'selected' : 'manual';
    }

    if ($mode === 'all') {
        $mode = 'selected';
    }

    return in_array($mode, ['manual', 'selected'], true) ? $mode : 'manual';
}

function local_aiskillnavigator_material_source_get_readable_materials(int $courseid, bool $respectprivacy = true): array {
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

        if ($respectprivacy && !local_aiskillnavigator_material_can_be_sent_to_current_ai($record)) {
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

    $materials = [];

    foreach ($selectedmaterialids as $id) {
        $id = (int)$id;

        if (isset($readablematerials[$id])) {
            $materials[] = $readablematerials[$id];
        }
    }

    return local_aiskillnavigator_filter_materials_for_current_ai($materials);
}

function local_aiskillnavigator_material_source_legacy_materialid(string $sourcemode, array $selectedmaterialids): int {
    if ($sourcemode !== 'selected' || empty($selectedmaterialids)) {
        return -1;
    }

    return (int)reset($selectedmaterialids);
}

function local_aiskillnavigator_material_source_clean_title(stdClass $material): string {
    $title = trim((string)($material->title ?? 'Course material'));
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/', '', $title);
    return trim($title) !== '' ? trim($title) : 'Course material';
}

function local_aiskillnavigator_material_source_excerpt(string $text, int $limit = 170): string {
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
            $results = $embeddingservice->search($query, $courseid, $limit);
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

    $selected = array_map('intval', $selectedmaterialids);

    if (!empty($selected)) {
        $results = array_values(array_filter($results, function($result) use ($selected) {
            $materialid = isset($result->materialid) ? (int)$result->materialid : 0;
            return in_array($materialid, $selected, true);
        }));
    }

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

function local_aiskillnavigator_material_source_short_title(stdClass $material): string {
    return local_aiskillnavigator_material_source_clean_title($material) . ' (' . strlen((string)($material->content ?? '')) . ' chars)';
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
    $sourcemode = in_array($sourcemode, ['manual', 'selected'], true) ? $sourcemode : 'manual';

    $html = '';

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
    $html .= html_writer::span('Only materials allowed for the current AI provider are shown.', 'aisn-choice-text');
    $html .= html_writer::end_tag('label');

    $html .= html_writer::end_div();

    $html .= html_writer::div(local_aiskillnavigator_provider_privacy_notice(), 'alert alert-info');

    $panelclass = $sourcemode === 'manual' ? 'aisn-material-panel aisn-hidden' : 'aisn-material-panel';

    $html .= html_writer::start_div($panelclass, ['data-aisn-material-panel' => '1']);

    if (empty($readablematerials)) {
        $html .= html_writer::div(
            'No readable materials are available for the current AI provider. If you use an external provider, allow selected materials from Course materials / RAG.',
            'aisn-empty'
        );
    } else {
        $html .= html_writer::tag('p', 'Select one or more course materials.', ['class' => 'aisn-muted']);
        $html .= html_writer::start_div('aisn-material-grid');

        foreach ($readablematerials as $material) {
            $id = (int)$material->id;
            $checked = in_array($id, $selectedmaterialids, true) && $sourcemode === 'selected';

            $html .= html_writer::start_tag('label', ['class' => 'aisn-material']);

            $html .= html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'materialids[]',
                'value' => $id,
                'checked' => $checked ? 'checked' : null,
            ]);

            $html .= html_writer::span(s(local_aiskillnavigator_material_source_clean_title($material)), 'aisn-material-title');
            $html .= html_writer::empty_tag('br');
            $html .= html_writer::span(strlen((string)$material->content) . ' chars', 'aisn-badge');
            $html .= ' ';
            $html .= html_writer::span(s(local_aiskillnavigator_ai_policy_label($material)), local_aiskillnavigator_ai_policy_badge_class($material));

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
    const radios = document.querySelectorAll("input[name=\"sourcemode\"]");
    const panels = document.querySelectorAll("[data-aisn-material-panel=\"1\"]");

    function refresh() {
        const selected = document.querySelector("input[name=\"sourcemode\"]:checked");

        panels.forEach(function(panel) {
            const boxes = panel.querySelectorAll("input[type=\"checkbox\"]");

            if (!selected || selected.value === "manual") {
                panel.classList.add("aisn-hidden");
                boxes.forEach(function(box) {
                    box.checked = false;
                    box.disabled = true;
                });
            } else {
                panel.classList.remove("aisn-hidden");
                boxes.forEach(function(box) {
                    box.disabled = false;
                });
            }
        });
    }

    radios.forEach(function(radio) {
        radio.addEventListener("change", refresh);
    });

    refresh();
})();
');

    $html .= html_writer::end_div();

    return $html;
}