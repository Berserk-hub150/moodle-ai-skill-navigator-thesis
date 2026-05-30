<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');

use local_aiskillnavigator\service\ai_provider_factory;
use local_aiskillnavigator\service\embedding_service;

global $PAGE, $OUTPUT, $DB, $USER;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);
if (isset($courseid) && (int)$courseid > 1 && function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}


$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/aiskillnavigator/assets/css/styles.css'));
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]));
$PAGE->set_title('Teacher diagnostic assessments');
$PAGE->set_heading('Teacher diagnostic assessments');

$action = optional_param('action', '', PARAM_ALPHA);
$message = '';
$error = '';
$rawresponse = '';

function local_aiskillnavigator_assessment_clean_json(string $raw): string {
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
    $lastbrace = strrpos($clean, '}');

    if ($lastbrace !== false) {
        $clean = substr($clean, 0, $lastbrace + 1);
    }

    return trim($clean);
}

function local_aiskillnavigator_assessment_parse_quiz(string $raw): ?array {
    $clean = local_aiskillnavigator_assessment_clean_json($raw);

    if ($clean === '') {
        return null;
    }

    $decoded = json_decode($clean, true);

    if (!is_array($decoded) || empty($decoded['questions']) || !is_array($decoded['questions'])) {
        return null;
    }

    $decoded['questions'] = array_slice(array_values($decoded['questions']), 0, 5);

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

        if (!isset($decoded['questions'][$index]['correct_index'])) {
            $decoded['questions'][$index]['correct_index'] = 0;
        }

        $correct = (int) $decoded['questions'][$index]['correct_index'];

        if ($correct < 0 || $correct > 3) {
            $decoded['questions'][$index]['correct_index'] = 0;
        }

        if (empty($decoded['questions'][$index]['skill'])) {
            $decoded['questions'][$index]['skill'] = 'Concetto valutato';
        }

        if (empty($decoded['questions'][$index]['explanation'])) {
            $decoded['questions'][$index]['explanation'] = 'Risposta corretta per il concetto valutato.';
        }
    }

    if (empty($decoded['title'])) {
        $decoded['title'] = 'AI diagnostic assessment';
    }

    if (empty($decoded['topic'])) {
        $decoded['topic'] = 'Materiali del corso';
    }

    return $decoded;
}

function local_aiskillnavigator_assessment_context_from_materials(array $materials, int $limit = 7500): string {
    $context = '';
    $total = 0;
    $source = 1;

    foreach ($materials as $material) {
        $title = trim((string) ($material->title ?? 'Materiale senza titolo'));
        $type = trim((string) ($material->materialtype ?? 'text'));
        $content = trim((string) ($material->content ?? ''));

        if ($content === '') {
            continue;
        }

        $content = trim((string) preg_replace('/\s+/u', ' ', $content));
        $block = "FONTE {$source}\nTitolo: {$title}\nTipo: {$type}\nContenuto: {$content}\n\n";

        if (\core_text::strlen($context . $block) > $limit) {
            $remaining = $limit - \core_text::strlen($context);

            if ($remaining > 250) {
                $context .= \core_text::substr($block, 0, $remaining) . "...\n\n";
            }

            break;
        }

        $context .= $block;
        $source++;
        $total += \core_text::strlen($block);
    }

    return trim($context);
}

