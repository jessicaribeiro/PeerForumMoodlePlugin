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

/**
 * Define all the backup steps that will be used by the backup_peerforum_activity_task
 */

/**
 * Define the complete peerforum structure for backup, with file and id annotations
 */
class backup_peerforum_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated

        $peerforum = new backup_nested_element('peerforum', array('id'), array(
            'course', 'type', 'name', 'intro', 'introformat',
            'assessed', 'assesstimestart', 'assesstimefinish', 'scale',
            'maxbytes', 'maxattachments', 'forcesubscribe', 'trackingtype',
            'rsstype', 'rssarticles', 'timemodified', 'warnafter',
            'blockafter', 'blockperiod', 'completiondiscussions', 'completionreplies',
            'completionposts', 'displaywordcount', 'peergradescale', 'peergradeassessed',
            'peergradeassesstimestart', 'peergradeassesstimefinish', 'peergraders', 'peergradesvisibility',
            'whenpeergrades','feedbackvisibility', 'whenfeedback',
            'enablefeedback', 'remainanonymous', 'selectpeergraders', 'minpeergraders',
            'timetopeergrade', 'finalgrademode', 'studentpercentage', 'professorpercentage','allowpeergrade'));

        $discussions = new backup_nested_element('discussions');

        $discussion = new backup_nested_element('discussion', array('id'), array(
            'name', 'firstpost', 'userid', 'groupid',
            'assessed', 'peergradeassessed', 'timemodified', 'usermodified', 'timestart',
            'timeend'));

        $posts = new backup_nested_element('posts');

        $post = new backup_nested_element('post', array('id'), array(
            'parent', 'userid', 'created', 'modified',
            'mailed', 'subject', 'message', 'messageformat',
            'messagetrust', 'attachment', 'totalscore', 'mailnow'));

        $ratings = new backup_nested_element('ratings');

        $rating = new backup_nested_element('rating', array('id'), array(
            'component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated', 'timemodified'));

/******/
        $peergrades = new backup_nested_element('peergrades');

        $peergrade = new backup_nested_element('peergrade', array('id'), array(
            'component', 'peergradearea', 'peergradescaleid', 'value', 'userid', 'timecreated', 'timemodified', 'feedback'));

        $conflicts = new backup_nested_element('peergrade_conflicts');

        $conflict = new backup_nested_element('peergrade_conflict', array('id'), array(
            'courseid', 'conflictgroup', 'idstudents', 'namestudents'));

        $groups = new backup_nested_element('groupss');

        $group = new backup_nested_element('groups', array('id'), array(
            'courseid', 'groupid', 'studentsid', 'studentsname'));

        $peergradeusers = new backup_nested_element('peergrade_users');

        $peergradeuser = new backup_nested_element('peergrade_user', array('id'), array(
            'courseid', 'iduser', 'userblocked', 'poststopeergrade', 'postspeergradedone', 'postsblocked'));

        $peerforumpeergrades = new backup_nested_element('peergradings');

        $peerforumpeergrade = new backup_nested_element('peergrading', array('id'), array(
            'selectpeergradescale'));
