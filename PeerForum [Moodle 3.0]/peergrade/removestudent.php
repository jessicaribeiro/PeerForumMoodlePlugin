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

$contextid   = required_param('contextid', PARAM_INT);
$component   = required_param('component', PARAM_COMPONENT);
$peergradearea  = required_param('peergradearea', PARAM_AREA);
$itemid      = required_param('itemid', PARAM_INT);
$peergradescaleid     = required_param('peergradescaleid', PARAM_INT);
$userpeergrade = optional_param('peergrade', null, PARAM_INT);
$peergradeduserid = required_param('peergradeduserid', PARAM_INT); // Which user is being ratedpeer. Required to update their grade.
$returnurl   = required_param('returnurl', PARAM_LOCALURL); // Required for non-ajax requests.
$feedback   = required_param('feedback', PARAM_TEXT); // Required for non-ajax requests.
$peerforumid = required_param('peerforumid', PARAM_INT);


list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$contextid = null; // Now we have a context object, throw away the id from the user.
$PAGE->set_context($context);
$PAGE->set_url('/peergrade/removestudent.php', array('contextid' => $context->id));

if (!confirm_sesskey() || !has_capability('mod/peerforum:peergrade', $context)) {
    print_error('peergradepermissiondenied', 'peergrade');
}

//Select student to remove post to peergrade
if(isset($_POST['removepeer'.$itemid])){
    $student_id = $_POST['menustudents_rmv'.$itemid];

    global $DB, $COURSE;

    if($student_id != UNSET_STUDENT){
        $peergraders = $DB->get_record('peerforum_posts', array('id' => $itemid))->peergraders;
        $peers = explode(';', $peergraders);
        $peers = array_filter($peers);

        //remove student from post peergraders
        if(in_array($student_id, $peers)){
            $key = array_search($student_id, $peers);
            unset($peers[$key]);
            $peers = array_filter($peers);
            $peers_updated = implode(';', $peers);

            $data = new stdClass();
            $data->id = $itemid;
            $data->peergraders = $peers_updated;

            $DB->update_record("peerforum_posts", $data);
        }

       $peer_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$course->id, 'iduser' => $student_id));

        if(!empty($peer_info)){

            $posts_blocked = $peer_info->postsblocked;
            $posts_topeergrade = $peer_info->poststopeergrade;

            $blocked = explode(';', $posts_blocked);
            $blocked = array_filter($blocked);

            $topeergrade = explode(';', $posts_topeergrade);
            $topeergrade = array_filter($topeergrade);

            adjust_database();

            //verify if post is blocked
            if(in_array($itemid, $blocked)){
                $key = array_search($itemid, $blocked);
                unset($blocked[$key]);
                $blocked = array_filter($blocked);
                $blocked_updated = implode(';', $blocked);

                $data2 = new stdClass();
                $data2->id = $peer_info->id;
                $data2->postsblocked = $blocked_updated;

                $DB->update_record("peerforum_peergrade_users", $data2);
            }

            //verify if post is assigned to peergrade
            if(in_array($itemid, $topeergrade)){
                $key = array_search($itemid, $topeergrade);
                unset($topeergrade[$key]);
                $topeergrade = array_filter($topeergrade);
                $topeergrade_updated = implode(';', $topeergrade);

                $numpostsassigned = $peer_info->numpostsassigned;
                $numposts = $numpostsassigned - 1;

                $data2 = new stdClass();
                $data2->id = $peer_info->id;
                $data2->poststopeergrade = $topeergrade_updated;
                $data2->numpostsassigned = $numposts;

                $DB->update_record("peerforum_peergrade_users", $data2);

            }

            //remove post from student to peer grade
            $DB->delete_records("peerforum_time_assigned", array('postid' => $itemid, 'userid' => $peer_info->id));
        }
    }
}

redirect($returnurl.'#p'.$itemid);
?>
