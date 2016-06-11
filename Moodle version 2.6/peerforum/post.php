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
 * Edit and save a new post to a discussion
 *
 * @package mod-peerforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot .'/mod/peerforum/classes/peergrade_form.php');


$reply   = optional_param('reply', 0, PARAM_INT);
$peergrade   = optional_param('peergrade', 0, PARAM_INT);
$peerforum   = optional_param('peerforum', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);

$PAGE->set_url('/mod/peerforum/post.php', array(
        'reply' => $reply,
        'peergrade' => $peergrade,
        'peerforum' => $peerforum,
        'edit'  => $edit,
        'delete'=> $delete,
        'prune' => $prune,
        'name'  => $name,
        'confirm'=>$confirm,
        'groupid'=>$groupid,
        ));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'peerforum'=>$peerforum, 'edit'=>$edit, 'peergrade'=>$peergrade);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($peerforum)) {      // User is starting a new discussion in a peerforum
        if (! $peerforum = $DB->get_record('peerforum', array('id' => $peerforum))) {
            print_error('invalidpeerforumid', 'peerforum');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (! $parent = peerforum_get_post_full($reply)) {
            print_error('invalidparentpostid', 'peerforum');
        }
        if (! $discussion = $DB->get_record('peerforum_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'peerforum');
        }
        if (! $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum))) {
            print_error('invalidpeerforumid');
        }
    } else if (!empty($peergrade)) {      // User is writing a new peergrade
        if (! $parent = peerforum_get_post_full($peergrade)) {
            print_error('invalidparentpostid', 'peerforum');
        }
        if (! $discussion = $DB->get_record('peerforum_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'peerforum');
        }
        if (! $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum))) {
            print_error('invalidpeerforumid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $peerforum->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $peerforum);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = clean_param(get_referer(false), PARAM_LOCALURL);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'peerforum').'<br /><br />'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($peerforum)) {      // User is starting a new discussion in a peerforum
    if (! $peerforum = $DB->get_record("peerforum", array("id" => $peerforum))) {
        print_error('invalidpeerforumid', 'peerforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $peerforum->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! peerforum_user_can_post_discussion($peerforum, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = clean_param($_SERVER['HTTP_REFERER'], PARAM_LOCALURL);
                    redirect($CFG->wwwroot.'/enrol/index.php?id='.$course->id, get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostpeerforum', 'peerforum');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    if (isset($_SERVER["HTTP_REFERER"])) {
        $SESSION->fromurl = $_SERVER["HTTP_REFERER"];
    } else {
        $SESSION->fromurl = '';
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->peerforum         = $peerforum->id;
    $post->discussion    = 0;           // ie discussion # not defined yet
    $post->parent        = 0;
    $post->subject       = '';
    $post->userid        = $USER->id;
    $post->message       = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust  = 0;
    $post->peergraders   = 0;


    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($reply)) {      // User is writing a new reply

    if (! $parent = peerforum_get_post_full($reply)) {
        print_error('invalidparentpostid', 'peerforum');
    }
    if (! $discussion = $DB->get_record("peerforum_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'peerforum');
    }
    if (! $peerforum = $DB->get_record("peerforum", array("id" => $discussion->peerforum))) {
        print_error('invalidpeerforumid', 'peerforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $peerforum);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! peerforum_user_can_post($peerforum, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = clean_param($_SERVER['HTTP_REFERER'], PARAM_LOCALURL);
                redirect($CFG->wwwroot.'/enrol/index.php?id='.$course->id, get_string('youneedtoenrol'));
            }
        }
        print_error('nopostpeerforum', 'peerforum');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostpeerforum', 'peerforum');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostpeerforum', 'peerforum');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->peerforum       = $peerforum->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';
    $post->peergraders = 0;


    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'peerforum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($peergrade)) {      // User is writing a new peergrade
    if (! $parent = peerforum_get_post_full($peergrade)) {
        print_error('invalidparentpostid', 'peerforum');
    }
    if (! $discussion = $DB->get_record("peerforum_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'peerforum');
    }
    if (! $peerforum = $DB->get_record("peerforum", array("id" => $discussion->peerforum))) {
        print_error('invalidpeerforumid', 'peerforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $peerforum);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! peerforum_user_can_post($peerforum, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/peerforum/view.php?f=' . $peerforum->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('nopostpeerforum', 'peerforum');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostpeerforum', 'peerforum');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostpeerforum', 'peerforum');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->peerforum       = $peerforum->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';
    $post->peergraders = '';


    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'peerforum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = peerforum_get_post_full($edit)) {
        print_error('invalidpostid', 'peerforum');
    }
    if ($post->parent) {
        if (! $parent = peerforum_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'peerforum');
        }
    }

    if (! $discussion = $DB->get_record("peerforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'peerforum');
    }
    if (! $peerforum = $DB->get_record("peerforum", array("id" => $discussion->peerforum))) {
        print_error('invalidpeerforumid', 'peerforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $peerforum);

    if (!($peerforum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/peerforum:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'peerforum', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/peerforum:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'peerforum');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->peerforum  = $peerforum->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

}else if (!empty($delete)) {  // User is deleting a post

    if (! $post = peerforum_get_post_full($delete)) {
        print_error('invalidpostid', 'peerforum');
    }
    if (! $discussion = $DB->get_record("peerforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'peerforum');
    }
    if (! $peerforum = $DB->get_record("peerforum", array("id" => $discussion->peerforum))) {
        print_error('invalidpeerforumid', 'peerforum');
    }
    if (!$cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $peerforum->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $peerforum->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/peerforum:deleteownpost', $modcontext))
                || has_capability('mod/peerforum:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'peerforum');
    }


    $replycount = peerforum_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/peerforum:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "peerforum",
                      peerforum_go_back_to("discuss.php?d=$post->discussion"));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratingpeers', 'ratingpeer'),
                    peerforum_go_back_to("discuss.php?d=$post->discussion"));

        } else if ($replycount && !has_capability('mod/peerforum:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "peerforum",
                    peerforum_go_back_to("discuss.php?d=$post->discussion"));

        } else {
            if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($peerforum->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!",
                            peerforum_go_back_to("discuss.php?d=$post->discussion"));
                }
                peerforum_delete_discussion($discussion, false, $course, $cm, $peerforum);

                add_to_log($discussion->course, "peerforum", "delete discussion",
                           "view.php?id=$cm->id", "$peerforum->id", $cm->id);

                redirect("view.php?f=$discussion->peerforum");

            } else if (peerforum_delete_post($post, has_capability('mod/peerforum:deleteanypost', $modcontext),
                $course, $cm, $peerforum)) {

                if ($peerforum->type == 'single') {
                    // Single discussion peerforums are an exception. We show
                    // the peerforum itself since it only has one discussion
                    // thread.
                    $discussionurl = "view.php?f=$peerforum->id";
                } else {
                    $discussionurl = "discuss.php?d=$post->discussion";
                }

                add_to_log($discussion->course, "peerforum", "delete post", $discussionurl, "$post->id", $cm->id);

                redirect(peerforum_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'peerforum');
            }
        }


    } else { // User just asked to delete something

        peerforum_set_return();
        $PAGE->navbar->add(get_string('delete', 'peerforum'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        if ($replycount) {
            if (!has_capability('mod/peerforum:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "peerforum",
                      peerforum_go_back_to("discuss.php?d=$post->discussion"));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($peerforum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "peerforum", $replycount+1),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'#p'.$post->id);

            peerforum_print_post($post, $discussion, $peerforum, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $peerforumtracked = peerforum_tp_is_tracked($peerforum);
                $posts = peerforum_get_all_discussion_posts($discussion->id, "created ASC", $peerforumtracked);
                peerforum_print_posts_nested($course, $cm, $peerforum, $discussion, $post, false, false, $peerforumtracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($peerforum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "peerforum", $replycount),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'#p'.$post->id);
            peerforum_print_post($post, $discussion, $peerforum, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning

    if (!$post = peerforum_get_post_full($prune)) {
        print_error('invalidpostid', 'peerforum');
    }
    if (!$discussion = $DB->get_record("peerforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'peerforum');
    }
    if (!$peerforum = $DB->get_record("peerforum", array("id" => $discussion->peerforum))) {
        print_error('invalidpeerforumid', 'peerforum');
    }
    if ($peerforum->type == 'single') {
        print_error('cannotsplit', 'peerforum');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'peerforum');
    }
    if (!$cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $peerforum->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/peerforum:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'peerforum');
    }

    if (!empty($name) && confirm_sesskey()) {    // User has confirmed the prune

        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->peerforum        = $discussion->peerforum;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('peerforum_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record("peerforum_posts", $newpost);

        peerforum_change_discussionid($post->id, $newid);

        // update last post in each discussion
        peerforum_discussion_update_last_post($discussion->id);
        peerforum_discussion_update_last_post($newid);

        add_to_log($discussion->course, "peerforum", "prune post",
                       "discuss.php?d=$newid", "$post->id", $cm->id);

        redirect(peerforum_go_back_to("discuss.php?d=$newid"));

    } else { // User just asked to prune something

        $course = $DB->get_record('course', array('id' => $peerforum->course));

        $PAGE->set_cm($cm);
        $PAGE->set_context($modcontext);
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/peerforum/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string("prune", "peerforum"));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($peerforum->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'peerforum'), 3);
        echo '<center>';

        include('prune.html');

        peerforum_print_post($post, $discussion, $peerforum, $cm, $course, false, false, false);
        echo '</center>';
    }
    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($peerforum->course);
}


// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($peerforum->maxattachments)) {  // TODO - delete this once we add a field to the peerforum table
    $peerforum->maxattachments = 3;
}

$thresholdwarning = peerforum_check_throttling($peerforum, $cm);
/*$mform_post = new mod_peerforum_post_form('post.php', array('course' => $course,
                                                        'cm' => $cm,
                                                        'coursecontext' => $coursecontext,
                                                        'modcontext' => $modcontext,
                                                        'peerforum' => $peerforum,
                                                        'post' => $post,
                                                        'thresholdwarning' => $thresholdwarning,
                                                        'edit' => $edit), 'post', '', array('id' => 'mformpeerforum'));
*/


if(!empty($reply) || !empty($edit)){
    $mform_post = new mod_peerforum_post_form('post.php', array('course' => $course,
                                                            'cm' => $cm,
                                                            'coursecontext' => $coursecontext,
                                                            'modcontext' => $modcontext,
                                                            'peerforum' => $peerforum,
                                                            'post' => $post,
                                                            'thresholdwarning' => $thresholdwarning,
                                                            'edit' => $edit), 'post', '', array('id' => 'mformpeerforum'));
}

if(!empty($peergrade)){
    $mform_post = new mod_peergrade_post_form('post.php', array('course' => $course,
                                                            'cm' => $cm,
                                                            'coursecontext' => $coursecontext,
                                                            'modcontext' => $modcontext,
                                                            'peerforum' => $peerforum,
                                                            'post' => $post,
                                                            'thresholdwarning' => $thresholdwarning,
                                                            'peergrade' => $peergrade), 'post', '', array('id' => 'mformpeerforum'));
}
if(empty($peergrade) && empty($reply) && empty($edit)){
    $mform_post = new mod_peerforum_post_form('post.php', array('course' => $course,
                                                            'cm' => $cm,
                                                            'coursecontext' => $coursecontext,
                                                            'modcontext' => $modcontext,
                                                            'peerforum' => $peerforum,
                                                            'post' => $post,
                                                            'thresholdwarning' => $thresholdwarning,
                                                            'edit' => $edit), 'post', '', array('id' => 'mformpeerforum'));

}

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_peerforum', 'attachment', empty($post->id)?null:$post->id, mod_peerforum_post_form::attachment_options($peerforum));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'peerforum', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'peerforum', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "peerforum");
    $formheading = get_string('reply', 'peerforum');
}
if (!empty($parent) && !empty($peergrade)) {
    $heading = get_string("yourgradeandfeedback", "peerforum");
    $formheading = get_string('peergrade', 'peerforum');
} else {
    if ($peerforum->type == 'qanda') {
        $heading = get_string('yournewquestion', 'peerforum');
    } else {
        $heading = get_string('yournewtopic', 'peerforum');
    }
}

if (peerforum_is_subscribed($USER->id, $peerforum->id)) {
    $subscribe = true;

} else if (peerforum_user_has_posted($peerforum->id, 0, $USER->id)) {
    $subscribe = false;

} else {
    // user not posted yet - use subscription default specified in profile
    $subscribe = !empty($USER->autosubscribe);
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_peerforum', 'post', $postid, mod_peerforum_post_form::editor_options($modcontext, $postid), $post->message);
$mform_post->set_data(array(        'attachments'=>$draftitemid,
                                    'general'=>$heading,
                                    'subject'=>$post->subject,
                                    'message'=>array(
                                        'text'=>$currenttext,
                                        'format'=>empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
                                        'itemid'=>$draftid_editor
                                    ),
                                    'subscribe'=>$subscribe?1:0,
                                    'mailnow'=>!empty($post->mailnow),
                                    'userid'=>$post->userid,
                                    'parent'=>$post->parent,
                                    'discussion'=>$post->discussion,
                                    'course'=>$course->id) +
                                    $page_params +

                            (isset($post->format)?array(
                                    'format'=>$post->format):
                                array())+

                            (isset($discussion->timestart)?array(
                                    'timestart'=>$discussion->timestart):
                                array())+

                            (isset($discussion->timeend)?array(
                                    'timeend'=>$discussion->timeend):
                                array())+

                            (isset($post->groupid)?array(
                                    'groupid'=>$post->groupid):
                                array())+

                            (isset($discussion->id)?
                                    array('discussion'=>$discussion->id):
                                    array()));

if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/peerforum/view.php?f=$peerforum->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    $contextcheck = isset($fromform->groupinfo) && has_capability('mod/peerforum:movediscussions', $modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('peerforum_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/peerforum:replypost', $modcontext)
                            || has_capability('mod/peerforum:startdiscussion', $modcontext))) ||
                            has_capability('mod/peerforum:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'peerforum');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if ($contextcheck) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }
            $DB->set_field('peerforum_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; //realpost
        $updatepost->peerforum = $peerforum->id;
        if (!peerforum_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "peerforum", $errordestination);
        }

        // MDL-11818
        if (($peerforum->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating peerforum intro
            $peerforum->intro = $updatepost->message;
            $peerforum->timemodified = time();
            $DB->update_record("peerforum", $peerforum);
        }

        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />'.get_string("postupdated", "peerforum");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />'.get_string("editedpostupdated", "peerforum", fullname($realuser));
        }

        if ($subscribemessage = peerforum_post_subscription($fromform, $peerforum)) {
            $timemessage = 4;
        }
        if ($peerforum->type == 'single') {
            // Single discussion peerforums are an exception. We show
            // the peerforum itself since it only has one discussion
            // thread.
            $discussionurl = "view.php?f=$peerforum->id";
        } else {
            $discussionurl = "discuss.php?d=$discussion->id#p$fromform->id";
        }
        add_to_log($course->id, "peerforum", "update post",
                "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

        redirect(peerforum_go_back_to("$discussionurl"), $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        peerforum_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->peerforum=$peerforum->id;

        $fromform->peergraders = 0;

        if ($fromform->id = peerforum_add_new_post($addpost, $mform_post, $message)) {

            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($subscribemessage = peerforum_post_subscription($fromform, $peerforum)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "peerforum");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "peerforum") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "peerforum", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($peerforum->type == 'single') {
                // Single discussion peerforums are an exception. We show
                // the peerforum itself since it only has one discussion
                // thread.
                $discussionurl = "view.php?f=$peerforum->id";
            } else {
                $discussionurl = "discuss.php?d=$discussion->id";
            }
            add_to_log($course->id, "peerforum", "add post",
                      "$discussionurl&amp;parent=$fromform->id", "$fromform->id", $cm->id);

            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($peerforum->completionreplies || $peerforum->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            //assign posts for user to peergrade
            $peergraders = assign_peergraders($USER, $fromform->id, $course->id);

            if($peergraders){
                $all_peergraders = implode(';',$peergraders);

                insert_peergraders($fromform->id, $all_peergraders);
            }

            redirect(peerforum_go_back_to("$discussionurl#p$fromform->id"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "peerforum", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // Before we add this we must check that the user will not exceed the blocking threshold.
        peerforum_check_blocking_threshold($thresholdwarning);

        if (!peerforum_user_can_post_discussion($peerforum, $fromform->groupid, -1, $cm, $modcontext)) {
            print_error('cannotcreatediscussion', 'peerforum');
        }
        // If the user has access all groups capability let them choose the group.
        if ($contextcheck) {
            $fromform->groupid = $fromform->groupinfo;
        }
        if (empty($fromform->groupid)) {
            $fromform->groupid = -1;
        }

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name    = $fromform->subject;

        $newstopic = false;
        if ($peerforum->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $message = '';
        if ($discussion->id = peerforum_add_discussion($discussion, $mform_post, $message)) {

            add_to_log($course->id, "peerforum", "add discussion",
                    "discuss.php?d=$discussion->id", "$discussion->id", $cm->id);

            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($fromform->mailnow) {
                $message .= get_string("postmailnow", "peerforum");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "peerforum") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "peerforum", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($subscribemessage = peerforum_post_subscription($discussion, $peerforum)) {
                $timemessage = 6;
            }

            // Update completion status
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($peerforum->completiondiscussions || $peerforum->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(peerforum_go_back_to("view.php?f=$fromform->peerforum"), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "peerforum", $errordestination);
        }

        exit;
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $peerforum are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("peerforum_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'peerforum', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($peerforum->type == "news") ? get_string("addanewtopic", "peerforum") :
                                                   get_string("addanewdiscussion", "peerforum");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $peerforum->name;
}
if ($peerforum->type == 'single') {
    // There is only one discussion thread for this peerforum type. We should
    // not show the discussion name (same as peerforum name in this case) in
    // the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = format_string($discussion->name).':';
}

$forcefocus = empty($reply) ? NULL : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discuss.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'peerforum'));
}

if (($post->parent) && ($peergrade)){
    $PAGE->navbar->add(get_string('peergrade', 'peerforum'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'peerforum'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerforum->name), 2);

// checkup
if (!empty($parent) && !peerforum_user_can_see_post($peerforum, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'peerforum');
}
if (empty($parent) && empty($edit) && !peerforum_user_can_post_discussion($peerforum, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'peerforum');
}

if ($peerforum->type == 'qanda'
            && !has_capability('mod/peerforum:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !peerforum_user_has_posted($peerforum->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','peerforum'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    peerforum_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('peerforum_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'peerforum');
    }

    peerforum_print_post($parent, $discussion, $peerforum, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($peerforum->type != 'qanda' || peerforum_user_can_see_discussion($peerforum, $discussion, $modcontext)) {
            $peerforumtracked = peerforum_tp_is_tracked($peerforum);
            $posts = peerforum_get_all_discussion_posts($discussion->id, "created ASC", $peerforumtracked);
            peerforum_print_posts_threaded($course, $cm, $peerforum, $discussion, $parent, 0, false, $peerforumtracked, $posts);
        }
    }
} else {
    if (!empty($peerforum->intro)) {
        echo $OUTPUT->box(format_module_intro('peerforum', $peerforum, $cm->id), 'generalbox', 'intro');

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir.'/plagiarismlib.php');
            echo plagiarism_print_disclosure($cm->id);
        }
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}
$mform_post->display();

echo $OUTPUT->footer();
