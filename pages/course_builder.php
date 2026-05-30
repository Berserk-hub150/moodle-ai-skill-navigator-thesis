<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->dirroot . '/mod/resource/locallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../classes/service/material_extractor.php');
require_once(__DIR__ . '/../classes/service/ai_provider_interface.php');
require_once(__DIR__ . '/../classes/service/ai_provider_factory.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');

global $DB, $PAGE, $OUTPUT, $CFG, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$prompt = optional_param('prompt', '', PARAM_RAW_TRIMMED);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/course_builder.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Course Builder');
$PAGE->set_heading('AI Course Builder');

function local_aisn_p2m_clean(string $text, int $max = 800): string {
    $text = trim(strip_tags($text));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    if ($text === '') {
        return '';
    }

    if (core_text::strlen($text) > $max) {
        return core_text::substr($text, 0, $max) . '...';
    }

    return $text;
}

function local_aisn_p2m_low(string $text): string {
    return core_text::strtolower(trim($text));
}


function local_aisn_p2m_section_text(string $text): string {
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xC2\xA0", "\t", "\r", "\n"], ' ', $text);
    $text = str_replace(['Ã¢â‚¬Å“', 'Ã¢â‚¬Â', 'Ã¢â‚¬Å¾', 'Ã‚Â«', 'Ã‚Â»', 'Ã¢â‚¬Ëœ', 'Ã¢â‚¬â„¢', '`'], '"', $text);
    $text = preg_replace('/\s+/u', ' ', (string)$text);
    $text = trim((string)$text);
    $text = trim($text, " \t\n\r\0\x0B\"'.,:;()[]{}");

    $text = preg_replace('/^(?:la|il|lo|una|un)\s+/iu', '', (string)$text);
    $text = preg_replace('/^(?:sezione|sezioni|section)\s+/iu', '', (string)$text);
    $text = preg_replace('/^(?:numero|num\.?|n\.?|#)\s*/iu', '', (string)$text);
    $text = preg_replace('/^(?:chiamata|dal titolo|intitolata|nome)\s+/iu', '', (string)$text);
    $text = preg_replace('/\s+(?:del corso|nel corso|corrente)$/iu', '', (string)$text);

    $text = trim((string)$text);
    $text = trim($text, " \t\n\r\0\x0B\"'.,:;()[]{}");
    $text = preg_replace('/\s+/u', ' ', (string)$text);

    return trim((string)$text);
}

