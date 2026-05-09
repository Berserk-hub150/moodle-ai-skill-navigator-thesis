<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\skill_service;

require_login();

global $USER;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/student.php'));
$PAGE->set_title(get_string('studentdashboard', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('studentdashboard', 'local_aiskillnavigator'));

$service = new skill_service();
$profile = $service->get_student_skill_profile((int) $USER->id);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('studentdashboard', 'local_aiskillnavigator'));

echo html_writer::tag('p', 'This dashboard shows a first prototype of the student skill profile.');

echo html_writer::tag('h3', 'Skill profile');

$table = new html_table();
$table->head = ['Skill', 'Score', 'Status', 'Description'];
$table->data = [];

foreach ($profile['skills'] as $skill) {
    $table->data[] = [
        s($skill['name']),
        s($skill['score']) . '%',
        s($skill['status']),
        s($skill['description']),
    ];
}

echo html_writer::table($table);

echo html_writer::tag('h3', 'Main skill gap');
echo html_writer::tag('p', s($profile['main_gap']));

echo html_writer::tag('h3', 'AI recommendation prototype');
echo html_writer::tag('p', s($profile['recommendation']));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home'
    )
);

echo $OUTPUT->footer();