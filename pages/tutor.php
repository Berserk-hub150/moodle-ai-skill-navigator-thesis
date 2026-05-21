<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_ai_policy.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewstudent', $context);

if (function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Tutor');
$PAGE->set_heading('AI Tutor');

function local_aiskillnavigator_tutor_get_materials(int $courseid): array {
    global $DB;

    if (!$DB->get_manager()->table_exists(new xmldb_table('local_aiskillnav_material'))) {
        return [];
    }

    $records = $DB->get_records(
        'local_aiskillnav_material',
        ['courseid' => $courseid],
        'timemodified DESC, timecreated DESC'
    );

    $materials = [];

    foreach ($records as $record) {
        if (trim((string)($record->content ?? '')) !== '') {
            $materials[(int)$record->id] = $record;
        }
    }

    return $materials;
}

function local_aiskillnavigator_tutor_short_title(stdClass $material): string {
    $title = trim((string)($material->title ?? 'Course material'));
    $title = preg_replace('/^\[Course #[0-9]+ \/ cm #[0-9]+\]\s*/', '', $title);
    return trim($title) !== '' ? trim($title) : 'Course material';
}

function local_aiskillnavigator_tutor_excerpt(string $text, int $limit = 170): string {
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));

    if (core_text::strlen($text) > $limit) {
        return core_text::substr($text, 0, $limit) . '...';
    }

    return $text;
}

function local_aiskillnavigator_tutor_limit_context(string $text, int $limit = 9000): string {
    $text = trim($text);

    if (core_text::strlen($text) > $limit) {
        return core_text::substr($text, 0, $limit) . "\n[Content truncated]";
    }

    return $text;
}

