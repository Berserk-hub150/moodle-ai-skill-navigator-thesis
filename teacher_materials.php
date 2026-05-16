<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\embedding_service;
use local_aiskillnavigator\service\material_extractor;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:managematerials', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/teacher_materials.php', ['courseid' => $courseid]));
$PAGE->set_title('Teacher materials');
$PAGE->set_heading('Teacher materials');

$action = optional_param('action', '', PARAM_ALPHA);

$message = '';
$error = '';
$ragmessage = '';

if ($action === 'save') {
    require_sesskey();

    $title = required_param('title', PARAM_TEXT);
    $materialtype = optional_param('materialtype', 'text', PARAM_ALPHA);
    $manualcontent = optional_param('content', '', PARAM_RAW);

    $finalcontent = trim($manualcontent);
    $finaltype = $materialtype;

    if (!empty($_FILES['materialfile']) && !empty($_FILES['materialfile']['name'])) {
        $extraction = material_extractor::extract_from_upload($_FILES['materialfile']);

        if (!$extraction['success']) {
            $error = $extraction['message'];
        } else {
            $finalcontent = $extraction['content'];
            $finaltype = $extraction['type'];
            $message = $extraction['message'];
        }
    }

    if ($error === '') {
        if ($finalcontent === '') {
            $error = 'No content found. Upload a PPTX/TXT file or paste text manually.';
        } else {
            $now = time();

            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->title = $title;
            $record->materialtype = $finaltype;
            $record->content = $finalcontent;
            $record->timecreated = $now;
            $record->timemodified = $now;

            $materialid = $DB->insert_record('local_aiskillnav_material', $record);

            $message = $message === ''
                ? 'Material saved successfully.'
                : $message . ' Material saved successfully.';

            $embeddingservice = new embedding_service();
            $indexresult = $embeddingservice->index_material(
                (int) $materialid,
                $courseid,
                $title,
                $finalcontent
            );

            if ($indexresult['success']) {
                $ragmessage = $indexresult['message'];
            } else {
                $ragmessage = 'RAG indexing warning: ' . $indexresult['message']
                    . ' The material is saved, but semantic search may be limited until you re-index it.';
            }
        }
    }
}

if ($action === 'delete') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $material = $DB->get_record('local_aiskillnav_material', ['id' => $id, 'courseid' => $courseid]);

    if ($material) {
        $embeddingservice = new embedding_service();
        $embeddingservice->delete_material_chunks((int) $material->id);

        $DB->delete_records('local_aiskillnav_material', [
            'id' => (int) $material->id,
            'courseid' => $courseid,
        ]);

        $message = 'Material and RAG index deleted.';
    } else {
        $error = 'Material not found or not available in this course.';
    }
}

if ($action === 'reindex') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $material = $DB->get_record('local_aiskillnav_material', ['id' => $id, 'courseid' => $courseid]);

    if ($material) {
        $embeddingservice = new embedding_service();
        $indexresult = $embeddingservice->index_material(
            (int) $material->id,
            $courseid,
            (string) $material->title,
            (string) $material->content
        );

        $message = $indexresult['success']
            ? 'Re-indexed: ' . $indexresult['message']
            : 'Re-index failed: ' . $indexresult['message'];
    } else {
        $error = 'Material not found or not available in this course.';
    }
}

$materials = $DB->get_records(
    'local_aiskillnav_material',
    ['courseid' => $courseid],
    'timecreated DESC'
);

$embeddingservice = new embedding_service();
$chunkscounts = [];

foreach ($materials as $material) {
    $chunkscounts[(int) $material->id] = $embeddingservice->count_indexed_chunks($courseid, (int) $material->id);
}

$totalchunks = $embeddingservice->count_indexed_chunks($courseid);

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'Teacher materials');

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

echo html_writer::tag(
    'p',
    'Upload PowerPoint slides or text files. The plugin extracts the text, splits it into chunks, '
    . 'generates vector embeddings and enables semantic retrieval for the AI tutor, quiz generator, '
    . 'mind map generator and XR scenario generator.',
    ['class' => 'lead']
);

if ($totalchunks > 0) {
    echo html_writer::div(
        'RAG index active: ' . $totalchunks . ' chunks indexed for this course. Semantic search is enabled.',
        'alert alert-success'
    );
} else {
    echo html_writer::div(
        'No RAG chunks indexed yet. Upload or re-index materials to enable semantic search.',
        'alert alert-warning'
    );
}

if ($message !== '') {
    echo html_writer::div(s($message), 'alert alert-success');
}

if ($ragmessage !== '') {
    echo html_writer::div(s($ragmessage), 'alert alert-info');
}

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Add material');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/teacher_materials.php'),
    'enctype' => 'multipart/form-data',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'action',
    'value' => 'save',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $courseid,
]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Title', ['for' => 'title']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'title',
    'id' => 'title',
    'class' => 'form-control',
    'placeholder' => 'Example: Lesson 1 - Digital Twin slides',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Upload slides or text file', ['for' => 'materialfile']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'name' => 'materialfile',
    'id' => 'materialfile',
    'class' => 'form-control',
    'accept' => '.pptx,.txt',
]);
echo html_writer::tag(
    'small',
    'Supported: .pptx and .txt. Text is extracted, chunked and indexed automatically for RAG search.',
    ['class' => 'form-text text-muted']
);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Material type', ['for' => 'materialtype']);
echo html_writer::select(
    [
        'slide' => 'Slide',
        'note' => 'Note',
        'dispensa' => 'Dispensa',
        'link' => 'Link',
        'text' => 'Text',
    ],
    'materialtype',
    'slide',
    false,
    [
        'class' => 'form-control',
        'id' => 'materialtype',
    ]
);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Optional manual content / fallback text', ['for' => 'content']);
echo html_writer::tag('textarea', '', [
    'name' => 'content',
    'id' => 'content',
    'class' => 'form-control',
    'rows' => 8,
    'placeholder' => 'Optional: paste text here if you do not upload a file.',
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Extract, index and save material',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Saved materials');

if (empty($materials)) {
    echo html_writer::div('No materials saved yet.', 'alert alert-info');
} else {
    foreach ($materials as $material) {
        $materialid = (int) $material->id;
        $chunks = $chunkscounts[$materialid] ?? 0;

        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h4', s($material->title));
        echo html_writer::tag(
            'p',
            'Type: ' . s($material->materialtype)
            . ' | Created: ' . userdate($material->timecreated)
            . ' | RAG chunks: ' . html_writer::tag('span', (string) $chunks, [
                'class' => $chunks > 0 ? 'badge badge-success' : 'badge badge-warning',
            ]),
            ['class' => 'text-muted']
        );

        $preview = trim(preg_replace('/\s+/', ' ', (string) $material->content));

        if (core_text::strlen($preview) > 700) {
            $preview = core_text::substr($preview, 0, 700) . '...';
        }

        echo html_writer::tag('pre', s($preview), [
            'style' => 'white-space: pre-wrap; max-height: 220px; overflow:auto; background:#f8f9fa; padding:12px; border-radius:8px;',
        ]);

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/teacher_materials.php', [
                'action' => 'reindex',
                'id' => $materialid,
                'courseid' => $courseid,
                'sesskey' => sesskey(),
            ]),
            'Re-index for RAG',
            ['class' => 'btn btn-secondary btn-sm mr-2']
        );

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/teacher_materials.php', [
                'action' => 'delete',
                'id' => $materialid,
                'courseid' => $courseid,
                'sesskey' => sesskey(),
            ]),
            'Delete',
            ['class' => 'btn btn-danger btn-sm']
        );

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php', ['courseid' => $courseid]),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();
