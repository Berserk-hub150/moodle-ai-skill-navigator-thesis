<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('pluginname', 'local_aiskillnavigator'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'AI Skill Navigator is a Moodle plugin prototype for personalised learning paths, AI tutoring, quiz generation and Virtual Worlds scenario generation.'
);

echo html_writer::start_div('local-aiskillnavigator-menu');

echo html_writer::tag('h3', 'Prototype modules');

echo html_writer::start_tag('ul');

echo html_writer::tag(
    'li',
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/student.php'),
        get_string('studentdashboard', 'local_aiskillnavigator')
    )
);

echo html_writer::tag(
    'li',
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/teacher.php'),
        get_string('teacherdashboard', 'local_aiskillnavigator')
    )
);

echo html_writer::tag('li', 'AI Tutor - planned module');
echo html_writer::tag('li', 'AI Quiz Generator - planned module');
echo html_writer::tag('li', 'AI XR Scenario Generator - planned module');

echo html_writer::end_tag('ul');

echo html_writer::end_div();

echo $OUTPUT->footer();