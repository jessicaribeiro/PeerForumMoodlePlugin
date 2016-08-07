<?php

/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');

$contextid   = required_param('contextid', PARAM_INT);
$component   = required_param('component', PARAM_COMPONENT);
$peergradearea  = required_param('peergradearea', PARAM_AREA);
$itemid      = required_param('itemid', PARAM_INT);
$peergradescaleid     = required_param('peergradescaleid', PARAM_INT);
$peergradeduserid = required_param('peergradeduserid', PARAM_INT); // Which user is being ratedpeer. Required to update their grade.
$returnurl   = required_param('returnurl', PARAM_LOCALURL); // Required for non-ajax requests.
$feedback   = required_param('feedback', PARAM_TEXT); // Required for non-ajax requests.
$peerforumid = required_param('peerforumid', PARAM_INT);

$criteria1 = optional_param('criteria1', null, PARAM_TEXT);
$criteria2 = optional_param('criteria2', null, PARAM_TEXT);
$criteria3 = optional_param('criteria3', null, PARAM_TEXT);

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

if(isset($_POST['editpeergrade'.$itemid])){
    //edit post
    $returnurl = $returnurl.'&editpostid='.$itemid.'#p'.$itemid;

    redirect($returnurl);
}

$exists_feedback = false;
$feedback = PEERGRADE_UNSET_FEEDBACK;

$edit = false;

$all_grades = array();
$time = time();

if(isset($_POST['menu1peergradecriteria'.$itemid])){
    $grade1 = $_POST['menu1peergradecriteria'.$itemid];
}

if(isset($_POST['menu2peergradecriteria'.$itemid])){
    $grade2 = $_POST['menu2peergradecriteria'.$itemid];
}

if(isset($_POST['menu3peergradecriteria'.$itemid])){
    $grade3 = $_POST['menu3peergradecriteria'.$itemid];
}

if(isset($grade1)){
    $all_grades[1] = PEERGRADE_UNSET_PEERGRADE;
}
if(isset($grade2)){
     $all_grades[2] = PEERGRADE_UNSET_PEERGRADE;
}
if(isset($grade3)){
     $all_grades[3] = PEERGRADE_UNSET_PEERGRADE;
}

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

