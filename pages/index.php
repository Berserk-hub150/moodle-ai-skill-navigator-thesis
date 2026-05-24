<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);

if ($courseid <= SITEID) {
    $course = get_site();
    $context = context_system::instance();
    require_login();
} else {
    $course = get_course($courseid);
    $context = context_course::instance($courseid);
    require_login($course);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/index.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Skill Navigator');
$PAGE->set_heading('AI Skill Navigator');

function local_aiskillnavigator_index_link_exists(string $relativepath): bool {
    global $CFG;
    return file_exists($CFG->dirroot . $relativepath);
}

function local_aiskillnavigator_index_card(
    string $title,
    string $description,
    string $button,
    string $relativepath,
    int $courseid,
    string $buttonclass = 'btn btn-primary'
): string {
    if (!local_aiskillnavigator_index_link_exists($relativepath)) {
        return '';
    }

    $html = html_writer::start_div('card mb-3');
    $html .= html_writer::start_div('card-body');
    $html .= html_writer::tag('h3', s($title));
    $html .= html_writer::tag('p', s($description), ['class' => 'text-muted']);

    $params = [];

    if ($courseid > SITEID) {
        $params['courseid'] = $courseid;
    }

    $html .= html_writer::link(
        new moodle_url($relativepath, $params),
        s($button),
        ['class' => $buttonclass]
    );

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}

$canstudent = $courseid > SITEID && has_capability('local/aiskillnavigator:viewstudent', $context);
$canteacher = $courseid > SITEID && has_capability('local/aiskillnavigator:viewteacher', $context);
$canmaterials = $courseid > SITEID && has_capability('local/aiskillnavigator:managematerials', $context);

$isadmin = is_siteadmin();

if ($isadmin) {
    $canstudent = true;
    $canteacher = true;
    $canmaterials = true;
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Skill Navigator');

echo html_writer::tag(
    'p',
    'Moodle plugin for AI-supported tutoring, course materials, quizzes, mind maps, diagnostic assessments and teacher analytics.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($isadmin && $courseid > SITEID) {
    echo html_writer::div(
        'You are logged in as site administrator, so both student and teacher tools are visible.',
        'alert alert-info'
    );
}

if ($courseid <= SITEID) {
    echo html_writer::div(
        'Open this plugin from inside a Moodle course to use course-aware AI tools.',
        'alert alert-warning'
    );
}

echo html_writer::start_div('row');

if ($canstudent) {
    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'AI assessments',
        'Open the initial diagnostic quiz and final test published by the teacher.',
        'Open AI assessments',
        '/local/aiskillnavigator/pages/assessment.php',
        $courseid,
        'btn btn-success'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'AI Tutor',
        'Ask questions with no material, or select exactly which course materials the AI can use.',
        'Open AI Tutor',
        '/local/aiskillnavigator/pages/tutor.php',
        $courseid
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'AI Quiz',
        'Generate an AI micro-quiz from a topic or selected course materials.',
        'Open AI Quiz',
        '/local/aiskillnavigator/pages/quizgenerator.php',
        $courseid
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'AI Mind Map',
        'Generate an interactive mind map from a topic or selected course materials.',
        'Open AI Mind Map',
        '/local/aiskillnavigator/pages/mindmapgenerator.php',
        $courseid
    );
    echo html_writer::end_div();
}


    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'Adaptive review',
        'Review weak skills detected from previous quiz and test answers.',
        'Open adaptive review',
        '/local/aiskillnavigator/pages/adaptive_review.php',
        $courseid,
        'btn btn-warning'
    );
    echo html_writer::end_div();

if ($canteacher) {
    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'Teacher dashboard',
        'View class performance, student progress, weak topics and course analytics.',
        'Open teacher dashboard',
        '/local/aiskillnavigator/pages/teacher.php',
        $courseid,
        'btn btn-info'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'Initial/final tests',
        'Create an initial diagnostic quiz and a final test from course materials.',
        'Open initial/final tests',
        '/local/aiskillnavigator/pages/teacher_assessments.php',
        $courseid,
        'btn btn-success'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'Learning-gap analysis',
        'Analyze pre-test and final-test attempts to identify weak skills and remediation actions.',
        'Open learning-gap analysis',
        '/local/aiskillnavigator/pages/gap_analysis.php',
        $courseid,
        'btn btn-warning'
    );
    echo html_writer::end_div();
}

if ($canteacher) {
    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'AI Course Builder',
        'Transform website text or external material into a Moodle course plan with pre-test, final test and activities.',
        'Open AI Course Builder',
        '/local/aiskillnavigator/pages/course_builder.php',
        $courseid,
        'btn btn-info'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'AI Simulator Finder',
        'Generate a practical exercise and suggest a suitable online simulator or tool for a topic.',
        'Open Simulator Finder',
        '/local/aiskillnavigator/pages/simulator_finder.php',
        $courseid,
        'btn btn-info'
    );
    echo html_writer::end_div();
}
if ($canmaterials) {
    echo html_writer::start_div('col-md-6 col-lg-4');
    echo local_aiskillnavigator_index_card(
        'Course materials / RAG',
        'Manage course materials and automatically synchronized Moodle resources used by the AI.',
        'Open course materials / RAG',
        '/local/aiskillnavigator/pages/teacher_materials.php',
        $courseid,
        'btn btn-success'
    );
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();