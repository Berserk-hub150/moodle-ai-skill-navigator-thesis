<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

global $DB;

$dbman = $DB->get_manager();

$table = new xmldb_table('local_aiskillnav_material');

if (!$dbman->table_exists($table)) {
    echo "Table local_aiskillnav_material not found. Install the plugin first.\n";
    exit(0);
}

$field = new xmldb_field('externalaiallowed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');

if (!$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
    echo "Added field externalaiallowed\n";
} else {
    echo "Field externalaiallowed already exists\n";
}

$field = new xmldb_field('aipolicy', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'local_only', 'externalaiallowed');

if (!$dbman->field_exists($table, $field)) {
    $dbman->add_field($table, $field);
    echo "Added field aipolicy\n";
} else {
    echo "Field aipolicy already exists\n";
}

$DB->execute("UPDATE {local_aiskillnav_material}
                 SET aipolicy = CASE WHEN externalaiallowed = 1 THEN 'external_allowed' ELSE 'local_only' END
               WHERE aipolicy IS NULL OR aipolicy = ''");

echo "AI material policy schema ready.\n";