function local_aiskillnavigator_assessment_build_prompt(
    string $type,
    string $focus,
    string $difficulty,
    string $materialcontext
): string {
    $isfinal = $type === 'final';
    $typename = $isfinal ? 'test finale' : 'quiz iniziale diagnostico';

    if ($isfinal) {
        $focus = trim($focus) !== '' ? trim($focus) : 'contenuti principali dei materiali caricati dal docente';
        $goal = 'misurare quanto gli studenti hanno compreso dopo lo studio, usando i materiali del docente come riferimento obbligatorio';
    } else {
        $focus = trim($focus) !== '' ? trim($focus) : 'prerequisiti generali del corso';
        $goal = 'misurare il livello di partenza della classe prima dello studio dei materiali del docente';
        $materialcontext = '';
    }

    $prompt = "Genera un {$typename} per Moodle.\n\n";
    $prompt .= "Obiettivo: {$goal}.\n";
    $prompt .= "Focus: {$focus}.\n";
    $prompt .= "Difficoltà: {$difficulty}.\n\n";

    if ($isfinal) {
        $prompt .= "MATERIALI DEL DOCENTE / CONTESTO RAG:\n{$materialcontext}\n\n";
        $prompt .= "Regola fondamentale per il test finale: usa SOLO concetti presenti o chiaramente derivabili dai materiali del docente. ";
        $prompt .= "Le domande devono verificare comprensione, applicazione e collegamento dei contenuti studiati.\n\n";
    } else {
        $prompt .= "Regola fondamentale per il quiz iniziale: NON usare materiali del docente, NON usare RAG, NON citare dispense, slide o contenuti caricati. ";
        $prompt .= "Le domande devono valutare solo prerequisiti, conoscenze pregresse e concetti base necessari per affrontare il modulo.\n\n";
    }

    $prompt .= "Rispondi SOLO con JSON valido, senza Markdown e senza testo extra.\n";
    $prompt .= "Formato obbligatorio:\n";
    $prompt .= "{\n";
    $prompt .= "  \"title\": \"...\",\n";
    $prompt .= "  \"topic\": \"...\",\n";
    $prompt .= "  \"type\": \"{$type}\",\n";
    $prompt .= "  \"questions\": [\n";
    $prompt .= "    {\n";
    $prompt .= "      \"question\": \"...\",\n";
    $prompt .= "      \"options\": [\"...\", \"...\", \"...\", \"...\"],\n";
    $prompt .= "      \"correct_index\": 0,\n";
    $prompt .= "      \"skill\": \"...\",\n";
    $prompt .= "      \"ability\": \"...\",\n";
    $prompt .= "      \"explanation\": \"...\"\n";
    $prompt .= "    }\n";
    $prompt .= "  ]\n";
    $prompt .= "}\n\n";
    $prompt .= "Regole obbligatorie: genera esattamente 5 domande, ogni domanda deve avere esattamente 4 opzioni, ";
    $prompt .= "correct_index deve essere tra 0 e 3, explanation breve massimo 180 caratteri.\n";

    return $prompt;
}

function local_aiskillnavigator_assessment_generate(
    string $type,
    string $focus,
    string $difficulty,
    string $context
): array {
    $prompt = local_aiskillnavigator_assessment_build_prompt($type, $focus, $difficulty, $context);

    $provider = ai_provider_factory::create_from_config();
    $raw = $provider->generate(
        $prompt,
        3200,
        'You are a strict JSON generator for Moodle diagnostic assessments. Return only valid JSON.'
    );

    $quiz = local_aiskillnavigator_assessment_parse_quiz($raw);

    return [$quiz, $raw];
}

function local_aiskillnavigator_assessment_badge(string $type): string {
    if ($type === 'final') {
        return html_writer::span('Final test', 'badge bg-success');
    }

    return html_writer::span('Pre-test', 'badge bg-info');
}

