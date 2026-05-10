<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();

global $PAGE, $OUTPUT, $DB, $USER;

$context = context_system::instance();

require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/student.php'));
$PAGE->set_title(get_string('studentdashboard', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('studentdashboard', 'local_aiskillnavigator'));

$attempts = $DB->get_records(
    'local_aiskillnav_attempt',
    ['userid' => $USER->id],
    'timecreated DESC',
    '*',
    0,
    20
);

$totalattempts = count($attempts);
$average = 0;

if ($totalattempts > 0) {
    $sum = 0;

    foreach ($attempts as $attempt) {
        $sum += (int) $attempt->percentage;
    }

    $average = round($sum / $totalattempts);
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('studentdashboard', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'This dashboard shows your AI quiz attempts and learning progress.',
    ['class' => 'lead']
);

echo html_writer::start_div('row');

echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::div(
    html_writer::tag('h3', (string) $totalattempts) .
    html_writer::tag('p', 'Completed AI quizzes'),
    'card card-body text-center'
);
echo html_writer::end_div();

echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::div(
    html_writer::tag('h3', $average . '%') .
    html_writer::tag('p', 'Average score'),
    'card card-body text-center'
);
echo html_writer::end_div();

$recommendation = 'Complete at least one AI quiz to receive a recommendation.';

if ($totalattempts > 0) {
    if ($average >= 80) {
        $recommendation = 'Great work. Try a harder quiz or ask the AI tutor to deepen the topic.';
    } else if ($average >= 50) {
        $recommendation = 'Good start. Review the mind map and repeat the quiz after studying weak concepts.';
    } else {
        $recommendation = 'Focus on basic concepts. Use the Course AI Tutor and then retry an easier quiz.';
    }
}

echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::div(
    html_writer::tag('h3', 'Recommendation') .
    html_writer::tag('p', s($recommendation)),
    'card card-body'
);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::tag('h3', 'Recent quiz attempts', ['class' => 'mt-4']);

if (empty($attempts)) {
    echo html_writer::div('No quiz attempts saved yet.', 'alert alert-info');
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Date');
    echo html_writer::tag('th', 'Topic');
    echo html_writer::tag('th', 'Difficulty');
    echo html_writer::tag('th', 'Score');
    echo html_writer::tag('th', 'Percentage');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($attempts as $attempt) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', userdate($attempt->timecreated));
        echo html_writer::tag('td', s($attempt->topic));
        echo html_writer::tag('td', s($attempt->difficulty));
        echo html_writer::tag('td', s($attempt->score . '/' . $attempt->maxscore));
        echo html_writer::tag('td', s($attempt->percentage . '%'));
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::div(
    html_writer::link(new moodle_url('/local/aiskillnavigator/index.php'), 'Back to plugin home', ['class' => 'btn btn-secondary mt-3'])
);

echo html_writer::end_div();

echo $OUTPUT->footer();