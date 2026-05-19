<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help'])) {
    echo "Adds the AI Skill Navigator block to all existing courses.\n";
    echo "Usage: php local/aiskillnavigator/cli/sync_course_blocks.php\n";
    exit(0);
}

$courseids = $DB->get_fieldset_select('course', 'id', 'id <> ?', [SITEID]);

$count = 0;

foreach ($courseids as $courseid) {
    \local_aiskillnavigator\observer::ensure_course_block((int)$courseid);
    $count++;
}

echo "AI Skill Navigator block checked for {$count} courses.\n";