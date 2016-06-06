<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file keeps track of upgrades to
 * the peerforum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package mod-peerforum
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_peerforum_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    // Moodle v2.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.4.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2013020500) {

        // Define field displaywordcount to be added to peerforum.
        $table = new xmldb_table('peerforum');
        $field = new xmldb_field('displaywordcount', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completionposts');

        // Conditionally launch add field displaywordcount.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2013020500, 'peerforum');
    }

    // Forcefully assign mod/peerforum:allowforcesubscribe to frontpage role, as we missed that when
    // capability was introduced.
    if ($oldversion < 2013021200) {
        // If capability mod/peerforum:allowforcesubscribe is defined then set it for frontpage role.
        if (get_capability_info('mod/peerforum:allowforcesubscribe')) {
            assign_legacy_capabilities('mod/peerforum:allowforcesubscribe', array('frontpage' => CAP_ALLOW));
        }
        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2013021200, 'peerforum');
    }


    // Moodle v2.5.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2013071000) {
        // Define table peerforum_digests to be created.
        $table = new xmldb_table('peerforum_digests');

        // Adding fields to table peerforum_digests.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('peerforum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('maildigest', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '-1');

        // Adding keys to table peerforum_digests.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('peerforum', XMLDB_KEY_FOREIGN, array('peerforum'), 'peerforum', array('id'));
        $table->add_key('peerforumdigest', XMLDB_KEY_UNIQUE, array('peerforum', 'userid', 'maildigest'));

        // Conditionally launch create table for peerforum_digests.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2013071000, 'peerforum');
    }

    // Moodle v2.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2013110501) {

        // Incorrect values that need to be replaced.
        $replacements = array(
            11 => 20,
            12 => 50,
            13 => 100
        );

        // Run the replacements.
        foreach ($replacements as $old => $new) {
            $DB->set_field('peerforum', 'maxattachments', $new, array('maxattachments' => $old));
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2013110501, 'peerforum');
    }

    if ($oldversion < 2013110502) {
        // Find records with multiple userid/postid combinations and find the lowest ID.
        // Later we will remove all those which don't match this ID.
        $sql = "
            SELECT MIN(id) as lowid, userid, postid
            FROM {peerforum_read}
            GROUP BY userid, postid
            HAVING COUNT(id) > 1";

        if ($duplicatedrows = $DB->get_recordset_sql($sql)) {
            foreach ($duplicatedrows as $row) {
                $DB->delete_records_select('peerforum_read', 'userid = ? AND postid = ? AND id <> ?', array(
                    $row->userid,
                    $row->postid,
                    $row->lowid,
                ));
            }
        }
        $duplicatedrows->close();

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2013110502, 'peerforum');
    }

    // create rating table for peerforum (ratingpeer)
    if ($oldversion < 2016051015) {
        // Define table peerforum_digests to be created.
        $table = new xmldb_table('peerforum_ratingpeer');

        // Adding fields to table peerforum_digests.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ratingpeerarea', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scaleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ratingpeer', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table peerforum_digests.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for peerforum_digests.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2016051015, 'peerforum');
    }

    return true;
}
