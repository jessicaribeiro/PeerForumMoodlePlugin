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
 * Subscribe to or unsubscribe from a peerforum or manage peerforum subscription mode
 *
 * This script can be used by either individual users to subscribe to or
 * unsubscribe from a peerforum (no 'mode' param provided), or by peerforum managers
 * to control the subscription mode (by 'mode' param).
 * This script can be called from a link in email so the sesskey is not
 * required parameter. However, if sesskey is missing, the user has to go
 * through a confirmation page that redirects the user back with the
 * sesskey.
 *
 * @package   mod_peerforum
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');

$id             = required_param('id', PARAM_INT);             // The peerforum to set subscription on.
$mode           = optional_param('mode', null, PARAM_INT);     // The peerforum's subscription mode.
$user           = optional_param('user', 0, PARAM_INT);        // The userid of the user to subscribe, defaults to $USER.
$discussionid   = optional_param('d', null, PARAM_INT);        // The discussionid to subscribe.
$sesskey        = optional_param('sesskey', null, PARAM_RAW);
$returnurl      = optional_param('returnurl', null, PARAM_RAW);

$url = new moodle_url('/mod/peerforum/subscribe.php', array('id'=>$id));
if (!is_null($mode)) {
    $url->param('mode', $mode);
}
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
    $discussion = $DB->get_record('peerforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
}
$PAGE->set_url($url);

$peerforum   = $DB->get_record('peerforum', array('id' => $id), '*', MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $peerforum->course), '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

if ($user) {
    require_sesskey();
    if (!has_capability('mod/peerforum:managesubscriptions', $context)) {
        print_error('nopermissiontosubscribe', 'peerforum');
    }
    $user = $DB->get_record('user', array('id' => $user), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode = $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$issubscribed = \mod_peerforum\subscriptions::is_subscribed($user->id, $peerforum, $discussionid, $cm);

// For a user to subscribe when a groupmode is set, they must have access to at least one group.
if ($groupmode && !$issubscribed && !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_get_all_groups($course->id, $USER->id)) {
        print_error('cannotsubscribe', 'peerforum');
    }
}

require_login($course, false, $cm);

if (is_null($mode) and !is_enrolled($context, $USER, '', true)) {   // Guests and visitors can't subscribe - only enrolled
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'peerforum').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/mod/peerforum/view.php', array('f'=>$id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // there should not be any links leading to this place, just redirect
        redirect(new moodle_url('/mod/peerforum/view.php', array('f'=>$id)), get_string('subscribeenrolledonly', 'peerforum'));
    }
}

$returnto = optional_param('backtoindex',0,PARAM_INT)
    ? "index.php?id=".$course->id
    : "view.php?f=$id";

if ($returnurl) {
    $returnto = $returnurl;
}

if (!is_null($mode) and has_capability('mod/peerforum:managesubscriptions', $context)) {
    require_sesskey();
    switch ($mode) {
        case PEERFORUM_CHOOSESUBSCRIBE : // 0
            \mod_peerforum\subscriptions::set_subscription_mode($peerforum->id, PEERFORUM_CHOOSESUBSCRIBE);
            redirect($returnto, get_string("everyonecannowchoose", "peerforum"), 1);
            break;
        case PEERFORUM_FORCESUBSCRIBE : // 1
            \mod_peerforum\subscriptions::set_subscription_mode($peerforum->id, PEERFORUM_FORCESUBSCRIBE);
            redirect($returnto, get_string("everyoneisnowsubscribed", "peerforum"), 1);
            break;
        case PEERFORUM_INITIALSUBSCRIBE : // 2
            if ($peerforum->forcesubscribe <> PEERFORUM_INITIALSUBSCRIBE) {
                $users = \mod_peerforum\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email', '');
                foreach ($users as $user) {
                    \mod_peerforum\subscriptions::subscribe_user($user->id, $peerforum, $context);
                }
            }
            \mod_peerforum\subscriptions::set_subscription_mode($peerforum->id, PEERFORUM_INITIALSUBSCRIBE);
            redirect($returnto, get_string("everyoneisnowsubscribed", "peerforum"), 1);
            break;
        case PEERFORUM_DISALLOWSUBSCRIBE : // 3
            \mod_peerforum\subscriptions::set_subscription_mode($peerforum->id, PEERFORUM_DISALLOWSUBSCRIBE);
            redirect($returnto, get_string("noonecansubscribenow", "peerforum"), 1);
            break;
        default:
            print_error(get_string('invalidforcesubscribe', 'peerforum'));
    }
}

if (\mod_peerforum\subscriptions::is_forcesubscribed($peerforum)) {
    redirect($returnto, get_string("everyoneisnowsubscribed", "peerforum"), 1);
}

$info = new stdClass();
$info->name  = fullname($user);
$info->peerforum = format_string($peerforum->name);

if ($issubscribed) {
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/peerforum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->peerforum = format_string($peerforum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmunsubscribediscussion', 'peerforum', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmunsubscribe', 'peerforum', format_string($peerforum->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid === null) {
        if (\mod_peerforum\subscriptions::unsubscribe_user($user->id, $peerforum, $context, true)) {
            redirect($returnto, get_string("nownotsubscribed", "peerforum", $info), 1);
        } else {
            print_error('cannotunsubscribe', 'peerforum', get_local_referer(false));
        }
    } else {
        if (\mod_peerforum\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion, $context)) {
            $info->discussion = $discussion->name;
            redirect($returnto, get_string("discussionnownotsubscribed", "peerforum", $info), 1);
        } else {
            print_error('cannotunsubscribe', 'peerforum', get_local_referer(false));
        }
    }

} else {  // subscribe
    if (\mod_peerforum\subscriptions::subscription_disabled($peerforum) && !has_capability('mod/peerforum:managesubscriptions', $context)) {
        print_error('disallowsubscribe', 'peerforum', get_local_referer(false));
    }
    if (!has_capability('mod/peerforum:viewdiscussion', $context)) {
        print_error('noviewdiscussionspermission', 'peerforum', get_local_referer(false));
    }
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/peerforum/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->peerforum = format_string($peerforum->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmsubscribediscussion', 'peerforum', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmsubscribe', 'peerforum', format_string($peerforum->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid == null) {
        \mod_peerforum\subscriptions::subscribe_user($user->id, $peerforum, $context, true);
        redirect($returnto, get_string("nowsubscribed", "peerforum", $info), 1);
    } else {
        $info->discussion = $discussion->name;
        \mod_peerforum\subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        redirect($returnto, get_string("discussionnowsubscribed", "peerforum", $info), 1);
    }
}
