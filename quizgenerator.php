<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

require_login();

global $PAGE, $OUTPUT, $DB, $USER;

$context = context_system::instance();

require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/quizgenerator.php'));
$PAGE->set_title(get_string('quizgenerator', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('quizgenerator', 'local_aiskillnavigator'));

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$topic = optional_param('topic', 'Digital Twin', PARAM_TEXT);
$difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);
$generate = optional_param('generate', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);

$result = '';
$quiz = null;
$score = null;
$total = 0;
$studentanswers = [];
$savedmessage = '';

function local_aiskillnavigator_extract_quiz_json(string $raw): ?array {
    $cleanresult = trim($raw);
    $cleanresult = preg_replace('/^```json\s*/i', '', $cleanresult);
    $cleanresult = preg_replace('/^```\s*/i', '', $cleanresult);
    $cleanresult = preg_replace('/\s*```$/', '', $cleanresult);

    $jsonstart = strpos($cleanresult, '{');
    $jsonend = strrpos($cleanresult, '}');

    if ($jsonstart !== false && $jsonend !== false && $jsonend > $jsonstart) {
        $cleanresult = substr($cleanresult, $jsonstart, $jsonend - $jsonstart + 1);
    }

    $decoded = json_decode($cleanresult, true);

    if (is_array($decoded) && !empty($decoded['questions']) && is_array($decoded['questions'])) {
        return $decoded;
    }

    return null;
}

