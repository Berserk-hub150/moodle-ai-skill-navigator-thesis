<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_material_source_from_request(): array {
    $sourcemode = optional_param('sourcemode', '', PARAM_ALPHA);
    $materialids = optional_param_array('materialids', [], PARAM_INT);

    // Backward compatibility with old URLs using materialid.
    $oldmaterialid = optional_param('materialid', -999999, PARAM_INT);

    if ($sourcemode === '' && empty($materialids) && $oldmaterialid !== -999999) {
        if ($oldmaterialid === -1) {
            $sourcemode = 'manual';
        } else if ($oldmaterialid === 0) {
            $sourcemode = 'all';
        } else if ($oldmaterialid > 0) {
            $sourcemode = 'selected';
            $materialids = [$oldmaterialid];
        }
    }

    if (!in_array($sourcemode, ['manual', 'all', 'selected'], true)) {
        $sourcemode = 'manual';
    }

    $materialids = array_values(array_unique(array_filter(array_map('intval', $materialids), function ($id) {
        return $id > 0;
    })));

    return [
        'sourcemode' => $sourcemode,
        'materialids' => $materialids,
    ];
}

function local_aiskillnavigator_material_source_short_title(stdClass $material): string {
    $title = trim((string) ($material->title ?? 'Materiale senza titolo'));

    if ($title === '') {
        $title = 'Materiale senza titolo';
    }

    $contentlength = strlen((string) ($material->content ?? ''));

    return $title . ' (' . $contentlength . ' chars)';
}

function local_aiskillnavigator_material_source_get_readable_materials(int $courseid): array {
    global $DB;

    $materials = $DB->get_records(
        'local_aiskillnav_material',
        ['courseid' => $courseid],
        'timecreated DESC'
    );

    $readablematerials = [];

    foreach ($materials as $material) {
        $content = trim((string) ($material->content ?? ''));

        if ($content !== '') {
            $readablematerials[(int) $material->id] = $material;
        }
    }

    return $readablematerials;
}

function local_aiskillnavigator_material_source_select_materials(array $readablematerials, string $sourcemode, array $materialids): array {
    if ($sourcemode === 'manual') {
        return [];
    }

    if ($sourcemode === 'all') {
        return array_values($readablematerials);
    }

    $selected = [];

    foreach ($materialids as $materialid) {
        if (isset($readablematerials[$materialid])) {
            $selected[(int) $materialid] = $readablematerials[$materialid];
        }
    }

    return array_values($selected);
}

function local_aiskillnavigator_material_source_count_chunks(
    \local_aiskillnavigator\service\embedding_service $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $materialids
): int {
    if ($sourcemode === 'manual') {
        return 0;
    }

    if ($sourcemode === 'all') {
        return $embeddingservice->count_indexed_chunks($courseid);
    }

    $total = 0;

    foreach ($materialids as $materialid) {
        $total += $embeddingservice->count_indexed_chunks($courseid, (int) $materialid);
    }

    return $total;
}

function local_aiskillnavigator_material_source_search_rag(
    \local_aiskillnavigator\service\embedding_service $embeddingservice,
    string $query,
    int $courseid,
    string $sourcemode,
    array $materialids,
    int $topk
): array {
    if ($sourcemode === 'manual') {
        return [];
    }

    if ($sourcemode === 'all') {
        return $embeddingservice->search($query, $courseid, $topk, 0);
    }

    $merged = [];

    foreach ($materialids as $materialid) {
        $results = $embeddingservice->search($query, $courseid, $topk, (int) $materialid);

        foreach ($results as $result) {
            $key = (int) ($result->id ?? 0);

            if ($key <= 0) {
                $key = crc32(($result->title ?? '') . ($result->chunkindex ?? '') . ($result->chunktext ?? ''));
            }

            $merged[$key] = $result;
        }
    }

    $merged = array_values($merged);

    usort($merged, function ($a, $b) {
        return ((float) ($b->similarity ?? 0)) <=> ((float) ($a->similarity ?? 0));
    });

    return array_slice($merged, 0, $topk);
}

function local_aiskillnavigator_material_source_render_controls(
    array $readablematerials,
    \local_aiskillnavigator\service\embedding_service $embeddingservice,
    int $courseid,
    string $sourcemode,
    array $materialids,
    string $label,
    string $manualtext,
    string $alltext,
    string $selectedtext,
    string $helptext
): string {
    $html = '';

    $html .= html_writer::start_div('form-group');
    $html .= html_writer::tag('label', $label, ['for' => 'sourcemode']);

    $html .= html_writer::select(
        [
            'manual' => $manualtext,
            'all' => $alltext,
            'selected' => $selectedtext,
        ],
        'sourcemode',
        $sourcemode,
        false,
        [
            'class' => 'form-control',
            'id' => 'sourcemode',
        ]
    );

    $html .= html_writer::tag('small', $helptext, ['class' => 'form-text text-muted']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('form-group mt-3', ['id' => 'selectedMaterialsBlock']);
    $html .= html_writer::tag('label', 'Selected teacher materials', ['for' => 'materialids']);

    $materialoptions = [];

    foreach ($readablematerials as $material) {
        $chunks = $embeddingservice->count_indexed_chunks($courseid, (int) $material->id);
        $materialoptions[(int) $material->id] =
            local_aiskillnavigator_material_source_short_title($material) . ' - RAG chunks: ' . $chunks;
    }

    if (empty($materialoptions)) {
        $html .= html_writer::div('No readable teacher materials available yet.', 'alert alert-info mb-0');
    } else {
        $html .= html_writer::select(
            $materialoptions,
            'materialids[]',
            $materialids,
            false,
            [
                'class' => 'form-control',
                'id' => 'materialids',
                'multiple' => 'multiple',
                'size' => min(8, max(3, count($materialoptions))),
            ]
        );

        $html .= html_writer::tag(
            'small',
            'Hold CTRL to select multiple specific materials.',
            ['class' => 'form-text text-muted']
        );
    }

    $html .= html_writer::end_div();

    return $html;
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
            'value' => (int) $materialid,
        ]);
    }

    return $html;
}

function local_aiskillnavigator_material_source_footer_script(): string {
    return html_writer::tag('style', '
#selectedMaterialsBlock.is-hidden {
    display: none;
}
') . html_writer::tag('script', '
(function () {
    function updateMaterialSelectionVisibility() {
        var sourceMode = document.getElementById("sourcemode");
        var block = document.getElementById("selectedMaterialsBlock");

        if (!sourceMode || !block) {
            return;
        }

        if (sourceMode.value === "selected") {
            block.classList.remove("is-hidden");
        } else {
            block.classList.add("is-hidden");
        }
    }

    var sourceMode = document.getElementById("sourcemode");

    if (sourceMode) {
        sourceMode.addEventListener("change", updateMaterialSelectionVisibility);
    }

    updateMaterialSelectionVisibility();
})();
');
}