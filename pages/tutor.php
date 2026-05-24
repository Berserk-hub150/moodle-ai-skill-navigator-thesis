<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/markdown_table_formatter.php');
local_aisn_start_mdtable_formatter();
require_once(__DIR__ . '/../includes/ai_output_formatter.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/ui_style_helper.php');
require_once(__DIR__ . '/../includes/course_resource_sync.php');
require_once(__DIR__ . '/../includes/material_source_helper.php');
require_once(__DIR__ . '/../includes/tutor_signal_helper.php');
require_once(__DIR__ . '/../includes/ai_output_helper.php');

global $DB, $PAGE, $OUTPUT, $USER;

$courseid = optional_param('courseid', optional_param('id', SITEID, PARAM_INT), PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewstudent', $context);

if (function_exists('local_aiskillnavigator_sync_course_resources')) {
    local_aiskillnavigator_sync_course_resources((int)$courseid, (int)$USER->id, false);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));
$PAGE->set_title('AI Tutor');
$PAGE->set_heading('AI Tutor');

function local_aiskillnavigator_tutor_limit_context(string $text, int $limit = 9000): string {
    $text = local_aiskillnavigator_fix_mojibake(trim($text));

    if (\core_text::strlen($text) > $limit) {
        return \core_text::substr($text, 0, $limit) . "\n[Content truncated]";
    }

    return $text;
}

function local_aiskillnavigator_tutor_call_ai(string $prompt, string $systemprompt): string {
    try {
        if (class_exists('\local_aiskillnavigator\service\ai_provider_factory')) {
            $provider = \local_aiskillnavigator\service\ai_provider_factory::create_from_config();
            return $provider->generate($prompt, 2600, $systemprompt);
        }

        if (
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_config') &&
            class_exists('\local_aiskillnavigator\service\provider\ai_provider_selector')
        ) {
            $config = new \local_aiskillnavigator\service\provider\ai_provider_config();
            $selector = new \local_aiskillnavigator\service\provider\ai_provider_selector();
            $provider = $selector->create($config);
            return $provider->generate($prompt, 2600, $systemprompt);
        }
    } catch (Throwable $e) {
        return 'AI error: ' . $e->getMessage();
    }

    return 'AI provider not available. Configure it from plugin settings.';
}

$readablematerials = local_aiskillnavigator_material_source_get_readable_materials((int)$courseid);

$sourcemode = local_aiskillnavigator_material_source_mode_from_request(-1);
$selectedmaterialids = local_aiskillnavigator_material_source_selected_ids_from_request($readablematerials);
$question = optional_param('question', '', PARAM_RAW_TRIMMED);

if ($sourcemode === 'all') {
    $sourcemode = 'selected';
}

$answer = '';
$error = '';
$usedmaterialnames = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($question === '') {
        $error = 'Write a question first.';
    } else {
        $selectedmaterials = local_aiskillnavigator_material_source_selected_materials(
            $readablematerials,
            $sourcemode,
            $selectedmaterialids
        );

        if ($sourcemode === 'selected' && empty($selectedmaterials)) {
            $error = 'Select at least one material allowed for the current AI provider, or choose Question/topic only.';
        }

        if ($error === '') {
            if ($sourcemode === 'manual') {
                $systemprompt = 'You are a Moodle AI tutor. Answer using only the student question and general knowledge. Do not claim that course materials were used.';
                $prompt = "Student question:\n" . $question;
            } else {
                $contextparts = [];

                foreach ($selectedmaterials as $material) {
                    $name = local_aiskillnavigator_material_source_clean_title($material);
                    $usedmaterialnames[] = $name;

                    $contextparts[] =
                        "SOURCE: " . $name . "\n" .
                        local_aiskillnavigator_tutor_limit_context((string)$material->content);
                }

                $systemprompt = 'You are a Moodle AI tutor. Use only the selected course materials as grounding context. If the selected materials do not contain enough information, say it clearly. Do not invent sources. Answer in the same language as the student.';

                $prompt =
                    "Selected course materials:\n\n" .
                    implode("\n\n---\n\n", $contextparts) .
                    "\n\nStudent question:\n" .
                    $question;
            }

            $answer = local_aiskillnavigator_fix_mojibake(local_aiskillnavigator_tutor_call_ai($prompt, $systemprompt));
        }
    }
}

echo $OUTPUT->header();
local_aiskillnavigator_print_inline_styles();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', 'AI Tutor');

echo html_writer::tag(
    'p',
    'Ask a free question, or select exactly which Moodle course materials the AI can use.',
    ['class' => 'lead']
);

echo html_writer::tag('p', 'Course: ' . s($course->fullname), ['class' => 'text-muted']);

if ($error !== '') {
    echo html_writer::div(s($error), 'alert alert-danger');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo local_aiskillnavigator_material_source_selector_html(
    $readablematerials,
    null,
    $courseid,
    $sourcemode,
    $selectedmaterialids,
    '1. Source',
    ''
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', '2. Question');

echo html_writer::tag('textarea', s($question), [
    'name' => 'question',
    'id' => 'question',
    'class' => 'form-control',
    'rows' => 5,
    'placeholder' => 'Esempio: spiegami la differenza tra funzione lineare e funzione quadratica.',
    'required' => 'required',
]);

echo html_writer::start_div('mt-3');

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => 'Ask AI',
]);