function local_aiskillnavigator_assessment_render_edit_form(stdClass $assessment, array $quiz, int $courseid): void {
    $questions = isset($quiz['questions']) && is_array($quiz['questions']) ? array_values($quiz['questions']) : [];

    for ($i = 0; $i < 5; $i++) {
        if (!isset($questions[$i]) || !is_array($questions[$i])) {
            $questions[$i] = [
                'question' => '',
                'options' => ['', '', '', ''],
                'correct_index' => 0,
                'skill' => '',
                'explanation' => '',
            ];
        }

        $questions[$i]['options'] = isset($questions[$i]['options']) && is_array($questions[$i]['options'])
            ? array_values($questions[$i]['options'])
            : ['', '', '', ''];

        for ($j = 0; $j < 4; $j++) {
            if (!isset($questions[$i]['options'][$j])) {
                $questions[$i]['options'][$j] = '';
            }
        }
    }

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Edit assessment');
    echo html_writer::tag(
        'p',
        'Modify the AI-generated test according to your teaching needs. Changes are saved directly in the test shown to students.',
        ['class' => 'text-muted']
    );

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php'),
    ]);

    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'update']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$assessment->id]);

    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', 'Assessment title', ['for' => 'edit_title']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'title',
        'id' => 'edit_title',
        'class' => 'form-control',
        'required' => 'required',
        'value' => s((string)$assessment->title),
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('form-group mt-3');
    echo html_writer::tag('label', 'Focus / module', ['for' => 'edit_focus']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'focus',
        'id' => 'edit_focus',
        'class' => 'form-control',
        'value' => s((string)$assessment->focus),
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('form-group mt-3');
    echo html_writer::tag('label', 'Difficulty', ['for' => 'edit_difficulty']);
    echo html_writer::select(
        [
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard',
        ],
        'difficulty',
        (string)$assessment->difficulty,
        false,
        ['class' => 'form-control', 'id' => 'edit_difficulty']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('form-check mt-3');
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'visible', 'value' => 0]);
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'name' => 'visible',
        'id' => 'edit_visible',
        'class' => 'form-check-input',
        'value' => 1,
        'checked' => !empty($assessment->visible) ? 'checked' : null,
    ]);
    echo html_writer::tag('label', 'Published to students', ['for' => 'edit_visible', 'class' => 'form-check-label']);
    echo html_writer::end_div();

    echo html_writer::start_div('form-check mt-2');
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resetattempts', 'value' => 0]);
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'name' => 'resetattempts',
        'id' => 'resetattempts',
        'class' => 'form-check-input',
        'value' => 1,
        'checked' => 'checked',
    ]);
    echo html_writer::tag(
        'label',
        'Reset previous student attempts after editing this test',
        ['for' => 'resetattempts', 'class' => 'form-check-label']
    );
    echo html_writer::end_div();

    echo html_writer::tag('hr', '');

    for ($i = 0; $i < 5; $i++) {
        $question = $questions[$i];
        $options = array_values($question['options']);
        $correct = isset($question['correct_index']) ? (int)$question['correct_index'] : 0;
        $correct = max(0, min(3, $correct));

        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h4', 'Question ' . ($i + 1));

        echo html_writer::start_div('form-group');
        echo html_writer::tag('label', 'Question text', ['for' => 'q_' . $i]);
        echo html_writer::tag('textarea', s((string)($question['question'] ?? '')), [
            'name' => 'q_' . $i,
            'id' => 'q_' . $i,
            'class' => 'form-control',
            'rows' => 3,
            'required' => 'required',
        ]);
        echo html_writer::end_div();

        for ($j = 0; $j < 4; $j++) {
            echo html_writer::start_div('form-group mt-2');
            echo html_writer::tag('label', 'Option ' . chr(65 + $j), ['for' => 'opt_' . $i . '_' . $j]);
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'opt_' . $i . '_' . $j,
                'id' => 'opt_' . $i . '_' . $j,
                'class' => 'form-control',
                'required' => 'required',
                'value' => s((string)$options[$j]),
            ]);
            echo html_writer::end_div();
        }

        echo html_writer::start_div('form-group mt-2');
        echo html_writer::tag('label', 'Correct answer', ['for' => 'correct_' . $i]);
        echo html_writer::select(
            [
                0 => 'A',
                1 => 'B',
                2 => 'C',
                3 => 'D',
            ],
            'correct_' . $i,
            $correct,
            false,
            ['class' => 'form-control', 'id' => 'correct_' . $i]
        );
        echo html_writer::end_div();

        echo html_writer::start_div('form-group mt-2');
        echo html_writer::tag('label', 'Skill evaluated', ['for' => 'skill_' . $i]);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'skill_' . $i,
            'id' => 'skill_' . $i,
            'class' => 'form-control',
            'value' => s((string)($question['skill'] ?? '')),
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('form-group mt-2');
        echo html_writer::tag('label', 'Explanation', ['for' => 'explanation_' . $i]);
        echo html_writer::tag('textarea', s((string)($question['explanation'] ?? '')), [
            'name' => 'explanation_' . $i,
            'id' => 'explanation_' . $i,
            'class' => 'form-control',
            'rows' => 2,
        ]);
        echo html_writer::end_div();

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => 'Save test changes',
    ]);

    echo ' ';

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]),
        'Cancel',
        ['class' => 'btn btn-outline-secondary']
    );

    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($action === 'delete') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $assessment = $DB->get_record('local_aiskillnav_assessment', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);

    $DB->delete_records('local_aiskillnav_ass_att', ['assessmentid' => $assessment->id]);
    $DB->delete_records('local_aiskillnav_assessment', ['id' => $assessment->id]);

    $message = 'Assessment deleted.';
}

