<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php');

$options = getopt('', ['courseid::', 'userid::', 'force']);

$courseid = isset($options['courseid']) ? (int) $options['courseid'] : 0;
$userid = isset($options['userid']) ? (int) $options['userid'] : 2;
$force = array_key_exists('force', $options);

if ($courseid <= 1) {
    echo "Usage: php sync_course_resources.php --courseid=2 [--force]\n";
    exit(1);
}

$result = local_aiskillnavigator_sync_course_resources($courseid, $userid, $force);

echo "Course resource sync completed for course {$courseid}\n";
echo "Created: {$result['created']}\n";
echo "Updated: {$result['updated']}\n";
echo "Skipped: {$result['skipped']}\n";