function local_aiskillnavigator_tutor_call_ai(string $prompt, string $systemprompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, 2600, $systemprompt);
        }

        if (
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_config') &&
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_selector')
        ) {
            $config = new \local_aiskillnavigator\service\provider\ai_provider_config();
            $selector = new \local_aiskillnavigator\service\provider\ai_provider_selector();
            $provider = $selector->create($config);
            return $provider->generate($prompt, 2600, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

$materials = local_aiskillnavigator_tutor_get_materials((int)$courseid);
$materials = local_aiskillnavigator_filter_materials_for_current_ai((array)$materials);

$sourceMode = optional_param('source_mode', 'manual', PARAM_ALPHA);
$question = optional_param('question', '', PARAM_RAW_TRIMMED);
$selectedMaterialIds = optional_param_array('materialids', [], PARAM_INT);

if (!in_array($sourceMode, ['manual', 'selected'], true)) {
    $sourceMode = 'manual';
}

$answer = '';
$error = '';
$usedMaterialNames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($question === '') {
        $error = 'Write a question first.';
    } else {
        $selectedMaterials = [];

        if ($sourceMode === 'selected') {
            foreach ($selectedMaterialIds as $materialid) {
                $materialid = (int)$materialid;

                if (isset($materials[$materialid])) {
                    $selectedMaterials[] = $materials[$materialid];
                }
            }

            if (empty($selectedMaterials)) {
                $error = 'Select at least one course material, or choose Question only.';
            }
        }

        if ($error === '') {
            if ($sourceMode === 'manual') {
                $systemprompt = 'You are a Moodle AI tutor. Answer using only the student question and general knowledge. Do not claim that course materials were used.';
                $prompt = "Student question:\n" . $question;
            } else {
                $contextparts = [];

                foreach ($selectedMaterials as $material) {
                    $name = local_aiskillnavigator_tutor_short_title($material);
                    $usedMaterialNames[] = $name;

                    $contextparts[] =
                        "SOURCE: " . $name . "\n" .
                        local_aiskillnavigator_tutor_limit_context((string)$material->content);
                }

                $systemprompt = 'You are a Moodle AI tutor. Use only the selected course materials as grounding context. If the selected materials do not contain enough information, say it clearly. Do not invent sources.';

                $prompt =
                    "Selected course materials:\n\n" .
                    implode("\n\n---\n\n", $contextparts) .
                    "\n\nStudent question:\n" .
                    $question .
                    "\n\nAnswer in the same language as the student.";
            }

            $answer = local_aiskillnavigator_tutor_call_ai($prompt, $systemprompt);
        }
    }
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::tag('style', '
body.path-local-aiskillnavigator #page-header,
body[id^="page-local-aiskillnavigator"] #page-header,
body.path-local-aiskillnavigator .secondary-navigation,
body[id^="page-local-aiskillnavigator"] .secondary-navigation {
    display: none !important;
}

#page {
    background: #f6f8fb !important;
}

.aisn-wrap {
    max-width: 1180px;
    margin: 0 auto;
}

.aisn-hero {
    background: linear-gradient(135deg, #0f6cbf 0%, #2b82d9 55%, #68b3ff 100%);
    color: #fff;
    border-radius: 26px;
    padding: 30px 34px;
    margin-bottom: 24px;
    box-shadow: 0 18px 42px rgba(15, 108, 191, .24);
}

.aisn-hero h2 {
    color: #fff;
    font-size: 36px;
    font-weight: 850;
    margin: 0 0 8px;
    letter-spacing: -0.04em;
}

.aisn-hero p {
    margin: 0;
    font-size: 16px;
    opacity: .96;
}

.aisn-grid {
    display: grid;
    gap: 20px;
}

.aisn-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
}

.aisn-card h3 {
    margin: 0 0 16px;
    font-size: 23px;
    font-weight: 850;
    letter-spacing: -0.03em;
}

.aisn-muted {
    color: #64748b;
}

.aisn-choice-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.aisn-choice {
    display: block;
    border: 2px solid #e5e7eb;
    background: #f8fafc;
    border-radius: 18px;
    padding: 18px;
    cursor: pointer;
    transition: .15s ease-in-out;
}

.aisn-choice:hover {
    border-color: #0f6cbf;
    background: #eff6ff;
}

.aisn-choice:has(input:checked) {
    border-color: #0f6cbf;
    background: #eff6ff;
    box-shadow: 0 10px 24px rgba(15, 108, 191, .14);
}

.aisn-choice input {
    margin-right: 8px;
}

.aisn-choice-title {
    font-weight: 850;
}

.aisn-choice-text {
    display: block;
    margin-top: 8px;
    color: #64748b;
    font-size: 14px;
}

.aisn-material-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
}

.aisn-material {
    display: block;
    min-height: 150px;
    border: 2px solid #e5e7eb;
    border-radius: 18px;
    padding: 16px;
    background: #fff;
    cursor: pointer;
    transition: .15s ease-in-out;
}

.aisn-material:hover {
    border-color: #0f6cbf;
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
}

.aisn-material:has(input:checked) {
    border-color: #16a34a;
    background: #f0fdf4;
    box-shadow: 0 10px 24px rgba(22, 163, 74, .12);
}

.aisn-material input {
    margin-right: 8px;
}

.aisn-material-title {
    font-weight: 850;
}

.aisn-badge {
    display: inline-block;
    margin-top: 10px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    font-size: 12px;
    font-weight: 800;
}

.aisn-excerpt {
    margin-top: 10px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.45;
}

.aisn-question {
    width: 100%;
    min-height: 120px;
    border: 1px solid #cbd5e1;
    border-radius: 16px;
    padding: 14px 16px;
    font-size: 15px;
    resize: vertical;
}

.aisn-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-top: 16px;
}

.aisn-primary {
    border: 0;
    border-radius: 14px;
    padding: 12px 24px;
    background: #0f6cbf;
    color: #fff;
    font-weight: 850;
    cursor: pointer;
    box-shadow: 0 10px 20px rgba(15, 108, 191, .24);
}

.aisn-primary:hover {
    background: #0b5ca8;
}

.aisn-secondary {
    border-radius: 14px;
    padding: 11px 18px;
    background: #f1f5f9;
    color: #0f172a;
    text-decoration: none;
    font-weight: 750;
}

.aisn-answer {
    white-space: pre-wrap;
    background: #0f172a;
    color: #e5e7eb;
    padding: 22px;
    border-radius: 18px;
    line-height: 1.55;
}

.aisn-empty {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    padding: 14px 16px;
    border-radius: 16px;
}

.aisn-hidden {
    display: none !important;
}

@media (max-width: 760px) {
    .aisn-choice-row {
        grid-template-columns: 1fr;
    }
}
');

echo html_writer::start_div('aisn-wrap');

