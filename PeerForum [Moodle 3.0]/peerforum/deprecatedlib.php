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
 * @package   mod_peerforum
 * @copyright 2014 Andrew Robert Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Deprecated a very long time ago.

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 * @deprecated since Moodle 1.1 - please do not use this function any more.
 */
function peerforum_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;
    debugging('peerforum_count_unrated_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    $sql = "SELECT COUNT(*) as num
              FROM {peerforum_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {peerforum_posts} p,
                       {rating} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_peerforum' AND
                       r.ratingarea = 'post'";
        $rated = $DB->get_record_sql($sql, $params);
        if ($rated) {
            if ($posts->num > $rated->num) {
                return $posts->num - $rated->num;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}


// Since Moodle 1.5.

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function peerforum_tp_count_discussion_read_records($userid, $discussionid) {
    debugging('peerforum_tp_count_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = isset($CFG->peerforum_oldpostdays) ? (time() - ($CFG->peerforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {peerforum_discussions} d '.
           'LEFT JOIN {peerforum_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {peerforum_posts} p ON p.discussion = d.id '.
                'AND (p.modified < ? OR p.id = r.postid) '.
           'WHERE d.id = ? ';

    return ($DB->count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Get all discussions started by a particular user in a course (or group)
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 * @deprecated since Moodle 1.5 - please do not use this function any more.
 */
function peerforum_get_user_discussions($courseid, $userid, $groupid=0) {
    debugging('peerforum_get_user_discussions() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else  {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.groupid, $allnames, u.email, u.picture, u.imagealt,
                                   f.type as peerforumtype, f.name as peerforumname, f.id as peerforumid
                              FROM {peerforum_discussions} d,
                                   {peerforum_posts} p,
                                   {user} u,
                                   {peerforum} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.peerforum = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}


// Since Moodle 1.6.

/**
 * Returns the count of posts for the provided peerforum and [optionally] group.
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function peerforum_tp_count_peerforum_posts($peerforumid, $groupid=false) {
    debugging('peerforum_tp_count_peerforum_posts() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;
    $params = array($peerforumid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {peerforum_posts} fp,{peerforum_discussions} fd '.
           'WHERE fd.peerforum = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and peerforum and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $peerforumid
 * @param int|bool $groupid
 * @return int
 * @deprecated since Moodle 1.6 - please do not use this function any more.
 */
function peerforum_tp_count_peerforum_read_records($userid, $peerforumid, $groupid=false) {
    debugging('peerforum_tp_count_peerforum_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->peerforum_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $peerforumid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {peerforum_posts} p
                    JOIN {peerforum_discussions} d ON d.id = p.discussion
                    LEFT JOIN {peerforum_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.peerforum = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}


// Since Moodle 1.7.

/**
 * Returns array of peerforum open modes.
 *
 * @return array
 * @deprecated since Moodle 1.7 - please do not use this function any more.
 */
function peerforum_get_open_modes() {
    debugging('peerforum_get_open_modes() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
    return array();
}


// Since Moodle 1.9.

/**
 * Gets posts with all info ready for peerforum_print_post
 * We pass peerforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $peerforumid
 * @return array
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function peerforum_get_child_posts($parent, $peerforumid) {
    debugging('peerforum_get_child_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $peerforumid AS peerforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {peerforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * Gets posts with all info ready for peerforum_print_post
 * We pass peerforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 * @deprecated since Moodle 1.9 MDL-13303 - please do not use this function any more.
 */
function peerforum_get_discussion_posts($discussion, $sort, $peerforumid) {
    debugging('peerforum_get_discussion_posts() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $peerforumid AS peerforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {peerforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}


// Since Moodle 2.0.

/**
 * Returns a list of ratings for a particular post - sorted.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratings or false
 * @deprecated since Moodle 2.0 MDL-21657 - please do not use this function any more.
 */
function peerforum_get_ratings($context, $postid, $sort = "u.firstname ASC") {
    debugging('peerforum_get_ratings() is deprecated.', DEBUG_DEVELOPER);
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_peerforum';
    $options->ratingarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new rating_manager();
    return $rm->get_all_ratings_for_item($options);
}

/**
 * Generate and return the track or no track link for a peerforum.
 *
 * @global object
 * @global object
 * @global object
 * @param object $peerforum the peerforum. Fields used are $peerforum->id and $peerforum->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 * @deprecated since Moodle 2.0 MDL-14632 - please do not use this function any more.
 */
function peerforum_get_tracking_link($peerforum, $messages=array(), $fakelink=true) {
    debugging('peerforum_get_tracking_link() is deprecated.', DEBUG_DEVELOPER);

    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackpeerforum, $strtrackpeerforum;

    if (isset($messages['trackpeerforum'])) {
         $strtrackpeerforum = $messages['trackpeerforum'];
    }
    if (isset($messages['notrackpeerforum'])) {
         $strnotrackpeerforum = $messages['notrackpeerforum'];
    }
    if (empty($strtrackpeerforum)) {
        $strtrackpeerforum = get_string('trackpeerforum', 'peerforum');
    }
    if (empty($strnotrackpeerforum)) {
        $strnotrackpeerforum = get_string('notrackpeerforum', 'peerforum');
    }

    if (peerforum_tp_is_tracked($peerforum)) {
        $linktitle = $strnotrackpeerforum;
        $linktext = $strnotrackpeerforum;
    } else {
        $linktitle = $strtrackpeerforum;
        $linktext = $strtrackpeerforum;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/peerforum/peerforum.js');
        $PAGE->requires->js_function_call('peerforum_produce_tracking_link', Array($peerforum->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/peerforum/settracking.php', array(
            'id' => $peerforum->id,
            'sesskey' => sesskey(),
        ));
    $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function peerforum_tp_count_discussion_unread_posts($userid, $discussionid) {
    debugging('peerforum_tp_count_discussion_unread_posts() is deprecated.', DEBUG_DEVELOPER);
    global $CFG, $DB;

    $cutoffdate = isset($CFG->peerforum_oldpostdays) ? (time() - ($CFG->peerforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {peerforum_posts} p '.
           'LEFT JOIN {peerforum_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Converts a peerforum to use the Roles System
 *
 * @deprecated since Moodle 2.0 MDL-23479 - please do not use this function any more.
 */
function peerforum_convert_to_roles() {
    debugging('peerforum_convert_to_roles() is deprecated and will not be replaced.', DEBUG_DEVELOPER);
}

/**
 * Returns all records in the 'peerforum_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $peerforumid
 * @return array
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function peerforum_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $peerforumid=-1) {
    debugging('peerforum_tp_get_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = '';
    $params = array();

    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($peerforumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'peerforumid = ?';
        $params[] = $peerforumid;
    }

    return $DB->get_records_select('peerforum_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 * @deprecated since Moodle 2.0 MDL-14113 - please do not use this function any more.
 */
function peerforum_tp_get_discussion_read_records($userid, $discussionid) {
    debugging('peerforum_tp_get_discussion_read_records() is deprecated and will not be replaced.', DEBUG_DEVELOPER);

    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('peerforum_read', $select, array($userid, $discussionid), '', $fields);
}

// Deprecated in 2.3.

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated since Moodle 2.3 MDL-33166 - please do not use this function any more.
 * @param stdClass $cp
 * @return void
 */
function peerforum_user_enrolled($cp) {
    debugging('peerforum_user_enrolled() is deprecated. Please use peerforum_user_role_assigned instead.', DEBUG_DEVELOPER);
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/peerforum:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {peerforum} f
         LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>PEERFORUM_INITIALSUBSCRIBE);

    $peerforums = $DB->get_records_sql($sql, $params);
    foreach ($peerforums as $peerforum) {
        \mod_peerforum\subscriptions::subscribe_user($cp->userid, $peerforum);
    }
}


// Deprecated in 2.4.

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use peerforum_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function peerforum_user_can_view_post($post, $course, $cm, $peerforum, $discussion, $user=null){
    debugging('peerforum_user_can_view_post() is deprecated. Please use peerforum_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return peerforum_user_can_see_post($peerforum, $discussion, $post, $user, $cm);
}


// Deprecated in 2.6.

/**
 * PEERFORUM_TRACKING_ON - deprecated alias for PEERFORUM_TRACKING_FORCED.
 * @deprecated since 2.6
 */
define('PEERFORUM_TRACKING_ON', 2);

/**
 * @deprecated since Moodle 2.6
 * @see shorten_text()
 */
function peerforum_shorten_post($message) {
    throw new coding_exception('peerforum_shorten_post() can not be used any more. Please use shorten_text($message, $CFG->peerforum_shortpost) instead.');
}

// Deprecated in 2.8.

/**
 * @global object
 * @param int $userid
 * @param object $peerforum
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::is_subscribed() instead
 */
function peerforum_is_subscribed($userid, $peerforum) {
    global $DB;
    debugging("peerforum_is_subscribed() has been deprecated, please use \\mod_peerforum\\subscriptions::is_subscribed() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of peerforum.
    if (is_numeric($peerforum)) {
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforum));
    }

    return mod_peerforum\subscriptions::is_subscribed($userid, $peerforum);
}

/**
 * Adds user to the subscriber list
 *
 * @param int $userid
 * @param int $peerforumid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::subscribe_user() instead
 */
function peerforum_subscribe($userid, $peerforumid, $context = null, $userrequest = false) {
    global $DB;
    debugging("peerforum_subscribe() has been deprecated, please use \\mod_peerforum\\subscriptions::subscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of peerforum.
    $peerforum = $DB->get_record('peerforum', array('id' => $peerforumid));
    \mod_peerforum\subscriptions::subscribe_user($userid, $peerforum, $context, $userrequest);
}

/**
 * Removes user from the subscriber list
 *
 * @param int $userid
 * @param int $peerforumid
 * @param context_module|null $context Module context, may be omitted if not known or if called for the current module set in page.
 * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
 * discussion subscriptions are removed too.
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::unsubscribe_user() instead
 */
function peerforum_unsubscribe($userid, $peerforumid, $context = null, $userrequest = false) {
    global $DB;
    debugging("peerforum_unsubscribe() has been deprecated, please use \\mod_peerforum\\subscriptions::unsubscribe_user() instead.",
            DEBUG_DEVELOPER);

    // Note: The new function does not take an integer form of peerforum.
    $peerforum = $DB->get_record('peerforum', array('id' => $peerforumid));
    \mod_peerforum\subscriptions::unsubscribe_user($userid, $peerforum, $context, $userrequest);
}

/**
 * Returns list of user objects that are subscribed to this peerforum.
 *
 * @param stdClass $course the course
 * @param stdClass $peerforum the peerforum
 * @param int $groupid group id, or 0 for all.
 * @param context_module $context the peerforum context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @param boolean $considerdiscussions Whether to take discussion subscriptions and unsubscriptions into consideration.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::fetch_subscribed_users() instead
  */
function peerforum_subscribed_users($course, $peerforum, $groupid = 0, $context = null, $fields = null) {
    debugging("peerforum_subscribed_users() has been deprecated, please use \\mod_peerforum\\subscriptions::fetch_subscribed_users() instead.",
            DEBUG_DEVELOPER);

    \mod_peerforum\subscriptions::fetch_subscribed_users($peerforum, $groupid, $context, $fields);
}

/**
 * Determine whether the peerforum is force subscribed.
 *
 * @param object $peerforum
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::is_forcesubscribed() instead
 */
function peerforum_is_forcesubscribed($peerforum) {
    debugging("peerforum_is_forcesubscribed() has been deprecated, please use \\mod_peerforum\\subscriptions::is_forcesubscribed() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($peerforum->forcesubscribe)) {
       $peerforum = $DB->get_field('peerforum', 'forcesubscribe', array('id' => $peerforum));
    }

    return \mod_peerforum\subscriptions::is_forcesubscribed($peerforum);
}

/**
 * Set the subscription mode for a peerforum.
 *
 * @param int $peerforumid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::set_subscription_mode() instead
 */
function peerforum_forcesubscribe($peerforumid, $value = 1) {
    debugging("peerforum_forcesubscribe() has been deprecated, please use \\mod_peerforum\\subscriptions::set_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    return \mod_peerforum\subscriptions::set_subscription_mode($peerforumid, $value);
}

/**
 * Get the current subscription mode for the peerforum.
 *
 * @param int|stdClass $peerforumid
 * @param mixed $value
 * @return bool
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::get_subscription_mode() instead
 */
function peerforum_get_forcesubscribed($peerforum) {
    debugging("peerforum_get_forcesubscribed() has been deprecated, please use \\mod_peerforum\\subscriptions::get_subscription_mode() instead.",
            DEBUG_DEVELOPER);

    global $DB;
    if (!isset($peerforum->forcesubscribe)) {
       $peerforum = $DB->get_field('peerforum', 'forcesubscribe', array('id' => $peerforum));
    }

    return \mod_peerforum\subscriptions::get_subscription_mode($peerforumid, $value);
}

/**
 * Get a list of peerforums in the specified course in which a user can change
 * their subscription preferences.
 *
 * @param stdClass $course The course from which to find subscribable peerforums.
 * @return array
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::is_subscribed in combination wtih
 * \mod_peerforum\subscriptions::fill_subscription_cache_for_course instead.
 */
function peerforum_get_subscribed_peerforums($course) {
    debugging("peerforum_get_subscribed_peerforums() has been deprecated, please see " .
              "\\mod_peerforum\\subscriptions::is_subscribed::() " .
              " and \\mod_peerforum\\subscriptions::fill_subscription_cache_for_course instead.",
              DEBUG_DEVELOPER);

    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {peerforum} f
                   LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND f.forcesubscribe <> ".PEERFORUM_DISALLOWSUBSCRIBE."
                   AND (f.forcesubscribe = ".PEERFORUM_FORCESUBSCRIBE." OR fs.id IS NOT NULL)";
    if ($subscribed = $DB->get_records_sql($sql, array($USER->id, $course->id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of peerforums that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable peerforums
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::get_unsubscribable_peerforums() instead
 */
function peerforum_get_optional_subscribed_peerforums() {
    debugging("peerforum_get_optional_subscribed_peerforums() has been deprecated, please use \\mod_peerforum\\subscriptions::get_unsubscribable_peerforums() instead.",
            DEBUG_DEVELOPER);

    return \mod_peerforum\subscriptions::get_unsubscribable_peerforums();
}

/**
 * Get the list of potential subscribers to a peerforum.
 *
 * @param object $peerforumcontext the peerforum context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 * @deprecated since Moodle 2.8 use \mod_peerforum\subscriptions::get_potential_subscribers() instead
 */
function peerforum_get_potential_subscribers($peerforumcontext, $groupid, $fields, $sort = '') {
    debugging("peerforum_get_potential_subscribers() has been deprecated, please use \\mod_peerforum\\subscriptions::get_potential_subscribers() instead.",
            DEBUG_DEVELOPER);

    \mod_peerforum\subscriptions::get_potential_subscribers($peerforumcontext, $groupid, $fields, $sort);
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email body in plain text format.
 * @deprecated since Moodle 3.0 use \mod_peerforum\output\peerforum_post_email instead
 */
function peerforum_make_mail_text($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto, $bare = false, $replyaddress = null) {
    global $PAGE;
    $renderable = new \mod_peerforum\output\peerforum_post_email(
        $course,
        $cm,
        $peerforum,
        $discussion,
        $post,
        $userfrom,
        $userto,
        peerforum_user_can_post($peerforum, $discussion, $userto, $cm, $course)
        );

    $modcontext = context_module::instance($cm->id);
    $renderable->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    if ($bare) {
        $renderer = $PAGE->get_renderer('mod_peerforum', 'emaildigestfull', 'textemail');
    } else {
        $renderer = $PAGE->get_renderer('mod_peerforum', 'email', 'textemail');
    }

    debugging("peerforum_make_mail_text() has been deprecated, please use the \mod_peerforum\output\peerforum_post_email renderable instead.",
            DEBUG_DEVELOPER);

    return $renderer->render($renderable);
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param string $replyaddress The inbound address that a user can reply to the generated e-mail with. [Since 2.8].
 * @return string The email text in HTML format
 * @deprecated since Moodle 3.0 use \mod_peerforum\output\peerforum_post_email instead
 */
function peerforum_make_mail_html($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto, $replyaddress = null) {
    return peerforum_make_mail_post($course,
        $cm,
        $peerforum,
        $discussion,
        $post,
        $userfrom,
        $userto,
        peerforum_user_can_post($peerforum, $discussion, $userto, $cm, $course)
    );
}

/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $rate
 * @param string $footer
 * @return string
 * @deprecated since Moodle 3.0 use \mod_peerforum\output\peerforum_post_email instead
 */
function peerforum_make_mail_post($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto,
                              $ownpost=false, $reply=false, $link=false, $rate=false, $footer="") {
    global $PAGE;
    $renderable = new \mod_peerforum\output\peerforum_post_email(
        $course,
        $cm,
        $peerforum,
        $discussion,
        $post,
        $userfrom,
        $userto,
        $reply);

    $modcontext = context_module::instance($cm->id);
    $renderable->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    // Assume that this is being used as a standard peerforum email.
    $renderer = $PAGE->get_renderer('mod_peerforum', 'email', 'htmlemail');

    debugging("peerforum_make_mail_post() has been deprecated, please use the \mod_peerforum\output\peerforum_post_email renderable instead.",
            DEBUG_DEVELOPER);

    return $renderer->render($renderable);
}
