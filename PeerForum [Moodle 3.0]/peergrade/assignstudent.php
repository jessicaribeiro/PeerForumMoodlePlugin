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
$PAGE->set_url('/peergrade/assignstudent.php', array('contextid' => $context->id));

if (!confirm_sesskey() || !has_capability('mod/peerforum:peergrade', $context)) {
    print_error('peergradepermissiondenied', 'peergrade');
}

//Assign peer graders from parent post
if(isset($_POST['assignpeergradersparent'.$itemid])){
    adjust_database();

    $postparent = $DB->get_record('peerforum_posts', array('id' => $itemid))->parent;

    $parentpeergraders = $DB->get_record('peerforum_posts', array('id' => $postparent))->peergraders;

    $data = new stdClass();
    $data->id = $itemid;
    $data->peergraders = $parentpeergraders;

    $DB->update_record("peerforum_posts", $data);

    $peers = explode(';', $parentpeergraders);
    $peers = array_filter($peers);

    foreach ($peers as $i => $value) {

        $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $COURSE->id, 'iduser' => $peers[$i]));

        if(!empty($peers_info)){
            $poststograde = $peers_info->poststopeergrade;

            $posts = explode(';', $poststograde);

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
        }
    }
}

redirect($returnurl.'#p'.$itemid);
