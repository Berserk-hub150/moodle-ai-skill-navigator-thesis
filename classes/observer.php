<?php

namespace local_aiskillnavigator;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_created(\core\event\course_created $event): void {
        $courseid = (int)($event->courseid ?: $event->objectid);

        self::ensure_course_block($courseid);
        self::sync_course_resources($courseid, (int)$event->userid);
    }

    public static function course_module_created(\core\event\course_module_created $event): void {
        self::sync_course_resources((int)$event->courseid, (int)$event->userid);
    }

    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::sync_course_resources((int)$event->courseid, (int)$event->userid);
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        $courseid = (int)$event->courseid;
        $cmid = (int)$event->objectid;

        if ($courseid <= 1 || $cmid <= 0) {
            return;
        }

        $DB->delete_records_select(
            'local_aiskillnav_material',
            'courseid = :courseid AND materialtype = :materialtype AND ' . $DB->sql_like('title', ':title', false),
            [
                'courseid' => $courseid,
                'materialtype' => 'course_resource',
                'title' => '%cm #' . $cmid . ']%',
            ]
        );
    }

    public static function ensure_course_block(int $courseid): void {
        global $DB;

        if ($courseid <= 1) {
            return;
        }

        if (!$DB->record_exists('block', ['name' => 'aiskillnavigator'])) {
            return;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);

        if (!$context) {
            return;
        }

        if ($DB->record_exists('block_instances', [
            'blockname' => 'aiskillnavigator',
            'parentcontextid' => $context->id,
        ])) {
            return;
        }

        $record = new \stdClass();
        $record->blockname = 'aiskillnavigator';
        $record->parentcontextid = $context->id;
        $record->showinsubcontexts = 0;
        $record->pagetypepattern = 'course-view-*';
        $record->subpagepattern = '';
        $record->defaultregion = 'side-pre';
        $record->defaultweight = -10;
        $record->configdata = '';
        $record->timecreated = time();
        $record->timemodified = time();

        $DB->insert_record('block_instances', $record);
    }

    private static function sync_course_resources(int $courseid, int $userid): void {
        global $CFG;

        if ($courseid <= 1) {
            return;
        }

        require_once($CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php');

        try {
            local_aiskillnavigator_sync_course_resources($courseid, $userid, false);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator automatic course resource sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}