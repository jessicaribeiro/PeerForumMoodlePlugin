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
 * Event observers used in peerforum.
 *
 * @package    mod_peerforum
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_peerforum.
 */
class mod_peerforum_observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // NOTE: this has to be as fast as possible.
        // Get user enrolment info from event.
        $cp = (object)$event->other['userenrolment'];
        if ($cp->lastenrol) {
            $params = array('userid' => $cp->userid, 'courseid' => $cp->courseid);
            $peerforumselect = "IN (SELECT f.id FROM {peerforum} f WHERE f.course = :courseid)";

            $DB->delete_records_select('peerforum_digests', 'userid = :userid AND peerforum '.$peerforumselect, $params);
            $DB->delete_records_select('peerforum_subscriptions', 'userid = :userid AND peerforum '.$peerforumselect, $params);
            $DB->delete_records_select('peerforum_track_prefs', 'userid = :userid AND peerforumid '.$peerforumselect, $params);
            $DB->delete_records_select('peerforum_read', 'userid = :userid AND peerforumid '.$peerforumselect, $params);
        }
    }

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $CFG, $DB;

        $context = context::instance_by_id($event->contextid, MUST_EXIST);

        // If contextlevel is course then only subscribe user. Role assignment
        // at course level means user is enroled in course and can subscribe to peerforum.
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // PeerForum lib required for the constant used below.
        require_once($CFG->dirroot . '/mod/peerforum/lib.php');

        $userid = $event->relateduserid;
        $sql = "SELECT f.id, f.course as course, cm.id AS cmid, f.forcesubscribe
                  FROM {peerforum} f
                  JOIN {course_modules} cm ON (cm.instance = f.id)
                  JOIN {modules} m ON (m.id = cm.module)
             LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = :userid)
                 WHERE f.course = :courseid
                   AND f.forcesubscribe = :initial
                   AND m.name = 'peerforum'
                   AND fs.id IS NULL";
        $params = array('courseid' => $context->instanceid, 'userid' => $userid, 'initial' => PEERFORUM_INITIALSUBSCRIBE);

        $peerforums = $DB->get_records_sql($sql, $params);
        foreach ($peerforums as $peerforum) {
            // If user doesn't have allowforcesubscribe capability then don't subscribe.
            $modcontext = context_module::instance($peerforum->cmid);
            if (has_capability('mod/peerforum:allowforcesubscribe', $modcontext, $userid)) {
                \mod_peerforum\subscriptions::subscribe_user($userid, $peerforum, $modcontext);
            }
        }
    }

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'peerforum') {
            // Include the peerforum library to make use of the peerforum_instance_created function.
            require_once($CFG->dirroot . '/mod/peerforum/lib.php');

            $peerforum = $event->get_record_snapshot('peerforum', $event->other['instanceid']);
            peerforum_instance_created($event->get_context(), $peerforum);
        }
    }
}
