<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\embedding_service;
use local_aiskillnavigator\service\real_ai_service;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/quizgenerator.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('quizgenerator', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('quizgenerator', 'local_aiskillnavigator'));

$topic = optional_param('topic', '', PARAM_TEXT);
$difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);

// -1 = argomento libero senza materiali.
//  0 = tutti i materiali leggibili.
// >0 = singolo materiale selezionato.
$materialid = optional_param('materialid', -1, PARAM_INT);

$generate = optional_param('generate', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);

$result = '';
$quiz = null;
$score = null;
$total = 0;
$studentanswers = [];
$savedmessage = '';
$selectedmaterials = [];
$parseerror = '';
$warning = '';
$ragdebug = '';
$ragsources = [];

$materials = $DB->get_records(
    'local_aiskillnav_material',
    ['courseid' => $courseid],
    'timecreated DESC'
);

$readablematerials = [];

foreach ($materials as $material) {
    $content = trim((string) ($material->content ?? ''));

    if ($content !== '') {
        $readablematerials[(int) $material->id] = $material;
    }
}

$embeddingservice = new embedding_service();
$totalchunks = $embeddingservice->count_indexed_chunks($courseid);

function local_aiskillnavigator_clean_ai_json_response(string $raw): string {
    $clean = trim($raw);

    $clean = preg_replace('/^```json\s*/i', '', $clean);
    $clean = preg_replace('/^```\s*/i', '', $clean);
    $clean = preg_replace('/\s*```$/', '', $clean);
    $clean = trim($clean);

    $start = strpos($clean, '{');

    if ($start === false) {
        return '';
    }

    $clean = substr($clean, $start);
    $clean = trim($clean);

    $lastbrace = strrpos($clean, '}');

    if ($lastbrace !== false) {
        $possiblejson = substr($clean, 0, $lastbrace + 1);
        $decoded = json_decode($possiblejson, true);

        if (is_array($decoded)) {
            return $possiblejson;
        }
    }

    return local_aiskillnavigator_repair_json($clean);
}

function local_aiskillnavigator_repair_json(string $json): string {
    $json = trim($json);

    $json = preg_replace('/,\s*([}\]])/', '$1', $json);

    $stack = [];
    $instring = false;
    $escaped = false;
    $length = strlen($json);

    for ($i = 0; $i < $length; $i++) {
        $char = $json[$i];

        if ($escaped) {
            $escaped = false;
            continue;
        }

        if ($char === '\\' && $instring) {
            $escaped = true;
            continue;
        }

        if ($char === '"') {
            $instring = !$instring;
            continue;
        }

        if ($instring) {
            continue;
        }

        if ($char === '{') {
            $stack[] = '}';
        } else if ($char === '[') {
            $stack[] = ']';
        } else if ($char === '}' || $char === ']') {
            if (!empty($stack) && end($stack) === $char) {
                array_pop($stack);
            }
        }
    }

    while (!empty($stack)) {
        $json .= array_pop($stack);
    }

    return $json;
}

function local_aiskillnavigator_extract_quiz_json(string $raw): ?array {
    $cleanresult = local_aiskillnavigator_clean_ai_json_response($raw);

    if ($cleanresult === '') {
        return null;
    }

    $decoded = json_decode($cleanresult, true);

    if (!is_array($decoded)) {
        return null;
    }

    if (empty($decoded['questions']) || !is_array($decoded['questions'])) {
        return null;
    }

    $decoded['questions'] = array_slice(array_values($decoded['questions']), 0, 3);

    foreach ($decoded['questions'] as $index => $question) {
        if (!is_array($question)) {
            return null;
        }

        if (empty($question['question'])) {
            return null;
        }

        if (empty($question['options']) || !is_array($question['options'])) {
            return null;
        }

        $decoded['questions'][$index]['options'] = array_slice(array_values($question['options']), 0, 4);

        if (count($decoded['questions'][$index]['options']) !== 4) {
            return null;
        }

        if (!isset($question['correct_index'])) {
            $correcttext = '';

            if (!empty($question['correct']) && is_string($question['correct'])) {
                $correcttext = $question['correct'];
            } else if (!empty($question['answer']) && is_string($question['answer'])) {
                $correcttext = $question['answer'];
            } else if (!empty($question['correct_answer']) && is_string($question['correct_answer'])) {
                $correcttext = $question['correct_answer'];
            }

            if ($correcttext !== '') {
                $foundindex = array_search($correcttext, $decoded['questions'][$index]['options'], true);
                $decoded['questions'][$index]['correct_index'] = $foundindex !== false ? (int) $foundindex : 0;
            } else {
                $decoded['questions'][$index]['correct_index'] = 0;
            }
        }

        $correctindex = (int) $decoded['questions'][$index]['correct_index'];

        if ($correctindex < 0 || $correctindex > 3) {
            $decoded['questions'][$index]['correct_index'] = 0;
        }

        if (empty($decoded['questions'][$index]['explanation'])) {
            $decoded['questions'][$index]['explanation'] = 'Risposta corretta secondo il materiale o argomento selezionato.';
        }

        if (empty($decoded['questions'][$index]['skill'])) {
            $decoded['questions'][$index]['skill'] = 'Concetto valutato';
        }
    }

    if (empty($decoded['title'])) {
        $decoded['title'] = 'Generated AI test';
    }

    if (empty($decoded['topic'])) {
        $decoded['topic'] = 'Argomento generico';
    }

    if (empty($decoded['difficulty'])) {
        $decoded['difficulty'] = 'medium';
    }

    return $decoded;
}

