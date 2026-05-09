<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\ai_recommendation_service;
use local_aiskillnavigator\service\skill_service;

require_login();

global $PAGE, $OUTPUT, $USER;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/student.php'));
$PAGE->set_title(get_string('studentdashboard', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('studentdashboard', 'local_aiskillnavigator'));

$skillservice = new skill_service();
$recommendationservice = new ai_recommendation_service();

$profile = $skillservice->get_student_skill_profile((int) $USER->id);
$recommendation = $recommendationservice->generate_student_recommendation($profile);

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('studentdashboard', 'local_aiskillnavigator'));
echo html_writer::tag(
    'p',
    'This dashboard shows a first prototype of the student skill profile.',
    ['class' => 'lead']
);

echo html_writer::start_div('row mt-4');

foreach ($profile['skills'] as $skill) {
    $badgeclass = $skillservice->get_score_badge_class((int) $skill['score']);

    echo html_writer::start_div('col-md-6 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h4', s($skill['name']), ['class' => 'card-title']);

    echo html_writer::tag(
        'span',
        s($skill['score']) . '% - ' . s($skill['status']),
        ['class' => $badgeclass]
    );

    echo html_writer::tag('p', s($skill['description']), ['class' => 'mt-3']);

    echo html_writer::tag('strong', 'Next action: ');
    echo html_writer::tag('span', s($skill['nextaction']));

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', get_string('main_gap', 'local_aiskillnavigator'));
echo html_writer::tag('p', s($profile['main_gap']), ['class' => 'lead']);

echo html_writer::tag('h3', get_string('ai_recommendation', 'local_aiskillnavigator'));
echo html_writer::tag('p', s($recommendation));

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