function local_aisn_p2m_section_key(string $text): string {
    $text = local_aisn_p2m_low(local_aisn_p2m_section_text($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$text);
    return trim((string)$text);
}

function local_aisn_p2m_section_tokens(string $text): array {
    $text = local_aisn_p2m_low(local_aisn_p2m_section_text($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$text);
    $parts = preg_split('/\s+/u', trim((string)$text));
    return array_values(array_filter((array)$parts, function ($p) {
        return core_text::strlen((string)$p) >= 2;
    }));
}

function local_aisn_p2m_all_tokens_match(array $needles, string $haystack): bool {
    if (empty($needles)) {
        return false;
    }

    $haystack = local_aisn_p2m_low($haystack);

    foreach ($needles as $token) {
        if (!str_contains($haystack, local_aisn_p2m_low((string)$token))) {
            return false;
        }
    }

    return true;
}

function local_aisn_p2m_clean_section_title(string $title, int $max = 120): string {
    $title = local_aisn_p2m_section_text($title);

    if ($title === '') {
        return 'Nuova sezione';
    }

    if (core_text::strlen($title) > $max) {
        $title = core_text::substr($title, 0, $max);
    }

    return trim($title);
}


function local_aisn_p2m_next_section(int $courseid): int {
    global $DB;
    $max = $DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);
    return ((int)$max) + 1;
}

function local_aisn_p2m_get_section(int $courseid, int $sectionnum): ?stdClass {
    global $DB;
    return $DB->get_record('course_sections', ['course' => $courseid, 'section' => $sectionnum]) ?: null;
}

function local_aisn_p2m_find_section(int $courseid, string $ref): ?stdClass {
    global $DB;

    $ref = local_aisn_p2m_section_text($ref);

    if ($ref === '') {
        return null;
    }

    if (ctype_digit($ref)) {
        return local_aisn_p2m_get_section($courseid, (int)$ref);
    }

    $reflow = local_aisn_p2m_low($ref);
    $refkey = local_aisn_p2m_section_key($ref);
    $reftokens = local_aisn_p2m_section_tokens($ref);
    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

    foreach ($sections as $section) {
        $name = local_aisn_p2m_section_text((string)($section->name ?? ''));

        if ($name !== '' && local_aisn_p2m_low($name) === $reflow) {
            return $section;
        }
    }

    foreach ($sections as $section) {
        $namekey = local_aisn_p2m_section_key((string)($section->name ?? ''));

        if ($namekey !== '' && $refkey !== '' && $namekey === $refkey) {
            return $section;
        }
    }

    foreach ($sections as $section) {
        $name = local_aisn_p2m_section_text((string)($section->name ?? ''));
        $namekey = local_aisn_p2m_section_key($name);

        if ($namekey === '' || $refkey === '') {
            continue;
        }

        if (core_text::strlen($refkey) >= 3 &&
            (str_contains($namekey, $refkey) || str_contains($refkey, $namekey))) {
            return $section;
        }

        if (local_aisn_p2m_all_tokens_match($reftokens, $name)) {
            return $section;
        }
    }

    return null;
}

function local_aisn_p2m_create_section(int $courseid, string $title, string $summary = ''): stdClass {
    global $DB;

    $sectionnum = local_aisn_p2m_next_section($courseid);

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

    $section->name = local_aisn_p2m_clean_section_title($title, 120);
    $section->summary = $summary !== '' ? $summary : '<p>Sezione creata da AI Course Builder tramite prompt docente.</p>';
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 1;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);

    return $DB->get_record('course_sections', ['id' => $section->id]);
}

function local_aisn_p2m_update_section(stdClass $section, string $title = '', string $summary = ''): void {
    global $DB;

    if ($title !== '') {
        $section->name = local_aisn_p2m_clean_section_title($title, 120);
    }

    if ($summary !== '') {
        $section->summary = '<p>' . s(local_aisn_p2m_clean($summary, 1800)) . '</p>';
        $section->summaryformat = FORMAT_HTML;
    }

    $section->timemodified = time();
    $DB->update_record('course_sections', $section);
}

function local_aisn_p2m_set_visibility(stdClass $section, bool $visible): void {
    global $DB;

    $section->visible = $visible ? 1 : 0;
    $section->timemodified = time();

    $DB->update_record('course_sections', $section);
}

function local_aisn_p2m_duplicate_section(int $courseid, stdClass $source, string $newtitle = ''): stdClass {
    $title = $newtitle !== '' ? $newtitle : ((string)$source->name . ' - copia');
    $summary = (string)($source->summary ?? '');

    return local_aisn_p2m_create_section($courseid, $title, $summary);
}

function local_aisn_p2m_move_section(int $courseid, stdClass $section, int $destination): string {
    $course = get_course($courseid);

    if (function_exists('move_section_to')) {
        move_section_to($course, (int)$section->section, $destination);
        rebuild_course_cache($courseid, true);
        return 'spostata';
    }

    return 'move_section_to non disponibile in questa versione Moodle';
}

function local_aisn_p2m_table_exists(string $table): bool {
    global $DB;

    try {
        return in_array($table, $DB->get_tables(false), true);
    } catch (Throwable $e) {
        return false;
    }
}

