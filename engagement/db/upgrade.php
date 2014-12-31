<?php

/**
 * Database upgrade script
 */
function xmldb_report_engagement_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    $result = true;

 if ($oldversion < 2014102500) {

        // Define table report_engagement to be created.
        $table = new xmldb_table('report_engagement');

        // Adding fields to table report_engagement.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('completeby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table report_engagement.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for report_engagement.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Engagement savepoint reached.
        upgrade_plugin_savepoint(true, 2014102500, 'report', 'engagement');
    }
    
 if ($oldversion < 2014123100) {

        // Define table report_engagement_lecturers to be created.
        $table = new xmldb_table('report_engagement_lecturers');

        // Adding fields to table report_engagement_lecturers.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '201', null, XMLDB_NOTNULL, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table report_engagement_lecturers.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for report_engagement_lecturers.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
     
        // Engagement savepoint reached.
        upgrade_plugin_savepoint(true, 2014123100, 'report', 'engagement');
    }


    return $result;
}
