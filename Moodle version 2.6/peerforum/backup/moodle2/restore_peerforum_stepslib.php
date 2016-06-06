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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_peerforum_activity_task
 */

/**
 * Structure step to restore one peerforum activity
 */
class restore_peerforum_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('peerforum', '/activity/peerforum');
        if ($userinfo) {
            $paths[] = new restore_path_element('peerforum_discussion', '/activity/peerforum/discussions/discussion');
            $paths[] = new restore_path_element('peerforum_post', '/activity/peerforum/discussions/discussion/posts/post');
            $paths[] = new restore_path_element('peerforum_ratingpeer', '/activity/peerforum/discussions/discussion/posts/post/ratingpeers/ratingpeer');
            $paths[] = new restore_path_element('peerforum_subscription', '/activity/peerforum/subscriptions/subscription');
            $paths[] = new restore_path_element('peerforum_digest', '/activity/peerforum/digests/digest');
            $paths[] = new restore_path_element('peerforum_read', '/activity/peerforum/readposts/read');
            $paths[] = new restore_path_element('peerforum_track', '/activity/peerforum/trackedprefs/track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_peerforum($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        $newitemid = $DB->insert_record('peerforum', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_peerforum_discussion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->peerforum = $this->get_new_parentid('peerforum');
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('peerforum_discussions', $data);
        $this->set_mapping('peerforum_discussion', $oldid, $newitemid);
    }

    protected function process_peerforum_post($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('peerforum_discussion');
        $data->created = $this->apply_date_offset($data->created);
        $data->modified = $this->apply_date_offset($data->modified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        // If post has parent, map it (it has been already restored)
        if (!empty($data->parent)) {
            $data->parent = $this->get_mappingid('peerforum_post', $data->parent);
        }

        $newitemid = $DB->insert_record('peerforum_posts', $data);
        $this->set_mapping('peerforum_post', $oldid, $newitemid, true);

        // If !post->parent, it's the 1st post. Set it in discussion
        if (empty($data->parent)) {
            $DB->set_field('peerforum_discussions', 'firstpost', $newitemid, array('id' => $data->discussion));
        }
    }

    protected function process_peerforum_ratingpeer($data) {
        global $DB;

        $data = (object)$data;

        // Cannot use ratingpeers API, cause, it's missing the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid    = $this->get_new_parentid('peerforum_post');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->ratingpeer = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // We need to check that component and ratingpeerarea are both set here.
        if (empty($data->component)) {
            $data->component = 'mod_peerforum';
        }
        if (empty($data->ratingpeerarea)) {
            $data->ratingpeerarea = 'post';
        }

        $newitemid = $DB->insert_record('peerforum_ratingpeer', $data);
    }

    protected function process_peerforum_subscription($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peerforum = $this->get_new_parentid('peerforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('peerforum_subscriptions', $data);
    }

    protected function process_peerforum_digest($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peerforum = $this->get_new_parentid('peerforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('peerforum_digests', $data);
    }

    protected function process_peerforum_read($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peerforumid = $this->get_new_parentid('peerforum');
        $data->discussionid = $this->get_mappingid('peerforum_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('peerforum_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('peerforum_read', $data);
    }

    protected function process_peerforum_track($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peerforumid = $this->get_new_parentid('peerforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('peerforum_track_prefs', $data);
    }

    protected function after_execute() {
        global $DB;

        // Add peerforum related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_peerforum', 'intro', null);

        // If the peerforum is of type 'single' and no discussion has been ignited
        // (non-userinfo backup/restore) create the discussion here, using peerforum
        // information as base for the initial post.
        $peerforumid = $this->task->get_activityid();
        $peerforumrec = $DB->get_record('peerforum', array('id' => $peerforumid));
        if ($peerforumrec->type == 'single' && !$DB->record_exists('peerforum_discussions', array('peerforum' => $peerforumid))) {
            // Create single discussion/lead post from peerforum data
            $sd = new stdclass();
            $sd->course   = $peerforumrec->course;
            $sd->peerforum    = $peerforumrec->id;
            $sd->name     = $peerforumrec->name;
            $sd->assessed = $peerforumrec->assessed;
            $sd->message  = $peerforumrec->intro;
            $sd->messageformat = $peerforumrec->introformat;
            $sd->messagetrust  = true;
            $sd->mailnow  = false;
            $sdid = peerforum_add_discussion($sd, null, null, $this->task->get_userid());
            // Mark the post as mailed
            $DB->set_field ('peerforum_posts','mailed', '1', array('discussion' => $sdid));
            // Copy all the files from mod_foum/intro to mod_peerforum/post
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->task->get_contextid(), 'mod_peerforum', 'intro');
            foreach ($files as $file) {
                $newfilerecord = new stdclass();
                $newfilerecord->filearea = 'post';
                $newfilerecord->itemid   = $DB->get_field('peerforum_discussions', 'firstpost', array('id' => $sdid));
                $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }

        // Add post related files, matching by itemname = 'peerforum_post'
        $this->add_related_files('mod_peerforum', 'post', 'peerforum_post');
        $this->add_related_files('mod_peerforum', 'attachment', 'peerforum_post');
    }
}
