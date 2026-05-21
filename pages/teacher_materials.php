<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_ai_policy.php');

use local_aiskillnavigator\service\embedding_service;
use local_aiskillnavigator\service\material_extractor;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

if (function_exists('local_aiskillnavigator_sync_course_resources') && $courseid > 1) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:managematerials', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', ['courseid' => $courseid]));
$PAGE->set_title('Course materials / RAG');
$PAGE->set_heading('Course materials / RAG');

$action = optional_param('action', '', PARAM_ALPHA);

$message = '';
$error = '';
$ragmessage = '';

if ($action === 'save') {
    require_sesskey();

    $title = required_param('title', PARAM_TEXT);
    $materialtype = optional_param('materialtype', 'text', PARAM_ALPHA);
    $manualcontent = optional_param('content', '', PARAM_RAW);
    $externalaiallowed = optional_param('externalaiallowed', 0, PARAM_BOOL);

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
            $error = 'No content found. Upload a supported file or paste text manually.';
        } else {
            $now = time();

            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->title = $title;
            $record->materialtype = $finaltype;
            $record->content = $finalcontent;
            $record->externalaiallowed = $externalaiallowed ? 1 : 0;
            $record->aipolicy = $externalaiallowed ? 'external_allowed' : 'local_only';
            $record->timecreated = $now;
            $record->timemodified = $now;

            $materialid = $DB->insert_record('local_aiskillnav_material', $record);

            $message = trim($message . ' Material saved successfully.');

            $embeddingservice = new embedding_service();
            $indexresult = $embeddingservice->index_material(
                (int)$materialid,
                $courseid,
                $title,
                $finalcontent
            );

            $ragmessage = $indexresult['success']
                ? $indexresult['message']
                : 'RAG indexing warning: ' . $indexresult['message'];
        }
    }
}

if ($action === 'allowexternal' || $action === 'localonly') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $ok = local_aiskillnavigator_set_material_ai_policy($id, $courseid, $action === 'allowexternal');

    $message = $ok
        ? 'AI access policy updated.'
        : 'Material not found.';
}

if ($action === 'delete') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $material = $DB->get_record('local_aiskillnav_material', ['id' => $id, 'courseid' => $courseid]);

    if ($material) {
        $embeddingservice = new embedding_service();

        if (method_exists($embeddingservice, 'delete_material_chunks')) {
            $embeddingservice->delete_material_chunks((int)$material->id);
        }

        $DB->delete_records('local_aiskillnav_material', ['id' => $id, 'courseid' => $courseid]);
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
            (int)$material->id,
            $courseid,
            (string)$material->title,
            (string)$material->content
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
    $chunkscounts[(int)$material->id] = $embeddingservice->count_indexed_chunks($courseid, (int)$material->id);
}

$totalchunks = $embeddingservice->count_indexed_chunks($courseid);

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'Course materials / RAG');

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

echo html_writer::tag(
    'p',
    'Upload or review Moodle course materials. The teacher decides whether each material can be used only with local AI or also with external AI providers.',
    ['class' => 'lead']
);

echo html_writer::div(local_aiskillnavigator_provider_privacy_notice(), 'alert alert-info');

if ($totalchunks > 0) {
    echo html_writer::div('RAG index active: ' . $totalchunks . ' chunks indexed for this course.', 'alert alert-success');
} else {
    echo html_writer::div('No RAG chunks indexed yet. Upload or re-index materials to enable semantic search.', 'alert alert-warning');
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
    'action' => new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php'),
    'enctype' => 'multipart/form-data',
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Title', ['for' => 'title']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'title',
    'id' => 'title',
    'class' => 'form-control',
    'required' => 'required',
    'placeholder' => 'Example: Lesson 1 - Functions',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Upload slides/text file', ['for' => 'materialfile']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'name' => 'materialfile',
    'id' => 'materialfile',
    'class' => 'form-control',
]);
echo html_writer::tag('small', 'Supported by extractor: TXT/PPTX and other text-like files depending on server support.', ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Material type', ['for' => 'materialtype']);
echo html_writer::select(
    [
        'slide' => 'Slide',
        'text' => 'Text',
        'course_resource' => 'Course resource',
        'other' => 'Other',
    ],
    'materialtype',
    'text',
    false,
    ['class' => 'form-control', 'id' => 'materialtype']
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

echo html_writer::start_div('form-group form-check mt-3');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'externalaiallowed',
    'id' => 'externalaiallowed',
    'value' => 1,
    'class' => 'form-check-input',
]);
echo html_writer::tag(
    'label',
    'Allow this material to be used also with external AI providers such as OpenRouter/OpenAI/DeepSeek/Gemini/Claude via API.',
    ['for' => 'externalaiallowed', 'class' => 'form-check-label']
);
echo html_writer::tag('small', 'If unchecked, the material remains usable only with local/prototype AI providers.', ['class' => 'form-text text-muted']);
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
        $materialid = (int)$material->id;
        $preview = trim(preg_replace('/\s+/', ' ', (string)$material->content));

        if (core_text::strlen($preview) > 600) {
            $preview = core_text::substr($preview, 0, 600) . '...';
        }

        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h4', s($material->title));
        echo html_writer::tag(
            'p',
            'Type: ' . s($material->materialtype) .
            ' · RAG chunks: ' . (int)($chunkscounts[$materialid] ?? 0) .
            ' · AI policy: ' . s(local_aiskillnavigator_ai_policy_label($material)),
            ['class' => 'text-muted']
        );

        echo html_writer::span(s(local_aiskillnavigator_ai_policy_label($material)), local_aiskillnavigator_ai_policy_badge_class($material));

        echo html_writer::tag('pre', s($preview), [
            'style' => 'white-space: pre-wrap; max-height: 220px; overflow:auto; background:#f8f9fa; padding:12px; border-radius:12px; margin-top:12px;',
        ]);

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                'action' => 'reindex',
                'id' => $materialid,
                'courseid' => $courseid,
                'sesskey' => sesskey(),
            ]),
            'Re-index for RAG',
            ['class' => 'btn btn-secondary btn-sm mr-2']
        );

        if (local_aiskillnavigator_material_external_allowed($material)) {
            echo html_writer::link(
                new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                    'action' => 'localonly',
                    'id' => $materialid,
                    'courseid' => $courseid,
                    'sesskey' => sesskey(),
                ]),
                'Restrict to local AI only',
                ['class' => 'btn btn-warning btn-sm mr-2']
            );
        } else {
            echo html_writer::link(
                new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
                    'action' => 'allowexternal',
                    'id' => $materialid,
                    'courseid' => $courseid,
                    'sesskey' => sesskey(),
                ]),
                'Allow external AI',
                ['class' => 'btn btn-success btn-sm mr-2']
            );
        }

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', [
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
        new moodle_url('/local/aiskillnavigator/pages/index.php', ['courseid' => $courseid]),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();