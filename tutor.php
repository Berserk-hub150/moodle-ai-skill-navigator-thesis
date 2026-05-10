<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

require_login();

global $PAGE, $OUTPUT;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/tutor.php'));
$PAGE->set_title(get_string('aitutor', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('aitutor', 'local_aiskillnavigator'));

$question = optional_param('question', '', PARAM_TEXT);
$answer = '';

if ($question !== '') {
    $service = new real_ai_service();
    $answer = $service->ask_tutor($question);
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('aitutor', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Real AI Tutor connected to OpenRouter. Ask a question about AI, IoT, Digital Twin or Virtual Worlds.',
    ['class' => 'lead']
);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/tutor.php'),
    'class' => 'mb-4',
]);

echo html_writer::start_div('form-group');

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
    'value' => 'Ask real AI',
]);

echo html_writer::end_tag('form');

if ($answer !== '') {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'AI answer');

    echo html_writer::tag('pre', s($answer), [
        'style' => 'white-space: pre-wrap; font-family: inherit; font-size: 1rem;',
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