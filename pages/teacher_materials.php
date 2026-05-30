<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/material_ai_policy.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/mojibake_guard.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$materialid = optional_param('materialid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

require_capability('local/aiskillnavigator:managematerials', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', ['courseid' => $courseid]));
$PAGE->set_title('Course materials / RAG');
$PAGE->set_heading('Course materials / RAG');

function local_aisn_tm_table_exists(string $name): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($name));
}

function local_aisn_tm_get_material(int $materialid, int $courseid): stdClass {
    global $DB;

    $material = $DB->get_record('local_aiskillnav_material', [
        'id' => $materialid,
        'courseid' => $courseid,
    ]);

    if (!$material) {
        throw new moodle_exception('invalidrecord', 'error');
    }

    return $material;
}

function local_aisn_tm_visible_course_materials(int $courseid): array {
    global $DB;

    if (!local_aisn_tm_table_exists('local_aiskillnav_material')) {
        return [];
    }

    local_aiskillnavigator_sync_course_resources($courseid, 0, true);

    $records = $DB->get_records('local_aiskillnav_material', [
        'courseid' => $courseid,
        'materialtype' => 'course_resource',
    ], 'title ASC');

    $modinfo = get_fast_modinfo($courseid);
    $visible = [];

    foreach ($records as $record) {
        $title = (string)($record->title ?? '');

        if (!preg_match('/^\[Course #([0-9]+) \/ cm #([0-9]+)\]/', $title, $matches)) {
            continue;
        }

        $cmid = (int)$matches[2];

        if (empty($modinfo->cms[$cmid]) || empty($modinfo->cms[$cmid]->visible)) {
            continue;
        }

        $visible[(int)$record->id] = $record;
    }

    return $visible;
}

function local_aisn_tm_cm_id_from_title(string $title): int {
    if (preg_match('/^\[Course #[0-9]+ \/ cm #([0-9]+)\]/', $title, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function local_aisn_tm_clean_course_title(string $title): string {
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/', '', $title);
    return trim((string)$title);
}

function local_aisn_tm_excerpt(string $content, int $max = 600): string {
    $content = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));

    if (core_text::strlen($content) > $max) {
        return core_text::substr($content, 0, $max) . '...';
    }

    return $content;
}

function local_aisn_tm_chunk_counts(array $materials): array {
    global $DB;

    if (empty($materials) || !local_aisn_tm_table_exists('local_aiskillnav_chunk')) {
        return [];
    }

    $ids = array_keys($materials);
    list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mid');

    $sql = "SELECT materialid, COUNT(1) AS chunks
              FROM {local_aiskillnav_chunk}
             WHERE materialid $insql
          GROUP BY materialid";

    $rows = $DB->get_records_sql($sql, $params);
    $counts = [];

    foreach ($rows as $row) {
        $counts[(int)$row->materialid] = (int)$row->chunks;
    }

    return $counts;
}

function local_aisn_tm_set_policy(stdClass $material, bool $externalallowed): void {
    global $DB;

    $material->externalaiallowed = $externalallowed ? 1 : 0;
    $material->aipolicy = $externalallowed ? 'external_allowed' : 'local_only';
    $material->timemodified = time();

    $DB->update_record('local_aiskillnav_material', $material);

    $cmid = local_aisn_tm_cm_id_from_title((string)$material->title);

    if ($cmid > 0) {
        set_config(
            'cm_external_ai_' . $cmid,
            $externalallowed ? '1' : '0',
            'local_aiskillnavigator'
        );
    }
}

function local_aisn_tm_delete_material(stdClass $material): void {
    global $DB;

    if (local_aisn_tm_table_exists('local_aiskillnav_chunk')) {
        $DB->delete_records('local_aiskillnav_chunk', ['materialid' => (int)$material->id]);
    }

    $DB->delete_records('local_aiskillnav_material', ['id' => (int)$material->id]);
}

if ($action !== '' && $materialid > 0) {
    $material = local_aisn_tm_get_material($materialid, $courseid);

    if ($action === 'allow') {
        local_aisn_tm_set_policy($material, true);
        redirect($PAGE->url, 'Material allowed for external AI providers.', 1);
    }

    if ($action === 'restrict') {
        local_aisn_tm_set_policy($material, false);
        redirect($PAGE->url, 'Material restricted to local AI only.', 1);
    }

    if ($action === 'delete') {
        local_aisn_tm_delete_material($material);
        redirect($PAGE->url, 'Material record deleted from AI index.', 1);
    }
}

$materials = local_aisn_tm_visible_course_materials($courseid);
$chunkscounts = local_aisn_tm_chunk_counts($materials);

echo $OUTPUT->header();

echo html_writer::tag('style', '
body.path-local-aiskillnavigator #page-header,
body.path-local-aiskillnavigator #page-navbar,
body.path-local-aiskillnavigator .secondary-navigation,
body.path-local-aiskillnavigator nav.moremenu,
body.path-local-aiskillnavigator .moremenu,
body.path-local-aiskillnavigator .nav-tabs {
    display: none !important;
}

.aisn-material-policy-page {
    max-width: 1180px;
    margin: 0 auto;
}

.aisn-material-policy-page .alert {
    border-radius: 14px;
}

.aisn-material-policy-page .card {
    border-radius: 18px;
}
');


echo html_writer::start_div('container-fluid aisn-material-policy-page');

if (empty($materials)) {
    echo html_writer::div(
        'No visible course resources found. Add a file/page/resource in Moodle Edit mode or create material through AI Course Builder; this page will synchronize automatically.',
        'alert alert-warning'
    );

    echo html_writer::end_div();
echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('h3', 'Synchronized course materials');

echo html_writer::tag('style', '
.aisn-material-search-wrap {
    margin: 16px 0 22px;
}
.aisn-material-search {
    max-width: 520px;
    border-radius: 12px;
    padding: 12px 14px;
}
.aisn-bottom-back {
    margin-top: 28px;
}
');

echo html_writer::start_div('aisn-material-search-wrap');
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'id' => 'aisn-material-search',
    'class' => 'form-control aisn-material-search',
    'placeholder' => 'Search by title, filename, text or AI policy...'
]);
echo html_writer::end_div();

echo html_writer::tag('script', '
(function() {
    const search = document.getElementById("aisn-material-search");
    if (!search) { return; }

    const cards = Array.from(document.querySelectorAll("[data-aisn-material-card]"));

    search.addEventListener("input", function() {
        const q = String(search.value || "").toLowerCase().trim();

        cards.forEach(function(card) {
            const haystack = String(card.dataset.search || card.textContent || "").toLowerCase();
            card.style.display = haystack.indexOf(q) !== -1 ? "" : "none";
        });
    });
})();
');


foreach ($materials as $material) {
    $materialid = (int)$material->id;
    $title = local_aisn_tm_clean_course_title((string)$material->title);
    $content = (string)($material->content ?? '');
    $chunks = (int)($chunkscounts[$materialid] ?? 0);
    $policylabel = local_aiskillnavigator_ai_policy_label($material);
    $externalallowed = local_aiskillnavigator_material_can_be_sent_to_current_ai($material);

    echo html_writer::start_div('card mb-3 shadow-sm', [
        'data-aisn-material-card' => '1',
        'data-search' => core_text::strtolower($title . ' ' . $content . ' ' . $policylabel),
    ]);
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h4', s($title));

    echo html_writer::tag(
        'p',
        'Type: course resource | RAG chunks: ' . $chunks . ' | AI policy: ' . s($policylabel),
        ['class' => 'text-muted']
    );

    echo html_writer::span(
        s($policylabel),
        local_aiskillnavigator_ai_policy_badge_class($material)
    );

    echo html_writer::tag('pre', s(local_aisn_tm_excerpt($content)), [
        'class' => 'mt-3 p-3 bg-light rounded',
        'style' => 'white-space: pre-wrap;',
    ]);

    echo html_writer::start_div('mt-3');
if ($externalallowed) {
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                'courseid' => $courseid,
                'materialid' => $materialid,
                'action' => 'restrict',
                'sesskey' => sesskey(),
            ]),
            'Restrict to local AI only',
            ['class' => 'btn btn-warning btn-sm mr-2']
        );
    } else {
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                'courseid' => $courseid,
                'materialid' => $materialid,
                'action' => 'allow',
                'sesskey' => sesskey(),
            ]),
            'Allow external AI',
            ['class' => 'btn btn-success btn-sm mr-2']
        );
    }

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
            'courseid' => $courseid,
            'materialid' => $materialid,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]),
        'Delete AI index record',
        ['class' => 'btn btn-danger btn-sm']
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}


