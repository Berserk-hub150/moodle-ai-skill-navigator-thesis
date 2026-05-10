<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

require_login();

global $PAGE, $OUTPUT, $DB;

$context = context_system::instance();

require_capability('local/aiskillnavigator:viewstudent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/course_tutor.php'));
$PAGE->set_title('Course AI Tutor');
$PAGE->set_heading('Course AI Tutor');

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$question = optional_param('question', '', PARAM_TEXT);

$answer = '';
$selectedmaterials = [];
$warning = '';

function local_aiskillnavigator_is_summary_question(string $question): bool {
    $q = core_text::strtolower($question);

    $needles = [
        'riassumi',
        'riassunto',
        'sintesi',
        'concetti principali',
        'slide',
        'materiale',
        'materiali',
        'lezione',
        'spiegami tutto',
        'cosa dicono',
    ];

    foreach ($needles as $needle) {
        if (strpos($q, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function local_aiskillnavigator_score_material(stdClass $material, string $question): int {
    $question = core_text::strtolower($question);
    $title = core_text::strtolower($material->title ?? '');
    $content = core_text::strtolower($material->content ?? '');

    $words = preg_split('/[^\p{L}\p{N}]+/u', $question);
    $score = 0;

    foreach ($words as $word) {
        $word = trim($word);

        if (core_text::strlen($word) < 4) {
            continue;
        }

        if (strpos($title, $word) !== false) {
            $score += 4;
        }

        if (strpos($content, $word) !== false) {
            $score += 1;
        }
    }

    return $score;
}

function local_aiskillnavigator_material_preview(string $content, int $limit = 1200): string {
    $preview = trim($content);
    $preview = preg_replace('/[ \t]+/', ' ', $preview);
    $preview = preg_replace("/\n{3,}/", "\n\n", $preview);

    if (core_text::strlen($preview) > $limit) {
        $preview = core_text::substr($preview, 0, $limit) . '...';
    }

    return $preview;
}

if ($question !== '') {
    $allmaterials = $DB->get_records(
        'local_aiskillnav_material',
        ['courseid' => $courseid],
        'timecreated ASC'
    );

    $readablematerials = [];

    foreach ($allmaterials as $material) {
        $content = trim((string) ($material->content ?? ''));

        if ($content !== '') {
            $readablematerials[] = $material;
        }
    }

    if (empty($readablematerials)) {
        $warning = 'Sono presenti materiali, ma il testo estratto Ã¨ vuoto. Probabilmente le slide contengono immagini/testo non selezionabile.';
    } else if (local_aiskillnavigator_is_summary_question($question)) {
        $selectedmaterials = array_slice($readablematerials, 0, 10);
    } else {
        $scored = [];

        foreach ($readablematerials as $material) {
            $score = local_aiskillnavigator_score_material($material, $question);

            if ($score > 0) {
                $scored[] = [
                    'score' => $score,
                    'material' => $material,
                ];
            }
        }

        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        foreach (array_slice($scored, 0, 5) as $item) {
            $selectedmaterials[] = $item['material'];
        }

        if (empty($selectedmaterials)) {
            $selectedmaterials = array_slice($readablematerials, 0, 5);
        }
    }

    if (!empty($selectedmaterials)) {
        $enhancedquestion = "Domanda dello studente:\n"
            . $question
            . "\n\nISTRUZIONI OBBLIGATORIE:\n"
            . "- Usa SOLO il testo dei materiali forniti.\n"
            . "- Non dare una risposta generica.\n"
            . "- Riporta concetti specifici effettivamente presenti nelle slide/materiali.\n"
            . "- Se devi fare un riassunto, organizza la risposta per punti.\n"
            . "- Quando possibile, indica da quale fonte deriva il concetto.\n"
            . "- Se i materiali sono poveri o non bastano, dillo chiaramente.";

        $service = new real_ai_service();
        $answer = $service->ask_with_course_materials($enhancedquestion, $selectedmaterials);
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'Course AI Tutor');

echo html_writer::tag(
    'p',
    'Ask questions about the teacher materials saved in the course knowledge base.',
    ['class' => 'lead']
);

$materialcount = $DB->count_records('local_aiskillnav_material', ['courseid' => $courseid]);

echo html_writer::div(
    'Available teacher materials: ' . $materialcount,
    $materialcount > 0 ? 'alert alert-info' : 'alert alert-warning'
);

if ($warning !== '') {
    echo html_writer::div(s($warning), 'alert alert-warning');
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/course_tutor.php'),
    'class' => 'mb-4',
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $courseid,
]);

echo html_writer::start_div('form-group');

echo html_writer::tag('label', 'Student question', ['for' => 'question']);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'question',
    'id' => 'question',
    'class' => 'form-control',
    'value' => s($question),
    'placeholder' => 'Example: Riassumi i concetti principali delle slide caricate dal docente',
]);

echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-2',
    'value' => 'Ask course AI',
]);

echo html_writer::end_tag('form');

if ($answer !== '') {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body ai-answer-card');

    echo html_writer::tag('h3', 'AI answer grounded on teacher materials');

    $formattedanswer = format_text(
        $answer,
        FORMAT_MARKDOWN,
        [
            'context' => $context,
            'trusted' => false,
            'noclean' => false,
            'filter' => true,
        ]
    );

    echo html_writer::div($formattedanswer, 'ai-answer-content');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

if ($question !== '') {
    echo html_writer::tag('h3', 'Retrieved sources with extracted text', ['class' => 'mt-4']);

    if (empty($selectedmaterials)) {
        echo html_writer::div(
            'No readable source was selected. Check if the uploaded slides contain real selectable text.',
            'alert alert-warning'
        );
    } else {
        foreach ($selectedmaterials as $material) {
            $content = (string) ($material->content ?? '');
            $preview = local_aiskillnavigator_material_preview($content);

            echo html_writer::start_div('card mb-3');
            echo html_writer::start_div('card-body');

            echo html_writer::tag('h5', s($material->title));
            echo html_writer::tag(
                'p',
                'Type: ' . s($material->materialtype) . ' | Extracted characters: ' . strlen($content),
                ['class' => 'text-muted']
            );

            if ($preview === '') {
                echo html_writer::div(
                    'No extracted text available for this source.',
                    'alert alert-danger'
                );
            } else {
                echo html_writer::tag('pre', s($preview), [
                    'style' => 'white-space: pre-wrap; max-height: 260px; overflow:auto; background:#f8f9fa; padding:12px; border-radius:8px;',
                ]);
            }

            echo html_writer::end_div();
            echo html_writer::end_div();
        }
    }
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo html_writer::tag('style', '
.ai-answer-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.ai-answer-content th,
.ai-answer-content td {
    border: 1px solid #d8dee9;
    padding: 8px 10px;
}

.ai-answer-content th {
    background: #f3f6fb;
}

.ai-answer-content h2,
.ai-answer-content h3,
.ai-answer-content h4 {
    margin-top: 1.2rem;
}

.ai-answer-card {
    line-height: 1.55;
}
');

echo $OUTPUT->footer();