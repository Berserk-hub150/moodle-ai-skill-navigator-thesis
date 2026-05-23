<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../includes/ai_output_helper.php');

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
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Tutor');
$PAGE->set_heading('AI Tutor');

function local_aiskillnavigator_tutor_limit_context(string $text, int $limit = 9000): string {
    $text = local_aiskillnavigator_fix_mojibake(trim($text));

    if (\core_text::strlen($text) > $limit) {
        return \core_text::substr($text, 0, $limit) . "\n[Content truncated]";
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

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials((int)$courseid);

$sourcemode = local_aiskillnavigator_material_source_mode_from_request(-1);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
$question = optional_param('question', '', PARAM_RAW_TRIMMED);

if ($sourcemode === 'all') {
    $sourcemode = 'selected';
}

$answer = '';
$error = '';
$usedmaterialnames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($question === '') {
        $error = 'Write a question first.';
    } else {
        $selectedmaterials = local_aiskillnavigator_material_source_selected_materials(
            $readablematerials,
            $sourcemode,
            $selectedmaterialids
        );

        if ($sourcemode === 'selected' && empty($selectedmaterials)) {
            $error = 'Select at least one material allowed for the current AI provider, or choose Question/topic only.';
        }

        if ($error === '') {
            if ($sourcemode === 'manual') {
                $systemprompt = 'You are a Moodle AI tutor. Answer using only the student question and general knowledge. Do not claim that course materials were used.';
                $prompt = "Student question:\n" . $question;
            } else {
                $contextparts = [];

                foreach ($selectedmaterials as $material) {
                    $name = local_aiskillnavigator_material_source_clean_title($material);
                    $usedmaterialnames[] = $name;

                    $contextparts[] =
                        "SOURCE: " . $name . "\n" .
                        local_aiskillnavigator_tutor_limit_context((string)$material->content);
                }

                $systemprompt = 'You are a Moodle AI tutor. Use only the selected course materials as grounding context. If the selected materials do not contain enough information, say it clearly. Do not invent sources. Answer in the same language as the student.';

                $prompt =
                    "Selected course materials:\n\n" .
                    implode("\n\n---\n\n", $contextparts) .
                    "\n\nStudent question:\n" .
                    $question;
            }

            $answer = local_aiskillnavigator_fix_mojibake(local_aiskillnavigator_tutor_call_ai($prompt, $systemprompt));
        }
    }
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Tutor');

echo html_writer::tag(
    'p',
    'Ask a free question, or select exactly which Moodle course materials the AI can use.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

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

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    null,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    '1. Source',
    ''
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', '2. Question');

echo html_writer::tag('textarea', s($question), [
    'name' => 'question',
    'id' => 'question',
    'class' => 'form-control',
    'rows' => 5,
    'placeholder' => 'Esempio: spiegami la differenza tra funzione lineare e funzione quadratica.',
    'required' => 'required',
]);

echo html_writer::start_div('mt-3');

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Ask AI',
]);

echo ' ';

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

if ($answer !== '') {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Answer');

    if (!empty($usedmaterialnames)) {
        echo html_writer::div(
            'Used materials: ' . s(implode(', ', $usedmaterialnames)),
            'text-muted mb-3'
        );
    } else {
        echo html_writer::div('Used materials: none', 'text-muted mb-3');
    }

    echo local_aiskillnavigator_render_ai_answer($answer);

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo $OUTPUT->footer();