if ($action === 'grade') {
    require_sesskey();

    $encodedquiz = required_param('quizdata', PARAM_RAW);
    $decodedjson = base64_decode($encodedquiz, true);

    if ($decodedjson !== false) {
        $quiz = json_decode($decodedjson, true);
    }

    if (is_array($quiz) && !empty($quiz['questions']) && is_array($quiz['questions'])) {
        $score = 0;
        $total = count($quiz['questions']);

        foreach ($quiz['questions'] as $index => $question) {
            $answer = optional_param('answer_' . $index, -1, PARAM_INT);
            $studentanswers[$index] = $answer;

            $correctindex = isset($question['correct_index']) ? (int) $question['correct_index'] : -1;

            if ($answer === $correctindex) {
                $score++;
            }
        }

        $percentage = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        $record = new stdClass();
        $record->courseid = $courseid;
        $record->userid = $USER->id;
        $record->topic = (string) ($quiz['topic'] ?? $topic);
        $record->difficulty = (string) ($quiz['difficulty'] ?? $difficulty);
        $record->score = $score;
        $record->maxscore = $total;
        $record->percentage = $percentage;
        $record->quizjson = json_encode($quiz);
        $record->answersjson = json_encode($studentanswers);
        $record->timecreated = time();

        $DB->insert_record('local_aiskillnav_attempt', $record);

        $savedmessage = 'Quiz attempt saved in the student profile.';
    }
} else if ($generate) {
    $service = new real_ai_service();
    $result = $service->generate_quiz($topic, $difficulty);
    $quiz = local_aiskillnavigator_extract_quiz_json($result);
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('quizgenerator', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Generate an AI micro-quiz, let the student answer it, and save the score in Moodle.',
    ['class' => 'lead']
);

if ($savedmessage !== '') {
    echo html_writer::div(s($savedmessage), 'alert alert-success');
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Generate a new test');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/quizgenerator.php'),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'generate',
    'value' => '1',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $courseid,
]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', get_string('quiz_topic', 'local_aiskillnavigator'), ['for' => 'topic']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Difficulty', ['for' => 'difficulty']);
echo html_writer::select(
    [
        'easy' => 'Easy',
        'medium' => 'Medium',
        'hard' => 'Hard',
    ],
    'difficulty',
    $difficulty,
    false,
    [
        'class' => 'form-control',
        'id' => 'difficulty',
    ]
);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate test with AI',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($quiz !== null) {
    $quizjson = json_encode($quiz);
    $encodedquiz = base64_encode($quizjson);

    echo html_writer::start_div('card mt-4 mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', s($quiz['title'] ?? 'Generated AI test'));
    echo html_writer::tag(
        'p',
        'Topic: ' . s($quiz['topic'] ?? $topic) . ' | Difficulty: ' . s($quiz['difficulty'] ?? $difficulty),
        ['class' => 'text-muted']
    );

    if ($score !== null) {
        $percentage = $total > 0 ? round(($score / $total) * 100) : 0;

        $alertclass = 'alert-danger';
        if ($percentage >= 80) {
            $alertclass = 'alert-success';
        } else if ($percentage >= 50) {
            $alertclass = 'alert-warning';
        }

        echo html_writer::div(
            html_writer::tag('h4', 'Result') .
            html_writer::tag('p', 'Score: ' . $score . '/' . $total . ' (' . $percentage . '%)'),
            'alert ' . $alertclass
        );
    }

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/aiskillnavigator/quizgenerator.php'),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'action',
        'value' => 'grade',
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'courseid',
        'value' => $courseid,
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'topic',
        'value' => s($topic),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'difficulty',
        'value' => s($difficulty),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'quizdata',
        'value' => s($encodedquiz),
    ]);

    foreach ($quiz['questions'] as $index => $question) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo html_writer::tag('h4', 'Question ' . ($index + 1));
        echo html_writer::tag('p', s($question['question'] ?? ''), ['class' => 'font-weight-bold']);

        $options = $question['options'] ?? [];
        $correctindex = isset($question['correct_index']) ? (int) $question['correct_index'] : -1;
        $selectedanswer = $studentanswers[$index] ?? null;

        if (is_array($options)) {
            foreach ($options as $optionindex => $option) {
                $inputid = 'q' . $index . '_option' . $optionindex;

                $attributes = [
                    'type' => 'radio',
                    'name' => 'answer_' . $index,
                    'id' => $inputid,
                    'value' => $optionindex,
                    'class' => 'mr-2',
                ];

                if ($selectedanswer !== null && (int) $selectedanswer === (int) $optionindex) {
                    $attributes['checked'] = 'checked';
                }

                if ($score !== null) {
                    $attributes['disabled'] = 'disabled';
                }

                $labeltext = s($option);

                if ($score !== null && $optionindex === $correctindex) {
                    $labeltext .= ' ' . html_writer::tag('span', 'Correct answer', ['class' => 'badge badge-success ml-2']);
                }

                if (
                    $score !== null &&
                    $selectedanswer !== null &&
                    (int) $selectedanswer === (int) $optionindex &&
                    (int) $selectedanswer !== $correctindex
                ) {
                    $labeltext .= ' ' . html_writer::tag('span', 'Your answer', ['class' => 'badge badge-danger ml-2']);
                }

                echo html_writer::start_div('form-check mb-2');
                echo html_writer::empty_tag('input', $attributes);
                echo html_writer::tag('label', $labeltext, [
                    'for' => $inputid,
                    'class' => 'form-check-label',
                ]);
                echo html_writer::end_div();
            }
        }

        if ($score !== null) {
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

    if ($score === null) {
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-success mt-3',
            'value' => 'Submit test',
        ]);
    } else {
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/quizgenerator.php', [
                'generate' => 1,
                'topic' => $topic,
                'difficulty' => $difficulty,
                'courseid' => $courseid,
            ]),
            'Generate another test',
            ['class' => 'btn btn-primary mt-3']
        );
    }

    echo html_writer::end_tag('form');
} else if ($result !== '') {
    echo html_writer::start_div('alert alert-warning mt-4');
    echo html_writer::tag('h4', 'The AI response could not be parsed as a structured test.');
    echo html_writer::tag('p', 'Raw response:');
    echo html_writer::tag('pre', s($result), [
        'style' => 'white-space: pre-wrap; font-family: inherit;',
    ]);
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    ),
    'mt-4'
);

echo html_writer::end_div();

echo $OUTPUT->footer();