<?php

namespace local_aiskillnavigator;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_created(\core\event\course_created $event): void {
        $courseid = (int)($event->courseid ?: $event->objectid);

        self::ensure_course_block($courseid);
        self::sync_course_resources($courseid, (int)$event->userid);
    }

    public static function course_updated(\core\event\course_updated $event): void {
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

        if ($courseid <= SITEID || $cmid <= 0) {
            return;
        }

        try {
            if (!self::table_exists('local_aiskillnav_material')) {
                return;
            }

            $select = 'courseid = :courseid AND materialtype = :materialtype AND ' . $DB->sql_like('title', ':title', false, false);
            $params = [
                'courseid' => $courseid,
                'materialtype' => 'course_resource',
                'title' => '%cm #' . $cmid . ']%',
            ];

            $materials = $DB->get_records_select('local_aiskillnav_material', $select, $params);
            $materialids = array_map('intval', array_keys($materials));

            if (!empty($materialids) && self::table_exists('local_aiskillnav_chunk')) {
                list($insql, $inparams) = $DB->get_in_or_equal($materialids, SQL_PARAMS_NAMED, 'mid');
                $DB->delete_records_select('local_aiskillnav_chunk', 'materialid ' . $insql, $inparams);
            }

            $DB->delete_records_select('local_aiskillnav_material', $select, $params);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator course module cleanup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    public static function ensure_course_block(int $courseid): void {
        global $DB;

        if ($courseid <= SITEID) {
            return;
        }

        try {
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
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator block auto-install failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    private static function sync_course_resources(int $courseid, int $userid): void {
        global $CFG;

        if ($courseid <= SITEID) {
            return;
        }

        $syncfile = $CFG->dirroot . '/local/aiskillnavigator/includes/course_resource_sync.php';

        if (!file_exists($syncfile)) {
            debugging('AI Skill Navigator sync file missing: ' . $syncfile, DEBUG_DEVELOPER);
            return;
        }

        require_once($syncfile);

        if (!function_exists('local_aiskillnavigator_sync_course_resources')) {
            debugging('AI Skill Navigator sync function missing.', DEBUG_DEVELOPER);
            return;
        }

        try {
            local_aiskillnavigator_sync_course_resources($courseid, $userid, false);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator automatic course resource sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    private static function table_exists(string $table): bool {
        global $DB;

        try {
            return in_array($table, $DB->get_tables(false), true);
        } catch (\Throwable $e) {
            debugging('AI Skill Navigator table check failed for ' . $table . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}