if ($action === 'toggle') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $assessment = $DB->get_record('local_aiskillnav_assessment', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
    $assessment->visible = (int) !$assessment->visible;
    $assessment->timemodified = time();

    $DB->update_record('local_aiskillnav_assessment', $assessment);

    $message = $assessment->visible ? 'Assessment published to students.' : 'Assessment hidden from students.';
}

if ($action === 'update') {
    require_sesskey();

    $id = required_param('id', PARAM_INT);
    $assessment = $DB->get_record('local_aiskillnav_assessment', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);

    $title = required_param('title', PARAM_TEXT);
    $focus = optional_param('focus', '', PARAM_TEXT);
    $difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);
    $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';
    $visible = optional_param('visible', 0, PARAM_BOOL);
    $resetattempts = optional_param('resetattempts', 0, PARAM_BOOL);

    $questions = [];

    for ($i = 0; $i < 5; $i++) {
        $questiontext = required_param('q_' . $i, PARAM_RAW_TRIMMED);
        $options = [];

        for ($j = 0; $j < 4; $j++) {
            $optiontext = required_param('opt_' . $i . '_' . $j, PARAM_RAW_TRIMMED);
            $options[] = $optiontext;
        }

        $correct = optional_param('correct_' . $i, 0, PARAM_INT);
        $correct = max(0, min(3, $correct));

        $skill = optional_param('skill_' . $i, '', PARAM_TEXT);
        $explanation = optional_param('explanation_' . $i, '', PARAM_RAW_TRIMMED);

        if (trim($skill) === '') {
            $skill = 'Concetto valutato';
        }

        if (trim($explanation) === '') {
            $explanation = 'Risposta corretta per il concetto valutato.';
        }

        $questions[] = [
            'question' => $questiontext,
            'options' => $options,
            'correct_index' => $correct,
            'skill' => $skill,
            'explanation' => $explanation,
        ];
    }

    $oldquiz = json_decode((string)$assessment->quizjson, true);
    if (!is_array($oldquiz)) {
        $oldquiz = [];
    }

    $oldquiz['title'] = $title;
    $oldquiz['topic'] = $focus !== '' ? $focus : ($oldquiz['topic'] ?? 'Assessment');
    $oldquiz['type'] = (string)$assessment->assessmenttype;
    $oldquiz['questions'] = $questions;

    $assessment->title = $title;
    $assessment->focus = $focus;
    $assessment->difficulty = $difficulty;
    $assessment->visible = $visible ? 1 : 0;
    $assessment->quizjson = json_encode($oldquiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $assessment->timemodified = time();

    $DB->update_record('local_aiskillnav_assessment', $assessment);

    if ($resetattempts) {
        $DB->delete_records('local_aiskillnav_ass_att', ['assessmentid' => $assessment->id]);
    }

    $message = $resetattempts
        ? 'Assessment updated. Previous student attempts were reset.'
        : 'Assessment updated.';
}

