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
 * This page receives non-ajax peergrade submissions
 *
 * It is similar to ratepeer_ajax.php. Unlike ratepeer_ajax.php a return url is required.
 *
 * @package    core_peergrade
 * @category   peergrade
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

$contextid   = required_param('contextid', PARAM_INT);
$component   = required_param('component', PARAM_COMPONENT);
$peergradearea  = required_param('peergradearea', PARAM_AREA);
$itemid      = required_param('itemid', PARAM_INT);
$peergradescaleid     = required_param('peergradescaleid', PARAM_INT);
//$userpeergrade  = required_param('peergrade', PARAM_INT);
$userpeergrade = optional_param('peergrade', null, PARAM_INT);
$peergradeduserid = required_param('peergradeduserid', PARAM_INT); // Which user is being ratedpeer. Required to update their grade.
$returnurl   = required_param('returnurl', PARAM_LOCALURL); // Required for non-ajax requests.
$feedback   = required_param('feedback', PARAM_TEXT); // Required for non-ajax requests.
$peerforumid = required_param('peerforumid', PARAM_INT);

$result = new stdClass;

global $USER, $COURSE, $DB;

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$contextid = null; // Now we have a context object, throw away the id from the user.
$PAGE->set_context($context);
$PAGE->set_url('/peergrade/peergrade.php', array('contextid' => $context->id));


if (!confirm_sesskey() || !has_capability('mod/peerforum:peergrade', $context)) {
    print_error('peergradepermissiondenied', 'peergrade');
}

$exists_feedback = false;
$feedback = PEERGRADE_UNSET_FEEDBACK;

$edit = false;


//Submit the written feedback
if(isset($_POST['postpeergrademenusubmit'.$itemid])){

    if (isset($_POST['feedbacktext'.$itemid])) {
        if(strlen(trim($_POST['feedbacktext'.$itemid]))){

            $feedback = htmlspecialchars($_POST['feedbacktext'.$itemid]);
            $exists_feedback = true;


        } else {
            $feedback = PEERGRADE_UNSET_FEEDBACK;
            $exists_feedback = false;
        }
    }
}

if(isset($_POST['editpeergrade'.$itemid])){
    $returnurl = $returnurl.'&editpostid='.$itemid.'#p'.$itemid;

    redirect($returnurl);

}

// Check the module peergrade permissions.
// Doing this check here rather than within peergrade_manager::get_peergrades() so we can choose how to handle the error.
$pm = new peergrade_manager();
$pluginpermissionsarray = $pm->get_plugin_permissions_array($context->id, $component, $peergradearea);

if (!$pluginpermissionsarray['peergrade']) {
    print_error('peergradepermissiondenied', 'peergrade');
} else {
    $params = array(
        'context'     => $context,
        'component'   => $component,
        'peergradearea'  => $peergradearea,
        'itemid'      => $itemid,
        'peergradescaleid'     => $peergradescaleid,
        'peergrade'      => $userpeergrade,
        'peergradeduserid' => $peergradeduserid,
        'feedback' => $feedback

    );
    if (!$pm->check_peergrade_is_valid($params)) {
        echo $OUTPUT->header();
        //echo get_string('peergradeinvalid', 'peerforum');
        echo $OUTPUT->footer();
        die();
    }
}

if (($userpeergrade != PEERGRADE_UNSET_PEERGRADE) && ($feedback != PEERGRADE_UNSET_FEEDBACK)) {

    $exists_peergrade = $DB->get_record('peerforum_peergrade', array('userid' => $USER->id, 'itemid' =>$itemid));
    if($exists_peergrade){
        $edit = true;
    } else {
        $edit = false;
    }

    $peergradeoptions = new stdClass;
    $peergradeoptions->context = $context;
    $peergradeoptions->component = $component;
    $peergradeoptions->peergradearea = $peergradearea;
    $peergradeoptions->itemid  = $itemid;
    $peergradeoptions->peergradescaleid = $peergradescaleid;
    $peergradeoptions->userid  = $USER->id;
    $peergradeoptions->feedback  = $feedback;

    $peergrade = new peergrade($peergradeoptions);
    $peergrade->update_peergrade($userpeergrade, $feedback);

    $pm->update_peergrader_posts($USER->id, $itemid, $COURSE->id);


} else { // Delete the peergrade if the user set to "PeerGrade..."
    /*$options = new stdClass;
    $options->contextid = $context->id;
    $options->component = $component;
    $options->peergradearea = $peergradearea;
    $options->userid = $USER->id;
    $options->itemid = $itemid;
    $options->feedback = $feedback;
    $options->peergradescaleid = $peergradescaleid;


    $pm->delete_peergrade_done($itemid, $USER->id, $COURSE->id);
    $pm->delete_peergrades($options);
    */

}