echo html_writer::start_div('aisn-hero');
echo html_writer::tag('h2', 'AI Tutor');
echo html_writer::tag('p', 'Ask a free question, or select exactly which Moodle course materials the AI can use.');
echo html_writer::end_div();

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::start_div('aisn-grid');

echo html_writer::start_div('aisn-card');
echo html_writer::tag('h3', '1. Source');

echo html_writer::start_div('aisn-choice-row');

echo html_writer::start_tag('label', ['class' => 'aisn-choice']);
echo html_writer::empty_tag('input', [
    'type' => 'radio',
    'name' => 'source_mode',
    'value' => 'manual',
    'checked' => $sourceMode === 'manual' ? 'checked' : null,
]);
echo html_writer::span('Question only', 'aisn-choice-title');
echo html_writer::span('Do not use course materials.', 'aisn-choice-text');
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'aisn-choice']);
echo html_writer::empty_tag('input', [
    'type' => 'radio',
    'name' => 'source_mode',
    'value' => 'selected',
    'checked' => $sourceMode === 'selected' ? 'checked' : null,
]);
echo html_writer::span('Use course materials', 'aisn-choice-title');
echo html_writer::span('Choose one or more materials below.', 'aisn-choice-text');
echo html_writer::end_tag('label');

echo html_writer::end_div();
echo html_writer::end_div();

$materialsclass = $sourceMode === 'manual' ? 'aisn-card aisn-hidden' : 'aisn-card';

echo html_writer::start_div($materialsclass, ['id' => 'materials-panel']);
echo html_writer::tag('h3', '2. Materials');

if (empty($materials)) {
    echo html_writer::div(
        'No course materials found yet. Add a Moodle File, Page, Label, Folder, URL or Book resource to this course.',
        'aisn-empty'
    );
} else {
    echo html_writer::tag('p', 'Select the exact course materials to use.', ['class' => 'aisn-muted']);
    echo html_writer::start_div('aisn-material-grid');

    foreach ($materials as $material) {
        $id = (int)$material->id;
        $checked = in_array($id, $selectedMaterialIds, true) && $sourceMode === 'selected';

        echo html_writer::start_tag('label', ['class' => 'aisn-material']);

        echo html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'materialids[]',
            'value' => $id,
            'checked' => $checked ? 'checked' : null,
        ]);

        echo html_writer::span(s(local_aiskillnavigator_tutor_short_title($material)), 'aisn-material-title');
        echo html_writer::empty_tag('br');
        echo html_writer::span(strlen((string)$material->content) . ' chars', 'aisn-badge');

        echo html_writer::div(
            s(local_aiskillnavigator_tutor_excerpt((string)$material->content)),
            'aisn-excerpt'
        );

        echo html_writer::end_tag('label');
    }

    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::start_div('aisn-card');
echo html_writer::tag('h3', '3. Question');

echo html_writer::tag('textarea', s($question), [
    'name' => 'question',
    'id' => 'question',
    'class' => 'aisn-question',
    'placeholder' => 'Esempio: spiegami la differenza tra funzione lineare e funzione quadratica.',
    'required' => 'required',
]);

echo html_writer::start_div('aisn-actions');

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'aisn-primary',
    'value' => 'Ask AI',
]);

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'aisn-secondary']
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

if ($answer !== '') {
    echo html_writer::start_div('aisn-card mt-4');
    echo html_writer::tag('h3', 'Answer');

    if (!empty($usedMaterialNames)) {
        echo html_writer::div('Used materials: ' . s(implode(', ', $usedMaterialNames)), 'aisn-muted mb-3');
    } else {
        echo html_writer::div('Used materials: none', 'aisn-muted mb-3');
    }

    echo html_writer::tag('div', s($answer), ['class' => 'aisn-answer']);
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::tag('script', '
(function() {
    const modeRadios = document.querySelectorAll("input[name=\"source_mode\"]");
    const panel = document.getElementById("materials-panel");

    function refresh() {
        const selected = document.querySelector("input[name=\"source_mode\"]:checked");

        if (!selected || !panel) {
            return;
        }

        const boxes = panel.querySelectorAll("input[type=\"checkbox\"]");

        if (selected.value === "manual") {
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
    }

    modeRadios.forEach(function(radio) {
        radio.addEventListener("change", refresh);
    });

    refresh();
})();
');

echo $OUTPUT->footer();