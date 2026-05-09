<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\skill_service;

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/teacher.php'));
$PAGE->set_title(get_string('teacherdashboard', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('teacherdashboard', 'local_aiskillnavigator'));

$service = new skill_service();
$overview = $service->get_teacher_skill_overview();

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('teacherdashboard', 'local_aiskillnavigator'));

echo html_writer::tag('p', 'This dashboard shows a first prototype of course-level skill gap analytics.');

echo html_writer::tag('h3', 'Weakest skills');

$table = new html_table();
$table->head = ['Skill', 'Average score', 'Students at risk'];
$table->data = [];

foreach ($overview['weakestskills'] as $skill) {
    $table->data[] = [
        s($skill['name']),
        s($skill['average']) . '%',
        s($skill['studentsatrisk']),
    ];
}

echo html_writer::table($table);

echo html_writer::tag('h3', 'Suggested teaching actions');

echo html_writer::start_tag('ul');

foreach ($overview['suggestedactions'] as $action) {
    echo html_writer::tag('li', s($action));
}

echo html_writer::end_tag('ul');

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home'
    )
);

echo $OUTPUT->footer();