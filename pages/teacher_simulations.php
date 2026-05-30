<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../includes/back_to_course_helper.php');
require_once(__DIR__ . '/../includes/simulator_materials_helper.php');

global $DB, $PAGE, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$simulationid = optional_param('id', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/aiskillnavigator:viewteacher', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', ['courseid' => $courseid]));
$PAGE->set_title('Saved simulations');
$PAGE->set_heading('Saved simulations');

local_aisn_sim_ensure_table();

if ($action === 'delete' && $simulationid > 0) {
    require_sesskey();

    $DB->delete_records('local_aiskillnav_sim', [
        'id' => $simulationid,
        'courseid' => $courseid,
    ]);

    redirect($PAGE->url, 'Simulation deleted.', 1);
}

$records = $DB->get_records('local_aiskillnav_sim', ['courseid' => $courseid], 'timecreated DESC');

echo $OUTPUT->header();

echo html_writer::tag('style', '
.aisn-saved-sim-page {
    max-width: 1180px;
    margin: 0 auto;
}
.aisn-saved-search {
    max-width: 520px;
    margin: 12px 0 22px;
    border-radius: 12px;
    padding: 11px 13px;
}
.aisn-saved-card {
    border-radius: 18px;
}
.aisn-saved-result {
    white-space: pre-wrap;
    max-height: 360px;
    overflow-y: auto;
}
');

echo html_writer::start_div('container-fluid aisn-saved-sim-page');

echo html_writer::tag('h2', 'Saved simulations');

echo html_writer::empty_tag('input', [
    'type' => 'search',
    'id' => 'aisn-saved-sim-search',
    'class' => 'form-control aisn-saved-search',
    'placeholder' => 'Search saved simulation...',
]);

if (empty($records)) {
    echo html_writer::div('No saved simulations yet.', 'alert alert-info');
}

foreach ($records as $record) {
    $titles = json_decode((string)$record->materialtitles, true);
    $titles = is_array($titles) ? $titles : [];

    echo html_writer::start_div('card mb-3 shadow-sm aisn-saved-card', [
        'data-aisn-saved-card' => '1',
    ]);

    echo html_writer::start_div('card-body');

    echo html_writer::tag('h3', s($record->topic ?: 'Untitled simulation'));

    echo html_writer::tag(
        'p',
        'Level: ' . s($record->level ?: '-') .
        ' | Date: ' . userdate((int)$record->timecreated),
        ['class' => 'text-muted']
    );

    if (!empty($titles)) {
        echo html_writer::tag('p', 'Materials: ' . s(implode(', ', $titles)));
    }

    echo html_writer::tag('pre', s((string)$record->resulttext), [
        'class' => 'bg-light p-3 rounded aisn-saved-result',
    ]);

    echo html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/teacher_simulations.php', [
            'courseid' => $courseid,
            'id' => (int)$record->id,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]),
        'Delete',
        ['class' => 'btn btn-danger']
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/pages/simulator_finder.php', ['courseid' => $courseid]),
        'Back to Simulator Finder',
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        'Back to course',
        ['class' => 'btn btn-secondary']
    ),
    'mt-4'
);

echo html_writer::tag('script', '
(function() {
    const search = document.getElementById("aisn-saved-sim-search");
    const cards = Array.from(document.querySelectorAll("[data-aisn-saved-card]"));

    if (!search) {
        return;
    }

    search.addEventListener("input", function() {
        const q = String(search.value || "").toLowerCase().trim();

        cards.forEach(function(card) {
            const text = String(card.textContent || "").toLowerCase();
            card.style.display = text.indexOf(q) !== -1 ? "" : "none";
        });
    });
})();
');

echo html_writer::end_div();

echo $OUTPUT->footer();