/******/

        $discussionsubs = new backup_nested_element('discussion_subs');

        $discussionsub = new backup_nested_element('discussion_sub', array('id'), array(
            'userid',
            'preference',
        ));

        $subscriptions = new backup_nested_element('subscriptions');

        $subscription = new backup_nested_element('subscription', array('id'), array(
            'userid'));

        $digests = new backup_nested_element('digests');

        $digest = new backup_nested_element('digest', array('id'), array(
            'userid', 'maildigest'));

        $readposts = new backup_nested_element('readposts');

        $read = new backup_nested_element('read', array('id'), array(
            'userid', 'discussionid', 'postid', 'firstread',
            'lastread'));

        $trackedprefs = new backup_nested_element('trackedprefs');

        $track = new backup_nested_element('track', array('id'), array(
            'userid'));

        // Build the tree

        $peerforum->add_child($discussions);
        $discussions->add_child($discussion);

        $peerforum->add_child($subscriptions);
        $subscriptions->add_child($subscription);

        $peerforum->add_child($digests);
        $digests->add_child($digest);

        $peerforum->add_child($readposts);
        $readposts->add_child($read);

        $peerforum->add_child($trackedprefs);
        $trackedprefs->add_child($track);

        $peerforum->add_child($conflicts);
        $conflicts->add_child($conflict);

        $peerforum->add_child($peergradeusers);
        $peergradeusers->add_child($peergradeuser);

        $peerforum->add_child($peerforumpeergrades);
        $peerforumpeergrades->add_child($peerforumpeergrade);

        $discussion->add_child($posts);
        $posts->add_child($post);

        $post->add_child($ratings);
        $ratings->add_child($rating);

        $post->add_child($peergrades);
        $peergrades->add_child($peergrade);

        $discussion->add_child($discussionsubs);
        $discussionsubs->add_child($discussionsub);

        // Define sources

        $peerforum->set_source_table('peerforum', array('id' => backup::VAR_ACTIVITYID));

        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $discussion->set_source_sql('
                SELECT *
                  FROM {peerforum_discussions}
                 WHERE peerforum = ?',
                array(backup::VAR_PARENTID));

            // Need posts ordered by id so parents are always before childs on restore
            $post->set_source_table('peerforum_posts', array('discussion' => backup::VAR_PARENTID), 'id ASC');
            $discussionsub->set_source_table('peerforum_discussion_subs', array('discussion' => backup::VAR_PARENTID));

            $peergrade->set_source_table('peergrade', array('contextid'  => backup::VAR_CONTEXTID,
                                                      'component'  => backup_helper::is_sqlparam('mod_peerforum'),
                                                      'peergradearea' => backup_helper::is_sqlparam('post'),
                                                      'itemid'     => backup::VAR_PARENTID));
            $peergrade->set_source_alias('peergrade', 'value');


            $conflict->set_source_table('peergrade_conflicts', array('peerforum'  => backup::VAR_PARENTID));

            $peergradeuser->set_source_table('peergrade_users', array('peerforum'  => backup::VAR_PARENTID));

            $group->set_source_table('groups', array('peerforum'  => backup::VAR_PARENTID));

            $peergradeuser->set_source_table('peergrade_users', array());


            $subscription->set_source_table('peerforum_subscriptions', array('peerforum' => backup::VAR_PARENTID));

            $digest->set_source_table('peerforum_digests', array('peerforum' => backup::VAR_PARENTID));

            $read->set_source_table('peerforum_read', array('peerforumid' => backup::VAR_PARENTID));

            $track->set_source_table('peerforum_track_prefs', array('peerforumid' => backup::VAR_PARENTID));

            $rating->set_source_table('rating', array('contextid'  => backup::VAR_CONTEXTID,
                                                      'component'  => backup_helper::is_sqlparam('mod_peerforum'),
                                                      'ratingarea' => backup_helper::is_sqlparam('post'),
                                                      'itemid'     => backup::VAR_PARENTID));
            $rating->set_source_alias('rating', 'value');
        }

        // Define id annotations

        $peerforum->annotate_ids('scale', 'scale');

        $discussion->annotate_ids('group', 'groupid');

        $post->annotate_ids('user', 'userid');

        $discussionsub->annotate_ids('user', 'userid');

        $rating->annotate_ids('scale', 'scaleid');

        $rating->annotate_ids('user', 'userid');

        $peergrade->annotate_ids('peergradescale', 'peergradescaleid');

        $peergrade->annotate_ids('user', 'userid');

        $subscription->annotate_ids('user', 'userid');

        $digest->annotate_ids('user', 'userid');

        $read->annotate_ids('user', 'userid');

        $track->annotate_ids('user', 'userid');

        // Define file annotations

        $peerforum->annotate_files('mod_peerforum', 'intro', null); // This file area hasn't itemid

        $post->annotate_files('mod_peerforum', 'post', 'id');
        $post->annotate_files('mod_peerforum', 'attachment', 'id');

        // Return the root element (peerforum), wrapped into standard activity structure
        return $this->prepare_activity_structure($peerforum);
    }

}
