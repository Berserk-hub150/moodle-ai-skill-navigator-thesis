<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\embedding_service;
use local_aiskillnavigator\service\real_ai_service;

global $PAGE, $OUTPUT, $DB;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);

require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/scenariogenerator.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('scenariogenerator', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('scenariogenerator', 'local_aiskillnavigator'));

$topic = optional_param('topic', 'Digital Twin and IoT', PARAM_TEXT);
$environment = optional_param('environment', 'Smart Factory', PARAM_TEXT);

// -1 = manual topic.
//  0 = all readable teacher materials.
// >0 = selected teacher material.
$materialid = optional_param('materialid', -1, PARAM_INT);

$generate = optional_param('generate', 0, PARAM_INT);

$result = '';
$selectedmaterials = [];
$warning = '';
$debugmessage = '';
$ragdebug = '';
$ragsources = [];

function local_aiskillnavigator_scenario_material_short_title(stdClass $material): string {
    $title = trim((string) ($material->title ?? 'Materiale senza titolo'));

    if ($title === '') {
        $title = 'Materiale senza titolo';
    }

    $contentlength = strlen((string) ($material->content ?? ''));

    return $title . ' (' . $contentlength . ' chars)';
}

function local_aiskillnavigator_scenario_get_readable_materials(int $courseid): array {
    global $DB;

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

    return $readablematerials;
}

function local_aiskillnavigator_scenario_select_materials(array $readablematerials, int $materialid): array {
    if ($materialid === -1) {
        return [];
    }

    if ($materialid > 0 && isset($readablematerials[$materialid])) {
        return [$readablematerials[$materialid]];
    }

    return array_values($readablematerials);
}

$embeddingservice = new embedding_service();
$totalchunks = $embeddingservice->count_indexed_chunks($courseid);

$readablematerials = local_aiskillnavigator_scenario_get_readable_materials($courseid);
$selectedmaterials = local_aiskillnavigator_scenario_select_materials($readablematerials, $materialid);

if ($generate === 1) {
    $service = new real_ai_service();

    if ($materialid === -1) {
        $debugmessage = 'Generation triggered in manual topic mode.';
        $result = $service->generate_xr_scenario($topic, $environment);
    } else if ($totalchunks > 0) {
        $debugmessage = 'Generation triggered in RAG teacher materials mode.';
        $searchquery = $topic !== '' ? $topic : 'virtual learning scenario based on course materials';
        $searchmaterialid = $materialid > 0 ? $materialid : 0;
        $results = $embeddingservice->search($searchquery, $courseid, 6, $searchmaterialid);

        if (!empty($results)) {
            $ragcontext = $embeddingservice->build_context($results, 7500);
            $result = $service->generate_xr_scenario_with_rag_context($topic, $environment, $ragcontext);
            $ragdebug = count($results) . ' RAG chunks retrieved, top similarity: ' . $results[0]->similarity;

            foreach ($results as $ragresult) {
                $ragsources[$ragresult->title . ' — chunk ' . (((int) $ragresult->chunkindex) + 1)] = $ragresult->similarity;
            }
        } else if (empty($selectedmaterials)) {
            $warning = 'Non sono stati trovati chunk RAG per questo focus. Usa Manual topic only oppure carica materiali in Teacher Materials.';
        } else {
            $warning = 'No RAG chunks found for this focus. Falling back to full material context.';
            $result = $service->generate_xr_scenario_from_course_materials($topic, $environment, $selectedmaterials);
        }
    } else {
        $debugmessage = 'Generation triggered in teacher materials mode without RAG index.';

        if (empty($selectedmaterials)) {
            $warning = 'Non sono stati trovati materiali leggibili del docente. Usa Manual topic only oppure carica materiali in Teacher Materials.';
        } else {
            $warning = 'Teacher materials exist but are not indexed for RAG yet. Falling back to full material context.';
            $result = $service->generate_xr_scenario_from_course_materials($topic, $environment, $selectedmaterials);
        }
    }

    if (trim($result) === '' && $warning === '') {
        $warning = 'La generazione è partita, ma il servizio AI ha restituito una risposta vuota.';
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('scenariogenerator', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Generate Virtual Worlds training scenarios from a manual topic or from RAG-retrieved teacher material chunks.',
    ['class' => 'lead']
);

echo html_writer::tag(
    'p',
    'Course: ' . s($course->fullname),
    ['class' => 'text-muted']
);

if ($totalchunks > 0) {
    echo html_writer::div(
        'RAG index active: ' . $totalchunks . ' chunks indexed. Scenario generation can use semantic retrieval.',
        'alert alert-success'
    );
} else if (empty($readablematerials)) {
    echo html_writer::div(
        'No readable teacher materials found yet. You can still generate scenarios from a manual topic.',
        'alert alert-warning'
    );
} else {
    echo html_writer::div(
        'Readable teacher materials available: ' . count($readablematerials) . ', but no RAG chunks are indexed yet. Re-index materials from Teacher Materials.',
        'alert alert-warning'
    );
}

if ($warning !== '') {
    echo html_writer::div(s($warning), 'alert alert-warning');
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Generate a new XR scenario');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/scenariogenerator.php'),
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
    $materialoptions[(int) $material->id] = local_aiskillnavigator_scenario_material_short_title($material) . ' — RAG chunks: ' . $chunks;
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
    'Choose Manual topic only for any generic scenario, or choose RAG mode for a grounded course scenario based on indexed material chunks.',
    ['class' => 'form-text text-muted']
);

echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');

echo html_writer::tag(
    'label',
    get_string('scenario_topic', 'local_aiskillnavigator'),
    ['for' => 'topic']
);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
    'placeholder' => 'Example: Digital Twin, cybersecurity, emergency training, One Piece...',
]);

echo html_writer::tag(
    'small',
    'With Manual topic only this is the scenario topic. With teacher materials this is an optional focus inside the selected materials.',
    ['class' => 'form-text text-muted']
);

echo html_writer::end_div();

echo html_writer::start_div('form-group mt-3');

echo html_writer::tag('label', 'Virtual environment', ['for' => 'environment']);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'environment',
    'id' => 'environment',
    'class' => 'form-control',
    'value' => s($environment),
    'placeholder' => 'Example: Smart Factory, Virtual Classroom, Cyber Range, Hospital Simulation...',
]);

echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate scenario',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($generate === 1 && $debugmessage !== '') {
    echo html_writer::div(s($debugmessage), 'alert alert-secondary');
}

if ($result !== '') {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body ai-scenario-card');

    echo html_writer::tag('h3', 'Generated scenario');

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

    $formattedresult = format_text(
        $result,
        FORMAT_MARKDOWN,
        [
            'context' => $context,
            'trusted' => false,
            'noclean' => false,
            'filter' => true,
        ]
    );

    echo html_writer::div($formattedresult, 'ai-scenario-content');

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php', ['courseid' => $courseid]),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    )
);

echo html_writer::end_div();

echo html_writer::tag('style', '
.ai-scenario-card {
    font-size: 1rem;
    line-height: 1.55;
}

.ai-scenario-content h2,
.ai-scenario-content h3,
.ai-scenario-content h4 {
    margin-top: 1.4rem;
    margin-bottom: 0.7rem;
}

.ai-scenario-content ul,
.ai-scenario-content ol {
    margin-bottom: 1rem;
}

.ai-scenario-content code {
    background: #f3f4f6;
    padding: 2px 4px;
    border-radius: 4px;
}
');

echo $OUTPUT->footer();