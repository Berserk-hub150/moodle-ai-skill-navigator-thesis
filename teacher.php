<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\ai_recommendation_service;
use local_aiskillnavigator\service\skill_service;

require_login();

global $PAGE, $OUTPUT;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/teacher.php'));
$PAGE->set_title(get_string('teacherdashboard', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('teacherdashboard', 'local_aiskillnavigator'));

$skillservice = new skill_service();
$recommendationservice = new ai_recommendation_service();

$overview = $skillservice->get_teacher_skill_overview();
$recommendation = $recommendationservice->generate_teacher_recommendation($overview);

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('teacherdashboard', 'local_aiskillnavigator'));
echo html_writer::tag(
    'p',
    'This dashboard shows a first prototype of course-level skill gap analytics.',
    ['class' => 'lead']
);

echo html_writer::tag('h3', 'Weakest skills');

$table = new html_table();
$table->head = ['Skill', 'Average score', 'Students at risk', 'Suggested action'];
$table->attributes['class'] = 'generaltable table table-striped';
$table->data = [];

foreach ($overview['weakestskills'] as $skill) {
    $table->data[] = [
        s($skill['name']),
        s($skill['average']) . '%',
        s($skill['studentsatrisk']),
        s($skill['suggestion']),
    ];
}

echo html_writer::table($table);

echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'AI teaching recommendation prototype');
echo html_writer::tag('p', s($recommendation), ['class' => 'lead']);

echo html_writer::tag('h4', 'Suggested teaching actions');

echo html_writer::start_tag('ul');

foreach ($overview['suggestedactions'] as $action) {
    echo html_writer::tag('li', s($action));
}

echo html_writer::end_tag('ul');

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();