<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

global $DB;

$dbman = $DB->get_manager();

$table = new xmldb_table('local_aiskillnav_assessment');

if (!$dbman->table_exists($table)) {
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $table->add_field('assessmenttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pre');
    $table->add_field('focus', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
    $table->add_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'medium');
    $table->add_field('quizjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
    $table->add_field('sourcemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all');
    $table->add_field('materialids', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
    $table->add_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmenttype']);
    $table->add_index('visible_ix', XMLDB_INDEX_NOTUNIQUE, ['visible']);

    $dbman->create_table($table);

    echo "Created table local_aiskillnav_assessment\n";
} else {
    echo "Table local_aiskillnav_assessment already exists\n";
}

$table = new xmldb_table('local_aiskillnav_ass_att');

if (!$dbman->table_exists($table)) {
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('assessmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('answersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('assessmentid_ix', XMLDB_INDEX_NOTUNIQUE, ['assessmentid']);
    $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
    $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

    $dbman->create_table($table);

    echo "Created table local_aiskillnav_ass_att\n";
} else {
    echo "Table local_aiskillnav_ass_att already exists\n";
}

echo "Assessment tables ready.\n";