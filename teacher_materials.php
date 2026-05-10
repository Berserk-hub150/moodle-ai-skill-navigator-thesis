<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\material_extractor;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:managematerials', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/teacher_materials.php', ['courseid' => $courseid]));
$PAGE->set_title('Teacher materials');
$PAGE->set_heading('Teacher materials');

$action = optional_param('action', '', PARAM_ALPHA);

$message = '';
$error = '';

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
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->title = $title;
            $record->materialtype = $finaltype;
            $record->content = $finalcontent;
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('local_aiskillnav_material', $record);

            if ($message === '') {
                $message = 'Material saved successfully.';
            } else {
                $message .= ' Material saved successfully.';
            }
        }
    }
}

if ($action === 'delete') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $DB->delete_records('local_aiskillnav_material', [
        'id' => $id,
        'courseid' => $courseid,
    ]);

    $message = 'Material deleted.';
}

$materials = $DB->get_records(
    'local_aiskillnav_material',
    ['courseid' => $courseid],
    'timecreated DESC'
);

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
    'Upload real PowerPoint slides or text files. The Course AI Tutor will use the extracted text to answer student questions.',
    ['class' => 'lead']
);

if ($message !== '') {
    echo html_writer::div(s($message), 'alert alert-success');
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
    'placeholder' => 'Example: Lesson 1 - Arduino Uno slides',
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
    'Supported now: .pptx and .txt. For PPTX, the plugin extracts text from all slides.',
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
    'value' => 'Extract and save material',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Saved materials');

if (empty($materials)) {
    echo html_writer::div('No materials saved yet.', 'alert alert-info');
} else {
    foreach ($materials as $material) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h4', s($material->title));
        echo html_writer::tag(
            'p',
            'Type: ' . s($material->materialtype) . ' | Created: ' . userdate($material->timecreated),
            ['class' => 'text-muted']
        );

        $preview = trim(preg_replace('/\s+/', ' ', $material->content));

        if (core_text::strlen($preview) > 700) {
            $preview = core_text::substr($preview, 0, 700) . '...';
        }

        echo html_writer::tag('pre', s($preview), [
            'style' => 'white-space: pre-wrap; max-height: 220px; overflow:auto; background:#f8f9fa; padding:12px; border-radius:8px;',
        ]);

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/teacher_materials.php', [
                'action' => 'delete',
                'id' => $material->id,
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