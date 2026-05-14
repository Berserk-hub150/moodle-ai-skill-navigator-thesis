<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\embedding_service;
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

// -1 = general AI, 0 = RAG across all materials, >0 = RAG restricted to selected material.
$materialid = optional_param('materialid', -1, PARAM_INT);

$answer = '';
$warning = '';
$ragsources = [];
$ragdebug = '';

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

if ($materialid > 0 && !isset($readablematerials[$materialid])) {
    $materialid = 0;
    $warning = 'Selected material not found. RAG search was switched to all course materials.';
}

$embeddingservice = new embedding_service();
$totalchunks = $embeddingservice->count_indexed_chunks($courseid);
$selectedchunkcount = $materialid > 0 ? $embeddingservice->count_indexed_chunks($courseid, $materialid) : $totalchunks;

if ($question !== '') {
    $aiservice = new real_ai_service();

    if ($materialid === -1) {
        $answer = $aiservice->ask_tutor($question);
    } else if ($selectedchunkcount === 0) {
        $warning = 'Non ci sono chunk RAG indicizzati per questa sorgente. '
            . 'Chiedi al docente di usare “Re-index for RAG” in Teacher Materials oppure usa General AI.';
    } else {
        $results = $embeddingservice->search($question, $courseid, 5, $materialid > 0 ? $materialid : 0);

        if (empty($results)) {
            $warning = 'Nessun chunk rilevante trovato nel RAG index. Prova a riformulare la domanda o usa General AI.';
        } else {
            $ragcontext = $embeddingservice->build_context($results, 6000);
            $answer = $aiservice->ask_with_rag_context($question, $ragcontext);

            foreach ($results as $result) {
                $sourcekey = $result->title . ' — chunk ' . (((int) $result->chunkindex) + 1);
                $ragsources[$sourcekey] = $result->similarity;
            }

            $ragdebug = count($results) . ' chunks retrieved, top similarity: ' . $results[0]->similarity;
        }
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('aitutor', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Ask questions using general AI or teacher materials. In RAG mode, the system retrieves the most relevant indexed chunks and grounds the answer on them.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if ($totalchunks > 0) {
    echo html_writer::div(
        'RAG index: ' . $totalchunks . ' chunks indexed. Semantic search is active.',
        'alert alert-success'
    );
} else if (!empty($readablematerials)) {
    echo html_writer::div(
        'Materials found but not indexed for RAG. Ask the teacher to re-index them from Teacher Materials.',
        'alert alert-warning'
    );
} else {
    echo html_writer::div(
        'No teacher materials found yet. You can still use General AI.',
        'alert alert-warning'
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
    0 => 'RAG semantic search (all course materials)',
];

foreach ($readablematerials as $material) {
    $chunks = $embeddingservice->count_indexed_chunks($courseid, (int) $material->id);
    $materialoptions[(int) $material->id] = $material->title . ' — RAG chunks: ' . $chunks;
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
    'General AI answers freely. RAG mode searches indexed teacher materials and uses only the retrieved chunks as context.',
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
    'placeholder' => 'Esempio: spiegami il rapporto tra IoT e Digital Twin',
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

    if ($materialid === -1) {
        echo html_writer::tag('h3', 'AI answer from general model');
        echo html_writer::tag('p', 'Generated from general AI, without teacher materials.', ['class' => 'text-muted']);
    } else {
        echo html_writer::tag('h3', 'AI answer grounded on teacher materials (RAG)');

        if (!empty($ragsources)) {
            echo html_writer::tag('p', 'Retrieved sources:', ['class' => 'text-muted mb-1']);
            echo html_writer::start_tag('ul', ['class' => 'text-muted small']);

            foreach ($ragsources as $title => $similarity) {
                echo html_writer::tag(
                    'li',
                    s($title) . ' ' . html_writer::tag('span', 'similarity: ' . $similarity, ['class' => 'badge badge-info'])
                );
            }

            echo html_writer::end_tag('ul');
        }

        if ($ragdebug !== '') {
            echo html_writer::tag('p', s($ragdebug), ['class' => 'text-muted small']);
        }
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
.ai-answer-card { font-size: 1rem; line-height: 1.55; }
.ai-answer-content h2,
.ai-answer-content h3,
.ai-answer-content h4 { margin-top: 1.4rem; margin-bottom: 0.7rem; }
.ai-answer-content table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.ai-answer-content th,
.ai-answer-content td { border: 1px solid #d8dee9; padding: 8px 10px; }
.ai-answer-content th { background: #f3f6fb; }
.ai-answer-content code { background: #f3f4f6; padding: 2px 4px; border-radius: 4px; }
');

echo $OUTPUT->footer();