if($feedback != PEERGRADE_UNSET_FEEDBACK){

    if(isset($grade1)){
        if($grade1 != PEERGRADE_UNSET_PEERGRADE){
            $exists_peergrade = $DB->get_record('peerforum_peergradecriteria', array('userid' => $USER->id, 'itemid' =>$itemid, 'criteria' => $criteria1));

            //insert/update into DB peerforum_peergradecriteria
            $data = new stdClass();

            $data->criteria  = $criteria1;
            $data->grade  = $grade1;

            if($exists_peergrade){
                $data->id = $exists_peergrade->id;
                $DB->update_record('peerforum_peergradecriteria', $data);

            } else {
                $data->itemid  = $itemid;
                $data->userid  = $USER->id;
                $data->contextid = $context->id;
                $data->component = $component;
                $data->peergradearea = $peergradearea;
                $data->peergradescaleid = $peergradescaleid;
                $data->timecreated = $time;
                $data->timemodified = $time;
                $data->feedback = $feedback;

                $DB->insert_record('peerforum_peergradecriteria', $data);

            }
            $all_grades[1] = $grade1;
        } else {
            //error - no grade to submit
            $str = $OUTPUT->notification(get_string('error:nopeergradecriteria', 'peerforum', $criteria1), 'notifyproblem');

            $returnurl = $returnurl.'&editpostid=-1'.'#p'.$itemid;
            redirect($returnurl, $str, 10);
        }
    }

    if(isset($grade2)){
        if($grade2 != PEERGRADE_UNSET_PEERGRADE){
            $exists_peergrade = $DB->get_record('peerforum_peergradecriteria', array('userid' => $USER->id, 'itemid' =>$itemid, 'criteria' => $criteria2));

            //insert/update into DB peerforum_peergradecriteria
            $data = new stdClass();

            $data->criteria  = $criteria2;
            $data->grade  = $grade2;

            if($exists_peergrade){
                $data->id = $exists_peergrade->id;
                $DB->update_record('peerforum_peergradecriteria', $data);

            } else {
                $data->itemid  = $itemid;
                $data->userid  = $USER->id;
                $data->contextid = $context->id;
                $data->component = $component;
                $data->peergradearea = $peergradearea;
                $data->peergradescaleid = $peergradescaleid;
                $data->timecreated = $time;
                $data->timemodified = $time;
                $data->feedback = $feedback;

                $DB->insert_record('peerforum_peergradecriteria', $data);

            }
            $all_grades[2] = $grade2;
        } else {
            //error - no grade to submit
            $str = $OUTPUT->notification(get_string('error:nopeergradecriteria', 'peerforum', $criteria2), 'notifyproblem');

            $returnurl = $returnurl.'&editpostid=-1'.'#p'.$itemid;
            redirect($returnurl, $str, 10);
        }
    }

    if(isset($grade3)){
        if($grade3 != PEERGRADE_UNSET_PEERGRADE){
            $exists_peergrade = $DB->get_record('peerforum_peergradecriteria', array('userid' => $USER->id, 'itemid' =>$itemid, 'criteria' => $criteria3));

            //insert/update into DB peerforum_peergradecriteria
            $data = new stdClass();

            $data->criteria  = $criteria3;
            $data->grade  = $grade3;

            if($exists_peergrade){
                $data->id = $exists_peergrade->id;
                $DB->update_record('peerforum_peergradecriteria', $data);

            } else {
                $data->itemid  = $itemid;
                $data->userid  = $USER->id;
                $data->contextid = $context->id;
                $data->component = $component;
                $data->peergradearea = $peergradearea;
                $data->peergradescaleid = $peergradescaleid;
                $data->timecreated = $time;
                $data->timemodified = $time;
                $data->feedback = $feedback;

                $DB->insert_record('peerforum_peergradecriteria', $data);

            }
            $all_grades[3] = $grade3;
        } else {
            //error - no grade to submit
            $str = $OUTPUT->notification(get_string('error:nopeergradecriteria', 'peerforum', $criteria3), 'notifyproblem');

            $returnurl = $returnurl.'&editpostid=-2'.'#p'.$itemid;
            redirect($returnurl, $str, 10);
        }
    }

    if(!in_array(PEERGRADE_UNSET_PEERGRADE, $all_grades)){

        //get assessed method
        $assessedmethod = $DB->get_record('peerforum', array('id' => $peerforumid))->peergradeassessed;

        if($assessedmethod == PEERGRADE_AGGREGATE_AVERAGE){
            $assessed_grade_original = array_sum($all_grades)/count($all_grades);
            $assessed_grade = number_format((float)$assessed_grade_original, 2, '.', '');
        }
        if($assessedmethod == PEERGRADE_AGGREGATE_COUNT){
            $assessed_grade = count($all_grades);
        }
        if($assessedmethod == PEERGRADE_AGGREGATE_MAXIMUM){
            $assessed_grade = max($all_grades);
        }
        if($assessedmethod == PEERGRADE_AGGREGATE_MINIMUM){
            $assessed_grade = min($all_grades);
        }
        if($assessedmethod == PEERGRADE_AGGREGATE_SUM){
            $assessed_grade = array_sum($all_grades);
        }

        $exists_grade = $DB->get_record('peerforum_peergrade', array('userid' => $USER->id, 'itemid' => $itemid));

        $tosubmit = new stdClass();

        $tosubmit->peergrade = $assessed_grade;
        $tosubmit->feedback = $feedback;

        if(!$exists_grade){
            $tosubmit->contextid = $context->id;
            $tosubmit->component = $component;
            $tosubmit->peergradearea = $peergradearea;
            $tosubmit->itemid = $itemid;
            $tosubmit->scaleid = $peergradescaleid; //apagar?
            $tosubmit->userid = $USER->id;
            $tosubmit->timecreated = $time;
            $tosubmit->timemodified = $time;
            $tosubmit->peergradescaleid = $peergradescaleid;
            $tosubmit->peergraderid = 0; //apagar?

            $DB->insert_record('peerforum_peergrade', $tosubmit);

        } else {
            $tosubmit->id = $exists_grade->id;
            $DB->update_record('peerforum_peergrade', $tosubmit);
        }

        $peergradeoptions = new stdClass();
        $peergradeoptions->context = $context;
        $peergradeoptions->component = $component;
        $peergradeoptions->peergradearea = $peergradearea;
        $peergradeoptions->itemid  = $itemid;
        $peergradeoptions->peergradescaleid = $peergradescaleid;
        $peergradeoptions->userid  = $USER->id;
        $peergradeoptions->feedback  = $feedback;

        $peergrade_obj = new peergrade($peergradeoptions);
        $peergrade_obj->update_peergrade($assessed_grade, $feedback);

        if ($context->contextlevel == CONTEXT_MODULE) {
            // Tell the module that its grades have changed.
            $modinstance = $DB->get_record($cm->modname, array('id' => $cm->instance));
            if ($modinstance) {
                $modinstance->cmidnumber = $cm->id; // MDL-12961.
                $functionname = $cm->modname.'_update_grades';
                require_once($CFG->dirroot."/mod/{$cm->modname}/lib.php");
                if (function_exists($functionname)) {
                    $functionname($modinstance, $peergradeduserid);
                }
            }
        }
            $exists_time = $DB->get_record('peerforum_time_assigned', array('postid' => $itemid, 'userid' => $USER->id));

            $time_obj = new stdclass();

            if(!$exists_time){
                $time_obj->courseid = $COURSE->id;
                $time_obj->postid = $itemid;
                $time_obj->userid = $USER->id;
                $time_obj->timeassigned = $time;
                $time_obj->timemodified = $time;

                $DB->insert_record("peerforum_time_assigned", $time_obj);
            } else {
                $time_obj->id = $exists_time->id;
                $time_obj->timemodified = $time;

                $DB->update_record('peerforum_time_assigned', $time_obj);
            }


        $pm = new peergrade_manager();
        $pm->update_peergrader_posts($USER->id, $itemid, $COURSE->id);

        //can submit
        $str = $OUTPUT->notification(get_string('submited:peergrade', 'peerforum'), 'notifymessage');

        $returnurl = $returnurl.'&editpostid=-1'.'#p'.$itemid;
        redirect($returnurl, $str, 10);
    }

} else {
    //error - no feedback to submit
    $str = $OUTPUT->notification(get_string('error:nofeedback', 'peerforum'), 'notifyproblem');

    $returnurl = $returnurl.'&editpostid=-2'.'#p'.$itemid;
    redirect($returnurl, $str, 10);
}