if ($action === 'generate') {
    require_sesskey();

    $type = optional_param('assessmenttype', 'pre', PARAM_ALPHA);
    $type = in_array($type, ['pre', 'final'], true) ? $type : 'pre';

    $title = required_param('title', PARAM_TEXT);
    $focus = optional_param('focus', '', PARAM_TEXT);
    $difficulty = optional_param('difficulty', 'medium', PARAM_ALPHA);
    $visible = optional_param('visible', 0, PARAM_BOOL);

    $readablematerials = local_aiskillnavigator_material_source_get_readable_materials($courseid);
    $embeddingservice = new embedding_service();

    $contexttext = '';
    $selectedmaterials = [];
    $recordmaterialids = [];

    if ($type === 'pre') {
        // Initial diagnostic quiz: generated WITHOUT teacher materials and WITHOUT RAG.
        // This is enforced server-side, even if hidden material fields are submitted by the browser.
        $sourcemode = 'manual';
        $selectedmaterialids = [];
    } else {
        // Final test: must be grounded on teacher materials.
        $sourcemode = local_aiskillnavigator_material_source_mode_from_request(0);
        $selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);

        if ($sourcemode === 'manual') {
            $sourcemode = 'all';
            $selectedmaterialids = [];
        }

        if (empty($readablematerials)) {
            $error = 'Final test cannot be generated yet: upload at least one readable teacher material first.';
        }

        if ($error === '' && $sourcemode === 'selected' && empty($selectedmaterialids)) {
            $error = 'Final test requires teacher materials: select at least one material or use all course materials.';
        }

        if ($error === '') {
            $selectedmaterials = local_aiskillnavigator_material_source_selected_materials(
                $readablematerials,
                $sourcemode,
                $selectedmaterialids
            );

            if (empty($selectedmaterials)) {
                $error = 'Final test requires readable teacher materials. Upload or select at least one material with extracted text.';
            }
        }

        if ($error === '') {
            $recordmaterialids = array_values(array_map(static function($material): int {
                return (int) $material->id;
            }, $selectedmaterials));

            $totalchunks = $embeddingservice->count_indexed_chunks($courseid);

            if ($totalchunks > 0) {
                $query = trim($focus) !== '' ? $focus : 'final test course material concepts';
                $results = local_aiskillnavigator_material_source_search(
                    $embeddingservice,
                    $query,
                    $courseid,
                    8,
                    $sourcemode,
                    $selectedmaterialids
                );

                if (!empty($results)) {
                    $contexttext = $embeddingservice->build_context($results, 8000);
                }
            }

            if ($contexttext === '') {
                $contexttext = local_aiskillnavigator_assessment_context_from_materials($selectedmaterials, 8000);
            }

            if (trim($contexttext) === '') {
                $error = 'Final test could not be generated because the selected teacher materials have no usable text.';
            }
        }
    }

    if ($error === '') {
        try {
            [$quiz, $rawresponse] = local_aiskillnavigator_assessment_generate($type, $focus, $difficulty, $contexttext);
        } catch (Throwable $e) {
            $quiz = null;
            $error = 'AI generation failed: ' . $e->getMessage();
        }
    }

    if ($error === '') {
        if ($quiz === null) {
            $error = 'The AI response could not be parsed as a valid assessment JSON.';
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $USER->id;
            $record->title = $title;
            $record->assessmenttype = $type;
            $record->focus = $focus;
            $record->difficulty = $difficulty;
            $record->quizjson = json_encode($quiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record->sourcemode = $type === 'pre' ? 'manual' : $sourcemode;
            $record->materialids = json_encode($type === 'pre' ? [] : $recordmaterialids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record->visible = $visible ? 1 : 0;
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('local_aiskillnav_assessment', $record);

            $message = $type === 'final'
                ? 'Final test generated from teacher materials and saved.'
                : 'Initial diagnostic quiz generated without teacher materials and saved.';
        }
    }
}

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials($courseid);
$embeddingservice = new embedding_service();

$sourcemode = local_aiskillnavigator_material_source_mode_from_request(0);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);

$assessments = $DB->get_records(
    'local_aiskillnav_assessment',
    ['courseid' => $courseid],
    'timecreated DESC'
);

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'Teacher diagnostic assessments');