function local_aisn_p2m_uploaded_files(): array {
    if (empty($_FILES['materials']) || empty($_FILES['materials']['name'])) {
        return [];
    }

    $files = [];
    $names = $_FILES['materials']['name'];

    if (!is_array($names)) {
        if ((int)($_FILES['materials']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $files[] = [
                'name' => $_FILES['materials']['name'],
                'type' => $_FILES['materials']['type'] ?? '',
                'tmp_name' => $_FILES['materials']['tmp_name'],
                'error' => $_FILES['materials']['error'],
                'size' => $_FILES['materials']['size'] ?? 0,
            ];
        }

        return $files;
    }

    foreach ($names as $i => $name) {
        if ((int)($_FILES['materials']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        if (empty($_FILES['materials']['tmp_name'][$i]) || !is_uploaded_file($_FILES['materials']['tmp_name'][$i])) {
            continue;
        }

        $files[] = [
            'name' => $name,
            'type' => $_FILES['materials']['type'][$i] ?? '',
            'tmp_name' => $_FILES['materials']['tmp_name'][$i],
            'error' => $_FILES['materials']['error'][$i],
            'size' => $_FILES['materials']['size'][$i] ?? 0,
        ];
    }

    return $files;
}

function local_aisn_p2m_extract_file_text(array $file): string {
    try {
        $extract = \local_aiskillnavigator\service\material_extractor::extract_from_upload($file);

        if (!empty($extract['success']) && trim((string)($extract['content'] ?? '')) !== '') {
            return trim((string)$extract['content']);
        }
    } catch (Throwable $e) {
        debugging('AI Course Builder file extraction failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    return '';
}

function local_aisn_p2m_save_material_record(int $courseid, int $userid, int $sectionnum, string $title, string $content): int {
    // Source of truth policy:
    // Prompt-to-Moodle must create real Moodle course resources only.
    // The RAG/material table is populated later by course_resource_sync.php from visible Moodle course modules.
    // This prevents duplicate material entries in AI selectors.
    return 0;
}

function local_aisn_p2m_create_resource_from_file(int $courseid, int $sectionnum, array $file, string $resourcename): int {
    global $DB, $USER;

    $moduleid = $DB->get_field('modules', 'id', ['name' => 'resource'], MUST_EXIST);
    $draftitemid = file_get_unused_draft_itemid();
    $usercontext = context_user::instance((int)$USER->id);

    $filename = clean_param((string)$file['name'], PARAM_FILE);
    if ($filename === '') {
        $filename = 'materiale.txt';
    }

    $fs = get_file_storage();
    $fs->create_file_from_pathname([
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftitemid,
        'filepath' => '/',
        'filename' => $filename,
    ], $file['tmp_name']);

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'resource';
    $moduleinfo->module = $moduleid;
    $moduleinfo->course = $courseid;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->visible = 1;
    $moduleinfo->name = local_aisn_p2m_clean($resourcename, 120);
    $moduleinfo->intro = 'Risorsa caricata tramite Prompt-to-Moodle Course Builder.';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->showdescription = 0;
    $moduleinfo->files = $draftitemid;
    $moduleinfo->display = defined('RESOURCELIB_DISPLAY_AUTO') ? RESOURCELIB_DISPLAY_AUTO : 0;
    $moduleinfo->displayoptions = '';
    $moduleinfo->printintro = 0;
    $moduleinfo->printlastmodified = 1;
    $moduleinfo->completion = 0;
    $moduleinfo->availability = null;

    $created = add_moduleinfo($moduleinfo, get_course($courseid));

    return !empty($created->coursemodule) ? (int)$created->coursemodule : 0;
}

function local_aisn_p2m_attach_files_to_section(int $courseid, int $userid, stdClass $section, array $files): array {
    $logs = [];

    foreach ($files as $file) {
        $filename = clean_param((string)$file['name'], PARAM_FILE);
        $resourcename = 'Materiale - ' . ($filename !== '' ? $filename : 'file docente');

        $cmid = local_aisn_p2m_create_resource_from_file($courseid, (int)$section->section, $file, $resourcename);

        $logs[] = 'File "' . $filename . '" aggiunto alla sezione "' . (string)$section->name . '"' .
            ($cmid > 0 ? ' come risorsa Moodle' : '');

        if ($cmid > 0 && function_exists('local_aiskillnavigator_sync_course_resources')) {
            local_aiskillnavigator_sync_course_resources($courseid, $userid, true);
        }
    }

    return $logs;
}

function local_aisn_p2m_lines(string $prompt): array {
    $lines = preg_split('/[\r\n]+/u', $prompt);
    $lines = array_values(array_filter(array_map('trim', (array)$lines)));

    if (count($lines) <= 1) {
        $lines = preg_split('/[.;]+/u', $prompt);
        $lines = array_values(array_filter(array_map('trim', (array)$lines)));
    }

    return $lines;
}


function local_aisn_p2m_strip_outer_quotes(string $value): string {
    $value = trim($value);
    return trim($value, " \t\n\r\0\x0B\"'“”‘’«»");
}


function local_aisn_p2m_extract_target_after_section(string $prompt): ?string {
    $patterns = [
        '/(?:nella|alla|dentro la|sotto la|in)\\s+sezione\\s+["“”\'‘’«»]?([^"“”\'‘’«».:,;\\n\\r]+)["“”\'‘’«»]?/iu',
        '/sezione\\s+["“”\'‘’«»]([^"“”\'‘’«»]+)["“”\'‘’«»]/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            $name = local_aisn_p2m_strip_outer_quotes((string)$matches[1]);
            return $name !== '' ? $name : null;
        }
    }

    return null;
}


function local_aisn_p2m_execute_prompt(int $courseid, string $prompt): array {
    global $DB;

    $norm = local_aisn_p2m_normalize_text($prompt);
    $logs = [];
    $created = [];

    $targetsection = local_aisn_p2m_extract_target_after_section($prompt);
    $targetsectionnum = null;
    if ($targetsection !== null) {
        $targetsectionnum = local_aisn_p2m_find_section_number_by_name($courseid, $targetsection);
        if ($targetsectionnum === null) {
            $logs[] = 'Sezione richiesta non trovata: ' . s($targetsection) . '. Creo il contenuto nella prima sezione disponibile.';
        }
    }

    if (preg_match('/quiz|test|verifica|domande|questionario/i', $norm)) {
        $name = 'Quiz AI - ' . userdate(time(), get_string('strftimedatetimeshort', 'core_langconfig'));
        if (preg_match('/(?:chiamato|nome|titolo)\s+["“”\'‘’«»]([^"“”\'‘’«»]+)["“”\'‘’«»]/iu', $prompt, $matches)) {
            $candidate = local_aisn_p2m_strip_outer_quotes((string)$matches[1]);
            if ($candidate !== '') {
                $name = $candidate;
            }
        }

        $quiz = local_aisn_p2m_create_quiz($courseid, $name, $targetsectionnum);
        $created[] = [
            'type' => 'quiz',
            'name' => $quiz['name'],
            'cmid' => $quiz['cmid'],
            'url' => $quiz['url'],
        ];
        $logs[] = 'Creato quiz: ' . $quiz['name'];
    }

    if (preg_match('/pagina|html|contenuto|risorsa|materiale|spiegazione|testo/i', $norm)) {
        $title = 'Materiale AI - ' . userdate(time(), get_string('strftimedatetimeshort', 'core_langconfig'));
        if (preg_match('/(?:chiamat[ao]|nome|titolo)\s+["“”\'‘’«»]([^"“”\'‘’«»]+)["“”\'‘’«»]/iu', $prompt, $matches)) {
            $candidate = local_aisn_p2m_strip_outer_quotes((string)$matches[1]);
            if ($candidate !== '') {
                $title = $candidate;
            }
        }

        $page = local_aisn_p2m_create_page($courseid, $title, '<p>' . s($prompt) . '</p>', $targetsectionnum);
        $created[] = [
            'type' => 'page',
            'name' => $page['name'],
            'cmid' => $page['cmid'],
            'url' => $page['url'],
        ];
        $logs[] = 'Creata pagina: ' . $page['name'];
    }

    if (empty($created)) {
        $logs[] = 'Nessun comando riconosciuto. Prova con: crea un quiz, crea una pagina, aggiungi materiale HTML.';
    }

    return ['created' => $created, 'logs' => $logs];
}

$result = [];
$error = '';

if ($action === 'build') {
    if (!confirm_sesskey()) {
        $error = 'Sessione non valida. Ricarica la pagina.';
    } else if (trim($prompt) === '') {
        $error = 'Inserisci un prompt.';
    } else {
        try {
            $result = local_aisn_p2m_execute_prompt_ai($courseid, (int)$USER->id, $prompt, local_aisn_p2m_uploaded_files());
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::tag('style', '
.aisn-p2m-hero {
    background: linear-gradient(135deg,#0f6cbf,#2563eb);
    color: #fff;
    border-radius: 24px;
    padding: 26px 30px;
    margin-bottom: 18px;
    box-shadow: 0 18px 40px rgba(15,108,191,.22);
}
.aisn-p2m-hero h2 { margin: 0 0 8px; font-weight: 900; }
.aisn-p2m-card {
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:22px;
    padding:24px;
    box-shadow:0 14px 34px rgba(15,23,42,.07);
    margin-bottom:18px;
}
.aisn-p2m-help {
    background:#eff6ff;
    border:1px solid #bfdbfe;
    border-radius:18px;
    padding:16px;
    margin-bottom:18px;
}
.aisn-p2m-result {
    border-left:6px solid #16a34a;
    background:#f0fdf4;
    border-radius:16px;
    padding:16px;
    margin-bottom:18px;
}
.aisn-p2m-muted { color:#64748b; }
');

echo html_writer::start_div('container-fluid');

echo html_writer::start_div('aisn-p2m-hero');
echo html_writer::tag('h2', 'AI Course Builder');
echo html_writer::tag('p', 'Prompt-to-Moodle AI editor: scrivi una richiesta libera, il modello AI la trasforma in azioni Moodle e il corso cambia davvero.');
echo html_writer::end_div();

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

if (!empty($result)) {
    echo html_writer::start_div('aisn-p2m-result');
    echo html_writer::tag('h3', 'Operazioni eseguite');
    echo html_writer::start_tag('ul');
    foreach ($result as $line) {
        echo html_writer::tag('li', s($line));
    }
    echo html_writer::end_tag('ul');
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Vai al corso e verifica', ['class' => 'btn btn-success']);
    echo html_writer::end_div();
}

echo html_writer::start_div('aisn-p2m-card');
echo html_writer::tag('h3', 'Prompt unico per modificare il corso');
echo html_writer::tag('p', 'Scrivi anche un prompt naturale complesso. Il sistema chiede al modello AI un piano JSON di azioni Moodle e poi lo esegue.', ['class' => 'aisn-p2m-muted']);

echo html_writer::start_div('aisn-p2m-help');
echo html_writer::tag('strong', 'Esempi validi:');
echo html_writer::tag('pre',
'Crea sezione "HTML base"
Crea sezione "Esercizi HTML"
Metti i materiali nella sezione "HTML base"
Nascondi sezione "Esercizi HTML"
Duplica sezione "HTML base" come "HTML base - recupero"
Sposta sezione "HTML base - recupero" dopo sezione "HTML base"
Metti riassunto nella sezione "HTML base": Questa sezione introduce struttura, tag e attributi fondamentali.'
);
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'action' => new moodle_url('/local/aiskillnavigator/pages/course_builder.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'build']);

echo html_writer::tag('label', 'Prompt docente', ['for' => 'prompt']);
echo html_writer::tag('textarea', s($prompt), [
    'id' => 'prompt',
    'name' => 'prompt',
    'class' => 'form-control mb-3',
    'rows' => 10,
    'required' => 'required',
    'placeholder' => 'Esempio: crea sezione "Introduzione HTML"; metti i materiali nella sezione "Introduzione HTML"; nascondi sezione 3;'
]);

echo html_writer::tag('label', 'Materiali da usare nel prompt', ['for' => 'materials']);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'id' => 'materials',
    'name' => 'materials[]',
    'class' => 'form-control mb-3',
    'multiple' => 'multiple',
    'accept' => '.pptx,.txt,.md,.pdf,.docx,.html,.css,.js'
]);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Esegui prompt sul corso Moodle'
]);

echo ' ';
echo html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), 'Torna al corso', ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));
if (function_exists('local_aisn_ai_output_formatter_assets')) { echo local_aisn_ai_output_formatter_assets(); }
echo $OUTPUT->footer();

function local_aisn_p2m_sections_for_ai(int $courseid): array {
    global $DB;

    $rows = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
    $out = [];

    foreach ($rows as $s) {
        $out[] = [
            'number' => (int)$s->section,
            'name' => trim((string)($s->name ?? '')),
            'visible' => !empty($s->visible),
            'summary' => local_aisn_p2m_clean((string)($s->summary ?? ''), 240),
        ];
    }

    return $out;
}

function local_aisn_p2m_files_for_ai(array $files): array {
    $out = [];

    foreach ($files as $f) {
        $out[] = [
            'name' => clean_param((string)($f['name'] ?? 'file'), PARAM_FILE),
            'size' => (int)($f['size'] ?? 0),
            'type' => (string)($f['type'] ?? ''),
        ];
    }

    return $out;
}

function local_aisn_p2m_extract_json_object(string $raw): ?array {
    $raw = trim($raw);
    $raw = preg_replace('/^```json\s*/iu', '', $raw);
    $raw = preg_replace('/^```\s*/iu', '', $raw);
    $raw = preg_replace('/\s*```$/u', '', $raw);

    if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
        $raw = $m[0];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return null;
    }

    if (!isset($data['actions']) || !is_array($data['actions'])) {
        return null;
    }

    return $data;
}

function local_aisn_p2m_ai_plan_actions(int $courseid, string $teacherprompt, array $files): array {
    if (!class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
        return [];
    }

    $sections = local_aisn_p2m_sections_for_ai($courseid);
    $fileinfo = local_aisn_p2m_files_for_ai($files);

    $system = 'You are a strict Moodle course editing planner. Return ONLY valid JSON. No markdown. No explanations.';

    $prompt = "Il docente vuole modificare un corso Moodle usando linguaggio naturale.\n"
        . "Trasforma la richiesta in una lista JSON di azioni operative.\n\n"
        . "SEZIONI ATTUALI DEL CORSO:\n"
        . json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
        . "FILE CARICATI DAL DOCENTE:\n"
        . json_encode($fileinfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
        . "RICHIESTA DOCENTE:\n"
        . $teacherprompt . "\n\n"
        . "AZIONI AMMESSE:\n"
        . "- create_section: {action,title,summary}\n"
        . "- rename_section: {action,target,title}\n"
        . "- update_summary: {action,target,summary}\n"
        . "- hide_section: {action,target}\n"
        . "- show_section: {action,target}\n"
        . "- duplicate_section: {action,target,new_title}\n"
        . "- move_section: {action,target,position,reference}; position puo essere before, after, beginning, end\n"
        . "- attach_files: {action,target,files}; files opzionale, se assente usa tutti i file caricati\n"
        . "- delete_section: {action,target}; solo se il docente chiede esplicitamente elimina/cancella\n\n"
        . "REGOLE:\n"
        . "1. Se il docente chiede un percorso o un modulo, crea sezioni coerenti.\n"
        . "2. Se nomina materiali/slide/file, usa attach_files verso la sezione piu coerente.\n"
        . "3. Se chiede di spostare, nascondere, duplicare o rinominare, genera l'azione corrispondente.\n"
        . "4. Non inventare funzioni non elencate.\n"
        . "5. Restituisci solo questo formato:\n"
        . "{\"actions\":[{\"action\":\"create_section\",\"title\":\"...\",\"summary\":\"...\"}]}";

    try {
        $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
        $raw = $provider->generate($prompt, 1800, $system);
        $data = local_aisn_p2m_extract_json_object($raw);

        if (!$data) {
            debugging('Prompt-to-Moodle AI JSON parse failed. Raw: ' . $raw, DEBUG_DEVELOPER);
            return [];
        }

        return $data['actions'];
    } catch (Throwable $e) {
        debugging('Prompt-to-Moodle AI planner failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
}

function local_aisn_p2m_pick_files(array $files, array $wanted): array {
    if (empty($wanted)) {
        return $files;
    }

    $selected = [];

    foreach ($files as $file) {
        $name = core_text::strtolower(clean_param((string)($file['name'] ?? ''), PARAM_FILE));

        foreach ($wanted as $needle) {
            $needle = core_text::strtolower(trim((string)$needle));

            if ($needle !== '' && str_contains($name, $needle)) {
                $selected[] = $file;
                break;
            }
        }
    }

    return !empty($selected) ? $selected : $files;
}

function local_aisn_p2m_max_section_number(int $courseid): int {
    global $DB;
    return (int)$DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$courseid]);
}

function local_aisn_p2m_find_section_smart(int $courseid, string $target): ?stdClass {
    $target = local_aisn_p2m_section_text($target);

    if ($target === '') {
        return null;
    }

    $low = local_aisn_p2m_low($target);

    if (in_array($low, ['ultima', 'last', 'fine'], true)) {
        return local_aisn_p2m_get_section($courseid, local_aisn_p2m_max_section_number($courseid));
    }

    if (in_array($low, ['prima', 'inizio', 'first'], true)) {
        return local_aisn_p2m_get_section($courseid, 1);
    }

    return local_aisn_p2m_find_section($courseid, $target);
}

function local_aisn_p2m_delete_section_safe(int $courseid, stdClass $section): string {
    if ((int)$section->section <= 0) {
        return 'non cancellata: la sezione 0 non va eliminata';
    }

    $course = get_course($courseid);

    if (function_exists('course_delete_section')) {
        course_delete_section($course, (int)$section->section, true);
        rebuild_course_cache($courseid, true);
        return 'cancellata';
    }

    local_aisn_p2m_set_visibility($section, false);
    return 'course_delete_section non disponibile: sezione nascosta invece di cancellata';
}

function local_aisn_p2m_execute_ai_actions(int $courseid, int $userid, array $actions, array $files): array {
    $logs = [];
    $lastsection = null;

    foreach ($actions as $a) {
        if (!is_array($a)) {
            continue;
        }

        $type = local_aisn_p2m_low((string)($a['action'] ?? ''));

        if ($type === 'create_section') {
            $title = trim((string)($a['title'] ?? 'Nuova sezione'));
            $summary = trim((string)($a['summary'] ?? ''));

            $section = local_aisn_p2m_create_section($courseid, $title, $summary !== '' ? '<p>' . s($summary) . '</p>' : '');
            $lastsection = $section;
            $logs[] = 'AI: creata sezione "' . (string)$section->name . '" (#' . (int)$section->section . ')';
            continue;
        }

        if ($type === 'rename_section') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if ($section) {
                local_aisn_p2m_update_section($section, (string)($a['title'] ?? ''), '');
                $logs[] = 'AI: rinominata sezione "' . (string)$a['target'] . '"';
            } else {
                $logs[] = 'AI: sezione da rinominare non trovata: ' . (string)($a['target'] ?? '');
            }
            continue;
        }

        if ($type === 'update_summary') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if ($section) {
                local_aisn_p2m_update_section($section, '', (string)($a['summary'] ?? ''));
                $logs[] = 'AI: riassunto aggiornato nella sezione "' . (string)$section->name . '"';
            } else {
                $logs[] = 'AI: sezione per riassunto non trovata: ' . (string)($a['target'] ?? '');
            }
            continue;
        }

        if ($type === 'hide_section' || $type === 'show_section') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if ($section) {
                local_aisn_p2m_set_visibility($section, $type === 'show_section');
                $logs[] = 'AI: sezione "' . (string)$section->name . '" ' . ($type === 'show_section' ? 'resa visibile' : 'nascosta');
            } else {
                $logs[] = 'AI: sezione visibilita non trovata: ' . (string)($a['target'] ?? '');
            }
            continue;
        }

        if ($type === 'duplicate_section') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if ($section) {
                $copy = local_aisn_p2m_duplicate_section($courseid, $section, (string)($a['new_title'] ?? ''));
                $lastsection = $copy;
                $logs[] = 'AI: duplicata "' . (string)$section->name . '" in "' . (string)$copy->name . '"';
            } else {
                $logs[] = 'AI: sezione da duplicare non trovata: ' . (string)($a['target'] ?? '');
            }
            continue;
        }

        if ($type === 'move_section') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if (!$section) {
                $logs[] = 'AI: sezione da spostare non trovata: ' . (string)($a['target'] ?? '');
                continue;
            }

            $position = local_aisn_p2m_low((string)($a['position'] ?? 'end'));
            $reference = (string)($a['reference'] ?? '');
            $dest = local_aisn_p2m_max_section_number($courseid);

            if ($position === 'beginning') {
                $dest = 1;
            } else if ($position === 'before' || $position === 'after') {
                $target = local_aisn_p2m_find_section_smart($courseid, $reference);
                if ($target) {
                    $dest = (int)$target->section + ($position === 'after' ? 1 : 0);
                }
            }

            $logs[] = 'AI: spostamento "' . (string)$section->name . '": ' . local_aisn_p2m_move_section($courseid, $section, $dest);
            continue;
        }

        if ($type === 'attach_files') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if (!$section && $lastsection) {
                $section = $lastsection;
            }

            if (!$section) {
                $logs[] = 'AI: nessuna sezione target trovata per i file.';
                continue;
            }

            $wanted = isset($a['files']) && is_array($a['files']) ? $a['files'] : [];
            $selected = local_aisn_p2m_pick_files($files, $wanted);

            if (empty($selected)) {
                $logs[] = 'AI: nessun file caricato da collegare.';
                continue;
            }

            $logs = array_merge($logs, local_aisn_p2m_attach_files_to_section($courseid, $userid, $section, $selected));
            continue;
        }

        if ($type === 'delete_section') {
            $section = local_aisn_p2m_find_section_smart($courseid, (string)($a['target'] ?? ''));

            if ($section) {
                $logs[] = 'AI: sezione "' . (string)$section->name . '" ' . local_aisn_p2m_delete_section_safe($courseid, $section);
            } else {
                $logs[] = 'AI: sezione da eliminare non trovata: ' . (string)($a['target'] ?? '');
            }
            continue;
        }

        if ($type !== '') {
            $logs[] = 'AI: azione non supportata ignorata: ' . $type;
        }
    }

    rebuild_course_cache($courseid, true);

    return $logs;
}

function local_aisn_p2m_execute_prompt_ai(int $courseid, int $userid, string $prompt, array $files): array {
    $actions = local_aisn_p2m_ai_plan_actions($courseid, $prompt, $files);

    if (!empty($actions)) {
        $logs = ['AI planner attivo: prompt convertito in ' . count($actions) . ' azioni Moodle.'];
        return array_merge($logs, local_aisn_p2m_execute_ai_actions($courseid, $userid, $actions, $files));
    }

    $fallback = local_aisn_p2m_execute_prompt($courseid, $userid, $prompt, $files);
    array_unshift($fallback, 'AI planner non disponibile o JSON non valido: usato fallback a comandi semplici.');
    return $fallback;
}