if (!empty($cm) && $context->contextlevel == CONTEXT_MODULE) {
    // Tell the module that its grades have changed.
    $modinstance = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    $modinstance->cmidnumber = $cm->id; // MDL-12961.
    $functionname = $cm->modname.'_update_grades';
    require_once($CFG->dirroot."/mod/{$cm->modname}/lib.php");
    if (function_exists($functionname)) {
        $functionname($modinstance, $peergradeduserid);
    }
}


if($userpeergrade == PEERGRADE_UNSET_PEERGRADE && $feedback == PEERGRADE_UNSET_FEEDBACK){
//    $erro =  $OUTPUT->error_text(get_string('error:nopeergrade', 'peerforum'));
    $erro = $OUTPUT->notification(get_string('error:nopeergrade', 'peerforum'), 'notifyproblem');

    $returnurl = $returnurl.'&editpostid=-2'.'#p'.$itemid;
    redirect($returnurl, $erro, 10);

} else if($userpeergrade != PEERGRADE_UNSET_PEERGRADE && $feedback == PEERGRADE_UNSET_FEEDBACK){
    global $DB;
    $enable_feedback = $DB->get_record('peerforum', array('id' => $peerforumid))->enablefeedback;

    if($enable_feedback){
        $erro = $OUTPUT->notification(get_string('error:nofeedback', 'peerforum'), 'notifyproblem');
        //$erro =  $OUTPUT->error_text(get_string('error:nofeedback', 'peerforum'));

        $returnurl = $returnurl.'&editpostid=-2'.'#p'.$itemid;
        redirect($returnurl, $erro, 10);
    } else {

        if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){

                $time = new stdclass();
                $time->courseid = $COURSE->id;
                $time->postid = $itemid;
                $time->userid = $USER->id;
                $time->timeassigned = time();
                $time->timemodified = time();

                $DB->insert_record("peerforum_time_assigned", $time);

                $str = $OUTPUT->notification(get_string('submited:peergrade', 'peerforum'), 'notifymessage');
        }
        if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
                $str = $OUTPUT->notification(get_string('submited:peergradeupdated', 'peerforum'), 'notifymessage');
        }

        $returnurl = $returnurl.'&editpostid=-1'.'#p'.$itemid;
        redirect($returnurl, $str, 10);
    }


} else if ($userpeergrade == PEERGRADE_UNSET_PEERGRADE && $feedback != PEERGRADE_UNSET_FEEDBACK){
    //$erro = $OUTPUT->error_text(get_string('error:nopeergrade', 'peerforum'));
    $erro = $OUTPUT->notification(get_string('error:nopeergrade', 'peerforum'), 'notifyproblem');

    $returnurl = $returnurl.'&editpostid=-2'.'#p'.$itemid;
    redirect($returnurl, $erro, 10);

    //redirect($returnurl);
} else if ($userpeergrade != PEERGRADE_UNSET_PEERGRADE && $feedback != PEERGRADE_UNSET_FEEDBACK){

    $maxtime = $CFG->maxeditingtime;

    if(!$edit){
        $edit = '<p>'.get_string("peergradeaddedtimeleft", "peerforum", format_time($maxtime)) . '</p>';
        $str = $OUTPUT->notification(get_string('submited:peergrade', 'peerforum').$edit, 'notifymessage');
    } else {

        if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){

            //$time_teacher = $DB->get_record('peerforum_time_assigned', array('postid' => $itemid, 'userid' => $USER->id));

            //if(!$time_teacher){

                $time = new stdclass();
                $time->courseid = $COURSE->id;
                $time->postid = $itemid;
                $time->userid = $USER->id;
                $time->timeassigned = time();
                $time->timemodified = time();

                $DB->insert_record("peerforum_time_assigned", $time);

                $str = $OUTPUT->notification(get_string('submited:peergrade', 'peerforum'), 'notifymessage');
        //    }
        }
        if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
                $str = $OUTPUT->notification(get_string('submited:peergradeupdated', 'peerforum'), 'notifymessage');
        }
    }

    $returnurl = $returnurl.'&editpostid=-1'.'#p'.$itemid;
    redirect($returnurl, $str, 10);

}