echo html_writer::tag(
    'p',
    'Create an initial diagnostic quiz before using teacher materials and a final test grounded on teacher materials after the lesson or module.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($action === 'edit') {
    $id = required_param('id', PARAM_INT);
    $editassessment = $DB->get_record('local_aiskillnav_assessment', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
    $editquiz = json_decode((string)$editassessment->quizjson, true);

    if (!is_array($editquiz) || empty($editquiz['questions']) || !is_array($editquiz['questions'])) {
        echo html_writer::div('This assessment cannot be edited because its JSON structure is invalid.', 'alert alert-danger');
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', ['courseid' => $courseid]),
            'Back to assessments',
            ['class' => 'btn btn-secondary']
        );
        echo html_writer::end_div();
        echo local_aisn_back_to_course_autofix((int)$courseid);
        if (function_exists('local_aisn_ai_output_formatter_assets')) {
            echo local_aisn_ai_output_formatter_assets();
        }
        echo $OUTPUT->footer();
        exit;
    }

    local_aiskillnavigator_assessment_render_edit_form($editassessment, $editquiz, $courseid);

    echo html_writer::end_div();
    echo local_aisn_back_to_course_autofix((int)$courseid);
    if (function_exists('local_aisn_ai_output_formatter_assets')) {
        echo local_aisn_ai_output_formatter_assets();
    }
    echo $OUTPUT->footer();
    exit;
}

if ($message !== '') {
    echo html_writer::div(s($message), 'alert alert-success');
}

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');

    if ($rawresponse !== '') {
        echo html_writer::tag('pre', s($rawresponse), [
            'style' => 'white-space: pre-wrap; background:#f8f9fa; padding:12px; border-radius:8px;',
        ]);
    }
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Generate initial/final assessment');

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php'),
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Assessment title', ['for' => 'title']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'title',
    'id' => 'title',
    'class' => 'form-control',
    'required' => 'required',
    'placeholder' => 'Example: Initial diagnostic quiz - Digital Twin',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Assessment type', ['for' => 'assessmenttype']);
echo html_writer::select(
    [
        'pre' => 'Initial diagnostic quiz / pre-test',
        'final' => 'Final comprehension test / post-test',
    ],
    'assessmenttype',
    'pre',
    false,
    ['class' => 'form-control', 'id' => 'assessmenttype']
);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3', ['id' => 'aisn-final-material-source']);
echo html_writer::tag('div', 'Initial quiz: generated without teacher materials. Final test: generated from teacher materials/RAG.', ['class' => 'alert alert-info py-2']);
echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    $embeddingservice,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    'Material source',
    'Used only for the final test. The initial diagnostic quiz always ignores teacher materials.'
);
echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');
echo html_writer::tag('label', 'Focus / module', ['for' => 'focus']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'focus',
    'id' => 'focus',
    'class' => 'form-control',
    'placeholder' => 'Example: Digital Twin, IoT sensors, AI model evaluation...',
]);
echo html_writer::tag(
    'small',
    'For the initial quiz this defines the prerequisite area; for the final test it focuses retrieval inside teacher materials.',
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
    'medium',
    false,
    ['class' => 'form-control', 'id' => 'difficulty']
);
echo html_writer::end_div();

echo html_writer::start_div('form-check mt-3');
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'visible', 'value' => 0]);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'visible',
    'id' => 'visible',
    'class' => 'form-check-input',
    'value' => 1,
    'checked' => 'checked',
]);
echo html_writer::tag('label', 'Publish immediately to students', ['for' => 'visible', 'class' => 'form-check-label']);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate and save assessment',
]);

echo html_writer::end_tag('form');

