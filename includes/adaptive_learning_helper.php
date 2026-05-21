<?php

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_adaptive_table_exists(string $tablename): bool {
    global $DB;
    return $DB->get_manager()->table_exists(new xmldb_table($tablename));
}

function local_aiskillnavigator_adaptive_collect_student(int $courseid, int $userid): array {
    global $DB;

    $skills = [];

    $add = function(string $skill, bool $correct) use (&$skills) {
        $skill = trim($skill) !== '' ? trim($skill) : 'General understanding';

        if (!isset($skills[$skill])) {
            $skills[$skill] = [
                'skill' => $skill,
                'total' => 0,
                'correct' => 0,
                'wrong' => 0,
                'mastery' => 0,
            ];
        }

        $skills[$skill]['total']++;

        if ($correct) {
            $skills[$skill]['correct']++;
        } else {
            $skills[$skill]['wrong']++;
        }
    };

    if (local_aiskillnavigator_adaptive_table_exists('local_aiskillnav_attempt')) {
        $attempts = $DB->get_records(
            'local_aiskillnav_attempt',
            ['courseid' => $courseid, 'userid' => $userid],
            'timecreated ASC'
        );

        foreach ($attempts as $attempt) {
            $quiz = json_decode((string)$attempt->quizjson, true);
            $answers = json_decode((string)$attempt->answersjson, true);

            if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
                continue;
            }

            if (!is_array($answers)) {
                $answers = [];
            }

            foreach (array_values($quiz['questions']) as $index => $question) {
                $skill = (string)($question['skill'] ?? 'General understanding');
                $correctindex = isset($question['correct_index']) ? (int)$question['correct_index'] : -999;
                $answer = isset($answers[$index]) ? (int)$answers[$index] : -998;

                $add($skill, $answer === $correctindex);
            }
        }
    }

    if (
        local_aiskillnavigator_adaptive_table_exists('local_aiskillnav_assessment') &&
        local_aiskillnavigator_adaptive_table_exists('local_aiskillnav_ass_att')
    ) {
        $assessments = $DB->get_records('local_aiskillnav_assessment', ['courseid' => $courseid]);

        foreach ($assessments as $assessment) {
            $attempts = $DB->get_records(
                'local_aiskillnav_ass_att',
                ['assessmentid' => (int)$assessment->id, 'userid' => $userid],
                'timecreated ASC'
            );

            $quiz = json_decode((string)$assessment->quizjson, true);

            if (!is_array($quiz) || empty($quiz['questions']) || !is_array($quiz['questions'])) {
                continue;
            }

            $questions = array_values($quiz['questions']);

            foreach ($attempts as $attempt) {
                $answers = json_decode((string)$attempt->answersjson, true);

                if (!is_array($answers)) {
                    $answers = [];
                }

                foreach ($questions as $index => $question) {
                    $skill = (string)($question['skill'] ?? 'General understanding');
                    $correctindex = isset($question['correct_index']) ? (int)$question['correct_index'] : -999;
                    $answer = isset($answers[$index]) ? (int)$answers[$index] : -998;

                    $add($skill, $answer === $correctindex);
                }
            }
        }
    }

    foreach ($skills as $skill => $stats) {
        // Smoothing bayesiano leggero: evita mastery 0/100 con pochi tentativi.
        $skills[$skill]['mastery'] = round((($stats['correct'] + 1) / ($stats['total'] + 2)) * 100, 1);
    }

    uasort($skills, function($a, $b) {
        return $a['mastery'] <=> $b['mastery'];
    });

    return [
        'skills' => array_values($skills),
        'weakest' => array_slice(array_values($skills), 0, 5),
    ];
}

function local_aiskillnavigator_adaptive_prompt_context(array $profile): string {
    if (empty($profile['weakest'])) {
        return "No previous weak skill detected. Use a balanced quiz.\n";
    }

    $lines = [];
    $lines[] = "Adaptive student model based on previous answers:";
    foreach ($profile['weakest'] as $skill) {
        $lines[] = "- " . $skill['skill'] . ": mastery " . $skill['mastery'] . "%, wrong " . $skill['wrong'] . "/" . $skill['total'];
    }

    $lines[] = "Generate new questions that revisit the weakest skills with different wording and explanations.";

    return implode("\n", $lines);
}