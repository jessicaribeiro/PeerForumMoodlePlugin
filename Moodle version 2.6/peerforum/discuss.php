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
 * @package mod-peerforum
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

/// move discussion if requested
    if ($move > 0 and confirm_sesskey()) {
        $return = $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id;

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

        if (!$cmto = get_coursemodule_from_instance('peerforum', $peerforumto->id, $course->id)) {
            print_error('cannotmovetonotfound', 'peerforum', $return);
        }

        if (!coursemodule_visible_for_user($cmto)) {
            print_error('cannotmovenotvisible', 'peerforum', $return);
        }

        require_capability('mod/peerforum:startdiscussion', context_module::instance($cmto->id));

        if (!peerforum_move_attachments($discussion, $peerforum->id, $peerforumto->id)) {
            echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
        }
        $DB->set_field('peerforum_discussions', 'peerforum', $peerforumto->id, array('id' => $discussion->id));
        $DB->set_field('peerforum_read', 'peerforumid', $peerforumto->id, array('discussionid' => $discussion->id));
        add_to_log($course->id, 'peerforum', 'move discussion', "discuss.php?d=$discussion->id", $discussion->id, $cmto->id);

        // Delete the RSS files for the 2 peerforums to force regeneration of the feeds
        require_once($CFG->dirroot.'/mod/peerforum/rsslib.php');
        peerforum_rss_delete_file($peerforum);
        peerforum_rss_delete_file($peerforumto);

        redirect($return.'&moved=-1&sesskey='.sesskey());
    }

    add_to_log($course->id, 'peerforum', 'view discussion', "discuss.php?d=$discussion->id", $discussion->id, $cm->id);

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
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($peerforum->name), 2);

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
                        array(''=>get_string("movethisdiscussionto", "peerforum")),
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
        echo $OUTPUT->notification(get_string('discussionmoved', 'peerforum', format_string($peerforum->name,true)));
    }

    $canrate = has_capability('mod/peerforum:rate', $modcontext);
    $cangrade = has_capability('mod/peerforum:grade', $modcontext);

    peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post, $displaymode, $canreply, $canrate,  $cangrade, false, true, null);

    echo $OUTPUT->footer();
