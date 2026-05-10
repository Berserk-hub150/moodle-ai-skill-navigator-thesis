<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();

global $PAGE, $OUTPUT;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('pluginname', 'local_aiskillnavigator'));

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('pluginname', 'local_aiskillnavigator'));
echo html_writer::tag(
    'p',
    'Prototype Moodle plugin for personalised learning paths on Artificial Intelligence, IoT, Digital Twin and Virtual Worlds.',
    ['class' => 'lead']
);

echo html_writer::start_div('row mt-4');

$cards = [
    [
        'title' => get_string('studentdashboard', 'local_aiskillnavigator'),
        'text' => 'View the student skill profile, main skill gap and personalised recommendation.',
        'url' => new moodle_url('/local/aiskillnavigator/student.php'),
        'button' => 'Open student dashboard',
    ],
    [
        'title' => get_string('teacherdashboard', 'local_aiskillnavigator'),
        'text' => 'View course-level skill gaps, students at risk and suggested teaching actions.',
        'url' => new moodle_url('/local/aiskillnavigator/teacher.php'),
        'button' => 'Open teacher dashboard',
    ],
    [
        'title' => get_string('aitutor', 'local_aiskillnavigator'),
        'text' => 'Ask questions and receive AI explanations on AI, IoT, Digital Twin and Virtual Worlds.',
        'url' => new moodle_url('/local/aiskillnavigator/tutor.php'),
        'button' => 'Open AI Tutor',
    ],
    [
        'title' => get_string('quizgenerator', 'local_aiskillnavigator'),
        'text' => 'Generate an AI micro-test, let the student answer it, and calculate a score.',
        'url' => new moodle_url('/local/aiskillnavigator/quizgenerator.php'),
        'button' => 'Open Quiz Generator',
    ],
    [
        'title' => get_string('mindmapgenerator', 'local_aiskillnavigator'),
        'text' => 'Generate a structured mind map to organise concepts, skills and study paths.',
        'url' => new moodle_url('/local/aiskillnavigator/mindmapgenerator.php'),
        'button' => 'Open Mind Map Generator',
    ],
    [
        'title' => get_string('scenariogenerator', 'local_aiskillnavigator'),
        'text' => 'Generate structured Virtual Worlds training scenarios for digital skills.',
        'url' => new moodle_url('/local/aiskillnavigator/scenariogenerator.php'),
        'button' => 'Open Scenario Generator',
    ],
];

foreach ($cards as $card) {
    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h4', s($card['title']), ['class' => 'card-title']);
    echo html_writer::tag('p', s($card['text']), ['class' => 'card-text']);

    echo html_writer::link(
        $card['url'],
        s($card['button']),
        ['class' => 'btn btn-primary']
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();