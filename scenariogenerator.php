<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

global $PAGE, $OUTPUT;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/scenariogenerator.php', ['courseid' => $courseid]));
$PAGE->set_title('AI XR Scenario Generator');
$PAGE->set_heading('AI XR Scenario Generator');

$topic = optional_param('topic', 'Digital Twin', PARAM_TEXT);
$environment = optional_param('environment', 'Virtual laboratory', PARAM_TEXT);
$generate = optional_param('generate', 0, PARAM_BOOL);

$result = '';

if ($generate) {
    $service = new real_ai_service();
    $result = $service->generate_xr_scenario($topic, $environment);
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI XR Scenario Generator');

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

echo html_writer::tag(
    'p',
    'Generate structured Virtual Worlds training scenarios for teacher-led digital skills activities.',
    ['class' => 'lead']
);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Generate a new XR scenario');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/scenariogenerator.php'),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'generate',
    'value' => '1',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $courseid,
]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Scenario topic', ['for' => 'topic']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
    'placeholder' => 'Example: Digital Twin, IoT sensors, Arduino Uno',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Virtual environment', ['for' => 'environment']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'environment',
    'id' => 'environment',
    'class' => 'form-control',
    'value' => s($environment),
    'placeholder' => 'Example: virtual laboratory, smart factory, hospital simulation',
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate scenario',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($result !== '') {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Generated XR scenario');

    $formatted = format_text(
        $result,
        FORMAT_MARKDOWN,
        [
            'context' => $context,
            'trusted' => false,
            'noclean' => false,
            'filter' => true,
        ]
    );

    echo html_writer::div($formatted);

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

echo $OUTPUT->footer();