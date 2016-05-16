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
 * @package mod-peerforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // peerforum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single peerforum)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string

    $params = array();
    if ($id) {
        $params['id'] = $id;
    } else {
        $params['f'] = $f;
    }
    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/peerforum/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('peerforum', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $peerforum = $DB->get_record("peerforum", array("id" => $cm->instance))) {
            print_error('invalidpeerforumid', 'peerforum');
        }
        if ($peerforum->type == 'single') {
            $PAGE->set_pagetype('mod-peerforum-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strpeerforums = get_string("modulenameplural", "peerforum");
        $strpeerforum = get_string("modulename", "peerforum");
    } else if ($f) {

        if (! $peerforum = $DB->get_record("peerforum", array("id" => $f))) {
            print_error('invalidpeerforumid', 'peerforum');
        }
        if (! $course = $DB->get_record("course", array("id" => $peerforum->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strpeerforums = get_string("modulenameplural", "peerforum");
        $strpeerforum = get_string("modulename", "peerforum");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(peerforum_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->peerforum_enablerssfeeds) && $peerforum->rsstype && $peerforum->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($peerforum->name);
        rss_add_http_header($context, 'mod_peerforum', $peerforum, $rsstitle);
    }

    // Mark viewed if required
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

/// Print header.

    $PAGE->set_title($peerforum->name);
    $PAGE->add_body_class('peerforumtype-'.$peerforum->type);
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/peerforum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'peerforum'));
    }

    echo $OUTPUT->heading(format_string($peerforum->name), 2);
    if (!empty($peerforum->intro) && $peerforum->type != 'single' && $peerforum->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('peerforum', $peerforum, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/peerforum/view.php?id=' . $cm->id);

/// Okay, we can show the discussions. Log the peerforum view.
    if ($cm->id) {
        add_to_log($course->id, "peerforum", "view peerforum", "view.php?id=$cm->id", "$peerforum->id", $cm->id);
    } else {
        add_to_log($course->id, "peerforum", "view peerforum", "view.php?f=$peerforum->id", "$peerforum->id");
    }

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion peerforum, we need to print the display
    // mode control.
    if ($peerforum->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('peerforum_discussions', array('peerforum'=>$peerforum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("peerforum_displaymode", $mode);
            }
            $displaymode = get_user_preferences("peerforum_displaymode", $CFG->peerforum_displaymode);
            peerforum_print_mode_form($peerforum->id, $displaymode, $peerforum->type);
        }
    }

    if (!empty($peerforum->blockafter) && !empty($peerforum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $peerforum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$peerforum->blockperiod);
        echo $OUTPUT->notification(get_string('thispeerforumisthrottled', 'peerforum', $a));
    }

    if ($peerforum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','peerforum'));
    }

    switch ($peerforum->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'peerforum'));
            }
            if (! $post = peerforum_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'peerforum');
            }
            if ($mode) {
                set_user_preference("peerforum_displaymode", $mode);
            }

            $canreply    = peerforum_user_can_post($peerforum, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/peerforum:rate', $context);
            $displaymode = get_user_preferences("peerforum_displaymode", $CFG->peerforum_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (peerforum_user_can_post_discussion($peerforum, null, -1, $cm)) {
                print_string("allowsdiscussions", "peerforum");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                peerforum_print_latest_discussions($course, $peerforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                peerforum_print_latest_discussions($course, $peerforum, -1, 'header', '', -1, -1, $page, $CFG->peerforum_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                peerforum_print_latest_discussions($course, $peerforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                peerforum_print_latest_discussions($course, $peerforum, -1, 'header', '', -1, -1, $page, $CFG->peerforum_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                peerforum_print_latest_discussions($course, $peerforum, 0, 'plain', '', -1, -1, -1, 0, $cm);
            } else {
                peerforum_print_latest_discussions($course, $peerforum, -1, 'plain', '', -1, -1, $page, $CFG->peerforum_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                peerforum_print_latest_discussions($course, $peerforum, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                peerforum_print_latest_discussions($course, $peerforum, -1, 'header', '', -1, -1, $page, $CFG->peerforum_manydiscussions, $cm);
            }


            break;
    }

    echo $OUTPUT->footer($course);