function local_aiskillnavigator_material_short_title(stdClass $material): string {
    $title = trim((string) ($material->title ?? 'Materiale senza titolo'));

    if ($title === '') {
        $title = 'Materiale senza titolo';
    }

    $contentlength = strlen((string) ($material->content ?? ''));

    return $title . ' (' . $contentlength . ' chars)';
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
        $record->topic = (string) ($quiz['topic'] ?? ($topic !== '' ? $topic : 'Argomento generico'));
        $record->difficulty = (string) ($quiz['difficulty'] ?? $difficulty);
        $record->score = $score;
        $record->maxscore = $total;
        $record->percentage = $percentage;
        $record->quizjson = json_encode($quiz, JSON_UNESCAPED_UNICODE);
        $record->answersjson = json_encode($studentanswers, JSON_UNESCAPED_UNICODE);
        $record->timecreated = time();

        $DB->insert_record('local_aiskillnav_attempt', $record);

        $savedmessage = 'Quiz attempt saved in the student profile.';
    }
} else if ($generate) {
    if ($materialid === -1) {
        $selectedmaterials = [];
    } else if ($materialid > 0 && isset($readablematerials[$materialid])) {
        $selectedmaterials = [$readablematerials[$materialid]];
    } else {
        $selectedmaterials = array_values($readablematerials);
    }

    $service = new real_ai_service();

    if ($materialid === -1) {
        $fallbacktopic = $topic !== '' ? $topic : 'Digital Twin';
        $result = $service->generate_quiz($fallbacktopic, $difficulty);
    } else if ($totalchunks > 0) {
        $searchquery = $topic !== '' ? $topic : 'quiz based on course materials';
        $searchmaterialid = $materialid > 0 ? $materialid : 0;
        $results = $embeddingservice->search($searchquery, $courseid, 6, $searchmaterialid);

        if (!empty($results)) {
            $ragcontext = $embeddingservice->build_context($results, 6500);
            $result = $service->generate_quiz_with_rag_context($topic, $difficulty, $ragcontext);
            $ragdebug = count($results) . ' RAG chunks retrieved, top similarity: ' . $results[0]->similarity;

            foreach ($results as $ragresult) {
                $ragsources[$ragresult->title . ' — chunk ' . (((int) $ragresult->chunkindex) + 1)] = $ragresult->similarity;
            }
        } else if (!empty($selectedmaterials)) {
            $warning = 'No RAG chunks found for this focus. Falling back to full material context.';
            $result = $service->generate_quiz_from_course_materials($topic, $difficulty, $selectedmaterials);
        } else {
            $warning = 'No RAG chunks found. Falling back to manual topic generation.';
            $result = $service->generate_quiz($topic !== '' ? $topic : 'Digital Twin', $difficulty);
        }
    } else if (!empty($selectedmaterials)) {
        $warning = 'Teacher materials exist but are not indexed for RAG yet. Falling back to full material context.';
        $result = $service->generate_quiz_from_course_materials($topic, $difficulty, $selectedmaterials);
    } else {
        $fallbacktopic = $topic !== '' ? $topic : 'Digital Twin';
        $result = $service->generate_quiz($fallbacktopic, $difficulty);
    }

    $quiz = local_aiskillnavigator_extract_quiz_json($result);

    if ($quiz === null) {
        if ($materialid !== -1 && $totalchunks > 0) {
            $searchquery = $topic !== '' ? $topic : 'quiz based on course materials';
            $searchmaterialid = $materialid > 0 ? $materialid : 0;
            $results = $embeddingservice->search($searchquery, $courseid, 6, $searchmaterialid);
            $ragcontext = $embeddingservice->build_context($results, 6500);
            $result = $service->generate_quiz_with_rag_context($topic, $difficulty, $ragcontext);
        } else {
            $result = !empty($selectedmaterials)
                ? $service->generate_quiz_from_course_materials($topic, $difficulty, $selectedmaterials)
                : $service->generate_quiz($topic !== '' ? $topic : 'Digital Twin', $difficulty);
        }

        $quiz = local_aiskillnavigator_extract_quiz_json($result);
    }

    if ($quiz === null) {
        $parseerror = 'The AI response could not be parsed as a structured test.';
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('quizgenerator', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Generate an AI micro-quiz from a generic topic or from teacher materials. In RAG mode, the quiz is grounded on the most relevant indexed chunks.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if ($savedmessage !== '') {
    echo html_writer::div(s($savedmessage), 'alert alert-success');
}

if ($warning !== '') {
    echo html_writer::div(s($warning), 'alert alert-warning');
}

if ($totalchunks > 0) {
    echo html_writer::div(
        'RAG index active: ' . $totalchunks . ' chunks indexed. Quiz generation can use semantic retrieval.',
        'alert alert-success'
    );
} else if (empty($readablematerials)) {
    echo html_writer::div(
        'No readable teacher materials found yet. You can still generate a quiz from a manual topic.',
        'alert alert-warning'
    );
} else {
    echo html_writer::div(
        'Readable teacher materials available: ' . count($readablematerials) . ', but no RAG chunks are indexed yet. Re-index materials from Teacher Materials.',
        'alert alert-warning'
    );
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

echo html_writer::tag('label', 'Generation source', ['for' => 'materialid']);

$materialoptions = [
    -1 => 'Manual topic only (do not use teacher materials)',
    0 => 'RAG semantic search (all course materials)',
];

foreach ($readablematerials as $material) {
    $chunks = $embeddingservice->count_indexed_chunks($courseid, (int) $material->id);
    $materialoptions[(int) $material->id] = local_aiskillnavigator_material_short_title($material) . ' — RAG chunks: ' . $chunks;
}

echo html_writer::select(
    $materialoptions,
    'materialid',
    $materialid,
    false,
    [
        'class' => 'form-control',
        'id' => 'materialid',
    ]
);

echo html_writer::tag(
    'small',
    'Choose "Manual topic only" for any generic topic. Choose RAG mode when you want the quiz grounded on indexed teacher materials.',
    ['class' => 'form-text text-muted']
);

echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');

echo html_writer::tag('label', 'Topic or optional focus', ['for' => 'topic']);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
    'placeholder' => 'Example: CSS, One Piece, Digital Twin, cybersecurity...',
]);

echo html_writer::tag(
    'small',
    'With manual topic mode this is the quiz topic. With teacher materials mode this is only an optional focus inside the selected materials.',
    ['class' => 'form-text text-muted']
);

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
    'value' => 'Generate test',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($quiz !== null) {
    $quizjson = json_encode($quiz, JSON_UNESCAPED_UNICODE);
    $encodedquiz = base64_encode($quizjson);

    echo html_writer::start_div('card mt-4 mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', s($quiz['title'] ?? 'Generated AI test'));

    echo html_writer::tag(
        'p',
        'Topic: ' . s($quiz['topic'] ?? ($topic !== '' ? $topic : 'Argomento generico')) .
        ' | Difficulty: ' . s($quiz['difficulty'] ?? $difficulty),
        ['class' => 'text-muted']
    );

    if (!empty($ragsources)) {
        echo html_writer::tag('p', 'Generated with RAG semantic retrieval:', ['class' => 'text-muted mb-1']);
        echo html_writer::start_tag('ul', ['class' => 'text-muted small']);

        foreach ($ragsources as $title => $similarity) {
            echo html_writer::tag(
                'li',
                s($title) . ' ' . html_writer::tag('span', 'similarity: ' . $similarity, ['class' => 'badge badge-info'])
            );
        }

        echo html_writer::end_tag('ul');

        if ($ragdebug !== '') {
            echo html_writer::tag('p', s($ragdebug), ['class' => 'text-muted small']);
        }
    } else if (!empty($selectedmaterials)) {
        $sourcenames = [];

        foreach ($selectedmaterials as $material) {
            $sourcenames[] = $material->title;
        }

        echo html_writer::tag(
            'p',
            'Generated from teacher materials: ' . s(implode(', ', $sourcenames)),
            ['class' => 'text-muted']
        );
    } else {
        echo html_writer::tag(
            'p',
            'Generated from manual topic, without teacher materials.',
            ['class' => 'text-muted']
        );
    }

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
        'name' => 'materialid',
        'value' => $materialid,
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'quizdata',
        'value' => $encodedquiz,
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
                    'required' => 'required',
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
                echo html_writer::tag(
                    'p',
                    html_writer::tag('strong', 'Skill: ') . s($question['skill']),
                    ['class' => 'mt-3']
                );
            }

            if (!empty($question['explanation'])) {
                echo html_writer::tag(
                    'p',
                    html_writer::tag('strong', 'Explanation: ') . s($question['explanation'])
                );
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
                'materialid' => $materialid,
                'courseid' => $courseid,
            ]),
            'Generate another test',
            ['class' => 'btn btn-primary mt-3']
        );
    }

    echo html_writer::end_tag('form');
} else if ($result !== '') {
    echo html_writer::start_div('alert alert-warning mt-4');
    echo html_writer::tag('h4', s($parseerror !== '' ? $parseerror : 'The AI response could not be parsed as a structured test.'));
    echo html_writer::tag('p', 'Raw response:');

    echo html_writer::tag('pre', s($result), [
        'style' => 'white-space: pre-wrap; font-family: inherit;',
    ]);

    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php', ['courseid' => $courseid]),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    ),
    'mt-4'
);

echo html_writer::end_div();

echo $OUTPUT->footer();