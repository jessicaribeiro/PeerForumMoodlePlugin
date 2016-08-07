<?php

/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$postauthor = required_param('postauthor', PARAM_INT);

$PAGE->set_url('/peergrading/studentassign.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'itemid' => $itemid, 'postauthor' => $postauthor));

require_login($courseid);

//Select student to assign post to peergrade
if(isset($_POST["assignstd".$itemid])) {

    $student_id = $_POST['menustds'.$itemid];

    global $DB;

    if($student_id != UNSET_STUDENT){
        $student_id = str_replace(".","",$student_id);
    }

    if($student_id == UNSET_STUDENT){

        $all_students = get_students_can_be_assigned($courseid, $itemid, $postauthor);

        $id = array_rand($all_students, 1);

        $student_id = $id;
    }

        if($student_id != 0){

            $peergraders = $DB->get_record('peerforum_posts', array('id' => $itemid))->peergraders;
            $peers = explode(';', $peergraders);

            $peers = array_filter($peers);
            array_push($peers, $student_id);
            $peers = array_filter($peers);

            $peers_updated = implode(';', $peers);

            $data = new stdClass();
            $data->id = $itemid;
            $data->peergraders = $peers_updated;

            $DB->update_record("peerforum_posts", $data);

            $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $courseid, 'iduser' => $student_id));

            if(!empty($peers_info)){
                $poststograde = $peers_info->poststopeergrade;

                $numpostsassigned = $peers_info->numpostsassigned;
                $numposts = $numpostsassigned + 1;

                $posts = explode(';', $poststograde);

                adjust_database();

                $posts = array_filter($posts);
                array_push($posts, $itemid);
                $posts = array_filter($posts);

                $posts_updated = array();
                $posts_updated = implode(';', $posts);

                $data2 = new stdClass();
                $data2->id = $peers_info->id;
                $data2->poststopeergrade = $posts_updated;
                $data2->numpostsassigned = $numposts;

                $DB->update_record("peerforum_peergrade_users", $data2);

                $time = new stdclass();
                $time->courseid = $courseid;
                $time->postid = $itemid;
                $time->userid = $student_id;
                $time->timeassigned = time();
                $time->timemodified = time();

                $DB->insert_record("peerforum_time_assigned", $time);

            } else{
                $data2 = new stdClass();
                $data2->courseid = $courseid;
                $data2->iduser = $student_id;
                $data2->poststopeergrade = $itemid;
                $data2->postspeergradedone = NULL;
                $data2->postsblocked = NULL;
                $data2->postsexpired = NULL;
                $data2->numpostsassigned = 0;

                $time = new stdclass();
                $time->courseid = $courseid;
                $time->postid = $itemid;
                $time->userid = $student_id;
                $time->timeassigned = time();
                $time->timemodified = time();

                $DB->insert_record("peerforum_time_assigned", $time);

                $DB->insert_record("peerforum_peergrade_users", $data2);
            }
        }
}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
?>
