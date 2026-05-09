<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('pluginname', 'local_aiskillnavigator'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('welcome', 'local_aiskillnavigator'));

echo html_writer::tag('p', 'Plugin Moodle installato correttamente.');

echo html_writer::tag('h3', 'Moduli previsti');

echo html_writer::start_tag('ul');
echo html_writer::tag('li', 'Skill Navigator: mappa delle competenze dello studente');
echo html_writer::tag('li', 'AI Tutor: spiegazioni e supporto contestuale');
echo html_writer::tag('li', 'AI Quiz Generator: generazione quiz e micro-esercizi');
echo html_writer::tag('li', 'AI XR Scenario Generator: scenari per Virtual Worlds');
echo html_writer::tag('li', 'Teacher Dashboard: skill gap e report per docente');
echo html_writer::end_tag('ul');

echo $OUTPUT->footer();