echo html_writer::script("
(function() {
    var type = document.getElementById('assessmenttype');
    var box = document.getElementById('aisn-final-material-source');
    if (!type || !box) {
        return;
    }

    function syncAssessmentMaterialPolicy() {
        if (type.value === 'pre') {
            box.style.display = 'none';
        } else {
            box.style.display = '';
        }
    }

    type.addEventListener('change', syncAssessmentMaterialPolicy);
    syncAssessmentMaterialPolicy();
})();
");

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Saved assessments');

if (empty($assessments)) {
    echo html_writer::div('No diagnostic assessments created yet.', 'alert alert-info');
} else {
    foreach ($assessments as $assessment) {
        $attempts = $DB->get_records(
            'local_aiskillnav_ass_att',
            ['assessmentid' => $assessment->id],
            'percentage DESC, timecreated ASC'
        );

        $count = count($attempts);
        $sum = 0;
        $high = 0;
        $low = 0;

        foreach ($attempts as $attempt) {
            $sum += (int) $attempt->percentage;

            if ((int) $attempt->percentage >= 75) {
                $high++;
            }

            if ((int) $attempt->percentage < 50) {
                $low++;
            }
        }

        $avg = $count > 0 ? round($sum / $count, 1) : 0;
echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');

        echo html_writer::tag(
            'h4',
            s($assessment->title) . ' ' . local_aiskillnavigator_assessment_badge((string) $assessment->assessmenttype)
        );

        echo html_writer::tag(
            'p',
            'Focus: ' . s($assessment->focus !== '' ? $assessment->focus : 'General course materials')
            . ' | Difficulty: ' . s($assessment->difficulty)
            . ' | Status: ' . ($assessment->visible ? 'Published' : 'Hidden')
            . ' | Created: ' . userdate($assessment->timecreated),
            ['class' => 'text-muted']
        );

        echo html_writer::start_div('row mb-3');

        echo html_writer::div(
            html_writer::tag('strong', (string) $count) . html_writer::empty_tag('br') . 'Attempts',
            'col-md-3 alert alert-secondary'
        );

        echo html_writer::div(
            html_writer::tag('strong', (string) $avg . '%') . html_writer::empty_tag('br') . 'Average score',
            'col-md-3 alert alert-info'
        );

        echo html_writer::div(
            html_writer::tag('strong', (string) $high) . html_writer::empty_tag('br') . (
                $assessment->assessmenttype === 'pre'
                    ? 'Potential expert students'
                    : 'Strong final results'
            ),
            'col-md-3 alert alert-success'
        );

        echo html_writer::div(
            html_writer::tag('strong', (string) $low) . html_writer::empty_tag('br') . 'Students below 50%',
            'col-md-3 alert alert-warning'
        );

        echo html_writer::end_div();
        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', [
                'courseid' => $courseid,
                'action' => 'edit',
                'id' => $assessment->id,
            ]),
            'Edit test',
            ['class' => 'btn btn-outline-primary btn-sm mr-2']
        );

echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', [
                'courseid' => $courseid,
                'action' => 'toggle',
                'id' => $assessment->id,
                'sesskey' => sesskey(),
            ]),
            $assessment->visible ? 'Hide from students' : 'Publish to students',
            ['class' => 'btn btn-outline-secondary btn-sm mr-2']
        );

        echo html_writer::link(
            new moodle_url('/local/aiskillnavigator/pages/teacher_assessments.php', [
                'courseid' => $courseid,
                'action' => 'delete',
                'id' => $assessment->id,
                'sesskey' => sesskey(),
            ]),
            'Delete',
            ['class' => 'btn btn-outline-danger btn-sm']
        );

        if (!empty($attempts)) {
            echo html_writer::tag('h5', 'Student results', ['class' => 'mt-4']);
            echo html_writer::start_tag('table', ['class' => 'table table-sm table-striped']);
            echo html_writer::start_tag('thead');
            echo html_writer::tag('tr',
                html_writer::tag('th', 'Student')
                . html_writer::tag('th', 'Score')
                . html_writer::tag('th', 'Percentage')
                . html_writer::tag('th', 'Submitted')
            );
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');

            foreach ($attempts as $attempt) {
                $student = $DB->get_record('user', ['id' => $attempt->userid], 'id, firstname, lastname, email');
                $studentname = $student ? fullname($student) : 'User ' . $attempt->userid;

                echo html_writer::tag('tr',
                    html_writer::tag('td', s($studentname))
                    . html_writer::tag('td', (int) $attempt->score . '/' . (int) $attempt->maxscore)
                    . html_writer::tag('td', (int) $attempt->percentage . '%')
                    . html_writer::tag('td', userdate($attempt->timecreated))
                );
            }

            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo html_writer::div(
    html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Back to course', ['class' => 'btn btn-secondary'])
);

echo html_writer::end_div();



echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/gap_analysis.php', ['courseid' => $courseid]),
        'Open AI learning-gap analysis',
        ['class' => 'btn btn-outline-primary mt-3']
    )
);
echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();

