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
 * Display user activity reports for a course
 *
 * @package mod-peerforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');
require_once($CFG->dirroot.'/ratingpeerpeer/lib.php');

$courseid  = optional_param('course', null, PARAM_INT); // Limit the posts to just this course
$userid = optional_param('id', $USER->id, PARAM_INT);        // User id whose posts we want to view
$mode = optional_param('mode', 'posts', PARAM_ALPHA);   // The mode to use. Either posts or discussions
$page = optional_param('page', 0, PARAM_INT);           // The page number to display
$perpage = optional_param('perpage', 5, PARAM_INT);     // The number of posts to display per page

if (empty($userid)) {
    if (!isloggedin()) {
        require_login();
    }
    $userid = $USER->id;
}

$discussionsonly = ($mode !== 'posts');
$isspecificcourse = !is_null($courseid);
$iscurrentuser = ($USER->id == $userid);

$url = new moodle_url('/mod/peerforum/user.php', array('id' => $userid));
if ($isspecificcourse) {
    $url->param('course', $courseid);
}
if ($discussionsonly) {
    $url->param('mode', 'discussions');
}

$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

if ($page != 0) {
    $url->param('page', $page);
}
if ($perpage != 5) {
    $url->param('perpage', $perpage);
}

add_to_log(($isspecificcourse)?$courseid:SITEID, "peerforum", "user report", 'user.php?'.$url->get_query_string(), $userid);

$user = $DB->get_record("user", array("id" => $userid), '*', MUST_EXIST);
$usercontext = context_user::instance($user->id, MUST_EXIST);
// Check if the requested user is the guest user
if (isguestuser($user)) {
    // The guest user cannot post, so it is not possible to view any posts.
    // May as well just bail aggressively here.
    print_error('invaliduserid');
}
// Make sure the user has not been deleted
if ($user->deleted) {
    $PAGE->set_title(get_string('userdeleted'));
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    echo $OUTPUT->heading($PAGE->title);
    echo $OUTPUT->footer();
    die;
}

$isloggedin = isloggedin();
$isguestuser = $isloggedin && isguestuser();
$isparent = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid'=>$USER->id, 'contextid'=>$usercontext->id));
$hasparentaccess = $isparent && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

// Check whether a specific course has been requested
if ($isspecificcourse) {
    // Get the requested course and its context
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $coursecontext = context_course::instance($courseid, MUST_EXIST);
    // We have a specific course to search, which we will also assume we are within.
    if ($hasparentaccess) {
        // A `parent` role won't likely have access to the course so we won't attempt
        // to enter it. We will however still make them jump through the normal
        // login hoops
        require_login();
        $PAGE->set_context($coursecontext);
        $PAGE->set_course($course);
    } else {
        // Enter the course we are searching
        require_login($course);
    }
    // Get the course ready for access checks
    $courses = array($courseid => $course);
} else {
    // We are going to search for all of the users posts in all courses!
    // a general require login here as we arn't actually within any course.
    require_login();
    $PAGE->set_context(context_system::instance());

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a peerforum will be returned.
    $courses = peerforum_get_courses_user_posted_in($user, $discussionsonly);
}

// Get the posts by the requested user that the current user can access.
$result = peerforum_get_posts_by_user($user, $courses, $isspecificcourse, $discussionsonly, ($page * $perpage), $perpage);

