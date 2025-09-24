<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_diplomaproject_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025092400) {
        // Define table diplomaproject_number_of_papers to be created.
        $table = new xmldb_table('diplomaproject_number_of_papers');

        // Adding fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('teacher_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('number_of_papers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Create the table if it doesn’t exist yet.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Save upgrade point.
        upgrade_mod_savepoint(true, 2025092400, 'diplomaproject');
    }

    return true;
}
