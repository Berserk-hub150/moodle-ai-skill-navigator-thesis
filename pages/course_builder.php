<?php

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');

global $DB, $PAGE, $OUTPUT, $CFG;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$prompt = optional_param('prompt', '', PARAM_RAW_TRIMMED);
$numsections = optional_param('numsections', 4, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/course_builder.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Course Builder');
$PAGE->set_heading('AI Course Builder');

function local_aisn_cb_short(string $text, string $fallback): string {
    $text = trim(strip_tags($text));
    $text = preg_replace('/\s+/u', ' ', $text);
    if ($text === '') {
        $text = $fallback;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 230);
    }
    return substr($text, 0, 230);
}

function local_aisn_cb_html(string $text): string {
    return '<p>' . s(trim($text)) . '</p>';
}

function local_aisn_cb_plan(string $prompt, int $numsections): array {
    $numsections = max(2, min(8, $numsections));
    $parts = preg_split('/[\r\n,.;:]+/u', $prompt);
    $parts = array_values(array_filter(array_map('trim', (array)$parts), function($x) {
        return strlen($x) > 4;
    }));

    $defaults = [
        'Introduzione e obiettivi',
        'Concetti fondamentali',
        'Esempi guidati',
        'Laboratorio pratico',
        'Simulazione o attività interattiva',
        'Verifica formativa',
        'Ripasso adattivo',
        'Valutazione finale'
    ];

    $sections = [];

    for ($i = 0; $i < $numsections; $i++) {
        $topic = $parts[$i] ?? $defaults[$i] ?? ('Modulo ' . ($i + 1));
        $sections[] = [
            'title' => ($i + 1) . '. ' . local_aisn_cb_short($topic, $defaults[$i] ?? ('Modulo ' . ($i + 1))),
            'summary' => 'Sezione generata dal prompt del docente. Focus didattico: ' . $topic . '. Attività suggerite: spiegazione, esercizio pratico, controllo di comprensione e collegamento con gli strumenti AI del plugin.'
        ];
    }

    return $sections;
}

function local_aisn_cb_next_section(int $courseid): int {
    global $DB;
    $max = $DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);
    return ((int)$max) + 1;
}

function local_aisn_cb_create_section(int $courseid, int $sectionnum, string $title, string $summary): void {
    global $DB;

    $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $sectionnum]);

    if (!$section) {
        if (function_exists('course_create_section')) {
            $section = course_create_section($courseid, $sectionnum);
        } else {
            $section = new stdClass();
            $section->course = $courseid;
            $section->section = $sectionnum;
            $section->name = '';
            $section->summary = '';
            $section->summaryformat = FORMAT_HTML;
            $section->sequence = '';
            $section->visible = 1;
            $section->availability = null;
            $section->timemodified = time();
            $section->id = $DB->insert_record('course_sections', $section);
        }
    }

    $section->name = local_aisn_cb_short($title, 'AI generated section');
    $section->summary = local_aisn_cb_html($summary);
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 1;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
}

$result = null;
$error = '';

if ($action === 'build') {
    if (!confirm_sesskey()) {
        $error = 'Sessione non valida. Ricarica la pagina.';
    } else if (trim($prompt) === '') {
        $error = 'Inserisci un prompt.';
    } else {
        $start = local_aisn_cb_next_section($courseid);
        $sections = local_aisn_cb_plan($prompt, $numsections);

        foreach ($sections as $i => $section) {
            local_aisn_cb_create_section($courseid, $start + $i, $section['title'], $section['summary']);
        }

        rebuild_course_cache($courseid, true);

        $result = count($sections);
    }
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::tag('style', '
.aisn-cb-hero{background:linear-gradient(135deg,#0f6cbf,#2563eb);color:#fff;border-radius:24px;padding:26px 30px;margin-bottom:18px;box-shadow:0 18px 40px rgba(15,108,191,.22)}
.aisn-cb-hero h2{margin:0 0 8px;font-weight:900}
.aisn-cb-hero p{margin:0;opacity:.92}
.aisn-cb-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 14px 34px rgba(15,23,42,.07)}
.aisn-cb-result{border-left:6px solid #16a34a;background:#f0fdf4;border-radius:16px;padding:16px;margin-bottom:18px}
');

echo html_writer::start_div('container-fluid');

echo html_writer::start_div('aisn-cb-hero');
echo html_writer::tag('h2', 'AI Course Builder');
echo html_writer::tag('p', 'Scrivi un prompt: il plugin crea vere sezioni Moodle nel corso corrente.');
echo html_writer::end_div();

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

if ($result !== null) {
    echo html_writer::start_div('aisn-cb-result');
    echo html_writer::tag('h3', 'Corso modificato');
    echo html_writer::tag('p', 'Sezioni Moodle create: ' . (int)$result);
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Vai al corso e verifica le nuove sezioni', ['class' => 'btn btn-success']);
    echo html_writer::end_div();
}

echo html_writer::start_div('aisn-cb-card');
echo html_writer::tag('h3', 'Prompt per modificare il corso');
echo html_writer::tag('p', 'Esempio: Crea un percorso su funzioni matematiche con teoria, esempi guidati, esercizi, GeoGebra e verifica finale.', ['class' => 'text-muted']);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/local/aiskillnavigator/pages/course_builder.php', ['courseid' => $courseid])]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'build']);

echo html_writer::tag('label', 'Prompt docente', ['for' => 'prompt']);
echo html_writer::tag('textarea', s($prompt), ['id' => 'prompt', 'name' => 'prompt', 'class' => 'form-control mb-3', 'rows' => 8, 'required' => 'required']);

echo html_writer::tag('label', 'Numero sezioni da creare', ['for' => 'numsections']);
echo html_writer::select([2=>'2 sezioni',3=>'3 sezioni',4=>'4 sezioni',5=>'5 sezioni',6=>'6 sezioni',8=>'8 sezioni'], 'numsections', $numsections, false, ['class' => 'form-control mb-3', 'id' => 'numsections']);

echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => 'Modifica corso Moodle']);
echo ' ';
echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Torna al corso', ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();