<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../../config.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
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
    'Prototype Moodle plugin for AI-supported learning paths, grounded course tutoring, interactive quizzes, mind maps and teacher analytics.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if ($isadmin) {
    echo html_writer::div(
        'You are logged in as site administrator. Moodle administrators can see both student and teacher tools. To test role separation, use a real student account and a real teacher account enrolled in this course.',
        'alert alert-info'
    );
}

if (!$canstudent && !$canteacher && !$canmaterials) {
    echo html_writer::div(
        'No AI Skill Navigator tools are available for your current role in this course.',
        'alert alert-warning'
    );

    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('row mt-4');

if ($canstudent) {
    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'Student dashboard', ['class' => 'card-title']);
    echo html_writer::tag('p', 'View your quiz attempts, average score, best score and personalised recommendations.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/student.php', ['courseid' => $courseid]),
        'Open student dashboard',
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'Course AI Tutor', ['class' => 'card-title']);
    echo html_writer::tag('p', 'Ask questions grounded on the teacher materials saved in the course knowledge base.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
        'Open Course Tutor',
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'AI Quiz Generator', ['class' => 'card-title']);
    echo html_writer::tag('p', 'Generate an AI micro-test, answer it, and save the result in your student profile.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/quizgenerator.php', ['courseid' => $courseid]),
        'Open Quiz Generator',
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'AI Mind Map Generator', ['class' => 'card-title']);
    echo html_writer::tag('p', 'Generate an interactive draggable mind map from an AI-generated concept structure.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/mindmapgenerator.php', ['courseid' => $courseid]),
        'Open Mind Map Generator',
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'General AI Tutor', ['class' => 'card-title']);
    echo html_writer::tag('p', 'Ask general AI questions about course-related topics.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
        'Open AI Tutor',
        ['class' => 'btn btn-primary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($canteacher) {
    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100 border-info');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'Teacher dashboard', ['class' => 'card-title']);
    echo html_writer::tag('p', 'View class performance, student progress, weak topics and students at risk.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher.php', ['courseid' => $courseid]),
        'Open teacher dashboard',
        ['class' => 'btn btn-info']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100 border-info');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'AI XR Scenario Generator', ['class' => 'card-title']);
    echo html_writer::tag('p', 'Generate structured Virtual Worlds training scenarios for digital skills.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/scenariogenerator.php', ['courseid' => $courseid]),
        'Open Scenario Generator',
        ['class' => 'btn btn-info']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($canmaterials) {
    echo html_writer::start_div('col-md-4 mb-3');
    echo html_writer::start_div('card h-100 border-info');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h4', 'Teacher Materials', ['class' => 'card-title']);
    echo html_writer::tag('p', 'Upload PowerPoint slides or text files. The Course AI Tutor uses the extracted text.', ['class' => 'card-text']);
    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_materials.php', ['courseid' => $courseid]),
        'Manage materials',
        ['class' => 'btn btn-info']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();


