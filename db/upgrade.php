<?php
// This file is part of Moodle - https://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_local_aiskillnavigator_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026051001) {
        $table = new xmldb_table('local_aiskillnav_material');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('materialtype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'text');
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_aiskillnav_attempt');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'medium');
            $table->add_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('quizjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('answersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('topic_ix', XMLDB_INDEX_NOTUNIQUE, ['topic']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051001, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026051002) {
        upgrade_plugin_savepoint(true, 2026051002, 'local', 'aiskillnavigator');
    }

    if ($oldversion < 2026051003) {
        upgrade_plugin_savepoint(true, 2026051003, 'local', 'aiskillnavigator');
    }

    return true;
}