<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('pluginname', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('pluginname', 'local_aiskillnavigator'));

$canstudent = has_capability('local/aiskillnavigator:viewstudent', $context);
$canteacher = has_capability('local/aiskillnavigator:viewteacher', $context);
$canmaterials = has_capability('local/aiskillnavigator:managematerials', $context);
$isadmin = is_siteadmin($USER);

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('pluginname', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Prototype Moodle plugin for AI-supported learning paths, grounded course tutoring, interactive quizzes and mind maps.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if ($isadmin) {
    echo html_writer::div(
        'You are logged in as site administrator, so Moodle shows both student and teacher tools. Use student1/teacher1 in incognito to test separation.',
        'alert alert-info'
    );
}

echo html_writer::start_div('row mt-4');

$cards = [];

if ($canstudent) {
    $cards[] = [
        'title' => 'Student dashboard',
        'text' => 'View your saved quiz scores and personal recommendations.',
        'url' => new moodle_url('/local/aiskillnavigator/student.php', ['courseid' => $courseid]),
        'button' => 'Open student dashboard',
    ];

    $cards[] = [
        'title' => 'Course AI Tutor',
        'text' => 'Ask questions grounded on the teacher materials saved in the course knowledge base.',
        'url' => new moodle_url('/local/aiskillnavigator/course_tutor.php', ['courseid' => $courseid]),
        'button' => 'Open Course Tutor',
    ];

    $cards[] = [
        'title' => 'AI Quiz Generator',
        'text' => 'Generate an AI micro-test, answer it, and save the score.',
        'url' => new moodle_url('/local/aiskillnavigator/quizgenerator.php', ['courseid' => $courseid]),
        'button' => 'Open Quiz Generator',
    ];

    $cards[] = [
        'title' => 'AI Mind Map Generator',
        'text' => 'Generate an interactive draggable mind map from an AI-generated concept structure.',
        'url' => new moodle_url('/local/aiskillnavigator/mindmapgenerator.php', ['courseid' => $courseid]),
        'button' => 'Open Mind Map Generator',
    ];

    $cards[] = [
        'title' => 'General AI Tutor',
        'text' => 'Ask general AI questions about course-related topics.',
        'url' => new moodle_url('/local/aiskillnavigator/tutor.php', ['courseid' => $courseid]),
        'button' => 'Open AI Tutor',
    ];
}

if ($canteacher) {
    $cards[] = [
        'title' => 'Teacher dashboard',
        'text' => 'View class performance, materials and students at risk.',
        'url' => new moodle_url('/local/aiskillnavigator/teacher.php', ['courseid' => $courseid]),
        'button' => 'Open teacher dashboard',
    ];

    $cards[] = [
        'title' => 'AI XR Scenario Generator',
        'text' => 'Generate structured Virtual Worlds training scenarios for digital skills.',
        'url' => new moodle_url('/local/aiskillnavigator/scenariogenerator.php', ['courseid' => $courseid]),
        'button' => 'Open Scenario Generator',
    ];
}

if ($canmaterials) {
    $cards[] = [
        'title' => 'Teacher Materials',
        'text' => 'Upload PowerPoint slides or text files. The AI Course Tutor uses the extracted text.',
        'url' => new moodle_url('/local/aiskillnavigator/teacher_materials.php', ['courseid' => $courseid]),
        'button' => 'Manage materials',
    ];
}

if (empty($cards)) {
    echo html_writer::div(
        'No AI Skill Navigator tools are available for your current Moodle role in this course.',
        'alert alert-warning'
    );
}

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