echo ' ';

echo html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    'Back to course',
    ['class' => 'btn btn-secondary']
);

echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

if ($answer !== '') {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', 'Answer');

    if (!empty($usedmaterialnames)) {
        echo html_writer::div(
            'Used materials: ' . s(implode(', ', $usedmaterialnames)),
            'text-muted mb-3'
        );
    } else {
        echo html_writer::div('Used materials: none', 'text-muted mb-3');
    }

    echo local_aiskillnavigator_render_ai_answer($answer);

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo local_aiskillnavigator_tutor_signal_capture_assets((int)$courseid);
echo local_aisn_back_to_course_autofix((int)($courseid ?? optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT)));if (function_exists('local_aisn_mdtable_assets')) { echo local_aisn_mdtable_assets(); }
echo '<link rel="stylesheet" href="' . (new moodle_url('/local/aiskillnavigator/assets/aisn_answer_renderer_v3.css', ['v' => time()]))->out(false) . '">';
echo '<script>
window.MathJax = {
    tex: {
        inlineMath: [["\\\\(", "\\\\)"], ["$", "$"]],
        displayMath: [["\\\\[", "\\\\]"], ["$$", "$$"]]
    },
    svg: { fontCache: "global" }
};
</script>';
echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>';
echo '<script src="' . (new moodle_url('/local/aiskillnavigator/assets/aisn_answer_renderer_v3.js', ['v' => time()]))->out(false) . '"></script>';
echo $OUTPUT->footer();

function local_aiskillnavigator_tutor_signal_capture_assets(int $courseid): string {
    $endpoint = new moodle_url('/local/aiskillnavigator/pages/tutor_signal_capture.php', [
        'courseid' => $courseid,
        'sesskey' => sesskey(),
    ]);

    $endpointjson = json_encode($endpoint->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    return <<<HTML
<script id="aisn-tutor-signal-capture-v1">
(function () {
    const endpoint = {$endpointjson};

    function clean(value) {
        return String(value || "").replace(/\\s+/g, " ").trim();
    }

    function findAnswerText() {
        const answerBox = document.querySelector(".aisn-answer");
        if (answerBox) {
            return clean(answerBox.innerText || answerBox.textContent || "");
        }

        const headings = Array.from(document.querySelectorAll("h1,h2,h3,h4"));
        const answerHeading = headings.find(function (h) {
            return clean(h.textContent).toLowerCase() === "answer";
        });

        if (!answerHeading) {
            return "";
        }

        const card = answerHeading.closest(".card") || answerHeading.parentElement;
        if (!card) {
            return "";
        }

        let text = clean(card.innerText || card.textContent || "");
        text = text.replace(/^Answer\\s*/i, "");
        text = text.replace(/^Used materials:\\s*[^\\n]+/i, "");

        return clean(text);
    }

    function findQuestionText() {
        const textarea = document.querySelector('textarea[name="question"], #question');
        if (textarea && textarea.value) {
            return clean(textarea.value);
        }

        return "";
    }

    function findSourceMode() {
        const checked = document.querySelector('input[name="sourcemode"]:checked, input[name="source"]:checked, input[name="materialsource"]:checked');
        if (checked) {
            return checked.value || "selected";
        }

        return "unknown";
    }

    function findUsedMaterials() {
        const used = Array.from(document.querySelectorAll(".text-muted")).find(function (el) {
            return clean(el.textContent).toLowerCase().startsWith("used materials:");
        });

        if (!used) {
            return [];
        }

        const txt = clean(used.textContent).replace(/^Used materials:\\s*/i, "");
        if (!txt || txt.toLowerCase() === "none") {
            return [];
        }

        return txt.split(",").map(function (x) { return clean(x); }).filter(Boolean);
    }

    async function saveSignal() {
        const question = findQuestionText();
        const answer = findAnswerText();

        if (!question || !answer) {
            return;
        }

        const fingerprint = "aisn_tutor_signal_" + btoa(unescape(encodeURIComponent(question + "::" + answer))).slice(0, 80);

        if (sessionStorage.getItem(fingerprint) === "1") {
            return;
        }

        sessionStorage.setItem(fingerprint, "1");

        const body = new URLSearchParams();
        body.set("question", question);
        body.set("answer", answer);
        body.set("sourcemode", findSourceMode());
        body.set("materials", JSON.stringify(findUsedMaterials()));

        try {
            const response = await fetch(endpoint, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
                },
                body: body.toString()
            });

            const data = await response.json();

            const note = document.createElement("div");
            note.className = data.ok ? "alert alert-success mt-3" : "alert alert-warning mt-3";
            note.textContent = data.ok
                ? "Tutor-as-Sensor: domanda salvata nella dashboard docente."
                : "Tutor-as-Sensor: salvataggio non riuscito: " + (data.message || "errore sconosciuto");

            const answerBox = document.querySelector(".aisn-answer");
            if (answerBox && !document.getElementById("aisn-tutor-signal-note")) {
                note.id = "aisn-tutor-signal-note";
                answerBox.parentElement.appendChild(note);
            }
        } catch (e) {
            console.error("Tutor signal capture failed", e);
            sessionStorage.removeItem(fingerprint);
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(saveSignal, 300);
        setTimeout(saveSignal, 1200);
    });
})();
</script>
HTML;
}