// Check whether there are not posts to display.
if (empty($result->posts)) {
    // Ok no posts to display means that either the user has not posted or there
    // are no posts made by the requested user that the current user is able to
    // see.
    // In either case we need to decide whether we can show personal information
    // about the requested user to the current user so we will execute some checks

    // First check the obvious, its the current user, a specific course has been
    // provided (require_login has been called), or they have a course contact role.
    // True to any of those and the current user can see the details of the
    // requested user.
    $canviewuser = ($iscurrentuser || $isspecificcourse || empty($CFG->forceloginforprofiles) || has_coursecontact_role($userid));
    // Next we'll check the caps, if the current user has the view details and a
    // specific course has been requested, or if they have the view all details
    $canviewuser = ($canviewuser || ($isspecificcourse && has_capability('moodle/user:viewdetails', $coursecontext) || has_capability('moodle/user:viewalldetails', $usercontext)));

    // If none of the above was true the next step is to check a shared relation
    // through some course
    if (!$canviewuser) {
        // Get all of the courses that the users have in common
        $sharedcourses = enrol_get_shared_courses($USER->id, $user->id, true);
        foreach ($sharedcourses as $sharedcourse) {
            // Check the view cap within the course context
            if (has_capability('moodle/user:viewdetails', context_course::instance($sharedcourse->id))) {
                $canviewuser = true;
                break;
            }
        }
        unset($sharedcourses);
    }

    // Prepare the page title
    $pagetitle = get_string('noposts', 'mod_peerforum');

    // Get the page heading
    if ($isspecificcourse) {
        $pageheading = format_string($course->shortname, true, array('context' => $coursecontext));
    } else {
        $pageheading = get_string('pluginname', 'mod_peerforum');
    }

    // Next we need to set up the loading of the navigation and choose a message
    // to display to the current user.
    if ($iscurrentuser) {
        // No need to extend the navigation it happens automatically for the
        // current user.
        if ($discussionsonly) {
            $notification = get_string('nodiscussionsstartedbyyou', 'peerforum');
        } else {
            $notification = get_string('nopostsmadebyyou', 'peerforum');
        }
    } else if ($canviewuser) {
        $PAGE->navigation->extend_for_user($user);
        $PAGE->navigation->set_userid_for_parent_checks($user->id); // see MDL-25805 for reasons and for full commit reference for reversal when fixed.
        $fullname = fullname($user);
        if ($discussionsonly) {
            $notification = get_string('nodiscussionsstartedby', 'peerforum', $fullname);
        } else {
            $notification = get_string('nopostsmadebyuser', 'peerforum', $fullname);
        }
    } else {
        // Don't extend the navigation it would be giving out information that
        // the current uesr doesn't have access to.
        $notification = get_string('cannotviewusersposts', 'peerforum');
        if ($isspecificcourse) {
            $url = new moodle_url('/course/view.php', array('id' => $courseid));
        } else {
            $url = new moodle_url('/');
        }
        navigation_node::override_active_url($url);
    }

    // Display a page letting the user know that there's nothing to display;
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($pageheading);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($pagetitle);
    echo $OUTPUT->notification($notification);
    if (!$url->compare($PAGE->url)) {
        echo $OUTPUT->continue_button($url);
    }
    echo $OUTPUT->footer();
    die;
}

// Post output will contain an entry containing HTML to display each post by the
// time we are done.
$postoutput = array();

$discussions = array();
foreach ($result->posts as $post) {
    $discussions[] = $post->discussion;
}
$discussions = $DB->get_records_list('peerforum_discussions', 'id', array_unique($discussions));

