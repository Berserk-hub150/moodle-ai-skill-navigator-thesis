<?php

namespace local_aiskillnavigator;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_created(\core\event\course_created $event): void {
        $courseid = (int)($event->courseid ?: $event->objectid);
        self::ensure_course_block($courseid);
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
}