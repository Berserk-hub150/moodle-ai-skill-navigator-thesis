<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

global $PAGE, $OUTPUT, $DB;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/teacher.php', ['courseid' => $courseid]));
$PAGE->set_title('Teacher dashboard');
$PAGE->set_heading('Teacher dashboard');

require_capability('local/aiskillnavigator:viewteacher', $context);

$attempts = $DB->get_records(
    'local_aiskillnav_attempt',
    ['courseid' => $courseid],
    'timecreated DESC'
);

$materialcount = $DB->count_records('local_aiskillnav_material', ['courseid' => $courseid]);

$totalattempts = count($attempts);
$average = 0;
$students = [];

if ($totalattempts > 0) {
    $sum = 0;

    foreach ($attempts as $attempt) {
        $sum += (int) $attempt->percentage;

        if (!isset($students[$attempt->userid])) {
            $students[$attempt->userid] = [
                'count' => 0,
                'sum' => 0,
            ];
        }

        $students[$attempt->userid]['count']++;
        $students[$attempt->userid]['sum'] += (int) $attempt->percentage;
    }

    $average = round($sum / $totalattempts);
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'Teacher dashboard');

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

echo html_writer::tag(
    'p',
    'This dashboard shows saved course materials and aggregated AI quiz results for this course.',
    ['class' => 'lead']
);

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $materialcount) .
    html_writer::tag('p', 'Teacher materials'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $totalattempts) .
    html_writer::tag('p', 'Quiz attempts'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::div(
    html_writer::tag('h3', $average . '%') .
    html_writer::tag('p', 'Average class score'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/teacher_materials.php', ['courseid' => $courseid]),
        'Manage teacher materials',
        ['class' => 'btn btn-primary']
    ) .
    ' ' .
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/scenariogenerator.php', ['courseid' => $courseid]),
        'Open Scenario Generator',
        ['class' => 'btn btn-secondary']
    ),
    'mt-3 mb-4'
);

echo html_writer::tag('h3', 'Student performance');

if (empty($students)) {
    echo html_writer::div('No student quiz attempts yet.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'User ID');
    echo html_writer::tag('th', 'Attempts');
    echo html_writer::tag('th', 'Average');
    echo html_writer::tag('th', 'Status');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($students as $userid => $data) {
        $studentaverage = $data['count'] > 0 ? round($data['sum'] / $data['count']) : 0;

        $status = 'At risk';
        $class = 'badge badge-danger';

        if ($studentaverage >= 80) {
            $status = 'Strong';
            $class = 'badge badge-success';
        } else if ($studentaverage >= 50) {
            $status = 'Needs practice';
            $class = 'badge badge-warning';
        }

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s((string) $userid));
        echo html_writer::tag('td', s((string) $data['count']));
        echo html_writer::tag('td', s($studentaverage . '%'));
        echo html_writer::tag('td', html_writer::tag('span', $status, ['class' => $class]));
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php', ['courseid' => $courseid]),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();