echo html_writer::div(
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-secondary']
    ),
    'aisn-bottom-back'
);

echo html_writer::end_div();

if (function_exists('local_aiskillnavigator_mojibake_guard')) {
    echo local_aiskillnavigator_mojibake_guard();
}
echo $OUTPUT->footer();






echo html_writer::tag('script', <<<'JS'
/* AISN_SEARCH_V4_START */
(function () {
    function norm(value) {
        return String(value || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/\s+/g, " ")
            .trim();
    }

    function getSearchInput() {
        return document.getElementById("aisn-material-search") ||
            document.querySelector("input[type='search']") ||
            document.querySelector("input.form-control");
    }

    function getCards() {
        return Array.from(document.querySelectorAll(".card"));
    }

    function ensureEmptyMessage(input) {
        var empty = document.getElementById("aisn-material-search-empty-v4");

        if (!empty) {
            empty = document.createElement("div");
            empty.id = "aisn-material-search-empty-v4";
            empty.className = "alert alert-warning mt-3";
            empty.textContent = "No materials found.";
            empty.style.display = "none";
            input.insertAdjacentElement("afterend", empty);
        }

        return empty;
    }

    function applyFilter() {
        var input = getSearchInput();

        if (!input) {
            return;
        }

        input.id = "aisn-material-search";
        input.placeholder = "Search by title, filename, text or AI policy...";

        var query = norm(input.value);
        var cards = getCards();
        var visible = 0;

        cards.forEach(function (card) {
            var text = norm(card.innerText || card.textContent || "");
            var match = query === "" || text.indexOf(query) !== -1;

            card.style.setProperty("display", match ? "" : "none", "important");

            if (match) {
                visible++;
            }
        });

        var empty = ensureEmptyMessage(input);
        empty.style.display = visible === 0 ? "" : "none";
    }

    function init() {
        var input = getSearchInput();

        if (!input) {
            return;
        }

        input.oninput = applyFilter;
        input.onkeyup = applyFilter;
        input.onchange = applyFilter;
        input.onsearch = applyFilter;

        window.aisnMaterialSearchFilter = applyFilter;

        applyFilter();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    setTimeout(init, 200);
    setTimeout(init, 800);
    setTimeout(init, 1600);
})();
/* AISN_SEARCH_V4_END */
JS);
