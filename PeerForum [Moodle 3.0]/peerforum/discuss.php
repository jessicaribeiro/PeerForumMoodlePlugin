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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_peerforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another peerforum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

$currentpage = optional_param('page', 0, PARAM_INT);    // Used for pagination.


$url = new moodle_url('/mod/peerforum/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('peerforum_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/peerforum/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/peerforum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'peerforum');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->peerforum_enablerssfeeds) && $peerforum->rsstype && $peerforum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($peerforum->name);
    rss_add_http_header($modcontext, 'mod_peerforum', $peerforum, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id;

    if (!$peerforumto = $DB->get_record('peerforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'peerforum', $return);
    }

    require_capability('mod/peerforum:movediscussions', $modcontext);

    if ($peerforum->type == 'single') {
        print_error('cannotmovefromsinglepeerforum', 'peerforum', $return);
    }

    if (!$peerforumto = $DB->get_record('peerforum', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'peerforum', $return);
    }

    if ($peerforumto->type == 'single') {
        print_error('cannotmovetosinglepeerforum', 'peerforum', $return);
    }

    // Get target peerforum cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $peerforums = $modinfo->get_instances_of('peerforum');
    if (!array_key_exists($peerforumto->id, $peerforums)) {
        print_error('cannotmovetonotfound', 'peerforum', $return);
    }
    $cmto = $peerforums[$peerforumto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'peerforum', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/peerforum:startdiscussion', $destinationctx);

    if (!peerforum_move_attachments($discussion, $peerforum->id, $peerforumto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this peerforum and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_peerforum\subscriptions::fetch_subscribed_users(
        $peerforum,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the peerforum being moved to.
    \mod_peerforum\subscriptions::fill_subscription_cache($peerforumto->id);
    // And also for the discussion being moved.
    \mod_peerforum\subscriptions::fill_subscription_cache($peerforum->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_peerforum\subscriptions::is_subscribed($userid, $peerforumto, null, $cmto);
        $discussionsubscribed = \mod_peerforum\subscriptions::is_subscribed($userid, $peerforum, $discussion->id);
        $peerforumsubscribed = \mod_peerforum\subscriptions::is_subscribed($userid, $peerforum);

        if ($peerforumsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_peerforum\subscriptions::PEERFORUM_DISCUSSION_UNSUBSCRIBED;
        } else if (!$peerforumsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('peerforum_discussions', 'peerforum', $peerforumto->id, array('id' => $discussion->id));
    $DB->set_field('peerforum_read', 'peerforumid', $peerforumto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('peerforum_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->peerforum = $peerforumto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_peerforum\subscriptions::PEERFORUM_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/peerforum:viewdiscussion', $destinationctx, $userid)) {
                \mod_peerforum\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_peerforum\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'frompeerforumid' => $peerforum->id,
            'topeerforumid' => $peerforumto->id,
        )
    );
    $event = \mod_peerforum\event\discussion_moved::create($params);
    $event->add_record_snapshot('peerforum_discussions', $discussion);
    $event->add_record_snapshot('peerforum', $peerforum);
    $event->add_record_snapshot('peerforum', $peerforumto);
    $event->trigger();

    // Delete the RSS files for the 2 peerforums to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/peerforum/rsslib.php');
    peerforum_rss_delete_file($peerforum);
    peerforum_rss_delete_file($peerforumto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}

// Trigger discussion viewed event.
peerforum_discussion_view($modcontext, $peerforum, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('peerforum_displaymode', $mode);
}

$displaymode = get_user_preferences('peerforum_displaymode', $CFG->peerforum_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == PEERFORUM_MODE_FLATOLDEST or $displaymode == PEERFORUM_MODE_FLATNEWEST) {
        $displaymode = PEERFORUM_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = peerforum_get_post_full($parent)) {
    print_error("notexists", 'peerforum', "$CFG->wwwroot/mod/peerforum/view.php?f=$peerforum->id");
}

if (!peerforum_user_can_see_post($peerforum, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'peerforum', "$CFG->wwwroot/mod/peerforum/view.php?id=$peerforum->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->peerforum_usermarksread && peerforum_tp_can_track_peerforums($peerforum) && peerforum_tp_is_tracked($peerforum)) {
        if ($mark == 'read') {
            peerforum_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            peerforum_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = peerforum_search_form($course);

$peerforumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($peerforumnode)) {
    $peerforumnode = $PAGE->navbar;
} else {
    $peerforumnode->make_active();
}
$node = $peerforumnode->add(format_string($discussion->name), new moodle_url('/mod/peerforum/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_peerforum');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($peerforum->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/peerforum:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_peerforum\subscriptions::is_subscribable($peerforum)) {
        echo html_writer::div(
            peerforum_get_discussion_subscription_icon($peerforum, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo peerforum_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this peerforum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = peerforum_user_can_post($peerforum, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $peerforum->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = peerforum_get_discussion_neighbours($cm, $discussion, $peerforum);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix">';

if (!empty($CFG->enableportfolios) && has_capability('mod/peerforum:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('peerforum_portfolio_caller', array('discussionid' => $discussion->id), 'mod_peerforum');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_peerforum'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
peerforum_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($peerforum->type != 'single'
            && has_capability('mod/peerforum:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other peerforums. The discussion in a
    // single discussion peerforum can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['peerforum'])) {
        $peerforummenu = array();
        // Check peerforum types and eliminate simple discussions.
        $peerforumcheck = $DB->get_records('peerforum', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['peerforum'] as $peerforumcm) {
            if (!$peerforumcm->uservisible || !has_capability('mod/peerforum:startdiscussion',
                context_module::instance($peerforumcm->id))) {
                continue;
            }
            $section = $peerforumcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($peerforummenu[$section])) {
                $peerforummenu[$section] = array($sectionname => array());
            }
            $peerforumidcompare = $peerforumcm->instance != $peerforum->id;
            $peerforumtypecheck = $peerforumcheck[$peerforumcm->instance]->type !== 'single';
            if ($peerforumidcompare and $peerforumtypecheck) {
                $url = "/mod/peerforum/discuss.php?d=$discussion->id&move=$peerforumcm->instance&sesskey=".sesskey();
                $peerforummenu[$section][$sectionname][$url] = format_string($peerforumcm->name);
            }
        }
        if (!empty($peerforummenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($peerforummenu, '',
                    array('/mod/peerforum/discuss.php?d=' . $discussion->id => get_string("movethisdiscussionto", "peerforum")),
                    'peerforummenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}
echo '<div class="clearfloat">&nbsp;</div>';
echo "</div>";

if (!empty($peerforum->blockafter) && !empty($peerforum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $peerforum->blockafter;
    $a->blockperiod = get_string('secondstotime'.$peerforum->blockperiod);
    echo $OUTPUT->notification(get_string('thispeerforumisthrottled','peerforum',$a));
}

if ($peerforum->type == 'qanda' && !has_capability('mod/peerforum:viewqandawithoutposting', $modcontext) &&
            !peerforum_user_has_posted($peerforum->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','peerforum'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'peerforum', format_string($peerforum->name,true)), 'notifysuccess');
}

$canratepeer = has_capability('mod/peerforum:ratepeer', $modcontext);
$cangrade = has_capability('mod/peerforum:grade', $modcontext);

// Pagination of peerforum
$enable_pagination = $peerforum->pagination;

if($enable_pagination){
    $total_posts = count($DB->get_records('peerforum_posts', array('discussion' => $discussion->id)));

    $perpage = $peerforum->postsperpage;
    $start = $currentpage * $perpage;

    if ($start > $total_posts) {
          $currentpage = 0;
          $start = 0;
    }
    peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post, $displaymode, $canreply, $canratepeer,  $cangrade, false, true, null, null, $start, $perpage, $enable_pagination);

    //pagination of peerforum
    echo '</br>';
    $pageurl = new moodle_url('/mod/peerforum/discuss.php', array('d' => $discussion->id, 'page' => $currentpage));
    echo $OUTPUT->paging_bar($total_posts, $currentpage, $perpage, $pageurl);
} else {
    peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post, $displaymode, $canreply, $canratepeer,  $cangrade, false, true, null, null);
}

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_peerforum-subscriptiontoggle', 'Y.M.mod_peerforum.subscriptiontoggle.init');

echo $OUTPUT->footer();
