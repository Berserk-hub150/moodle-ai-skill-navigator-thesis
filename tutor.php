<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

global $PAGE, $OUTPUT, $DB;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/tutor.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('aitutor', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('aitutor', 'local_aiskillnavigator'));

$question = optional_param('question', '', PARAM_TEXT);

// -1 = general AI.
//  0 = all readable teacher materials.
// >0 = selected teacher material.
$materialid = optional_param('materialid', -1, PARAM_INT);

$answer = '';
$selectedmaterials = [];
$warning = '';

function local_aiskillnavigator_tutor_material_short_title(stdClass $material): string {
    $title = trim((string) ($material->title ?? 'Materiale senza titolo'));

    if ($title === '') {
        $title = 'Materiale senza titolo';
    }

    $contentlength = strlen((string) ($material->content ?? ''));

    return $title . ' (' . $contentlength . ' chars)';
}

function local_aiskillnavigator_tutor_get_readable_materials(int $courseid): array {
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

function local_aiskillnavigator_tutor_select_materials(array $readablematerials, int $materialid): array {
    if ($materialid === -1) {
        return [];
    }

    if ($materialid > 0 && isset($readablematerials[$materialid])) {
        return [$readablematerials[$materialid]];
    }

    return array_values($readablematerials);
}

$readablematerials = local_aiskillnavigator_tutor_get_readable_materials($courseid);
$selectedmaterials = local_aiskillnavigator_tutor_select_materials($readablematerials, $materialid);

if ($question !== '') {
    $service = new real_ai_service();

    if ($materialid === -1) {
        $answer = $service->ask_tutor($question);
    } else {
        if (empty($selectedmaterials)) {
            $warning = 'Non sono stati trovati materiali leggibili del docente. Usa General AI oppure carica materiali in Teacher Materials.';
        } else {
            $enhancedquestion = "Domanda dello studente:\n"
                . $question
                . "\n\nISTRUZIONI OBBLIGATORIE:\n"
                . "- Usa SOLO il testo dei materiali forniti.\n"
                . "- Non dare una risposta generica.\n"
                . "- Non inventare contenuti esterni ai materiali.\n"
                . "- Se i materiali non bastano, dillo chiaramente.";

            $answer = $service->ask_with_course_materials($enhancedquestion, $selectedmaterials);
        }
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('aitutor', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Ask questions using general AI or teacher materials. The source can be selected before sending the question.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if (empty($readablematerials)) {
    echo html_writer::div(
        'No readable teacher materials found yet. You can still use General AI.',
        'alert alert-warning'
    );
} else {
    echo html_writer::div(
        'Readable teacher materials available: ' . count($readablematerials) . '.',
        'alert alert-info'
    );
}

if ($warning !== '') {
    echo html_writer::div(s($warning), 'alert alert-warning');
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/tutor.php'),
    'class' => 'mb-4',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $courseid,
]);

echo html_writer::start_div('form-group');

echo html_writer::tag('label', 'Answer source', ['for' => 'materialid']);

$materialoptions = [
    -1 => 'General AI only (do not use teacher materials)',
    0 => 'All readable teacher materials',
];

foreach ($readablematerials as $material) {
    $materialoptions[(int) $material->id] = local_aiskillnavigator_tutor_material_short_title($material);
}

echo html_writer::select(
    $materialoptions,
    'materialid',
    $materialid,
    false,
    [
        'class' => 'form-control',
        'id' => 'materialid',
    ]
);

echo html_writer::tag(
    'small',
    'Choose General AI for any free topic, or choose teacher materials for grounded course answers.',
    ['class' => 'form-text text-muted']
);

echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');

echo html_writer::tag(
    'label',
    get_string('tutor_question', 'local_aiskillnavigator'),
    ['for' => 'question']
);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'question',
    'id' => 'question',
    'class' => 'form-control',
    'value' => s($question),
    'placeholder' => 'Esempio: spiegami come funziona Arduino Uno',
]);

echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-2',
    'value' => 'Ask AI',
]);

echo html_writer::end_tag('form');

if ($answer !== '') {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body ai-answer-card');

    echo html_writer::tag('h3', $materialid === -1 ? 'AI answer from general model' : 'AI answer grounded on teacher materials');

    if (!empty($selectedmaterials)) {
        $sourcenames = [];

        foreach ($selectedmaterials as $material) {
            $sourcenames[] = $material->title;
        }

        echo html_writer::tag(
            'p',
            'Generated from teacher materials: ' . s(implode(', ', $sourcenames)),
            ['class' => 'text-muted']
        );
    } else {
        echo html_writer::tag(
            'p',
            'Generated from general AI, without teacher materials.',
            ['class' => 'text-muted']
        );
    }

    $formattedanswer = format_text(
        $answer,
        FORMAT_MARKDOWN,
        [
            'context' => $context,
            'trusted' => false,
            'noclean' => false,
            'filter' => true,
        ]
    );

    echo html_writer::div($formattedanswer, 'ai-answer-content');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php', ['courseid' => $courseid]),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo html_writer::tag('style', '
.ai-answer-card {
    font-size: 1rem;
    line-height: 1.55;
}

.ai-answer-content h2,
.ai-answer-content h3,
.ai-answer-content h4 {
    margin-top: 1.4rem;
    margin-bottom: 0.7rem;
}

.ai-answer-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.ai-answer-content th,
.ai-answer-content td {
    border: 1px solid #d8dee9;
    padding: 8px 10px;
}

.ai-answer-content th {
    background: #f3f6fb;
}

.ai-answer-content code {
    background: #f3f4f6;
    padding: 2px 4px;
    border-radius: 4px;
}
');

echo $OUTPUT->footer();