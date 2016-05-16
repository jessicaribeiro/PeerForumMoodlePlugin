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
 * @package    mod_peerforum
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/peerforum/backup/moodle2/restore_peerforum_stepslib.php'); // Because it exists (must)

/**
 * peerforum restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_peerforum_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_peerforum_activity_structure_step('peerforum_structure', 'peerforum.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('peerforum', array('intro'), 'peerforum');
        $contents[] = new restore_decode_content('peerforum_posts', array('message'), 'peerforum_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of peerforums in course
        $rules[] = new restore_decode_rule('PEERFORUMINDEX', '/mod/peerforum/index.php?id=$1', 'course');
        // PeerForum by cm->id and peerforum->id
        $rules[] = new restore_decode_rule('PEERFORUMVIEWBYID', '/mod/peerforum/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('PEERFORUMVIEWBYF', '/mod/peerforum/view.php?f=$1', 'peerforum');
        // Link to peerforum discussion
        $rules[] = new restore_decode_rule('PEERFORUMDISCUSSIONVIEW', '/mod/peerforum/discuss.php?d=$1', 'peerforum_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('PEERFORUMDISCUSSIONVIEWPARENT', '/mod/peerforum/discuss.php?d=$1&parent=$2',
                                           array('peerforum_discussion', 'peerforum_post'));
        $rules[] = new restore_decode_rule('PEERFORUMDISCUSSIONVIEWINSIDE', '/mod/peerforum/discuss.php?d=$1#$2',
                                           array('peerforum_discussion', 'peerforum_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * peerforum logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('peerforum', 'add', 'view.php?id={course_module}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'update', 'view.php?id={course_module}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'view', 'view.php?id={course_module}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'view peerforum', 'view.php?id={course_module}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'mark read', 'view.php?f={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'start tracking', 'view.php?f={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'stop tracking', 'view.php?f={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'subscribe', 'view.php?f={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'unsubscribe', 'view.php?f={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'subscriber', 'subscribers.php?id={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'subscribers', 'subscribers.php?id={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'view subscribers', 'subscribers.php?id={peerforum}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'add discussion', 'discuss.php?d={peerforum_discussion}', '{peerforum_discussion}');
        $rules[] = new restore_log_rule('peerforum', 'view discussion', 'discuss.php?d={peerforum_discussion}', '{peerforum_discussion}');
        $rules[] = new restore_log_rule('peerforum', 'move discussion', 'discuss.php?d={peerforum_discussion}', '{peerforum_discussion}');
        $rules[] = new restore_log_rule('peerforum', 'delete discussi', 'view.php?id={course_module}', '{peerforum}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('peerforum', 'delete discussion', 'view.php?id={course_module}', '{peerforum}');
        $rules[] = new restore_log_rule('peerforum', 'add post', 'discuss.php?d={peerforum_discussion}&parent={peerforum_post}', '{peerforum_post}');
        $rules[] = new restore_log_rule('peerforum', 'update post', 'discuss.php?d={peerforum_discussion}#p{peerforum_post}&parent={peerforum_post}', '{peerforum_post}');
        $rules[] = new restore_log_rule('peerforum', 'update post', 'discuss.php?d={peerforum_discussion}&parent={peerforum_post}', '{peerforum_post}');
        $rules[] = new restore_log_rule('peerforum', 'prune post', 'discuss.php?d={peerforum_discussion}', '{peerforum_post}');
        $rules[] = new restore_log_rule('peerforum', 'delete post', 'discuss.php?d={peerforum_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('peerforum', 'view peerforums', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('peerforum', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('peerforum', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('peerforum', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('peerforum', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}
