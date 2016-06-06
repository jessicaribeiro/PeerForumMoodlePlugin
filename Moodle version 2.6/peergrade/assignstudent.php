<?php

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}


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



list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$contextid = null; // Now we have a context object, throw away the id from the user.
$PAGE->set_context($context);
$PAGE->set_url('/peergrade/assignstudent.php', array('contextid' => $context->id));


if (!confirm_sesskey() || !has_capability('mod/peerforum:peergrade', $context)) {
    print_error('peergradepermissiondenied', 'peergrade');
}

//Select student to assign post to peergrade
if(isset($_POST['assignpeer'.$itemid])){
    $student_id = $_POST['menustudents'.$itemid];

    global $DB, $COURSE;

    if($student_id == UNSET_STUDENT){

        $all_students = get_students_can_be_assigned($COURSE->id, $itemid, $peergradeduserid);

        $id = array_rand($all_students, '1');


        $student_id = $id;
    }

    if($student_id != 0){

        $peergraders = $DB->get_record('peerforum_posts', array('id' => $itemid))->peergraders;
        $peers = explode(';', $peergraders);

        //if(!empty($peergraders)){
            $peers = array_filter($peers);
            array_push($peers, $student_id);
            $peers = array_filter($peers);
    //    }
        $peers_updated = implode(';', $peers);

        $data = new stdClass();
        $data->id = $itemid;
        $data->peergraders = $peers_updated;

        $DB->update_record("peerforum_posts", $data);

        $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $COURSE->id, 'iduser' => $student_id));

        if(!empty($peers_info)){
            $poststograde = $peers_info->poststopeergrade;

            $posts = explode(';', $poststograde);

            adjust_database();

            $posts = array_filter($posts);
            array_push($posts, $itemid);
            $posts = array_filter($posts);

            $posts_updated = array();
            $posts_updated = implode(';', $posts);

            $numpostsassigned = $peers_info->numpostsassigned;

            $numposts = $numpostsassigned + 1;


            $data2 = new stdClass();
            $data2->id = $peers_info->id;
            $data2->poststopeergrade = $posts_updated;
            $data2->numpostsassigned = $numposts;

            $DB->update_record("peerforum_peergrade_users", $data2);

            $time = new stdclass();
            $time->courseid = $COURSE->id;
            $time->postid = $itemid;
            $time->userid = $peers_info->id;
            $time->timeassigned = time();
            $time->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time);

        } else{
            $data2 = new stdClass();
            $data2->courseid = $COURSE->id;
            $data2->iduser = $student_id;
            $data2->poststopeergrade = $itemid;
            $data2->postspeergradedone = -1;
            $data2->postsblocked = -1;
            $data2->postsexpired = -1;
            $data2->numpostsassigned = 1;


            $DB->insert_record("peerforum_peergrade_users", $data2);

            $time = new stdclass();
            $time->courseid = $COURSE->id;
            $time->postid = $itemid;
            $time->userid = $student_id;
            $time->timeassigned = time();
            $time->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time);
        }
    }

}

redirect($returnurl.'#p'.$itemid);