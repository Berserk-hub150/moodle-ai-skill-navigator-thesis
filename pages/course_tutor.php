<?php
// This file is part of Moodle - https://moodle.org/
//
// Backward-compatible entry point for old links.
// The real AI tutor page is tutor.php.

require_once(__DIR__ . '/../../../config.php');

$courseid = optional_param('courseid', SITEID, PARAM_INT);

redirect(new moodle_url('/local/aiskillnavigator/pages/tutor.php', ['courseid' => $courseid]));


