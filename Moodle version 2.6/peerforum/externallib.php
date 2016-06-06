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
 * External peerforum API
 *
 * @package    mod_peerforum
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_peerforum_external extends external_api {

    /**
     * Describes the parameters for get_peerforum.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_peerforums_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of peerforums in a provided list of courses,
     * if no list is provided all peerforums that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the peerforum details
     * @since Moodle 2.5
     */
    public static function get_peerforums_by_courses($courseids = array()) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/peerforum/lib.php");

        $params = self::validate_parameters(self::get_peerforums_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            // Get all the courses the user can view.
            $courseids = array_keys(enrol_get_my_courses());
        } else {
            $courseids = $params['courseids'];
        }

        // Array to store the peerforums to return.
        $arrpeerforums = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Go through the courseids and return the peerforums.
            foreach ($courseids as $cid) {
                // Get the course context.
                $context = context_course::instance($cid);
                // Check the user can function in this context.
                self::validate_context($context);
                // Get the peerforums in this course.
                if ($peerforums = $DB->get_records('peerforum', array('course' => $cid))) {
                    // Get the modinfo for the course.
                    $modinfo = get_fast_modinfo($cid);
                    // Get the peerforum instances.
                    $peerforuminstances = $modinfo->get_instances_of('peerforum');
                    // Loop through the peerforums returned by modinfo.
                    foreach ($peerforuminstances as $peerforumid => $cm) {
                        // If it is not visible or present in the peerforums get_records call, continue.
                        if (!$cm->uservisible || !isset($peerforums[$peerforumid])) {
                            continue;
                        }
                        // Set the peerforum object.
                        $peerforum = $peerforums[$peerforumid];
                        // Get the module context.
                        $context = context_module::instance($cm->id);
                        // Check they have the view peerforum capability.
                        require_capability('mod/peerforum:viewdiscussion', $context);
                        // Format the intro before being returning using the format setting.
                        list($peerforum->intro, $peerforum->introformat) = external_format_text($peerforum->intro, $peerforum->introformat,
                            $context->id, 'mod_peerforum', 'intro', 0);
                        // Add the course module id to the object, this information is useful.
                        $peerforum->cmid = $cm->id;
                        // Add the peerforum to the array to return.
                        $arrpeerforums[$peerforum->id] = (array) $peerforum;
                    }
                }
            }
        }

        return $arrpeerforums;
    }

    /**
     * Describes the get_peerforum return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_peerforums_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'PeerForum id'),
                    'course' => new external_value(PARAM_TEXT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The peerforum type'),
                    'name' => new external_value(PARAM_TEXT, 'PeerForum name'),
                    'intro' => new external_value(PARAM_RAW, 'The peerforum intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id')
                ), 'peerforum'
            )
        );
    }

    /**
     * Describes the parameters for get_peerforum_discussions.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_peerforum_discussions_parameters() {
        return new external_function_parameters (
            array(
                'peerforumids' => new external_multiple_structure(new external_value(PARAM_INT, 'peerforum ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of PeerForum IDs', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Returns a list of peerforum discussions as well as a summary of the discussion
     * in a provided list of peerforums.
     *
     * @param array $peerforumids the peerforum ids
     * @return array the peerforum discussion details
     * @since Moodle 2.5
     */
    public static function get_peerforum_discussions($peerforumids) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/peerforum/lib.php");

        // Validate the parameter.
        $params = self::validate_parameters(self::get_peerforum_discussions_parameters(), array('peerforumids' => $peerforumids));
        $peerforumids = $params['peerforumids'];

        // Array to store the peerforum discussions to return.
        $arrdiscussions = array();
        // Keep track of the users we have looked up in the DB.
        $arrusers = array();

        // Loop through them.
        foreach ($peerforumids as $id) {
            // Get the peerforum object.
            $peerforum = $DB->get_record('peerforum', array('id' => $id), '*', MUST_EXIST);
            $course = get_course($peerforum->course);

            $modinfo = get_fast_modinfo($course);
            $peerforums  = $modinfo->get_instances_of('peerforum');
            $cm = $peerforums[$peerforum->id];

            // Get the module context.
            $modcontext = context_module::instance($cm->id);

            // Validate the context.
            self::validate_context($modcontext);

            require_capability('mod/peerforum:viewdiscussion', $modcontext);

            // Get the discussions for this peerforum.
            $params = array();

            $groupselect = "";
            $groupmode = groups_get_activity_groupmode($cm, $course);

            if ($groupmode and $groupmode != VISIBLEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                // Get all the discussions from all the groups this user belongs to.
                $usergroups = groups_get_user_groups($course->id);
                if (!empty($usergroups['0'])) {
                    list($sql, $params) = $DB->get_in_or_equal($usergroups['0']);
                    $groupselect = "AND (groupid $sql OR groupid = -1)";
                }
            }
            array_unshift($params, $id);

            $select = "peerforum = ? $groupselect";

            if ($discussions = $DB->get_records_select('peerforum_discussions', $select, $params, 'timemodified DESC')) {

                // Check if they can view full names.
                $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);
                // Get the unreads array, this takes a peerforum id and returns data for all discussions.
                $unreads = array();
                if ($cantrack = peerforum_tp_can_track_peerforums($peerforum)) {
                    if ($peerforumtracked = peerforum_tp_is_tracked($peerforum)) {
                        $unreads = peerforum_get_discussions_unread($cm);
                    }
                }
                // The peerforum function returns the replies for all the discussions in a given peerforum.
                $replies = peerforum_count_discussion_replies($id);

                foreach ($discussions as $discussion) {
                    // This function checks capabilities, timed discussions, groups and qanda peerforums posting.
                    if (!peerforum_user_can_see_discussion($peerforum, $discussion, $modcontext)) {
                        continue;
                    }

                    $usernamefields = user_picture::fields();
                    // If we don't have the users details then perform DB call.
                    if (empty($arrusers[$discussion->userid])) {
                        $arrusers[$discussion->userid] = $DB->get_record('user', array('id' => $discussion->userid),
                                $usernamefields, MUST_EXIST);
                    }
                    // Get the subject.
                    $subject = $DB->get_field('peerforum_posts', 'subject', array('id' => $discussion->firstpost), MUST_EXIST);
                    // Create object to return.
                    $return = new stdClass();
                    $return->id = (int) $discussion->id;
                    $return->course = $discussion->course;
                    $return->peerforum = $discussion->peerforum;
                    $return->name = $discussion->name;
                    $return->userid = $discussion->userid;
                    $return->groupid = $discussion->groupid;
                    $return->assessed = $discussion->assessed;
                    $return->timemodified = (int) $discussion->timemodified;
                    $return->usermodified = $discussion->usermodified;
                    $return->timestart = $discussion->timestart;
                    $return->timeend = $discussion->timeend;
                    $return->firstpost = (int) $discussion->firstpost;
                    $return->firstuserfullname = fullname($arrusers[$discussion->userid], $canviewfullname);
                    $return->firstuserimagealt = $arrusers[$discussion->userid]->imagealt;
                    $return->firstuserpicture = $arrusers[$discussion->userid]->picture;
                    $return->firstuseremail = $arrusers[$discussion->userid]->email;
                    $return->subject = $subject;
                    $return->numunread = '';
                    if ($cantrack && $peerforumtracked) {
                        if (isset($unreads[$discussion->id])) {
                            $return->numunread = (int) $unreads[$discussion->id];
                        }
                    }
                    // Check if there are any replies to this discussion.
                    if (!empty($replies[$discussion->id])) {
                         $return->numreplies = (int) $replies[$discussion->id]->replies;
                         $return->lastpost = (int) $replies[$discussion->id]->lastpostid;
                    } else { // No replies, so the last post will be the first post.
                        $return->numreplies = 0;
                        $return->lastpost = (int) $discussion->firstpost;
                    }
                    // Get the last post as well as the user who made it.
                    $lastpost = $DB->get_record('peerforum_posts', array('id' => $return->lastpost), '*', MUST_EXIST);
                    if (empty($arrusers[$lastpost->userid])) {
                        $arrusers[$lastpost->userid] = $DB->get_record('user', array('id' => $lastpost->userid),
                                $usernamefields, MUST_EXIST);
                    }
                    $return->lastuserid = $lastpost->userid;
                    $return->lastuserfullname = fullname($arrusers[$lastpost->userid], $canviewfullname);
                    $return->lastuserimagealt = $arrusers[$lastpost->userid]->imagealt;
                    $return->lastuserpicture = $arrusers[$lastpost->userid]->picture;
                    $return->lastuseremail = $arrusers[$lastpost->userid]->email;
                    // Add the discussion statistics to the array to return.
                    $arrdiscussions[$return->id] = (array) $return;
                }
            }
        }

        return $arrdiscussions;
    }

    /**
     * Describes the get_peerforum_discussions return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
     public static function get_peerforum_discussions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'PeerForum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'peerforum' => new external_value(PARAM_INT, 'The peerforum id'),
                    'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'groupid' => new external_value(PARAM_INT, 'Group id'),
                    'assessed' => new external_value(PARAM_INT, 'Is this assessed?'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                    'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                    'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                    'firstpost' => new external_value(PARAM_INT, 'The first post in the discussion'),
                    'firstuserfullname' => new external_value(PARAM_TEXT, 'The discussion creators fullname'),
                    'firstuserimagealt' => new external_value(PARAM_TEXT, 'The discussion creators image alt'),
                    'firstuserpicture' => new external_value(PARAM_INT, 'The discussion creators profile picture'),
                    'firstuseremail' => new external_value(PARAM_TEXT, 'The discussion creators email'),
                    'subject' => new external_value(PARAM_TEXT, 'The discussion subject'),
                    'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                    'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                        not available due to peerforum settings.'),
                    'lastpost' => new external_value(PARAM_INT, 'The id of the last post in the discussion'),
                    'lastuserid' => new external_value(PARAM_INT, 'The id of the user who made the last post'),
                    'lastuserfullname' => new external_value(PARAM_TEXT, 'The last person to posts fullname'),
                    'lastuserimagealt' => new external_value(PARAM_TEXT, 'The last person to posts image alt'),
                    'lastuserpicture' => new external_value(PARAM_INT, 'The last person to posts profile picture'),
                    'lastuseremail' => new external_value(PARAM_TEXT, 'The last person to posts email'),
                ), 'discussion'
            )
        );
    }
}
