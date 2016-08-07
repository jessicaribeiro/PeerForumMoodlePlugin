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
 * @package   mod_peerforum
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

    if ($oldversion < 2014040400) {

        // Define index userid-postid (not unique) to be dropped form peerforum_read.
        $table = new xmldb_table('peerforum_read');
        $index = new xmldb_index('userid-postid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'postid'));

        // Conditionally launch drop index userid-postid.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }


        // Define index postid-userid (not unique) to be added to peerforum_read.
        $index = new xmldb_index('postid-userid', XMLDB_INDEX_NOTUNIQUE, array('postid', 'userid'));

        // Conditionally launch add index postid-userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2014040400, 'peerforum');
    }

    // Moodle v2.7.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2014051201) {

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
        upgrade_mod_savepoint(true, 2014051201, 'peerforum');
    }

    if ($oldversion < 2014081500) {

        // Define index course (not unique) to be added to peerforum_discussions.
        $table = new xmldb_table('peerforum_discussions');
        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));

        // Conditionally launch add index course.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2014081500, 'peerforum');
    }

    if ($oldversion < 2014081900) {

        // Define table peerforum_discussion_subs to be created.
        $table = new xmldb_table('peerforum_discussion_subs');

        // Adding fields to table peerforum_discussion_subs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('peerforum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('preference', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table peerforum_discussion_subs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('peerforum', XMLDB_KEY_FOREIGN, array('peerforum'), 'peerforum', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('discussion', XMLDB_KEY_FOREIGN, array('discussion'), 'peerforum_discussions', array('id'));
        $table->add_key('user_discussions', XMLDB_KEY_UNIQUE, array('userid', 'discussion'));

        // Conditionally launch create table for peerforum_discussion_subs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2014081900, 'peerforum');
    }

    if ($oldversion < 2014103000) {
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
        upgrade_mod_savepoint(true, 2014103000, 'peerforum');
    }

    if ($oldversion < 2014110300) {

        // Changing precision of field preference on table peerforum_discussion_subs to (10).
        $table = new xmldb_table('peerforum_discussion_subs');
        $field = new xmldb_field('preference', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'discussion');

        // Launch change of precision for field preference.
        $dbman->change_field_precision($table, $field);

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2014110300, 'peerforum');
    }

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2015102900) {
        // Groupid = 0 is never valid.
        $DB->set_field('peerforum_discussions', 'groupid', -1, array('groupid' => 0));

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2015102900, 'peerforum');
    }

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015111606) {

        // New field on peerforum table
        $table = new xmldb_table('peerforum');
        $field = new xmldb_field('showdetails', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'postsperpage');

        // Conditionally launch add field displaywordcount.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // PeerForum savepoint reached.
        upgrade_mod_savepoint(true, 2015111606, 'peerforum');
    }

    return true;
}
