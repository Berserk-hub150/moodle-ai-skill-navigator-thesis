<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

require_login();

global $PAGE, $OUTPUT;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/scenariogenerator.php'));
$PAGE->set_title(get_string('scenariogenerator', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('scenariogenerator', 'local_aiskillnavigator'));

$topic = optional_param('topic', 'Digital Twin and IoT', PARAM_TEXT);
$environment = optional_param('environment', 'Smart Factory', PARAM_TEXT);
$result = '';

if (optional_param('generate', 0, PARAM_BOOL)) {
    $service = new real_ai_service();
    $result = $service->generate_xr_scenario($topic, $environment);
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('scenariogenerator', 'local_aiskillnavigator'));
echo html_writer::tag(
    'p',
    'Real AI generator for Virtual Worlds training scenarios.',
    ['class' => 'lead']
);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/scenariogenerator.php'),
    'class' => 'mb-4',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'generate',
    'value' => '1',
]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', get_string('scenario_topic', 'local_aiskillnavigator'), ['for' => 'topic']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-2');
echo html_writer::tag('label', 'Virtual environment', ['for' => 'environment']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'environment',
    'id' => 'environment',
    'class' => 'form-control',
    'value' => s($environment),
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-2',
    'value' => 'Generate with real AI',
]);

echo html_writer::end_tag('form');

if ($result !== '') {
    echo html_writer::start_div('card');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Generated scenario');
    echo html_writer::tag('pre', s($result), [
        'style' => 'white-space: pre-wrap; font-family: inherit;',
    ]);

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();