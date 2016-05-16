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
 * mod_peerforum data generator
 *
 * @package    mod_peerforum
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * PeerForum module data generator class
 *
 * @package    mod_peerforum
 * @category   test
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerforum_generator extends testing_module_generator {

    /**
     * @var int keep track of how many peerforum discussions have been created.
     */
    protected $peerforumdiscussioncount = 0;

    /**
     * @var int keep track of how many peerforum posts have been created.
     */
    protected $peerforumpostcount = 0;

    /**
     * @var int keep track of how many peerforum subscriptions have been created.
     */
    protected $peerforumsubscriptionscount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->peerforumdiscussioncount = 0;
        $this->peerforumpostcount = 0;
        $this->peerforumsubscriptionscount = 0;

        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/peerforum/lib.php');
        $record = (object)(array)$record;

        if (!isset($record->type)) {
            $record->type = 'general';
        }
        if (!isset($record->assessed)) {
            $record->assessed = 0;
        }
        if (!isset($record->scale)) {
            $record->scale = 0;
        }
        if (!isset($record->forcesubscribe)) {
            $record->forcesubscribe = PEERFORUM_CHOOSESUBSCRIBE;
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Function to create a dummy subscription.
     *
     * @param array|stdClass $record
     * @return stdClass the subscription object
     */
    public function create_subscription($record = null) {
        global $DB;

        // Increment the peerforum subscription count.
        $this->peerforumsubscriptionscount++;

        $record = (array)$record;

        if (!isset($record['course'])) {
            throw new coding_exception('course must be present in phpunit_util::create_subscription() $record');
        }

        if (!isset($record['peerforum'])) {
            throw new coding_exception('peerforum must be present in phpunit_util::create_subscription() $record');
        }

        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_subscription() $record');
        }

        $record = (object)$record;

        // Add the subscription.
        $record->id = $DB->insert_record('peerforum_subscriptions', $record);

        return $record;
    }

    /**
     * Function to create a dummy discussion.
     *
     * @param array|stdClass $record
     * @return stdClass the discussion object
     */
    public function create_discussion($record = null) {
        global $DB;

        // Increment the peerforum discussion count.
        $this->peerforumdiscussioncount++;

        $record = (array) $record;

        if (!isset($record['course'])) {
            throw new coding_exception('course must be present in phpunit_util::create_discussion() $record');
        }

        if (!isset($record['peerforum'])) {
            throw new coding_exception('peerforum must be present in phpunit_util::create_discussion() $record');
        }

        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_discussion() $record');
        }

        if (!isset($record['name'])) {
            $record['name'] = "Discussion " . $this->peerforumdiscussioncount;
        }

        if (!isset($record['subject'])) {
            $record['subject'] = "Subject for discussion " . $this->peerforumdiscussioncount;
        }

        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'Message for discussion ' . $this->peerforumdiscussioncount);
        }

        if (!isset($record['messageformat'])) {
            $record['messageformat'] = editors_get_preferred_format();
        }

        if (!isset($record['messagetrust'])) {
            $record['messagetrust'] = "";
        }

        if (!isset($record['assessed'])) {
            $record['assessed'] = '1';
        }

        if (!isset($record['groupid'])) {
            $record['groupid'] = "-1";
        }

        if (!isset($record['timestart'])) {
            $record['timestart'] = "0";
        }

        if (!isset($record['timeend'])) {
            $record['timeend'] = "0";
        }

        if (!isset($record['mailnow'])) {
            $record['mailnow'] = "0";
        }

        if (isset($record['timemodified'])) {
            $timemodified = $record['timemodified'];
        }

        $record = (object) $record;

        // Add the discussion.
        $record->id = peerforum_add_discussion($record, null, null, $record->userid);

        if (isset($timemodified)) {
            // Enforce the time modified.
            $post = $DB->get_record('peerforum_posts', array('discussion' => $record->id));
            $record->timemodified = $timemodified;
            $post->modified = $post->created = $timemodified;

            $DB->update_record('peerforum_discussions', $record);
            $DB->update_record('peerforum_posts', $post);
        }

        return $record;
    }

    /**
     * Function to create a dummy post.
     *
     * @param array|stdClass $record
     * @return stdClass the post object
     */
    public function create_post($record = null) {
        global $DB;

        // Increment the peerforum post count.
        $this->peerforumpostcount++;

        // Variable to store time.
        $time = time() + $this->peerforumpostcount;

        $record = (array) $record;

        if (!isset($record['discussion'])) {
            throw new coding_exception('discussion must be present in phpunit_util::create_post() $record');
        }

        if (!isset($record['userid'])) {
            throw new coding_exception('userid must be present in phpunit_util::create_post() $record');
        }

        if (!isset($record['parent'])) {
            $record['parent'] = 0;
        }

        if (!isset($record['subject'])) {
            $record['subject'] = 'PeerForum post subject ' . $this->peerforumpostcount;
        }

        if (!isset($record['message'])) {
            $record['message'] = html_writer::tag('p', 'PeerForum message post ' . $this->peerforumpostcount);
        }

        if (!isset($record['created'])) {
            $record['created'] = $time;
        }

        if (!isset($record['modified'])) {
            $record['modified'] = $time;
        }

        if (!isset($record['mailed'])) {
            $record['mailed'] = 0;
        }

        if (!isset($record['messageformat'])) {
            $record['messageformat'] = 0;
        }

        if (!isset($record['messagetrust'])) {
            $record['messagetrust'] = 0;
        }

        if (!isset($record['attachment'])) {
            $record['attachment'] = "";
        }

        if (!isset($record['totalscore'])) {
            $record['totalscore'] = 0;
        }

        if (!isset($record['mailnow'])) {
            $record['mailnow'] = 0;
        }

        $record = (object) $record;

        // Add the post.
        $record->id = $DB->insert_record('peerforum_posts', $record);

        // Update the last post.
        peerforum_discussion_update_last_post($record->discussion);

        return $record;
    }

    public function create_content($instance, $record = array()) {
        global $USER, $DB;
        $record = (array)$record + array(
            'peerforum' => $instance->id,
            'userid' => $USER->id,
            'course' => $instance->course
        );
        if (empty($record['discussion']) && empty($record['parent'])) {
            // Create discussion.
            $discussion = $this->create_discussion($record);
            $post = $DB->get_record('peerforum_posts', array('id' => $discussion->firstpost));
        } else {
            // Create post.
            if (empty($record['parent'])) {
                $record['parent'] = $DB->get_field('peerforum_discussions', 'firstpost', array('id' => $record['discussion']), MUST_EXIST);
            } else if (empty($record['discussion'])) {
                $record['discussion'] = $DB->get_field('peerforum_posts', 'discussion', array('id' => $record['parent']), MUST_EXIST);
            }
            $post = $this->create_post($record);
        }
        return $post;
    }
}