//todo Rather than retrieving the ratingpeers for each post individually it would be nice to do them in groups
//however this requires creating arrays of posts with each array containing all of the posts from a particular peerforum,
//retrieving the ratingpeers then reassembling them all back into a single array sorted by post.modified (descending)
$rm = new ratingpeer_manager();
$ratingpeeroptions = new stdClass;
$ratingpeeroptions->component = 'mod_peerforum';
$ratingpeeroptions->ratingpeerarea = 'post';
foreach ($result->posts as $post) {
    if (!isset($result->peerforums[$post->peerforum]) || !isset($discussions[$post->discussion])) {
        // Something very VERY dodgy has happened if we end up here
        continue;
    }
    $peerforum = $result->peerforums[$post->peerforum];
    $cm = $peerforum->cm;
    $discussion = $discussions[$post->discussion];
    $course = $result->courses[$discussion->course];

    $peerforumurl = new moodle_url('/mod/peerforum/view.php', array('id' => $cm->id));
    $discussionurl = new moodle_url('/mod/peerforum/discuss.php', array('d' => $post->discussion));

    // load ratingpeers
    if ($peerforum->assessed != RATINGPEER_AGGREGATE_NONE) {
        $ratingpeeroptions->context = $cm->context;
        $ratingpeeroptions->items = array($post);
        $ratingpeeroptions->aggregate = $peerforum->assessed;//the aggregation method
        $ratingpeeroptions->scaleid = $peerforum->scale;
        $ratingpeeroptions->userid = $user->id;
        $ratingpeeroptions->assesstimestart = $peerforum->assesstimestart;
        $ratingpeeroptions->assesstimefinish = $peerforum->assesstimefinish;
        if ($peerforum->type == 'single' or !$post->discussion) {
            $ratingpeeroptions->returnurl = $peerforumurl;
        } else {
            $ratingpeeroptions->returnurl = $discussionurl;
        }

        $updatedpost = $rm->get_ratingpeers($ratingpeeroptions);
        //updating the array this way because we're iteratingpeer over a collection and updating them one by one
        $result->posts[$updatedpost[0]->id] = $updatedpost[0];
    }

    $courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    $peerforumname = format_string($peerforum->name, true, array('context' => $cm->context));

    $fullsubjects = array();
    if (!$isspecificcourse && !$hasparentaccess) {
        $fullsubjects[] = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $courseshortname);
        $fullsubjects[] = html_writer::link($peerforumurl, $peerforumname);
    } else {
        $fullsubjects[] = html_writer::tag('span', $courseshortname);
        $fullsubjects[] = html_writer::tag('span', $peerforumname);
    }
    if ($peerforum->type != 'single') {
        $discussionname = format_string($discussion->name, true, array('context' => $cm->context));
        if (!$isspecificcourse && !$hasparentaccess) {
            $fullsubjects[] .= html_writer::link($discussionurl, $discussionname);
        } else {
            $fullsubjects[] .= html_writer::tag('span', $discussionname);
        }
        if ($post->parent != 0) {
            $postname = format_string($post->subject, true, array('context' => $cm->context));
            if (!$isspecificcourse && !$hasparentaccess) {
                $fullsubjects[] .= html_writer::link(new moodle_url('/mod/peerforum/discuss.php', array('d' => $post->discussion, 'parent' => $post->id)), $postname);
            } else {
                $fullsubjects[] .= html_writer::tag('span', $postname);
            }
        }
    }
    $post->subject = join(' -> ', $fullsubjects);
    // This is really important, if the strings are formatted again all the links
    // we've added will be lost.
    $post->subjectnoformat = true;
    $discussionurl->set_anchor('p'.$post->id);
    $fulllink = html_writer::link($discussionurl, get_string("postincontext", "peerforum"));

    $postoutput[] = peerforum_print_post($post, $discussion, $peerforum, $cm, $course, false, false, false, $fulllink, '', null, true, null, true);
}

$userfullname = fullname($user);

if ($discussionsonly) {
    $inpageheading = get_string('discussionsstartedby', 'mod_peerforum', $userfullname);
} else {
    $inpageheading = get_string('postsmadebyuser', 'mod_peerforum', $userfullname);
}
if ($isspecificcourse) {
    $a = new stdClass;
    $a->fullname = $userfullname;
    $a->coursename = format_string($course->shortname, true, array('context' => $coursecontext));
    $pageheading = $a->coursename;
    if ($discussionsonly) {
        $pagetitle = get_string('discussionsstartedbyuserincourse', 'mod_peerforum', $a);
    } else {
        $pagetitle = get_string('postsmadebyuserincourse', 'mod_peerforum', $a);
    }
} else {
    $pagetitle = $inpageheading;
    $pageheading = $userfullname;
}

$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->navigation->extend_for_user($user);
$PAGE->navigation->set_userid_for_parent_checks($user->id); // see MDL-25805 for reasons and for full commit reference for reversal when fixed.

echo $OUTPUT->header();
echo $OUTPUT->heading($inpageheading);
echo html_writer::start_tag('div', array('class' => 'user-content'));

if (!empty($postoutput)) {
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $url);
    foreach ($postoutput as $post) {
        echo $post;
        echo html_writer::empty_tag('br');
    }
    echo $OUTPUT->paging_bar($result->totalcount, $page, $perpage, $url);
} else if ($discussionsonly) {
    echo $OUTPUT->heading(get_string('nodiscussionsstartedby', 'peerforum', $userfullname));
} else {
    echo $OUTPUT->heading(get_string('noposts', 'peerforum'));
}

echo html_writer::end_tag('div');
echo $OUTPUT->footer();
