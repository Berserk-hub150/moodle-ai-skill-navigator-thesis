<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

require_login();

global $PAGE, $OUTPUT;

$context = context_system::instance();

require_capability('local/aiskillnavigator:viewstudent', $context);

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
    'Ask questions and receive AI explanations rendered in a readable format.',
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
    'placeholder' => 'Esempio: spiegami come funziona Arduino Uno',
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
    echo html_writer::start_div('card-body ai-answer-card');

    echo html_writer::tag('h3', 'AI answer');

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
        new moodle_url('/local/aiskillnavigator/index.php'),
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