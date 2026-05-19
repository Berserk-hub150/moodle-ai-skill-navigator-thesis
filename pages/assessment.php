<?php

require_once(__DIR__ . '/../../../config.php');

global $PAGE, $OUTPUT, $DB, $USER;

$assessmentid = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if ($assessmentid > 0) {
    $assessment = $DB->get_record('local_aiskillnav_assessment', ['id' => $assessmentid], '*', MUST_EXIST);
    $courseid = (int) $assessment->courseid;
} else {
    $assessment = null;
}

$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/assessment.php', ['courseid' => $courseid]));
$PAGE->set_title('AI diagnostic assessments');
$PAGE->set_heading('AI diagnostic assessments');

function local_aiskillnavigator_assessment_type_label(string $type): string {
    return $type === 'final' ? 'Final test' : 'Initial diagnostic quiz';
}

function local_aiskillnavigator_assessment_type_badge(string $type): string {
    return $type === 'final'
        ? html_writer::span('Final test', 'badge bg-success')
        : html_writer::span('Pre-test', 'badge bg-info');
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

if ($assessment === null) {
    echo html_writer::tag('h2', 'Available AI assessments');
    echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

    $assessments = $DB->get_records(
        'local_aiskillnav_assessment',
        ['courseid' => $courseid, 'visible' => 1],
        'timecreated DESC'
    );

    if (empty($assessments)) {
        echo html_writer::div('No published assessments are available for this course yet.', 'alert alert-info');
    } else {
        foreach ($assessments as $item) {
            $attempt = $DB->get_record('local_aiskillnav_ass_att', [
                'assessmentid' => $item->id,
                'userid' => $USER->id,
            ]);

            echo html_writer::start_div('card mb-3');
            echo html_writer::start_div('card-body');

            echo html_writer::tag(
                'h4',
                s($item->title) . ' ' . local_aiskillnavigator_assessment_type_badge((string) $item->assessmenttype)
            );

            echo html_writer::tag(
                'p',
                'Focus: ' . s($item->focus !== '' ? $item->focus : 'Course materials')
                . ' | Difficulty: ' . s($item->difficulty),
                ['class' => 'text-muted']
            );

            if ($attempt) {
                echo html_writer::div(
                    'Already completed. Score: ' . (int) $attempt->score . '/' . (int) $attempt->maxscore
                    . ' (' . (int) $attempt->percentage . '%)',
                    'alert alert-success'
                );
            }

            echo html_writer::link(
                new moodle_url('/local/aiskillnavigator/pages/assessment.php', ['id' => $item->id]),
                $attempt ? 'Review result' : 'Start assessment',
                ['class' => 'btn btn-primary btn-sm']
            );

            echo html_writer::end_div();
            echo html_writer::end_div();
        }
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
    exit;
}

if ((int) $assessment->courseid !== $courseid) {
    throw new moodle_exception('invalidcourseid');
}

if (!(int) $assessment->visible && !has_capability('local/aiskillnavigator:viewteacher', $context)) {
    throw new required_capability_exception($context, 'local/aiskillnavigator:viewstudent', 'nopermissions', '');
}

$quiz = json_decode((string) $assessment->quizjson, true);

if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
    echo html_writer::div('This assessment has invalid quiz data.', 'alert alert-danger');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$existingattempt = $DB->get_record('local_aiskillnav_ass_att', [
    'assessmentid' => $assessment->id,
    'userid' => $USER->id,
]);

$message = '';
$score = null;
$studentanswers = [];

if ($action === 'submit' && !$existingattempt) {
    require_sesskey();

    $score = 0;
    $total = count($quiz['questions']);

    foreach ($quiz['questions'] as $index => $question) {
        $answer = optional_param('answer_' . $index, -1, PARAM_INT);
        $studentanswers[$index] = $answer;

        $correct = isset($question['correct_index']) ? (int) $question['correct_index'] : -1;

        if ($answer === $correct) {
            $score++;
        }
    }

    $percentage = $total > 0 ? (int) round(($score / $total) * 100) : 0;

    $record = new stdClass();
    $record->assessmentid = $assessment->id;
    $record->courseid = $courseid;
    $record->userid = $USER->id;
    $record->score = $score;
    $record->maxscore = $total;
    $record->percentage = $percentage;
    $record->answersjson = json_encode($studentanswers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $record->timecreated = time();

    $DB->insert_record('local_aiskillnav_ass_att', $record);

    $existingattempt = $record;
    $message = 'Assessment submitted successfully.';
}

if ($existingattempt) {
    $score = (int) $existingattempt->score;
    $studentanswers = json_decode((string) $existingattempt->answersjson, true);

    if (!is_array($studentanswers)) {
        $studentanswers = [];
    }
}

echo html_writer::tag('h2', s($assessment->title));

echo html_writer::tag(
    'p',
    local_aiskillnavigator_assessment_type_label((string) $assessment->assessmenttype)
    . ' | Course: ' . s($course->fullname)
    . ' | Difficulty: ' . s($assessment->difficulty),
    ['class' => 'text-muted']
);

if ($message !== '') {
    echo html_writer::div(s($message), 'alert alert-success');
}

if ($existingattempt) {
    $percentage = (int) $existingattempt->percentage;
    $alertclass = $percentage >= 75 ? 'alert-success' : ($percentage >= 50 ? 'alert-warning' : 'alert-danger');

    echo html_writer::div(
        html_writer::tag('h4', 'Result')
        . html_writer::tag('p', 'Score: ' . (int) $existingattempt->score . '/' . (int) $existingattempt->maxscore . ' (' . $percentage . '%)'),
        'alert ' . $alertclass
    );
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/assessment.php'),
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'submit']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $assessment->id]);

foreach ($quiz['questions'] as $index => $question) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h4', 'Question ' . ($index + 1));
    echo html_writer::tag('p', s($question['question'] ?? ''), ['class' => 'font-weight-bold']);

    $options = $question['options'] ?? [];
    $correct = isset($question['correct_index']) ? (int) $question['correct_index'] : -1;
    $selected = $studentanswers[$index] ?? null;

    foreach ($options as $optionindex => $option) {
        $inputid = 'q' . $index . '_option' . $optionindex;

        $attributes = [
            'type' => 'radio',
            'name' => 'answer_' . $index,
            'id' => $inputid,
            'value' => $optionindex,
            'class' => 'mr-2',
            'required' => 'required',
        ];

        if ($selected !== null && (int) $selected === (int) $optionindex) {
            $attributes['checked'] = 'checked';
        }

        if ($existingattempt) {
            $attributes['disabled'] = 'disabled';
        }

        $label = s($option);

        if ($existingattempt && $optionindex === $correct) {
            $label .= ' ' . html_writer::span('Correct answer', 'badge bg-success ml-2');
        }

        if (
            $existingattempt &&
            $selected !== null &&
            (int) $selected === (int) $optionindex &&
            (int) $selected !== $correct
        ) {
            $label .= ' ' . html_writer::span('Your answer', 'badge bg-danger ml-2');
        }

        echo html_writer::start_div('form-check mb-2');
        echo html_writer::empty_tag('input', $attributes);
        echo html_writer::tag('label', $label, ['for' => $inputid, 'class' => 'form-check-label']);
        echo html_writer::end_div();
    }

    if ($existingattempt) {
        if (!empty($question['skill'])) {
            echo html_writer::tag('p', html_writer::tag('strong', 'Skill: ') . s($question['skill']), ['class' => 'mt-3']);
        }

        if (!empty($question['explanation'])) {
            echo html_writer::tag('p', html_writer::tag('strong', 'Explanation: ') . s($question['explanation']));
        }
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

if (!$existingattempt) {
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-success mt-3',
        'value' => 'Submit assessment',
    ]);
}

echo html_writer::end_tag('form');

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/assessment.php', ['courseid' => $courseid]),
        'Back to available assessments',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();