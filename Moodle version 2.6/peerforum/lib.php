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
 * @package    mod
 * @subpackage peerforum
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->dirroot.'/user/selector/lib.php');
require_once($CFG->dirroot . '/peergrade/lib.php');
require_once($CFG->dirroot . '/ratingpeer/lib.php');



/// CONSTANTS ///////////////////////////////////////////////////////////

define('PEERFORUM_MODE_FLATOLDEST', 1);
define('PEERFORUM_MODE_FLATNEWEST', -1);
define('PEERFORUM_MODE_THREADED', 2);
define('PEERFORUM_MODE_NESTED', 3);

define('PEERFORUM_CHOOSESUBSCRIBE', 0);
define('PEERFORUM_FORCESUBSCRIBE', 1);
define('PEERFORUM_INITIALSUBSCRIBE', 2);
define('PEERFORUM_DISALLOWSUBSCRIBE',3);

//define('PEERGRADE_UNSET_PEERGRADE', -999);
//define('PEERGRADE_UNSET_FEEDBACK', '');


//define ('PEERGRADE_AGGREGATE_NONE', 0); // No peergrades.
//define ('PEERGRADE_AGGREGATE_AVERAGE', 1);
//define ('PEERGRADE_AGGREGATE_COUNT', 2);
//define ('PEERGRADE_AGGREGATE_MAXIMUM', 3);
//define ('PEERGRADE_AGGREGATE_MINIMUM', 4);
//define ('PEERGRADE_AGGREGATE_SUM', 5);

//define ('PEERGRADE_DEFAULT_SCALE', 5);

/**
 * PEERFORUM_TRACKING_OFF - Tracking is not available for this peerforum.
 */
define('PEERFORUM_TRACKING_OFF', 0);

/**
 * PEERFORUM_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('PEERFORUM_TRACKING_OPTIONAL', 1);

/**
 * PEERFORUM_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as PEERFORUM_TRACKING_OPTIONAL if $CFG->peerforum_allowforcedreadtracking is off.
 */
define('PEERFORUM_TRACKING_FORCED', 2);

/**
 * PEERFORUM_TRACKING_ON - deprecated alias for PEERFORUM_TRACKING_FORCED.
 * @deprecated since 2.6
 */
define('PEERFORUM_TRACKING_ON', 2);

define('PEERFORUM_MAILED_PENDING', 0);
define('PEERFORUM_MAILED_SUCCESS', 1);
define('PEERFORUM_MAILED_ERROR', 2);

if (!defined('PEERFORUM_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in peerforum cron. */
    define('PEERFORUM_CRON_USER_CACHE', 5000);
}

/*NEW FUNCTIONS PEERGRADE*/

define('PEERFORUM_MODE_PROFESSOR', 1);
define('PEERFORUM_MODE_STUDENT', 2);
define('PEERFORUM_MODE_PROFESSORSTUDENT', 3);

/**
 * Returns array of peerforum grade modes
 *
 * @return array
 */
function peerforum_get_final_grade_modes() {
    return array (PEERFORUM_MODE_PROFESSOR => get_string('onlyprofessorpeergrade', 'peerforum'),
                  PEERFORUM_MODE_STUDENT => get_string('onlystudentpeergrade', 'peerforum'),
                  PEERFORUM_MODE_PROFESSORSTUDENT   => get_string('professorstudentpeergrade', 'peerforum'));
}


/*NEW FUNCTIONS PEERGRADE*/


function mode($array){
    if(count(array_unique($array)) < count($array)) {
        // Array has duplicates
        $values = array_count_values($array);
        $mode = array_search(max($values), $values);
    } else {
        $mode = null;
    }

    return $mode;
}

function average($array) {
    if (!count($array)) {
        return 0;
    }

    $sum = 0;
    for ($i = 0; $i < count($array); $i++) {
        $sum += $array[$i];
    }

    return $sum / count($array);
}

// Function to calculate square of value - mean
function sd_square($x, $mean) {
    return pow($x - $mean, 2);
}

// Function to calculate standard deviation (uses sd_square)
function standart_deviation($array) {
// square root of sum of squares devided by N-1
    return sqrt(array_sum(array_map("sd_square", $array, array_fill(0, count($array), (array_sum($array) / count($array))))) / (count($array) - 1));
}

function peerforum_grading_permissions($contextid, $component, $gradingarea) {

    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_peerforum' || $gradingarea != 'post') {
        // We don't know about this component/ratingpeerarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view'    => has_capability('mod/peerforum:viewgrade', $context),
        'viewany' => has_capability('mod/peerforum:viewanygrade', $context),
        'viewall' => has_capability('mod/peerforum:viewallgrades', $context),
        'grade'    => has_capability('mod/peerforum:grade', $context)
    );
}

function get_peers_min_posts($arraypeers){
    $peers = explode(';', $arraypeers);
    $peers = array_filter($arraypeers);
}

function get_students_enroled_name($courseid){
    global $DB;
    $students = get_students_enroled($courseid);

    $all_students = array(); //[id]->nome completo

    $sql = "SELECT u.id, concat(u.firstname, ' ', u.lastname) as name
            FROM mdl_user u";

    $result = $DB->get_records_sql($sql);

    foreach ($students as $id => $value) {
        $all_students[$id] = $result[$id]->name;
    }

    return $all_students;
}

function update_post_expired($postid, $userid, $courseid, $peerforum){
    global $DB;

    adjust_database();

    $posts_user = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser' => $userid));

    if (user_has_role_assignment($userid, 5)) {
        $isstudent = true;
    } else {
        $isstudent = false;
    }

    if(!empty($posts_user)){

        if($isstudent){

            $data = new stdClass();
            $data->id = $posts_user->id;

            if(!empty($posts_user)){

                $poststopeergrade = $posts_user->poststopeergrade;
                $poststopeergrade = explode(';', $poststopeergrade);
                $poststopeergrade = array_filter($poststopeergrade);

                if(in_array($postid, $poststopeergrade)){
                    $key = array_search($postid, $poststopeergrade);
                    unset($poststopeergrade[$key]);
                    $poststopeergrade = array_filter($poststopeergrade);

                    $poststopeergrade = implode(';', $poststopeergrade);
                    $data->poststopeergrade = $poststopeergrade;

                    $DB->update_record("peerforum_peergrade_users", $data);


                    $posts_expired = $posts_user->postsexpired;
                    $posts_expired = explode(';', $posts_expired);
                    $posts_expired = array_filter($posts_expired);


                    if(!in_array($postid, $posts_expired)){
                        array_push($posts_expired, $postid);
                        $posts_expired = array_filter($posts_expired);
                        $posts_expired = implode(';', $posts_expired);

                        $data->postsexpired = $posts_expired;

                        $DB->update_record('peerforum_peergrade_users', $data);
                    }
                }
            }
        }
    }
}

function peerforum_get_user_professors_peergrades($peerforum, $userid = 0) {
    global $CFG;

    if($peerforum->peergradeassessed){
        require_once($CFG->dirroot.'/peergrade/lib.php');

        $peergradeoptions = new stdClass;
        $peergradeoptions->component = 'mod_peerforum';
        $peergradeoptions->peergradearea = 'post';

        //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
        $peergradeoptions->modulename = 'peerforum';
        $peergradeoptions->moduleid   = $peerforum->id;
        $peergradeoptions->userid = $userid;
        $peergradeoptions->aggregationmethod = $peerforum->peergradeassessed;
        $peergradeoptions->peergradescaleid = $peerforum->peergradescale;
        $peergradeoptions->itemtable = 'peerforum_posts';
        $peergradeoptions->itemtableusercolumn = 'userid';

        $pm = new peergrade_manager();
        return $pm->get_user_professors_peergrades($peergradeoptions);
    } else {
        return null;
    }
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $peerforum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function peerforum_get_user_students_peergrades($peerforum, $userid = 0) {
    global $CFG;

    if($peerforum->peergradeassessed){
        require_once($CFG->dirroot.'/peergrade/lib.php');

        $peergradeoptions = new stdClass;
        $peergradeoptions->component = 'mod_peerforum';
        $peergradeoptions->peergradearea = 'post';

        //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
        $peergradeoptions->modulename = 'peerforum';
        $peergradeoptions->moduleid   = $peerforum->id;
        $peergradeoptions->userid = $userid;
        $peergradeoptions->aggregationmethod = $peerforum->peergradeassessed;
        $peergradeoptions->peergradescaleid = $peerforum->peergradescale;
        $peergradeoptions->itemtable = 'peerforum_posts';
        $peergradeoptions->itemtableusercolumn = 'userid';

        $pm = new peergrade_manager();
        return $pm->get_user_students_peergrades($peergradeoptions);
    } else {
        return null;
    }
}

function time_created($postid) {

    global $DB;

    $sql = "SELECT p.id, p.created
              FROM {peerforum_posts} p
             WHERE p.id = $postid ";
    $post_time_created = $DB->get_records_sql($sql);

    return $post_time_created[$postid]->created;

}

function get_id($iduser){
    global $DB;

    $sql = "SELECT p.iduser, p.id
              FROM {peerforum_peergrade_users} p
             WHERE p.iduser = $iduser";
    $id_sql = $DB->get_records_sql($sql);

    return $id_sql[$iduser]->id;

}

function get_student_status($userid, $courseid){
    global $DB;

    $status_db = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser' => $userid));

    if(!empty($status_db)){
        return $status_db->userblocked;
    }
}

function verify_peergrade($postid, $peergrader){
    global $DB;

    $peergrade = $DB->get_record('peerforum_peergrade', array('itemid' => $postid, 'userid' => $peergrader));

    if(empty($peergrade)){
        return 0;
    } else {
        return 1;
    }
}

function get_post_status($postid, $userid, $courseid){
    global $DB;

    $posts_user = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser'=>$userid));

    if(!empty($posts_user)){
        $posts_blocked = $posts_user->postsblocked;
        $posts_topeergrade = $posts_user->poststopeergrade;

        adjust_database();

        if(!empty($posts_blocked)){
            $blocked = explode(';', $posts_blocked);
            $blocked = array_filter($blocked);
            if(in_array($postid, $blocked)){
                return 1;
            }
        }
        if(!empty($posts_topeergrade)){
            $topeergrade = explode(';', $posts_topeergrade);
            $topeergrade = array_filter($topeergrade);
            if(in_array($postid, $topeergrade)){
                return 0;
            }
        }
    }
}

/**
 * Validates a submitted peergrade
 * @param array $params submitted data
 *            context => object the context in which the peergraded items exists [required]
 *            component => The component for this module - should always be mod_peerforum [required]
 *            peergradearea => object the context in which the peergraded items exists [required]
 *            itemid => int the ID of the object being peergraded [required]
 *            scaleid => int the scale from which the user can select a peergrade. Used for bounds checking. [required]
 *            ratingpeer => int the submitted peergrade [required]
 *            ratedpeeruserid => int the id of the user whose items have been peergraded. NOT the user who submitted the peergrading. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie PEERGRADE_AGGREGATE_AVERAGE [required]
 * @return boolean true if the peergrade is valid. Will throw peergrade_exception if not
 */
function peerforum_peergrade_validate($params) {
    global $DB, $USER;

    // Check the component is mod_peerforum
    if ($params['component'] != 'mod_peerforum') {
        throw new peergrade_exception('invalidcomponent');
    }

    // Check the peergradearea is post (the only ratingpeer area in peerforum)
    if ($params['peergradearea'] != 'post') {
        throw new peergrade_exception('invalidpeergradearea');
    }

    // Check the ratedpeeruserid is not the current user .. you can't ratepeer your own posts
    if ($params['peergradeduserid'] == $USER->id) {
        throw new peergrade_exception('nopermissiontopeergrade');
    }

    // Fetch all the related records ... we need to do this anyway to call peerforum_user_can_see_post
    $post = $DB->get_record('peerforum_posts', array('id' => $params['itemid'], 'userid' => $params['peergradeduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $peerforum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id , false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the peerforum
    if ($context->id != $params['context']->id) {
        throw new peergrade_exception('invalidcontext');
    }

    if ($peerforum->peergradescale != $params['peergradescaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new peergrade_exception('invalidscaleid');
    }

    // check the item we're ratingpeer was created in the assessable time window
    if (!empty($peerforum->assesstimestart) && !empty($peerforum->assesstimefinish)) {
        if ($post->created < $peerforum->assesstimestart || $post->created > $peerforum->assesstimefinish) {
            throw new peergrade_exception('notavailable');
        }
    }

    //check that the submitted ratingpeer is valid for the scale

    // lower limit
    if ($params['peergrade'] < 0  && $params['peergrade'] != PEERGRADE_UNSET_PEERGRADE) {
        throw new peergrade_exception('invalidnum4');
    }

    // upper limit

    if ($peerforum->peergradescale < 0) {
        //its a custom scale
        $peergradescalerecord = $DB->get_record('peergradescale', array('id' => -$peerforum->peergradescale));

        if ($peergradescalerecord) {
            $peergradescalearray = explode(',', $peergradescalerecord->peergradescale);
            if ($params['peergrade'] > count($peergradescalearray)) {
                throw new peergrade_exception('invalidnum');
            }
        } else {
            throw new peergrade_exception('invalidscaleid');
        }
    } else if ($params['peergrade'] > $peerforum->peergradescale) {
        //if its numeric and submitted peergrade is above maximum
        throw new peergrade_exception('invalidnum8');
    }

    // Make sure groups allow this user to see the item they're ratingpeer
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new peergrade_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow ratingpeer of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new peergrade_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!peerforum_user_can_see_post($peerforum, $discussion, $post, $USER, $cm)) {
        throw new peergrade_exception('nopermissiontoratepeer');
    }

    return true;
}

function peerforum_get_user_posts_expired($userid, $courseid){

    global $DB;

    //get all the posts
    $sql = "SELECT p.id, p.postsexpired
            FROM {peerforum_peergrade_users} p
            WHERE p.iduser = $userid AND p.courseid = $courseid";

    $all_posts = $DB->get_records_sql($sql);

    $posts_expired = array();

    //verify which posts the user have already peergraded
    foreach($all_posts as $postid => $value){
        if(!empty($all_posts[$postid]->postsexpired)){
            $posts_expired = explode(";", ($all_posts[$postid]->postsexpired));
            $posts_expired = array_filter($posts_expired);
        }
    }
    return $posts_expired;
}

function peerforum_get_user_posts_peergraded($userid, $courseid){

    global $DB;

    //get all the posts
    $sql = "SELECT p.id, p.postspeergradedone
            FROM {peerforum_peergrade_users} p
            WHERE p.iduser = $userid AND p.courseid = $courseid";

    $all_posts = $DB->get_records_sql($sql);

    $posts_peergraded = array();

    //verify which posts the user have already peergraded
    foreach($all_posts as $postid => $value){
        if(!empty($all_posts[$postid]->postspeergradedone)){
            $posts_peergraded = explode(";", ($all_posts[$postid]->postspeergradedone));
            $posts_peergraded = array_filter($posts_peergraded);
        }
    }
    return $posts_peergraded;
}

function peerforum_get_user_posts_to_peergrade($userid, $courseid){

    global $DB;

    //get all the posts
    $sql = "SELECT p.id, p.poststopeergrade
            FROM {peerforum_peergrade_users} p
            WHERE p.iduser = $userid AND p.courseid = $courseid";

    $all_posts = $DB->get_records_sql($sql);

    $posts_to_peergrade = array();

    //verify which posts the user have to peergrade
    foreach($all_posts as $postid => $value){
        if(!empty($all_posts[$postid]->poststopeergrade)){
            $posts_to_peergrade = explode(";", ($all_posts[$postid]->poststopeergrade));
            $posts_to_peergrade = array_filter($posts_to_peergrade);
        }
    }
    return $posts_to_peergrade;
}

function time_assigned($postid, $userid) {
    global $DB;

    $time = $DB->get_record('peerforum_time_assigned', array('postid' => $postid, 'userid' => $userid));

    if(!empty($time)){
        return $time->timeassigned;
    } else {
        return null;
    }
}

function get_time_expire($postid, $userid){

    global $CFG, $DB;
    //when the post was assigned
    $time_assign = time_assigned($postid, $userid);

    if(!empty($time_assign)){

        $time_assigned_db = usergetdate($time_assign);

        $date_time_assigned = new stdClass();
        $date_time_assigned->year = $time_assigned_db['year'];
        $date_time_assigned->mon = $time_assigned_db['mon'];
        $date_time_assigned->mday = $time_assigned_db['mday'];
        $date_time_assigned->hours = $time_assigned_db['hours'];
        $date_time_assigned->minutes = $time_assigned_db['minutes'];
        $date_time_assigned->seconds = $time_assigned_db['seconds'];

        $time_assigned = new DateTime("$date_time_assigned->year-$date_time_assigned->mon-$date_time_assigned->mday $date_time_assigned->hours:$date_time_assigned->minutes:$date_time_assigned->seconds");

        $postdiscussion = $DB->get_record('peerforum_posts', array('id' => $postid))->discussion;
        $peerforumid = $DB->get_record('peerforum_discussions', array('id' => $postdiscussion))->peerforum;
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforumid));

        //how much time the user have to peergrade
        $time_to_peergrade = $peerforum->timetopeergrade;
        $time = 'P'.$time_to_peergrade.'D';

        //when the time to peergrade ends
        $time_finish = $time_assigned;
        $time_finish->add(new DateInterval("$time"));

        //current time
        $time_current_db = usergetdate(time());

        $date_time_current = new stdClass();
        $date_time_current->year = $time_current_db['year'];
        $date_time_current->mon = $time_current_db['mon'];
        $date_time_current->mday = $time_current_db['mday'];
        $date_time_current->hours = $time_current_db['hours'];
        $date_time_current->minutes = $time_current_db['minutes'];
        $date_time_current->seconds = $time_current_db['seconds'];


        $time_current = new DateTime("$date_time_current->year-$date_time_current->mon-$date_time_current->mday $date_time_current->hours:$date_time_current->minutes:$date_time_current->seconds");

        //time period to peergrade
        $time_interval = date_diff($time_finish,$time_current);

        return $time_interval;
    } else {
        return 0;
    }
}

function update_peergraders($array_peergraders, $postid, $courseid) {
    global $DB;

    foreach($array_peergraders as $i => $value){
        $userid = $array_peergraders[$i];
        $existing_info = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser'=>$userid));

        $data = new stdClass;
        $data->courseid = $courseid;
        $data->iduser = $userid;

        if(empty($existing_info)){
            $data->poststopeergrade = $postid;
            $data->postspeergradedone = -1;
            $data->postsblocked = -1;
            $data->postsexpired = -1;
            $data->numpostsassigned = 1;

            $DB->insert_record('peerforum_peergrade_users', $data);
        }

        else{
            $existing_posts = $existing_info->poststopeergrade;

            //$array_posts = array();
            $posts = explode(';', $existing_posts);
            $posts = array_filter($posts);
            $num = $existing_info->numpostsassigned;

            adjust_database();

            array_push($posts, $postid);

            $array_posts = array_filter($posts);
            $array_posts = implode(';', $array_posts);

            $data->poststopeergrade = $array_posts;
            $data->id = $existing_info->id;
            $data->numpostsassigned = $num + 1;

            $DB->update_record('peerforum_peergrade_users', $data);
        }

        $time = new stdclass();
        $time->courseid = $courseid;
        $time->postid = $postid;
        $time->userid = $userid;
        $time->timeassigned = time();
        $time->timemodified = time();

        $DB->insert_record('peerforum_time_assigned', $time);

    }
 }

function insert_peergraders($id, $all_peergraders){
    global $DB;

    $data = new stdClass();
    $data->id = $id;
    $data->peergraders = $all_peergraders;
    $DB->update_record('peerforum_posts', $data);
}

function randomGen($array, $quantity, $selected) {
    //$numbers = range($min, $max);
    $numbers = array_rand($array, $selected);
    shuffle($numbers);
    return array_slice($numbers, 0, $quantity);
}

function cmp($a, $b){
    return strcmp($a->numpostsassigned, $b->numpostsassigned);
}

function assign_peergraders($user, $postid, $courseid) {
    global $CFG, $DB;

    $post = $DB->get_record('peerforum_posts', array('id' => $postid));

    $postauthor = $post->userid;
    $postparent = $post->parent;

    $grandparent = $DB->get_record('peerforum_posts', array('id' => $postparent));

    if($grandparent->parent != 0 ){
        return null;
    }

    if (user_has_role_assignment($postauthor, 5)) {
        $isstudent = true;
    } else {
        $isstudent = false;
    }

    if($isstudent){

        $student = $user->id;

        $postdiscussion = $DB->get_record('peerforum_posts', array('id' => $postid))->discussion;
        $peerforumid = $DB->get_record('peerforum_discussions', array('id' => $postdiscussion))->peerforum;
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforumid));

        $array_peers = get_students_can_be_assigned($courseid, $postid, $student);

        $max_peers = $peerforum->selectpeergraders;

        $min_peers = $peerforum->minpeergraders;

        $min_std = min(array_keys($array_peers));

        $max_std = max(array_keys($array_peers));

        $num_enrolled = count($array_peers);

        if($num_enrolled < $max_peers){
            $max_peers = count($array_peers);
        }

        usort($array_peers, "cmp");

        $peers_obj = array_slice($array_peers, 0, $max_peers);

        $peers = array();

        foreach ($peers_obj as $key => $value) {
            $id = $peers_obj[$key]->id;
            $peers[$id] = $id;
        }

        update_peergraders($peers, $postid, $courseid, $peerforumid);

        return $peers;
    } else {
        return null;
    }
}

function get_time_assigned($postid, $userid) {
    global $DB;

    $time = $DB->get_record('peerforum_time_assigned', array('postid' => $postid, 'userid' => $userid));

    if(!empty($time)){
        return $time->timeassigned;
    } else {
        return null;
    }
}

function verify_post_expired($postid, $peerforum, $userid, $courseid){
    global $DB, $PAGE;

    //verify if the user can peergrade in a period of time
    $time_assign = get_time_assigned($postid, $userid);

    if(!empty($time_assign)){
        $time_assigned_db = usergetdate($time_assign);

        $time_to_peergrade = $peerforum->timetopeergrade;

        $date_time_assigned = new stdClass();
        $date_time_assigned->year = $time_assigned_db['year'];
        $date_time_assigned->mon = $time_assigned_db['mon'];
        $date_time_assigned->mday = $time_assigned_db['mday'];
        $date_time_assigned->hours = $time_assigned_db['hours'];
        $date_time_assigned->minutes = $time_assigned_db['minutes'];
        $date_time_assigned->seconds = $time_assigned_db['seconds'];

        $time_assigned = new DateTime("$date_time_assigned->year-$date_time_assigned->mon-$date_time_assigned->mday $date_time_assigned->hours:$date_time_assigned->minutes:$date_time_assigned->seconds");

        $time = 'P'.$time_to_peergrade.'D';

        $time_finish = $time_assigned;

        $time_finish->add(new DateInterval("$time"));

        $time_current_db = usergetdate(time());

        $date_time_current = new stdClass();
        $date_time_current->year = $time_current_db['year'];
        $date_time_current->mon = $time_current_db['mon'];
        $date_time_current->mday = $time_current_db['mday'];
        $date_time_current->hours = $time_current_db['hours'];
        $date_time_current->minutes = $time_current_db['minutes'];
        $date_time_current->seconds = $time_current_db['seconds'];

        $time_current = new DateTime("$date_time_current->year-$date_time_current->mon-$date_time_current->mday $date_time_current->hours:$date_time_current->minutes:$date_time_current->seconds");

        $time_interval = date_diff($time_finish, $time_current);

        $post_expired = true;

        $data = new stdclass();

        if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
            $data->post_expired = false;
            $data->time_interval = $time_interval;
            $data->time_current = $time_current;

        }
        else if($time_current <= $time_finish && $time_interval->invert > 0){
            $data->post_expired = false;
            $data->time_interval = $time_interval;
            $data->time_current = $time_current;

        } else {
            $data->post_expired = true;
            $data->time_interval = 0;
            $data->time_current = 0;

            update_post_expired($postid, $userid, $courseid, $peerforum);
        }

        return $data;
    } else {
        return null;
    }
}

function get_posts_grades(){
    global $DB;

    $info = $DB->get_records('peerforum_peergrade');

    $array_posts = array();

    foreach($info as $i => $value){
        $postid = $info[$i]->itemid;

        if(empty($array_posts[$postid])){
            $array_posts[$postid] = array();
        }

            $post = new stdClass();
            $post->user = $info[$i]->userid;
            $post->postid = $info[$i]->itemid;
            $post->peergrade = $info[$i]->peergrade;
            $post->feedback = $info[$i]->feedback;
            array_push($array_posts[$postid], $post);

    }
    return $array_posts;
}

function get_all_peergrades($courseid){
    global $DB;

    //get all the posts
    $sql = "SELECT p.iduser, p.poststopeergrade, p.postspeergradedone
            FROM {peerforum_peergrade_users} p
            WHERE p.courseid = $courseid";

    $posts = $DB->get_records_sql($sql);

    //get all the grades and feedbacks
    $sql2 = "SELECT p.id, p.itemid, p.peergrade, p.userid, p.feedback
            FROM {peerforum_peergrade} p";

    $posts_grades = $DB->get_records_sql($sql2);

    $all_posts =  array();

    foreach($posts as $userid => $values){
        $all_posts[$userid] = array();

        $info_post = new stdClass;
        $info_post->authorid = $userid;

        $topeergrade = explode(";", $posts[$userid]->poststopeergrade);
        $info_post->poststopeergrade = array_filter($topeergrade);

        $donepeergrade = explode(";", $posts[$userid]->postspeergradedone);
        $info_post->postspeergradedone = array_filter($donepeergrade);

        if(!empty($info_post->postspeergradedone)){
            $info_post->postsdonegrade = array();
            $info_post->postsdonefeedback = array();

            foreach ($info_post->postspeergradedone as $i => $value) {
                $postid = $info_post->postspeergradedone[$i];

                foreach($posts_grades as $d => $value){
                    if(!empty($posts_grades[$d])){
                        if($posts_grades[$d]->itemid == $postid){
                            $info_post->postsdonegrade[$postid] = $posts_grades[$d]->peergrade;
                            $info_post->postsdonefeedback[$postid] =  $posts_grades[$d]->feedback;
                        }
                    }
                }
            }
        }

        array_push($all_posts[$userid], $info_post);
    }

    return $all_posts;
}

function get_all_posts_info(){
    global $DB;

    //get all the posts
    $sql = "SELECT p.id, p.userid, p.subject, p.peergraders, p.discussion
            FROM {peerforum_posts} p";

    $posts = $DB->get_records_sql($sql);

    $all_posts =  array();

    foreach($posts as $postid => $values){
        if (user_has_role_assignment($posts[$postid]->userid, 5)) {
            $isstudent = true;
        } else {
            $isstudent = false;
        }
            if($isstudent){
            $info_post = new stdClass;
            $info_post->postid = $postid;
            $info_post->subject = $posts[$postid]->subject;
            $info_post->discussion = $posts[$postid]->discussion;
            $peergraders = explode(";", $posts[$postid]->peergraders);
            $info_post->peergraders = array_filter($peergraders);

            //$info_post->timeexpire = get_time_expire($postid, $posts[$postid]->userid);
            array_push($all_posts, $info_post);
        }
    }

    return $all_posts;
}

function get_students_assigned($courseid, $postid){
    global $DB;

    $peergraders = $DB->get_record('peerforum_posts', array('id' => $postid))->peergraders;

    $peers = explode(';', $peergraders);
    $peers = array_filter($peers);

    $assigned = array();

    adjust_database();

    foreach ($peers as $i => $value) {
        $id = $peers[$i];
        //verify if post was not already peer graded by this student, cannot remove student if post was already peer graded by him
        $posts_done_db = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $id));

        if(!empty($posts_done_db)){
            $posts_done = $posts_done_db->postspeergradedone;

            $posts = explode(';', $posts_done);
            $posts = array_filter($posts);

            if(!in_array($postid, $posts)){
                $assigned[$id] = $peers[$i];
            }
        }
    }

    return $assigned;
}

function get_student_name($userid){
    global $DB;

    $sql = "SELECT u.id, concat(u.firstname, ' ', u.lastname) as name
            FROM mdl_user u
            WHERE u.id = $userid";

    $student = $DB->get_records_sql($sql);

    return $student[$userid]->name;
}

function get_students_name($students){

    $assigned_students = array();

    foreach ($students as $key => $value) {
        $assigned_students[$key] = get_student_name($students[$key]);
    }

    return $assigned_students;
}

function get_post_peergraders($postid){
    global $DB;

    $post = $DB->get_record('peerforum_posts', array('id' => $postid));
    $peergraders = $post->peergraders;

    $peers = explode(';', $peergraders);
    $peers = array_filter($peers);

    return $peers;
}

function get_students_enroled($courseid){
    global $DB;

    //get all enroled users in course
    $sql = "SELECT u.id, c.id as courseid, u.id as userid
            FROM mdl_course c
            JOIN mdl_context ct ON c.id = ct.instanceid
            JOIN mdl_role_assignments ra ON ra.contextid = ct.id
            JOIN mdl_user u ON u.id = ra.userid
            JOIN mdl_role r ON r.id = ra.roleid
            WHERE c.id = $courseid AND r.id = 5";

    $enroled = $DB->get_records_sql($sql);

    return $enroled;
}

function get_students_can_be_assigned($courseid, $postid, $postauthor){
    global $DB;

    $can_be_assigned = array();

    $students = get_students_enroled($courseid);
    adjust_database();

    //verify students that was already assigned to the post
    $peergraders_db = $DB->get_record('peerforum_posts', array('id' => $postid));


    if(!empty($peergraders_db)){
        $peergraders = $peergraders_db->peergraders;
        $peergraders = explode(';', $peergraders);
        $peergraders = array_filter($peergraders);

        foreach ($students as $id => $value) {
            $student = $students[$id]->id;

            if(!in_array($student, $peergraders)){

                $peergraders_info = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser' => $student));

                if(!empty($peergraders_info)){
                    $topeergrade = $peergraders_info->poststopeergrade;
                    $blocked = $peergraders_info->postsblocked;
                    $donepeergrade = $peergraders_info->postspeergradedone;

                    $num_posts = $peergraders_info->numpostsassigned;

                    $posts_tograde = explode(';', $topeergrade);
                    $posts_tograde = array_filter($posts_tograde);
                    $block = explode(';', $blocked);
                    $block = array_filter($block);
                    $posts_graded = explode(';', $donepeergrade);
                    $posts_graded = array_filter($posts_graded);


                    //can peergrade
                    if(!(in_array($postid, $posts_tograde)) && !(in_array($postid, $block)) && !(in_array($postid, $posts_graded))){
                        //$can_be_assigned[$id] = $students[$id]->id;
                        $std = new stdClass();
                        $std->id = $students[$id]->id;
                        $std->numpostsassigned = $num_posts;

                        $can_be_assigned[$id] = $std;
                        continue;
                    }
                    //cannot peergrade
                    else if ((in_array($postid, $posts_tograde)) || (in_array($postid, $block)) || (in_array($postid, $posts_graded))){
                        continue;

                    }
                } else {
                    //can peergrade
                    $std = new stdClass();
                    $std->id = $students[$id]->id;
                    $std->numpostsassigned = 0;
                    $can_be_assigned[$id] = $std;
                    continue;
                }
            } else {
                //cannot peergrade (is in array of peergraders)
                continue;
            }
        }
    } else if (empty($peergraders)) {

        foreach ($students as $id => $value) {
            $num_posts_user = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $students[$id]->id));
            if(!empty($num_posts_user)){
                $num_posts = $num_posts_user->numpostsassigned;
            } else {
                $num_posts = 0;
            }
            $std = new stdClass();
            $std->id = $students[$id]->id;
            $std->numpostsassigned = $num_posts;
            $can_be_assigned[$id] = $std;

        }
    }


    //not assigned to the post
    foreach ($students as $id => $value) {
        if(!in_array($students[$id]->id, $peergraders)){
            if(!in_array($students[$id]->id, $can_be_assigned)){
            //    $can_be_assigned[$id] = $students[$id]->id;
            $num_posts_user = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $students[$id]->id));
            if(!empty($num_posts_user)){
                $num_posts = $num_posts_user->numpostsassigned;
            } else {
                $num_posts = 0;
            }
            $std = new stdClass();
            $std->id = $students[$id]->id;
            $std->numpostsassigned = $num_posts;
            $can_be_assigned[$id] = $std;

            }
        }
    }

    foreach ($can_be_assigned as $key => $value) {
        if($can_be_assigned[$key]->id == $postauthor){
            unset($can_be_assigned[$key]);
        }
    }

    //verify conflicts
    $conflicts = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));

    foreach ($conflicts as $key => $value) {
        $conflictstds = $conflicts[$key]->idstudents;
        $conflictstds = explode(';', $conflictstds);
        $conflictstds = array_filter($conflictstds);


        foreach ($can_be_assigned as $k => $value) {
            if(in_array($postauthor, $conflictstds)){
                $id = $can_be_assigned[$k]->id;

                if(in_array($id, $conflictstds)){
                    unset($can_be_assigned[$k]);
                }
            }
        }
    }


    return $can_be_assigned;
}

function update_all_posts_expired(){
    global $DB;
    adjust_database();

    $users = $DB->get_records('peerforum_peergrade_users');

    if(!empty($users)){
        foreach ($users as $key => $value) {
            $userid = $users[$key]->iduser;
            $poststopeergrade = $users[$key]->poststopeergrade;

            if(!empty($poststopeergrade)){
                $poststopeergrade = explode(';', $poststopeergrade);
                $poststopeergrade = array_filter($poststopeergrade);


                foreach ($poststopeergrade as $id => $value) {
                    $post = peerforum_get_post_full($poststopeergrade[$id]);
                    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion));
                    $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum));
                    $course = $DB->get_record('course', array('id' => $peerforum->course));

                    verify_post_expired($post->id, $peerforum, $userid, $course->id);
                }
            }
        }
    }
}

function get_all_enroled_id($courseid){
    global $DB;

    //get all enroled users in course
    $sql = "SELECT u.id
            FROM mdl_course c
            JOIN mdl_context ct ON c.id = ct.instanceid
            JOIN mdl_role_assignments ra ON ra.contextid = ct.id
            JOIN mdl_user u ON u.id = ra.userid
            JOIN mdl_role r ON r.id = ra.roleid
            WHERE c.id = $courseid AND r.id = 5 OR r.id = 3 OR r.id = 2";

    $enroled_sql = $DB->get_records_sql($sql);

    $enroled = array();
    foreach ($enroled_sql as $key => $value) {
        $id = $enroled_sql[$key]->id;
        $enroled[$id] = $id;
    }

    return $enroled;
}

function get_students_enroled_id($courseid){
    global $DB;

    //get all enroled users in course
    $sql = "SELECT u.id
            FROM mdl_course c
            JOIN mdl_context ct ON c.id = ct.instanceid
            JOIN mdl_role_assignments ra ON ra.contextid = ct.id
            JOIN mdl_user u ON u.id = ra.userid
            JOIN mdl_role r ON r.id = ra.roleid
            WHERE c.id = $courseid AND r.id = 5";

    $enroled_sql = $DB->get_records_sql($sql);


    $enroled = array();
    foreach ($enroled_sql as $key => $value) {
        $id = $enroled_sql[$key]->id;
        $enroled[$id] = $id;
    }

    return $enroled;
}

function adjust_database(){
    global $DB, $COURSE;

    //$all_enrolled = get_students_enroled_id($COURSE->id);
    $all_enrolled = get_all_enroled_id($COURSE->id);

    $all_users = $DB->get_records('peerforum_peergrade_users' , array('courseid' => $COURSE->id));

    foreach ($all_users as $i => $value) {
        $id_user = $all_users[$i]->iduser;

        if(!in_array($id_user, $all_enrolled)){
            $DB->delete_records('peerforum_peergrade_users', array('courseid' => $COURSE->id, 'iduser' => $id_user));
        }
    }

    $conflits = $DB->get_records('peerforum_peergrade_conflict');

    foreach ($conflits as $k => $value) {
        $conflictgroup = $conflits[$k]->conflictgroup;
        $idstudents = $conflits[$k]->idstudents;
        $namestudents = $conflits[$k]->namestudents;

        $conflictgroup = explode(';', $conflictgroup);
        $conflictgroup = array_filter($conflictgroup);

        $idstudents = explode(';', $idstudents);
        $idstudents = array_filter($idstudents);

        $namestudents = explode(';', $namestudents);
        $namestudents = array_filter($namestudents);

        if(in_array(-1, $conflictgroup)){
            $a = array_search(-1, $conflictgroup);
            unset($conflictgroup[$a]);
            $posts_new = implode(';', $conflictgroup);
            $data = new stdClass();
            $data->id = $conflits[$k]->id;
            $data->conflictgroup = $posts_new;
            $DB->update_record('peerforum_peergrade_conflict', $data);
        }

        if(in_array(-1, $idstudents)){
            $a = array_search(-1, $idstudents);
            unset($idstudents[$a]);
            $posts_new = implode(';', $idstudents);
            $data = new stdClass();
            $data->id = $conflits[$k]->id;
            $data->idstudents = $posts_new;
            $DB->update_record('peerforum_peergrade_conflict', $data);
        }

        if(in_array(-1, $namestudents)){
            $a = array_search(-1, $namestudents);
            unset($namestudents[$a]);
            $posts_new = implode(';', $namestudents);
            $data = new stdClass();
            $data->id = $conflits[$k]->id;
            $data->namestudents = $posts_new;
            $DB->update_record('peerforum_peergrade_conflict', $data);
        }
    }

    $posts_users = $DB->get_records('peerforum_peergrade_users');

    foreach ($posts_users as $key => $value) {

        $poststopeergrade = $posts_users[$key]->poststopeergrade;
        $postspeergradedone = $posts_users[$key]->postspeergradedone;

        $postsblocked = $posts_users[$key]->postsblocked;
        $postsexpired = $posts_users[$key]->postsexpired;

        $numpostsassigned = $posts_users[$key]->numpostsassigned;

        $poststopeergrade = explode(';', $poststopeergrade);
        $poststopeergrade = array_filter($poststopeergrade);

        if(!empty($poststopeergrade)){
            $num_poststopeergrade = count($poststopeergrade);
        } else {
            $num_poststopeergrade = 0;
        }

        $postspeergradedone = explode(';', $postspeergradedone);
        $postspeergradedone = array_filter($postspeergradedone);

        if(!empty($postspeergradedone)){
            $num_postspeergradedone = count($postspeergradedone);
        } else {
            $num_postspeergradedone = 0;
        }

        if($numpostsassigned == 0 && $num_poststopeergrade > 0){
            $numpostsassigned = $num_poststopeergrade;
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->numpostsassigned = $numpostsassigned;

            $DB->update_record('peerforum_peergrade_users', $data);
        } else if ($numpostsassigned == 0 && $num_postspeergradedone > 0){
            $numpostsassigned = $num_postspeergradedone;
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->numpostsassigned = $numpostsassigned;

            $DB->update_record('peerforum_peergrade_users', $data);
        } else if($num_poststopeergrade == 0 && $num_postspeergradedone == 0){
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->numpostsassigned = 0;

            $DB->update_record('peerforum_peergrade_users', $data);
        }

        $postsblocked = explode(';', $postsblocked);
        $postsblocked = array_filter($postsblocked);

        $postsexpired = explode(';', $postsexpired);
        $postsexpired = array_filter($postsexpired);

        if(in_array(-1, $poststopeergrade)){
            $a = array_search(-1, $poststopeergrade);
            unset($poststopeergrade[$a]);
            $posts_new = implode(';', $poststopeergrade);
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->poststopeergrade = $posts_new;
            $DB->update_record('peerforum_peergrade_users', $data);
        }

        if(in_array(-1, $postspeergradedone)){
            $a = array_search(-1, $postspeergradedone);
            unset($postspeergradedone[$a]);
            $posts_new = implode(';', $postspeergradedone);
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->postspeergradedone = $posts_new;
            $DB->update_record('peerforum_peergrade_users', $data);
        }

        if(in_array(-1, $postsblocked)){
            $a = array_search(-1, $postsblocked);
            unset($postsblocked[$a]);
            $posts_new = implode(';', $postsblocked);
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->postsblocked = $posts_new;
            $DB->update_record('peerforum_peergrade_users', $data);
        }

        if(in_array(-1, $postsexpired)){
            $a = array_search(-1, $postsexpired);
            unset($postsexpired[$a]);
            $posts_new = implode(';', $postsexpired);
            $data = new stdClass();
            $data->id = $posts_users[$key]->id;
            $data->postsexpired = $posts_new;
            $DB->update_record('peerforum_peergrade_users', $data);
        }
    }
}
/*-----------------------*/
/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $peerforum add peerforum instance
 * @param mod_peerforum_mod_form $mform
 * @return int intance id
 */
function peerforum_add_instance($peerforum, $mform = null) {
    global $CFG, $DB;

    $peerforum->timemodified = time();

    if (empty($peerforum->assessed)) {
        $peerforum->assessed = 0;
    }

    if (empty($peerforum->ratingpeertime) or empty($peerforum->assessed)) {
        $peerforum->assesstimestart  = 0;
        $peerforum->assesstimefinish = 0;
    }

    $peerforum->id = $DB->insert_record('peerforum', $peerforum);
    $modcontext = context_module::instance($peerforum->coursemodule);

    if ($peerforum->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course        = $peerforum->course;
        $discussion->peerforum         = $peerforum->id;
        $discussion->name          = $peerforum->name;
        $discussion->assessed      = $peerforum->assessed;
        $discussion->message       = $peerforum->intro;
        $discussion->messageformat = $peerforum->introformat;
        $discussion->messagetrust  = trusttext_trusted(context_course::instance($peerforum->course));
        $discussion->mailnow       = false;
        $discussion->groupid       = -1;

        $message = '';

        $discussion->id = peerforum_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('peerforum_discussions', array('id'=>$discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('peerforum_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_peerforum', 'post', $post->id, $options, $post->message);
            $DB->set_field('peerforum_posts', 'message', $post->message, array('id'=>$post->id));
        }
    }

    if ($peerforum->forcesubscribe == PEERFORUM_INITIALSUBSCRIBE) {
        $users = peerforum_get_potential_subscribers($modcontext, 0, 'u.id, u.email');
        foreach ($users as $user) {
            peerforum_subscribe($user->id, $peerforum->id);
        }
    }

    peerforum_grade_item_update($peerforum);

    return $peerforum->id;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $peerforum peerforum instance (with magic quotes)
 * @return bool success
 */
function peerforum_update_instance($peerforum, $mform) {
    global $DB, $OUTPUT, $USER;

    $peerforum->timemodified = time();
    $peerforum->id           = $peerforum->instance;

    if (empty($peerforum->assessed)) {
        $peerforum->assessed = 0;
    }

    if (empty($peerforum->ratingpeertime) or empty($peerforum->assessed)) {
        $peerforum->assesstimestart  = 0;
        $peerforum->assesstimefinish = 0;
    }

    if (empty($peerforum->peergradeassessed)) {
        $peerforum->peergradeassessed = 0;
    }

    if (empty($peerforum->peergradetime) or empty($peerforum->peergradeassessed)) {
        $peerforum->peergradeassesstimestart  = 0;
        $peerforum->peergradeassesstimefinish = 0;
    }

    $oldpeerforum = $DB->get_record('peerforum', array('id'=>$peerforum->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire peerforum
    // if  scale changes - do we need to recheck the ratingpeers, if ratingpeers higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldpeerforum->assessed<>$peerforum->assessed) or ($oldpeerforum->scale<>$peerforum->scale)) {
        peerforum_update_grades($peerforum); // recalculate grades for the peerforum
    }

    if (($oldpeerforum->peergradeassessed<>$peerforum->peergradeassessed) or ($oldpeerforum->peergradescale<>$peerforum->peergradescale)) {
        peerforum_update_grades($peerforum); // recalculate grades for the peerforum
    }

    if ($peerforum->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('peerforum_discussions', array('peerforum'=>$peerforum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'peerforum'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion->course          = $peerforum->course;
            $discussion->peerforum           = $peerforum->id;
            $discussion->name            = $peerforum->name;
            $discussion->assessed        = $peerforum->assessed;
            $discussion->message         = $peerforum->intro;
            $discussion->messageformat   = $peerforum->introformat;
            $discussion->messagetrust    = true;
            $discussion->mailnow         = false;
            $discussion->groupid         = -1;

            $message = '';

            peerforum_add_discussion($discussion, null, $message);

            if (! $discussion = $DB->get_record('peerforum_discussions', array('peerforum'=>$peerforum->id))) {
                print_error('cannotadd', 'peerforum');
            }
        }
        if (! $post = $DB->get_record('peerforum_posts', array('id'=>$discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'peerforum');
        }

        $cm         = get_coursemodule_from_instance('peerforum', $peerforum->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('peerforum_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);
        $post->subject       = $peerforum->name;
        $post->message       = $peerforum->intro;
        $post->messageformat = $peerforum->introformat;
        $post->messagetrust  = trusttext_trusted($modcontext);
        $post->modified      = $peerforum->timemodified;
        $post->userid        = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_peerforum', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('peerforum_posts', $post);
        $discussion->name = $peerforum->name;
        $DB->update_record('peerforum_discussions', $discussion);
    }

    $DB->update_record('peerforum', $peerforum);

    $modcontext = context_module::instance($peerforum->coursemodule);
    if (($peerforum->forcesubscribe == PEERFORUM_INITIALSUBSCRIBE) && ($oldpeerforum->forcesubscribe <> $peerforum->forcesubscribe)) {
        $users = peerforum_get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            peerforum_subscribe($user->id, $peerforum->id);
        }
    }

    peerforum_grade_item_update($peerforum);

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id peerforum instance id
 * @return bool success
 */
function peerforum_delete_instance($id) {
    global $DB;

    if (!$peerforum = $DB->get_record('peerforum', array('id'=>$id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    if ($discussions = $DB->get_records('peerforum_discussions', array('peerforum'=>$peerforum->id))) {
        foreach ($discussions as $discussion) {
            if (!peerforum_delete_discussion($discussion, true, $course, $cm, $peerforum)) {
                $result = false;
            }
        }
    }

    if (!$DB->delete_records('peerforum_digests', array('peerforum' => $peerforum->id))) {
        $result = false;
    }

    if (!$DB->delete_records('peerforum_subscriptions', array('peerforum'=>$peerforum->id))) {
        $result = false;
    }

    peerforum_tp_delete_read_records(-1, -1, -1, $peerforum->id);

    if (!$DB->delete_records('peerforum', array('id'=>$peerforum->id))) {
        $result = false;
    }

    peerforum_grade_item_delete($peerforum);

    return $result;
}


/**
 * Indicates API features that the peerforum supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function peerforum_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_PLAGIARISM:              return true;

        default: return null;
    }
}


/**
 * Obtains the automatic completion state for this peerforum based on any conditions
 * in peerforum settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function peerforum_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get peerforum details
    if (!($peerforum=$DB->get_record('peerforum',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find peerforum {$cm->instance}");
    }

    $result=$type; // Default return value

    $postcountparams=array('userid'=>$userid,'peerforumid'=>$peerforum->id);
    $postcountsql="
SELECT
    COUNT(1)
FROM
    {peerforum_posts} fp
    INNER JOIN {peerforum_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.peerforum=:peerforumid";

    if ($peerforum->completiondiscussions) {
        $value = $peerforum->completiondiscussions <=
                 $DB->count_records('peerforum_discussions',array('peerforum'=>$peerforum->id,'userid'=>$userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($peerforum->completionreplies) {
        $value = $peerforum->completionreplies <=
                 $DB->get_field_sql( $postcountsql.' AND fp.parent<>0',$postcountparams);
        if ($type==COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($peerforum->completionposts) {
        $value = $peerforum->completionposts <= $DB->get_field_sql($postcountsql,$postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Create a message-id string to use in the custom headers of peerforum notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the peerforum post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @param string $hostname The server's hostname
 * @return string A unique message-id
 */
function peerforum_get_email_message_id($postid, $usertoid, $hostname) {
    return '<'.hash('sha256',$postid.'to'.$usertoid).'@'.$hostname.'>';
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function peerforum_cron_minimise_user_record(stdClass $user) {

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CONTEXT_COURSE
 * @uses SITEID
 * @uses FORMAT_PLAIN
 * @return void
 */
function peerforum_cron() {
    global $CFG, $USER, $DB;

    $site = get_site();

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!

    // status arrays
    $mailcount  = array();
    $errorcount = array();

    // caches
    $discussions     = array();
    $peerforums          = array();
    $courses         = array();
    $coursemodules   = array();
    $subscribedusers = array();


    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier

    // Get the list of peerforum subscriptions for per-user per-peerforum maildigest settings.
    $digestsset = $DB->get_recordset('peerforum_digests', null, '', 'id, userid, peerforum, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->peerforum])) {
            $digests[$thisrow->peerforum] = array();
        }
        $digests[$thisrow->peerforum][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    if ($posts = peerforum_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!peerforum_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('peerforum_discussions', array('id'=> $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                } else {
                    mtrace('Could not find discussion '.$discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $peerforumid = $discussions[$discussionid]->peerforum;
            if (!isset($peerforums[$peerforumid])) {
                if ($peerforum = $DB->get_record('peerforum', array('id' => $peerforumid))) {
                    $peerforums[$peerforumid] = $peerforum;
                } else {
                    mtrace('Could not find peerforum '.$peerforumid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $peerforums[$peerforumid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$peerforumid])) {
                if ($cm = get_coursemodule_from_instance('peerforum', $peerforumid, $courseid)) {
                    $coursemodules[$peerforumid] = $cm;
                } else {
                    mtrace('Could not find course module for peerforum '.$peerforumid);
                    unset($posts[$pid]);
                    continue;
                }
            }


            // caching subscribed users of each peerforum
            if (!isset($subscribedusers[$peerforumid])) {
                $modcontext = context_module::instance($coursemodules[$peerforumid]->id);
                if ($subusers = peerforum_subscribed_users($courses[$courseid], $peerforums[$peerforumid], 0, $modcontext, "u.*")) {
                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this peerforum
                        $subscribedusers[$peerforumid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > PEERFORUM_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            peerforum_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }

            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {

            @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

            mtrace('Processing user '.$userto->id);

            // Init user caches - we keep the cache for one cycle only,
            // otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                peerforum_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();

            // set this so that the capabilities are cached, and environment matches receiving user
            cron_setup_user($userto);

            // reset the caches
            foreach ($coursemodules as $peerforumid=>$unused) {
                $coursemodules[$peerforumid]->cache       = new stdClass();
                $coursemodules[$peerforumid]->cache->caps = array();
                unset($coursemodules[$peerforumid]->uservisible);
            }

            foreach ($posts as $pid => $post) {

                // Set up the environment for the post, discussion, peerforum, course
                $discussion = $discussions[$post->discussion];
                $peerforum      = $peerforums[$discussion->peerforum];
                $course     = $courses[$peerforum->course];
                $cm         =& $coursemodules[$peerforum->id];

                // Do some checks  to see if we can bail out now
                // Only active enrolled users are in the list of subscribers
                if (!isset($subscribedusers[$peerforum->id][$userto->id])) {
                    continue; // user does not subscribe to this peerforum
                }

                // Don't send email if the peerforum is Q&A and the user has not posted
                // Initial topics are still mailed
                if ($peerforum->type == 'qanda' && !peerforum_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email '.$userto->id.' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user
                if (array_key_exists($post->userid, $users)) { // we might know him/her already
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        peerforum_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    peerforum_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= PEERFORUM_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }

                } else {
                    mtrace('Could not find user '.$post->userid);
                    continue;
                }

                //if we want to check that userto and userfrom are not the same person this is probably the spot to do it

                // setup global $COURSE properly - needed for roles and languages
                cron_setup_user($userto, $course);

                // Fill caches
                if (!isset($userto->viewfullnames[$peerforum->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$peerforum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = peerforum_user_can_post($peerforum, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$peerforum->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$peerforum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$peerforum->id] = $userfrom->groups[$peerforum->id];
                    }
                }

                // Make sure groups allow this user to see this email
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
                    if (!groups_group_exists($discussion->groupid)) { // Can't find group
                        continue;                           // Be safe and don't send it to anyone
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
                        continue;
                    }
                }

                // Make sure we're allowed to see it...
                if (!peerforum_user_can_see_post($peerforum, $discussion, $post, NULL, $cm)) {
                    mtrace('user '.$userto->id. ' can not see '.$post->id);
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = peerforum_get_user_maildigest_bulk($digests, $userto, $peerforum->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form
                    $queue = new stdClass();
                    $queue->userid       = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid       = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('peerforum_queue', $queue);
                    continue;
                }


                // Prepare to actually send the post now, and build up the content

                $cleanpeerforumname = str_replace('"', "'", strip_tags(format_string($peerforum->name)));

                $userfrom->customheaders = array (  // Headers to make emails easier to track
                           'Precedence: Bulk',
                           'List-Id: "'.$cleanpeerforumname.'" <moodlepeerforum'.$peerforum->id.'@'.$hostname.'>',
                           'List-Help: '.$CFG->wwwroot.'/mod/peerforum/view.php?f='.$peerforum->id,
                           'Message-ID: '.peerforum_get_email_message_id($post->id, $userto->id, $hostname),
                           'X-Course-Id: '.$course->id,
                           'X-Course-Name: '.format_string($course->fullname, true)
                );

                if ($post->parent) {  // This post is a reply, so add headers for threading (see MDL-22551)
                    $userfrom->customheaders[] = 'In-Reply-To: '.peerforum_get_email_message_id($post->parent, $userto->id, $hostname);
                    $userfrom->customheaders[] = 'References: '.peerforum_get_email_message_id($post->parent, $userto->id, $hostname);
                }

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                $postsubject = html_to_text("$shortname: ".format_string($post->subject, true));
                $posttext = peerforum_make_mail_text($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto);
                $posthtml = peerforum_make_mail_html($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto);

                // Send the post now!

                mtrace('Sending ', '');

                $eventdata = new stdClass();
                $eventdata->component        = 'mod_peerforum';
                $eventdata->name             = 'posts';
                $eventdata->userfrom         = $userfrom;
                $eventdata->userto           = $userto;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->notification = 1;

                // If peerforum_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->peerforum_replytouser)) {
                    // Clone userfrom as it is referenced by $users.
                    $cloneduserfrom = clone($userfrom);
                    $cloneduserfrom->email = $CFG->noreplyaddress;
                    $eventdata->userfrom = $cloneduserfrom;
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user = fullname($userfrom);
                $smallmessagestrings->peerforumname = "$shortname: ".format_string($peerforum->name,true).": ".$discussion->name;
                $smallmessagestrings->message = $post->message;
                //make sure strings are in message recipients language
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'peerforum', $smallmessagestrings, $userto->lang);

                $eventdata->contexturl = "{$CFG->wwwroot}/mod/peerforum/discuss.php?d={$discussion->id}#p{$post->id}";
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult){
                    mtrace("Error: mod/peerforum/lib.php peerforum_cron(): Could not send out mail for id $post->id to user $userto->id".
                         " ($userto->email) .. not trying again.");
                    add_to_log($course->id, 'peerforum', 'mail error', "discuss.php?d=$discussion->id#p$post->id",
                               substr(format_string($post->subject,true),0,30), $cm->id, $userto->id);
                    $errorcount[$post->id]++;
                } else {
                    $mailcount[$post->id]++;

                // Mark post as read if peerforum_usermarksread is set off
                    if (!$CFG->peerforum_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post '.$post->id. ': '.$post->subject);
            }

            // mark processed posts as read
            peerforum_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id]." users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('peerforum_posts', 'mailed', PEERFORUM_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = $CFG->timezone;

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    @set_time_limit(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->digestmailtimelast)) {    // To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('peerforum_queue', "timemodified < ?", array($weekago));
    mtrace ('Cleaned old digest records');

    if ($CFG->digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending peerforum digests: '.userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('peerforum_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('peerforum_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('peerforum_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $peerforumid = $discussions[$discussionid]->peerforum;
                if (!isset($peerforums[$peerforumid])) {
                    if ($peerforum = $DB->get_record('peerforum', array('id' => $peerforumid))) {
                        $peerforums[$peerforumid] = $peerforum;
                    } else {
                        continue;
                    }
                }

                $courseid = $peerforums[$peerforumid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$peerforumid])) {
                    if ($cm = get_coursemodule_from_instance('peerforum', $peerforumid, $courseid)) {
                        $coursemodules[$peerforumid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                @set_time_limit(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'peerforum', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('peerforum_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    peerforum_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost       = array();
                $userto->markposts     = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'peerforum', format_string($site->shortname, true));

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot.'/user/edit.php?id='.$userid.'&amp;course='.$site->id;

                $posttext = get_string('digestmailheader', 'peerforum', $headerdata)."\n\n";
                $headerdata->userprefs = '<a target="_blank" href="'.$headerdata->userprefs.'">'.get_string('digestmailprefs', 'peerforum').'</a>';

                $posthtml = "<head>";
/*                foreach ($CFG->stylesheets as $stylesheet) {
                    //TODO: MDL-21120
                    $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                }*/
                $posthtml .= "</head>\n<body id=\"email\">\n";
                $posthtml .= '<p>'.get_string('digestmailheader', 'peerforum', $headerdata).'</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    @set_time_limit(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $peerforum      = $peerforums[$discussion->peerforum];
                    $course     = $courses[$peerforum->course];
                    $cm         = $coursemodules[$peerforum->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$peerforum->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$peerforum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = peerforum_user_can_post($peerforum, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strpeerforums      = get_string('peerforums', 'peerforum');
                    $canunsubscribe = ! peerforum_is_forcesubscribed($peerforum);
                    $canreply       = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strpeerforums -> ".format_string($peerforum->name,true);
                    if ($discussion->name != $peerforum->name) {
                        $posttext  .= " -> ".format_string($discussion->name,true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/peerforum/index.php?id=$course->id\">$strpeerforums</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/peerforum/view.php?f=$peerforum->id\">".format_string($peerforum->name,true)."</a>";
                    if ($discussion->name == $peerforum->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/peerforum/discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                peerforum_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            peerforum_cron_minimise_user_record($userfrom);
                            if ($userscount <= PEERFORUM_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user '.$post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$peerforum->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$peerforum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$peerforum->id] = $userfrom->groups[$peerforum->id];
                            }
                        }

                        $userfrom->customheaders = array ("Precedence: Bulk");

                        $maildigest = peerforum_get_user_maildigest_bulk($digests, $userto, $peerforum->id);
                        if ($maildigest == 2) {
                            // Subjects and link only
                            $posttext .= "\n";
                            $posttext .= $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id;
                            $by = new stdClass();
                            $by->name = fullname($userfrom);
                            $by->date = userdate($post->modified);
                            $posttext .= "\n".format_string($post->subject,true).' '.get_string("bynameondate", "peerforum", $by);
                            $posttext .= "\n---------------------------------------------------------------------";

                            $by->name = "<a target=\"_blank\" href=\"$CFG->wwwroot/user/view.php?id=$userfrom->id&amp;course=$course->id\">$by->name</a>";
                            $posthtml .= '<div><a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id.'#p'.$post->id.'">'.format_string($post->subject,true).'</a> '.get_string("bynameondate", "peerforum", $by).'</div>';

                        } else {
                            // The full treatment
                            $posttext .= peerforum_make_mail_text($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto, true);
                            $posthtml .= peerforum_make_mail_post($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

                        // Create an array of postid's for this user to mark as read.
                            if (!$CFG->peerforum_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/peerforum/subscribe.php?id=$peerforum->id\">" . get_string("unsubscribe", "peerforum") . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", "peerforum");
                    }
                    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/peerforum/index.php?id={$peerforum->course}'>" . get_string("digestmailpost", "peerforum") . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $attachment = $attachname='';
                // Directly email peerforum digests rather than sending them via messaging, use the
                // site shortname as 'from name', the noreply address will be used by email_to_user.
                $mailresult = email_to_user($userto, $site->shortname, $postsubject, $posttext, $posthtml, $attachment, $attachname);

                if (!$mailresult) {
                    mtrace("ERROR!");
                    echo "Error: mod/peerforum/cron.php: Could not send out digest mail to user $userto->id ($userto->email)... not trying again.\n";
                    add_to_log($course->id, 'peerforum', 'mail digest error', '', '', $cm->id, $userto->id);
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if peerforum_usermarksread is set off
                    peerforum_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
    /// We have finishied all digest emails, update $CFG->digestmailtimelast
        set_config('digestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'peerforum', $usermailcount));
    }

    if (!empty($CFG->peerforum_lastreadclean)) {
        $timenow = time();
        if ($CFG->peerforum_lastreadclean + (24*3600) < $timenow) {
            set_config('peerforum_lastreadclean', $timenow);
            mtrace('Removing old peerforum read tracking info...');
            peerforum_tp_clean_read_records();
        }
    } else {
        set_config('peerforum_lastreadclean', time());
    }


    return true;
}

/**
 * Builds and returns the body of the email notification in plain text.
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @param boolean $bare
 * @return string The email body in plain text format.
 */
function peerforum_make_mail_text($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto, $bare = false) {
    global $CFG, $USER;

    $modcontext = context_module::instance($cm->id);

    if (!isset($userto->viewfullnames[$peerforum->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$peerforum->id];
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = peerforum_user_can_post($peerforum, $discussion, $userto, $cm, $course, $modcontext);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $by = New stdClass;
    $by->name = fullname($userfrom, $viewfullnames);
    $by->date = userdate($post->modified, "", $userto->timezone);

    $strbynameondate = get_string('bynameondate', 'peerforum', $by);

    $strpeerforums = get_string('peerforums', 'peerforum');

    $canunsubscribe = ! peerforum_is_forcesubscribed($peerforum);

    $posttext = '';

    if (!$bare) {
        $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
        $posttext  = "$shortname -> $strpeerforums -> ".format_string($peerforum->name,true);

        if ($discussion->name != $peerforum->name) {
            $posttext  .= " -> ".format_string($discussion->name,true);
        }
    }

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_peerforum', 'post', $post->id);

    $posttext .= "\n";
    $posttext .= $CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id;
    $posttext .= "\n---------------------------------------------------------------------\n";
    $posttext .= format_string($post->subject,true);
    if ($bare) {
        $posttext .= " ($CFG->wwwroot/mod/peerforum/discuss.php?d=$discussion->id#p$post->id)";
    }
    $posttext .= "\n".$strbynameondate."\n";
    $posttext .= "---------------------------------------------------------------------\n";
    $posttext .= format_text_email($post->message, $post->messageformat);
    $posttext .= "\n\n";
    $posttext .= peerforum_print_attachments($post, $cm, "text");

    if (!$bare && $canreply) {
        $posttext .= "---------------------------------------------------------------------\n";
        $posttext .= get_string("postmailinfo", "peerforum", $shortname)."\n";
        $posttext .= "$CFG->wwwroot/mod/peerforum/post.php?reply=$post->id\n";
    }
    if (!$bare && $canunsubscribe) {
        $posttext .= "\n---------------------------------------------------------------------\n";
        $posttext .= get_string("unsubscribe", "peerforum");
        $posttext .= ": $CFG->wwwroot/mod/peerforum/subscribe.php?id=$peerforum->id\n";
    }

    $posttext .= "\n---------------------------------------------------------------------\n";
    $posttext .= get_string("digestmailpost", "peerforum");
    $posttext .= ": {$CFG->wwwroot}/mod/peerforum/index.php?id={$peerforum->course}\n";

    return $posttext;
}

/**
 * Builds and returns the body of the email notification in html format.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $userfrom
 * @param object $userto
 * @return string The email text in HTML format
 */
function peerforum_make_mail_html($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto) {
    global $CFG;

    if ($userto->mailformat != 1) {  // Needs to be HTML
        return '';
    }

    if (!isset($userto->canpost[$discussion->id])) {
        $canreply = peerforum_user_can_post($peerforum, $discussion, $userto, $cm, $course);
    } else {
        $canreply = $userto->canpost[$discussion->id];
    }

    $strpeerforums = get_string('peerforums', 'peerforum');
    $canunsubscribe = ! peerforum_is_forcesubscribed($peerforum);
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

    $posthtml = '<head>';
/*    foreach ($CFG->stylesheets as $stylesheet) {
        //TODO: MDL-21120
        $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
    }*/
    $posthtml .= '</head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $posthtml .= '<div class="navbar">'.
    '<a target="_blank" href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'.$shortname.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/index.php?id='.$course->id.'">'.$strpeerforums.'</a> &raquo; '.
    '<a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/view.php?f='.$peerforum->id.'">'.format_string($peerforum->name,true).'</a>';
    if ($discussion->name == $peerforum->name) {
        $posthtml .= '</div>';
    } else {
        $posthtml .= ' &raquo; <a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$discussion->id.'">'.
                     format_string($discussion->name,true).'</a></div>';
    }
    $posthtml .= peerforum_make_mail_post($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto, false, $canreply, true, false);

    $footerlinks = array();
    if ($canunsubscribe) {
        $footerlinks[] = '<a href="' . $CFG->wwwroot . '/mod/peerforum/subscribe.php?id=' . $peerforum->id . '">' . get_string('unsubscribe', 'peerforum') . '</a>';
        $footerlinks[] = '<a href="' . $CFG->wwwroot . '/mod/peerforum/unsubscribeall.php">' . get_string('unsubscribeall', 'peerforum') . '</a>';
    }
    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/peerforum/index.php?id={$peerforum->course}'>" . get_string('digestmailpost', 'peerforum') . '</a>';
    $posthtml .= '<hr /><div class="mdl-align unsubscribelink">' . implode('&nbsp;', $footerlinks) . '</div>';

    $posthtml .= '</body>';

    return $posthtml;
}


/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $peerforum
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function peerforum_user_outline($course, $user, $mod, $peerforum) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'peerforum', $peerforum->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = peerforum_count_user_posts($peerforum->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string("numposts", "peerforum", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}


/**
 * @global object
 * @global object
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $peerforum
 */
function peerforum_user_complete($course, $user, $mod, $peerforum) {
    global $CFG,$USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'peerforum', $peerforum->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($posts = peerforum_get_user_posts($peerforum->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = peerforum_get_user_involved_discussions($peerforum->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            peerforum_print_post($post, $discussion, $peerforum, $cm, $course, false, false, false, "", "", null, true, null, false, true, false, true, null);
        }
    } else {
        echo "<p>".get_string("noposts", "peerforum")."</p>";
    }
}

/**
 * Filters the peerforum discussions according to groups membership and config.
 *
 * @since  Moodle 2.8, 2.7.1, 2.6.4
 * @param  array $discussions Discussions with new posts array
 * @return array PeerForums with the number of new posts
 */
function peerforum_filter_user_groups_discussions($discussions) {

    // Group the remaining discussions posts by their peerforumid.
    $filteredpeerforums = array();

    // Discard not visible groups.
    foreach ($discussions as $discussion) {

        // Course data is already cached.
        $instances = get_fast_modinfo($discussion->course)->get_instances();
        $peerforum = $instances['peerforum'][$discussion->peerforum];

        // Continue if the user should not see this discussion.
        if (!peerforum_is_user_group_discussion($peerforum, $discussion->groupid)) {
            continue;
        }

        // Grouping results by peerforum.
        if (empty($filteredpeerforums[$peerforum->instance])) {
            $filteredpeerforums[$peerforum->instance] = new stdClass();
            $filteredpeerforums[$peerforum->instance]->id = $peerforum->id;
            $filteredpeerforums[$peerforum->instance]->count = 0;
        }
        $filteredpeerforums[$peerforum->instance]->count += $discussion->count;

    }

    return $filteredpeerforums;
}

/**
 * Returns whether the discussion group is visible by the current user or not.
 *
 * @since Moodle 2.8, 2.7.1, 2.6.4
 * @param cm_info $cm The discussion course module
 * @param int $discussiongroupid The discussion groupid
 * @return bool
 */
function peerforum_is_user_group_discussion(cm_info $cm, $discussiongroupid) {

    if ($discussiongroupid == -1 || $cm->effectivegroupmode != SEPARATEGROUPS) {
        return true;
    }

    if (isguestuser()) {
        return false;
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id)) ||
            in_array($discussiongroupid, $cm->get_modinfo()->get_groups($cm->groupingid))) {
        return true;
    }

    return false;
}

/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function peerforum_print_overview($courses,&$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$peerforums = get_all_instances_in_courses('peerforum',$courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course->lastaccess == 0) {
            $coursessqls[] = '(f.course = ?)';
            $params[] = $course->id;

        // Only posts created after the course last access
        } else {
            $coursessqls[] = '(f.course = ? AND p.created > ?)';
            $params[] = $course->id;
            $params[] = $course->lastaccess;
        }
    }
    $params[] = $USER->id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT d.id, d.peerforum, f.course, d.groupid, COUNT(*) as count "
                .'FROM {peerforum} f '
                .'JOIN {peerforum_discussions} d ON d.peerforum = f.id '
                .'JOIN {peerforum_posts} p ON p.discussion = d.id '
                ."WHERE ($coursessql) "
                .'AND p.userid != ? '
                .'GROUP BY d.id, d.peerforum, f.course, d.groupid '
                .'ORDER BY f.course, d.peerforum';

    // Avoid warnings.
    if (!$discussions = $DB->get_records_sql($sql, $params)) {
        $discussions = array();
    }

    $peerforumsnewposts = peerforum_filter_user_groups_discussions($discussions);

    // also get all peerforum tracking stuff ONCE.
    $trackingpeerforums = array();
    foreach ($peerforums as $peerforum) {
        if (peerforum_tp_can_track_peerforums($peerforum)) {
            $trackingpeerforums[$peerforum->id] = $peerforum;
        }
    }

    if (count($trackingpeerforums) > 0) {
        $cutoffdate = isset($CFG->peerforum_oldpostdays) ? (time() - ($CFG->peerforum_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.peerforum,d.course,COUNT(p.id) AS count '.
            ' FROM {peerforum_posts} p '.
            ' JOIN {peerforum_discussions} d ON p.discussion = d.id '.
            ' LEFT JOIN {peerforum_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingpeerforums as $track) {
            $sql .= '(d.peerforum = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track->id;
            if (isset($SESSION->currentgroup[$track->course])) {
                $groupid =  $SESSION->currentgroup[$track->course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track->course, $USER->id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION->currentgroup[$track->course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL GROUP BY d.peerforum,d.course';
        $params[] = $cutoffdate;

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($peerforumsnewposts)) {
        return;
    }

    $strpeerforum = get_string('modulename','peerforum');

    foreach ($peerforums as $peerforum) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($peerforum->id, $peerforumsnewposts) && !empty($peerforumsnewposts[$peerforum->id])) {
            $count = $peerforumsnewposts[$peerforum->id]->count;
        }
        if (array_key_exists($peerforum->id,$unread)) {
            $thisunread = $unread[$peerforum->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview peerforum"><div class="name">'.$strpeerforum.': <a title="'.$strpeerforum.'" href="'.$CFG->wwwroot.'/mod/peerforum/view.php?f='.$peerforum->id.'">'.
                $peerforum->name.'</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'peerforum', $count)."</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">'.get_string('overviewnumunread', 'peerforum', $thisunread).'</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($peerforum->course,$htmlarray)) {
                $htmlarray[$peerforum->course] = array();
            }
            if (!array_key_exists('peerforum',$htmlarray[$peerforum->course])) {
                $htmlarray[$peerforum->course]['peerforum'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$peerforum->course]['peerforum'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function peerforum_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS peerforumtype, d.peerforum, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {peerforum_posts} p
                                              JOIN {peerforum_discussions} d ON d.id = p.discussion
                                              JOIN {peerforum} f             ON f.id = d.peerforum
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course->id))) { // order by initial posting date
         return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms    = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['peerforum'][$post->peerforum])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['peerforum'][$post->peerforum];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/peerforum:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->peerforum_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/peerforum:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        // Check that the user can see the discussion.
        if (peerforum_is_user_group_discussion($cm, $post->groupid)) {
            $printposts[] = $post;
        }

    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newpeerforumposts', 'peerforum').':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">'.
               '<div class="date">'.userdate($post->modified, $strftimerecent).'</div>'.
               '<div class="name">'.fullname($post, $viewfullnames).'</div>'.
             '</div>';
        echo '<div class="info'.$subjectclass.'">';
        if (empty($post->parent)) {
            echo '"<a href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'">';
        } else {
            echo '"<a href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'&amp;parent='.$post->parent.'#p'.$post->id.'">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $peerforum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function peerforum_get_user_grades($peerforum, $userid = 0) {
    global $CFG;

    if($peerforum->assessed){
        require_once($CFG->dirroot.'/ratingpeer/lib.php');

        $ratingpeeroptions = new stdClass;
        $ratingpeeroptions->component = 'mod_peerforum';
        $ratingpeeroptions->ratingpeerarea = 'post';

        //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
        $ratingpeeroptions->modulename = 'peerforum';
        $ratingpeeroptions->moduleid   = $peerforum->id;
        $ratingpeeroptions->userid = $userid;
        $ratingpeeroptions->aggregationmethod = $peerforum->assessed;
        $ratingpeeroptions->scaleid = $peerforum->scale;
        $ratingpeeroptions->itemtable = 'peerforum_posts';
        $ratingpeeroptions->itemtableusercolumn = 'userid';

        $rm = new ratingpeer_manager();
        return $rm->get_user_grades($ratingpeeroptions);

    } else {
        return null;
    }

}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $peerforum
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function peerforum_update_grades($peerforum, $userid = 0, $nullifnone = true) {

        global $CFG, $DB;

        require_once($CFG->libdir.'/gradelib.php');

        if (!$peerforum->peergradeassessed || !$peerforum->assessed) {
            peerforum_grade_item_update($peerforum);
        }

        $peergradesstudents = peerforum_get_user_students_peergrades($peerforum, $userid);

        $peergradesprofessors = peerforum_get_user_professors_peergrades($peerforum, $userid);

        $ratepeergrades = peerforum_get_user_grades($peerforum, $userid);


        if (!empty($peergradesstudents) && $peerforum->peergradeassessed) {
            peerforum_grade_item_update($peerforum, null, $peergradesstudents, null);
        }

        if (!empty($peergradesprofessors) && $peerforum->peergradeassessed) {
           peerforum_grade_item_update($peerforum, $peergradesprofessors, null, null);
        }

        if (!empty($ratepeergrades) && $peerforum->assessed) {
           peerforum_grade_item_update($peerforum, null, null, $ratepeergrades);
        }

         else if ($userid and $nullifnone) {

             $grade = new stdClass();
             $grade->userid   = $userid;
             $grade->rawgrade = NULL;

             if(empty($peergradesprofessors)){
                 $peergradesprofessors = $grade;
             }
             if(empty($peergradesstudents)){
                 $peergradesstudents = $grade;
             }
             if(empty($ratepeergrades)){
                 $ratepeergrades = $grade;
             }

             peerforum_grade_item_update($peerforum, $peergradesprofessors, $peergradesstudents, $ratepeergrades);

        } else {
            peerforum_grade_item_update($peerforum);
        }
    }

/**
 * Update all grades in gradebook.
 * @global object
 */
function peerforum_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {peerforum} f, {course_modules} cm, {modules} m
             WHERE m.name='peerforum' AND m.id=cm.module AND cm.instance=f.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT f.*, cm.idnumber AS cmidnumber, f.course AS courseid
              FROM {peerforum} f, {course_modules} cm, {modules} m
             WHERE m.name='peerforum' AND m.id=cm.module AND cm.instance=f.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        $pbar = new progress_bar('peerforumupgradegrades', 500, true);
        $i=0;
        foreach ($rs as $peerforum) {
            $i++;
            upgrade_set_timeout(60*5); // set up timeout, may also abort execution
            peerforum_update_grades($peerforum, 0, false);
            $pbar->update($i, $count, "Updating PeerForum grades ($i/$count).");
        }
    }
    $rs->close();
}

/**
 * Create/update grade item for given peerforum
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $peerforum PeerForum object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function peerforum_grade_item_update($peerforum,  $peergradesprofessors=NULL, $peergradesstudents=NULL, $ratepeergrades=NULL) {

        global $CFG;

        if (!function_exists('grade_update')) { //workaround for buggy PHP versions
            require_once($CFG->libdir.'/gradelib.php');
        }

        $a = new stdclass();
        $a->peerforumname = clean_param($peerforum->name, PARAM_NOTAGS);

    /*    if(empty($peerforum->cmidnumber)){
            $cm = get_coursemodule_from_instance('peerforum', $peerforum->id);
            $peerforum->cmidnumber = $cm->id;
        }*/

/*Peer grade professor*/
        $item = array();
        $item['idnumber'] = $peerforum->cmidnumber;
        $item['itemname'] = get_string('gradeitemprofessorpeergrade', 'peerforum', $a);

        if (!$peerforum->peergradeassessed || $peerforum->peergradescale == 0) {
            $item['gradetype'] = GRADE_TYPE_NONE;
        }
        else if ($peerforum->finalgrademode == 1) {
            $item['gradetype'] = GRADE_TYPE_NONE;
        }
        else if ($peerforum->peergradescale > 0) {
           $item['gradetype'] = GRADE_TYPE_VALUE;
           $item['grademax']  = $peerforum->professorpercentage;
           $item['grademin']  = 0;
       }
        else if ($peerforum->peergradescale < 0) {
            $item['gradetype'] = GRADE_TYPE_SCALE;
            $item['scaleid']   = -$peerforum->peergradescale;
        }

        if ($peergradesprofessors  === 'reset') {
            $item['reset'] = true;
            $peergradesprofessors = NULL;
        }

        grade_update('mod/peerforum', $peerforum->course, 'mod', 'peerforum', $peerforum->id, 1, $peergradesprofessors, $item);

/*Peer grade student*/
            $item2 = array();
            $item2['idnumber'] = $peerforum->cmidnumber;
            $item2['itemname'] = get_string('gradeitemstudentpeergrade', 'peerforum', $a);

            if (!$peerforum->peergradeassessed || $peerforum->peergradescale == 0) {
                $item2['gradetype'] = GRADE_TYPE_NONE;
            }
            else if ($peerforum->finalgrademode == 1) {
                $item2['gradetype'] = GRADE_TYPE_NONE;
            }
            else if ($peerforum->peergradescale > 0) {
               $item2['gradetype'] = GRADE_TYPE_VALUE;
               $item2['grademax']  = $peerforum->studentpercentage;
               $item2['grademin']  = 0;
           }
            else if ($peerforum->peergradescale < 0) {
                $item2['gradetype'] = GRADE_TYPE_SCALE;
                $item2['scaleid']   = -$peerforum->peergradescale;
            }

            if ($peergradesstudents  === 'reset') {
                $item2['reset'] = true;
                $peergradesstudents = NULL;
            }

            grade_update('mod/peerforum', $peerforum->course, 'mod', 'peerforum', $peerforum->id, 0, $peergradesstudents, $item2);

/*Rate*/

            $item3 = array();
            $item3['idnumber'] = $peerforum->cmidnumber;
            $item3['itemname'] = get_string('gradeitemratepeer', 'peerforum', $a);

            if (!$peerforum->assessed || $peerforum->scale == 0) {
                $item3['gradetype'] = GRADE_TYPE_NONE;
            }
            else if ($peerforum->scale > 0) {
               $item3['gradetype'] = GRADE_TYPE_VALUE;
               $item3['grademax']  = $peerforum->scale;
               $item3['grademin']  = 0;
            }
            else if ($peerforum->scale < 0) {
                $item3['gradetype'] = GRADE_TYPE_SCALE;
                $item3['scaleid']   = -$peerforum->scale;
            }

            if ($ratepeergrades  === 'reset') {
                $item3['reset'] = true;
                $ratepeergrades = NULL;
            }

            grade_update('mod/peerforum', $peerforum->course, 'mod', 'peerforum', $peerforum->id, 2, $ratepeergrades, $item3);
    }

/**
 * Delete grade item for given peerforum
 *
 * @category grade
 * @param stdClass $peerforum PeerForum object
 * @return grade_item
 */
function peerforum_grade_item_delete($peerforum) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/peerforum', $peerforum->course, 'mod', 'peerforum', $peerforum->id, 0, NULL, array('deleted'=>1));
}

/**
 * Delete peergrade item for given peerforum
 *
 * @category grade
 * @param stdClass $peerforum PeerForum object
 * @return grade_item
 */
function peerforum_peergrade_item_delete($peerforum) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    grade_update('mod/peerforum', $peerforum->course, 'mod', 'peerforum', $peerforum->id, 0, NULL, array('deleted'=>1));

    return grade_update('mod/peerforum', $peerforum->course, 'mod', 'peerforum', $peerforum->id, 1, NULL, array('deleted'=>1));
}


/**
 * This function returns if a scale is being used by one peerforum
 *
 * @global object
 * @param int $peerforumid
 * @param int $scaleid negative number
 * @return bool
 */
function peerforum_scale_used ($peerforumid,$scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("peerforum",array("id" => "$peerforumid","scale" => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * This function returns if a scale is being used by one peerforum
 *
 * @global object
 * @param int $peerforumid
 * @param int $scaleid negative number
 * @return bool
 */
function peerforum_peergradescale_used($peerforumid,$peergradescaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("peerforum",array("id" => "$peerforumid","peergradescale" => "-$peergradescaleid"));

    if (!empty($rec) && !empty($peergradescaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of peerforum
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any peerforum
 */
function peerforum_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('peerforum', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of peerforum
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any peerforum
 */
function peerforum_peergradescale_used_anywhere($peergradescaleid) {
    global $DB;
    if ($peergradescaleid and $DB->record_exists('peerforum', array('peergradescale' => -$peergradescaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for peerforum_print_post
 * Most of these joins are just to get the peerforum id
 *
 * @global object
 * @global object
 * @param int $postid
 * @return mixed array of posts or false
 */
function peerforum_get_post_full($postid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_record_sql("SELECT p.*, d.peerforum, $allnames, u.email, u.picture, u.imagealt
                             FROM {peerforum_posts} p
                                  JOIN {peerforum_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
}

/**
 * Gets posts with all info ready for peerforum_print_post
 * We pass peerforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @return mixed array of posts or false
 */
function peerforum_get_discussion_posts($discussion, $sort, $peerforumid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $peerforumid AS peerforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {peerforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.discussion = ?
                               AND p.parent > 0 $sort", array($discussion));
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @global object
 * @global object
 * @global object
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the peerforum?
 * @return array of posts
 */
function peerforum_get_all_discussion_posts($discussionid, $sort, $tracking=false) {
    global $CFG, $DB, $USER;

    $tr_sel  = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG->peerforum_oldpostdays * 24 * 3600);
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {peerforum_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER->id;
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $params[] = $discussionid;
    if (!$posts = $DB->get_records_sql("SELECT p.*, $allnames, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {peerforum_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid=>$p) {
        if ($tracking) {
            if (peerforum_tp_is_post_old($p)) {
                 $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    return $posts;
}

/**
 * Can the current user see ratingpeers for a given itemid?
 *
 * @param array $params submitted data
 *            contextid => int contextid [required]
 *            component => The component for this module - should always be mod_peerforum [required]
 *            ratingpeerarea => object the context in which the ratedpeer items exists [required]
 *            itemid => int the ID of the object being ratedpeer [required]
 *            scaleid => int scale id [optional]
 * @return bool
 * @throws coding_exception
 * @throws ratingpeer_exception
 */
function mod_peerforum_ratingpeer_can_see_item_ratingpeers($params) {
    global $DB, $USER;

    // Check the component is mod_peerforum.
    if (!isset($params['component']) || $params['component'] != 'mod_peerforum') {
        throw new ratingpeer_exception('invalidcomponent');
    }

    // Check the ratingpeerarea is post (the only ratingpeer area in peerforum).
    if (!isset($params['ratingpeerarea']) || $params['ratingpeerarea'] != 'post') {
        throw new ratingpeer_exception('invalidratingpeerarea');
    }

    if (!isset($params['itemid'])) {
        throw new ratingpeer_exception('invaliditemid');
    }

    $post = $DB->get_record('peerforum_posts', array('id' => $params['itemid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $peerforum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id , false, MUST_EXIST);

    // Perform some final capability checks.
    if (!peerforum_user_can_see_post($peerforum, $discussion, $post, $USER, $cm)) {
        return false;
    }
    return true;
}

/**
 * Gets posts with all info ready for peerforum_print_post
 * We pass peerforumid in because we always know it so no need to make a
 * complicated join to find it out.
 *
 * @global object
 * @global object
 * @param int $parent
 * @param int $peerforumid
 * @return array
 */
function peerforum_get_child_posts($parent, $peerforumid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, $peerforumid AS peerforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {peerforum_posts} p
                         LEFT JOIN {user} u ON p.userid = u.id
                             WHERE p.parent = ?
                          ORDER BY p.created ASC", array($parent));
}

/**
 * An array of peerforum objects that the user is allowed to read/search through.
 *
 * @global object
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid if 0, we look for peerforums throughout the whole site.
 * @return array of peerforum objects, or false if no matches
 *         PeerForum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function peerforum_get_readable_peerforums($userid, $courseid=0) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    if (!$peerforummod = $DB->get_record('modules', array('name' => 'peerforum'))) {
        print_error('notinstalled', 'peerforum');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readablepeerforums = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['peerforum'])) {
            // hmm, no peerforums?
            continue;
        }

        $coursepeerforums = $DB->get_records('peerforum', array('course' => $course->id));

        foreach ($modinfo->instances['peerforum'] as $peerforumid => $cm) {
            if (!$cm->uservisible or !isset($coursepeerforums[$peerforumid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $peerforum = $coursepeerforums[$peerforumid];
            $peerforum->context = $context;
            $peerforum->cm = $cm;

            if (!has_capability('mod/peerforum:viewdiscussion', $context)) {
                continue;
            }

         /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {

                $peerforum->onlygroups = $modinfo->get_groups($cm->groupingid);
                $peerforum->onlygroups[] = -1;
            }

        /// hidden timed discussions
            $peerforum->viewhiddentimedposts = true;
            if (!empty($CFG->peerforum_enabletimedposts)) {
                if (!has_capability('mod/peerforum:viewhiddentimedposts', $context)) {
                    $peerforum->viewhiddentimedposts = false;
                }
            }

        /// qanda access
            if ($peerforum->type == 'qanda'
                    && !has_capability('mod/peerforum:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda peerforum.
                $peerforum->onlydiscussions = array();  // Holds discussion ids for the discussions
                                                    // the user is allowed to see in this peerforum.
                if ($discussionspostedin = peerforum_discussions_user_has_posted_in($peerforum->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $peerforum->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readablepeerforums[$peerforum->id] = $peerforum;
        }

        unset($modinfo);

    } // End foreach $courses

    return $readablepeerforums;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @global object
 * @global object
 * @global object
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 */
function peerforum_search_posts($searchterms, $courseid=0, $limitfrom=0, $limitnum=50,
                            &$totalcount, $extrasql='') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/searchlib.php');

    $peerforums = peerforum_get_readable_peerforums($USER->id, $courseid);

    if (count($peerforums) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($peerforums as $peerforumid => $peerforum) {
        $select = array();

        if (!$peerforum->viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$peerforumid} OR (d.timestart < :timestart{$peerforumid} AND (d.timeend = 0 OR d.timeend > :timeend{$peerforumid})))";
            $params = array_merge($params, array('userid'.$peerforumid=>$USER->id, 'timestart'.$peerforumid=>$now, 'timeend'.$peerforumid=>$now));
        }

        $cm = $peerforum->cm;
        $context = $peerforum->context;

        if ($peerforum->type == 'qanda'
            && !has_capability('mod/peerforum:viewqandawithoutposting', $context)) {
            if (!empty($peerforum->onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($peerforum->onlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$peerforumid.'_');
                $params = array_merge($params, $discussionid_params);
                $select[] = "(d.id $discussionid_sql OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($peerforum->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($peerforum->onlygroups, SQL_PARAMS_NAMED, 'grps'.$peerforumid.'_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.peerforum = :peerforum{$peerforumid} AND $selects)";
            $params['peerforum'.$peerforumid] = $peerforumid;
        } else {
            $fullaccess[] = $peerforumid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.peerforum $fullid_sql)";
    }

    $selectdiscussion = "(".implode(" OR ", $where).")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach($searchterms as $searchterm){
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"","\"",$searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
    // Experimental feature under 1.8! MDL-8830
    // Use alternative text searches if defined
    // This feature only works under mysql until properly implemented for other DBs
    // Requires manual creation of text index for peerforum_posts before enabling it:
    // CREATE FULLTEXT INDEX foru_post_tix ON [prefix]peerforum_posts (subject, message)
    // Experimental feature under 1.8! MDL-8830
        if (!empty($CFG->peerforum_usetextsearches)) {
            list($messagesearch, $msparams) = search_generate_text_SQL($parsearray, 'p.message', 'p.subject',
                                                 'p.userid', 'u.id', 'u.firstname',
                                                 'u.lastname', 'p.modified', 'd.peerforum');
        } else {
            list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject',
                                                 'p.userid', 'u.id', 'u.firstname',
                                                 'u.lastname', 'p.modified', 'd.peerforum');
        }
        $params = array_merge($params, $msparams);
    }

    $fromsql = "{peerforum_posts} p,
                  {peerforum_discussions} d,
                  {user} u";

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.peerforum,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of ratingpeers for a particular post - sorted.
 *
 * TODO: Check if this function is actually used anywhere.
 * Up until the fix for MDL-27471 this function wasn't even returning.
 *
 * @param stdClass $context
 * @param int $postid
 * @param string $sort
 * @return array Array of ratingpeers or false
 */
function peerforum_get_ratingpeers($context, $postid, $sort = "u.firstname ASC") {
    $options = new stdClass;
    $options->context = $context;
    $options->component = 'mod_peerforum';
    $options->ratingpeerarea = 'post';
    $options->itemid = $postid;
    $options->sort = "ORDER BY $sort";

    $rm = new ratingpeer_manager();
    return $rm->get_all_ratingpeers_for_item($options);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function peerforum_get_unmailed_posts($starttime, $endtime, $now=null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = PEERFORUM_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->peerforum_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.peerforum
                                 FROM {peerforum_posts} p
                                 JOIN {peerforum_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 AND p.created >= :ptimestart
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @global object
 * @global object
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 */
function peerforum_mark_old_posts_as_mailed($endtime, $now=null) {
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = PEERFORUM_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = PEERFORUM_MAILED_PENDING;

    if (empty($CFG->peerforum_enabletimedposts)) {
        return $DB->execute("UPDATE {peerforum_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB->execute("UPDATE {peerforum_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {peerforum_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    }
}

/**
 * Get all the posts for a user in a peerforum suitable for peerforum_print_post
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return array
 */
function peerforum_get_user_posts($peerforumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($peerforumid, $userid);

    if (!empty($CFG->peerforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('peerforum', $peerforumid);
        if (!has_capability('mod/peerforum:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.peerforum, $allnames, u.email, u.picture, u.imagealt
                              FROM {peerforum} f
                                   JOIN {peerforum_discussions} d ON d.peerforum = f.id
                                   JOIN {peerforum_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $peerforumid
 * @param int $userid
 * @return array Array or false
 */
function peerforum_get_user_involved_discussions($peerforumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($peerforumid, $userid);
    if (!empty($CFG->peerforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('peerforum', $peerforumid);
        if (!has_capability('mod/peerforum:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
                              FROM {peerforum} f
                                   JOIN {peerforum_discussions} d ON d.peerforum = f.id
                                   JOIN {peerforum_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a peerforum suitable for peerforum_print_post
 *
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int $userid
 * @return array of counts or false
 */
function peerforum_count_user_posts($peerforumid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($peerforumid, $userid);
    if (!empty($CFG->peerforum_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('peerforum', $peerforumid);
        if (!has_capability('mod/peerforum:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {peerforum} f
                                  JOIN {peerforum_discussions} d ON d.peerforum = f.id
                                  JOIN {peerforum_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the peerforum post details for it.
 *
 * @global object
 * @global object
 * @param object $log
 * @return array|null
 */
function peerforum_get_post_from_log($log) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == "add post") {

        return $DB->get_record_sql("SELECT p.*, f.type AS peerforumtype, d.peerforum, d.groupid, $allnames, u.email, u.picture
                                 FROM {peerforum_discussions} d,
                                      {peerforum_posts} p,
                                      {peerforum} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.peerforum", array($log->info));


    } else if ($log->action == "add discussion") {

        return $DB->get_record_sql("SELECT p.*, f.type AS peerforumtype, d.peerforum, d.groupid, $allnames, u.email, u.picture
                                 FROM {peerforum_discussions} d,
                                      {peerforum_posts} p,
                                      {peerforum} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.peerforum", array($log->info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @global object
 * @global object
 * @param int $dicsussionid
 * @return array
 */
function peerforum_get_firstpost_from_discussion($discussionid) {
    global $CFG, $DB;

    return $DB->get_record_sql("SELECT p.*
                             FROM {peerforum_discussions} d,
                                  {peerforum_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ", array($discussionid));
}

/**
 * Returns an array of counts of replies to each discussion
 *
 * @global object
 * @global object
 * @param int $peerforumid
 * @param string $peerforumsort
 * @param int $limit
 * @param int $page
 * @param int $perpage
 * @return array
 */
function peerforum_count_discussion_replies($peerforumid, $peerforumsort="", $limit=-1, $page=-1, $perpage=0) {
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    if ($peerforumsort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $peerforumsort";
        $groupby = ", ".strtolower($peerforumsort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $peerforumsort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {peerforum_posts} p
                       JOIN {peerforum_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.peerforum = ?
              GROUP BY p.discussion";
        return $DB->get_records_sql($sql, array($peerforumid));

    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {peerforum_posts} p
                       JOIN {peerforum_discussions} d ON p.discussion = d.id
                 WHERE d.peerforum = ?
              GROUP BY p.discussion $groupby
              $orderby";
        return $DB->get_records_sql("SELECT * FROM ($sql) sq", array($peerforumid), $limitfrom, $limitnum);
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @staticvar array $cache
 * @param object $peerforum
 * @param object $cm
 * @param object $course
 * @return mixed
 */
function peerforum_count_discussions($peerforum, $cm, $course) {
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2); // db cache friendliness

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->peerforum_enabletimedposts)) {
            $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {peerforum} f
                       JOIN {peerforum_discussions} d ON d.peerforum = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$peerforum->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$peerforum->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$peerforum->id];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $peerforum->id;

    if (!empty($CFG->peerforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {peerforum_discussions} d
             WHERE d.groupid $mygroups_sql AND d.peerforum = ?
                   $timedsql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * How many posts by other users are unrated by a given user in the given discussion?
 *
 * TODO: Is this function still used anywhere?
 *
 * @param int $discussionid
 * @param int $userid
 * @return mixed
 */
function peerforum_count_unrated_posts($discussionid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT COUNT(*) as num
              FROM {peerforum_posts}
             WHERE parent > 0
               AND discussion = :discussionid
               AND userid <> :userid";
    $params = array('discussionid' => $discussionid, 'userid' => $userid);
    $posts = $DB->get_record_sql($sql, $params);
    if ($posts) {
        $sql = "SELECT count(*) as num
                  FROM {peerforum_posts} p,
                       {peerforum_ratingpeer} r
                 WHERE p.discussion = :discussionid AND
                       p.id = r.itemid AND
                       r.userid = userid AND
                       r.component = 'mod_peerforum' AND
                       r.ratingpeerarea = 'post'";
        $ratedpeer = $DB->get_record_sql($sql, $params);
        if ($ratedpeer) {
            if ($posts->num > $ratedpeer->num) {
                return $posts->num - $ratedpeer->num;
            } else {
                return 0;    // Just in case there was a counting error
            }
        } else {
            return $posts->num;
        }
    } else {
        return 0;
    }
}

/**
 * Get all discussions in a peerforum
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @param string $peerforumsort
 * @param bool $fullpost
 * @param int $unused
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @return array
 */
function peerforum_get_discussions($cm, $peerforumsort="d.timemodified DESC", $fullpost=true, $unused=-1, $limit=-1,
                                    $userlastmodified=false, $page=-1, $perpage=0) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm->instance);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/peerforum:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG->peerforum_enabletimedposts)) { /// Users must fulfill timed posts

        if (!has_capability('mod/peerforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }


    if (empty($peerforumsort)) {
        $peerforumsort = "d.timemodified DESC";
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable  = "";
    } else {
        $umfields = ', ' . get_all_user_name_fields(true, 'um', null, 'um');
        $umtable  = " LEFT JOIN {user} um ON (d.usermodified = um.id)";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, $allnames,
                   u.email, u.picture, u.imagealt $umfields
              FROM {peerforum_discussions} d
                   JOIN {peerforum_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.peerforum = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $peerforumsort";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Get the sql to use in the ORDER BY clause for peerforum discussions.
 *
 * This has the ordering take timed discussion windows into account.
 *
 * @param bool $desc True for DESC, False for ASC.
 * @param string $compare The field in the SQL to compare to normally sort by.
 * @param string $prefix The prefix being used for the discussion table.
 * @return string
 */
function peerforum_get_default_sort_order($desc = true, $compare = 'd.timemodified', $prefix = 'd') {
    global $CFG;

    if (!empty($prefix)) {
        $prefix .= '.';
    }

    $dir = $desc ? 'DESC' : 'ASC';

    $sort = "{$prefix}timemodified";
    if (!empty($CFG->peerforum_enabletimedposts)) {
        $sort = "CASE WHEN {$compare} < {$prefix}timestart
                 THEN {$prefix}timestart
                 ELSE {$compare}
                 END";
    }
    return "$sort $dir";
}

/**
 * Gets the neighbours (previous and next) of a discussion.
 *
 * The calculation is based on the timemodified of the discussion and does not handle
 * the neighbours having an identical timemodified. The reason is that we do not have any
 * other mean to sort the records, e.g. we cannot use IDs as a greater ID can have a lower
 * timemodified.
 *
 * For blog-style peerforums, the calculation is based on the original creation time of the
 * blog post.
 *
 * Please note that this does not check whether or not the discussion passed is accessible
 * by the user, it simply uses it as a reference to find the neighbours. On the other hand,
 * the returned neighbours are checked and are accessible to the current user.
 *
 * @param object $cm The CM record.
 * @param object $discussion The discussion record.
 * @param object $peerforum The peerforum instance record.
 * @return array That always contains the keys 'prev' and 'next'. When there is a result
 *               they contain the record with minimal information such as 'id' and 'name'.
 *               When the neighbour is not found the value is false.
 */
function peerforum_get_discussion_neighbours($cm, $discussion, $peerforum) {
    global $CFG, $DB, $USER;

    if ($cm->instance != $discussion->peerforum or $discussion->peerforum != $peerforum->id or $peerforum->id != $cm->instance) {
        throw new coding_exception('Discussion is not part of the same peerforum.');
    }

    $neighbours = array('prev' => false, 'next' => false);
    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    // Users must fulfill timed posts.
    $timelimit = '';
    if (!empty($CFG->peerforum_enabletimedposts)) {
        if (!has_capability('mod/peerforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= :tltimestart AND (d.timeend = 0 OR d.timeend > :tltimeend))';
            $params['tltimestart'] = $now;
            $params['tltimeend'] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = :tluserid';
                $params['tluserid'] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    // Limiting to posts accessible according to groups.
    $groupselect = '';
    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            }
        } else {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    }

    if ($peerforum->type === 'blog') {
        $params['peerforumid'] = $cm->instance;
        $params['discid1'] = $discussion->id;
        $params['discid2'] = $discussion->id;

        $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
                  FROM {peerforum_discussions} d
                  JOIN {peerforum_posts} p ON d.firstpost = p.id
                 WHERE d.peerforum = :peerforumid
                   AND d.id <> :discid1
                       $timelimit
                       $groupselect";

        $sub = "SELECT pp.created
                  FROM {peerforum_discussions} dd
                  JOIN {peerforum_posts} pp ON dd.firstpost = pp.id
                 WHERE dd.id = :discid2";

        $prevsql = $sql . " AND p.created < ($sub)
                       ORDER BY p.created DESC";

        $nextsql = $sql . " AND p.created > ($sub)
                       ORDER BY p.created ASC";

        $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
        $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);

    } else {
        $params['peerforumid'] = $cm->instance;
        $params['discid'] = $discussion->id;
        $params['disctimemodified'] = $discussion->timemodified;

        $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
                  FROM {peerforum_discussions} d
                 WHERE d.peerforum = :peerforumid
                   AND d.id <> :discid
                       $timelimit
                       $groupselect";

        if (empty($CFG->peerforum_enabletimedposts)) {
            $prevsql = $sql . " AND d.timemodified < :disctimemodified";
            $nextsql = $sql . " AND d.timemodified > :disctimemodified";

        } else {
            // Normally we would just use the timemodified for sorting
            // discussion posts. However, when timed discussions are enabled,
            // then posts need to be sorted base on the later of timemodified
            // or the release date of the post (timestart).
            $params['disctimecompare'] = $discussion->timemodified;
            if ($discussion->timemodified < $discussion->timestart) {
                $params['disctimecompare'] = $discussion->timestart;
            }

            // Here we need to take into account the release time (timestart)
            // if one is set, of the neighbouring posts and compare it to the
            // timestart or timemodified of *this* post depending on if the
            // release date of this post is in the future or not.
            // This stops discussions that appear later because of the
            // timestart value from being buried under discussions that were
            // made afterwards.
            $prevsql = $sql . " AND CASE WHEN d.timemodified < d.timestart
                                    THEN d.timestart ELSE d.timemodified END < :disctimecompare";
            $nextsql = $sql . " AND CASE WHEN d.timemodified < d.timestart
                                    THEN d.timestart ELSE d.timemodified END > :disctimecompare";
        }
        $prevsql .= ' ORDER BY '.peerforum_get_default_sort_order();
        $nextsql .= ' ORDER BY '.peerforum_get_default_sort_order(false);

        $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
        $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);
    }

    return $neighbours;
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function peerforum_get_discussions_unread($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->peerforum_oldpostdays*24*60*60);

    $params = array();
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //separate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (!empty($CFG->peerforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)";
        $params['now1'] = $now;
        $params['now2'] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {peerforum_discussions} d
                   JOIN {peerforum_posts} p     ON p.discussion = d.id
                   LEFT JOIN {peerforum_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.peerforum = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
                   $groupselect
                   $timedsql
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function peerforum_get_discussions_count($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG->peerforum_oldpostdays*24*60*60);

    $timelimit = "";

    if (!empty($CFG->peerforum_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/peerforum:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {peerforum_discussions} d
                   JOIN {peerforum_posts} p ON p.discussion = d.id
             WHERE d.peerforum = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB->get_field_sql($sql, $params);
}


/**
 * Get all discussions started by a particular user in a course (or group)
 * This function no longer used ...
 *
 * @todo Remove this function if no longer used
 * @global object
 * @global object
 * @param int $courseid
 * @param int $userid
 * @param int $groupid
 * @return array
 */
function peerforum_get_user_discussions($courseid, $userid, $groupid=0) {
    global $CFG, $DB;
    $params = array($courseid, $userid);
    if ($groupid) {
        $groupselect = " AND d.groupid = ? ";
        $params[] = $groupid;
    } else  {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.groupid, $allnames, u.email, u.picture, u.imagealt,
                                   f.type as peerforumtype, f.name as peerforumname, f.id as peerforumid
                              FROM {peerforum_discussions} d,
                                   {peerforum_posts} p,
                                   {user} u,
                                   {peerforum} f
                             WHERE d.course = ?
                               AND p.discussion = d.id
                               AND p.parent = 0
                               AND p.userid = u.id
                               AND u.id = ?
                               AND d.peerforum = f.id $groupselect
                          ORDER BY p.created DESC", $params);
}

/**
 * Get the list of potential subscribers to a peerforum.
 *
 * @param object $peerforumcontext the peerforum context.
 * @param integer $groupid the id of a group, or 0 for all groups.
 * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
 * @param string $sort sort order. As for get_users_by_capability.
 * @return array list of users.
 */
function peerforum_get_potential_subscribers($peerforumcontext, $groupid, $fields, $sort = '') {
    global $DB;

    // only active enrolled users or everybody on the frontpage
    list($esql, $params) = get_enrolled_sql($peerforumcontext, 'mod/peerforum:allowforcesubscribe', $groupid, true);
    if (!$sort) {
        list($sort, $sortparams) = users_order_by_sql('u');
        $params = array_merge($params, $sortparams);
    }

    $sql = "SELECT $fields
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
          ORDER BY $sort";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Returns list of user objects that are subscribed to this peerforum
 *
 * @global object
 * @global object
 * @param object $course the course
 * @param peerforum $peerforum the peerforum
 * @param integer $groupid group id, or 0 for all.
 * @param object $context the peerforum context, to save re-fetching it where possible.
 * @param string $fields requested user fields (with "u." table prefix)
 * @return array list of users.
 */
function peerforum_subscribed_users($course, $peerforum, $groupid=0, $context = null, $fields = null) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if (empty($fields)) {
        $fields ="u.id,
                  u.username,
                  $allnames,
                  u.maildisplay,
                  u.mailformat,
                  u.maildigest,
                  u.imagealt,
                  u.email,
                  u.emailstop,
                  u.city,
                  u.country,
                  u.lastaccess,
                  u.lastlogin,
                  u.picture,
                  u.timezone,
                  u.theme,
                  u.lang,
                  u.trackforums,
                  u.mnethostid";
    }

    if (empty($context)) {
        $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id);
        $context = context_module::instance($cm->id);
    }

    if (peerforum_is_forcesubscribed($peerforum)) {
        $results = peerforum_get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

    } else {
        // only active enrolled users or everybody on the frontpage
        list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
        $params['peerforumid'] = $peerforum->id;
        $results = $DB->get_records_sql("SELECT $fields
                                           FROM {user} u
                                           JOIN ($esql) je ON je.id = u.id
                                           JOIN {peerforum_subscriptions} s ON s.userid = u.id
                                          WHERE s.peerforum = :peerforumid
                                       ORDER BY u.email ASC", $params);
    }

    // Guest user should never be subscribed to a peerforum.
    unset($results[$CFG->siteguest]);

    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id);
    $modinfo = get_fast_modinfo($cm->course);
    return groups_filter_users_by_course_module_visible($modinfo->get_cm($cm->id), $results);
}



// OTHER FUNCTIONS ///////////////////////////////////////////////////////////


/**
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type
 */
function peerforum_get_course_peerforum($courseid, $type) {
// How to set up special 1-per-course peerforums
    global $CFG, $DB, $OUTPUT, $USER;

    if ($peerforums = $DB->get_records_select("peerforum", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($peerforums as $peerforum) {
            return $peerforum;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $peerforum = new stdClass();
    $peerforum->course = $courseid;
    $peerforum->type = "$type";
    if (!empty($USER->htmleditor)) {
        $peerforum->introformat = $USER->htmleditor;
    }
    switch ($peerforum->type) {
        case "news":
            $peerforum->name  = get_string("namenews", "peerforum");
            $peerforum->intro = get_string("intronews", "peerforum");
            $peerforum->forcesubscribe = PEERFORUM_FORCESUBSCRIBE;
            $peerforum->assessed = 0;
            if ($courseid == SITEID) {
                $peerforum->name  = get_string("sitenews");
                $peerforum->forcesubscribe = 0;
            }
            break;
        case "social":
            $peerforum->name  = get_string("namesocial", "peerforum");
            $peerforum->intro = get_string("introsocial", "peerforum");
            $peerforum->assessed = 0;
            $peerforum->forcesubscribe = 0;
            break;
        case "blog":
            $peerforum->name = get_string('blogpeerforum', 'peerforum');
            $peerforum->intro = get_string('introblog', 'peerforum');
            $peerforum->assessed = 0;
            $peerforum->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That peerforum type doesn't exist!");
            return false;
            break;
    }

    $peerforum->timemodified = time();
    $peerforum->id = $DB->insert_record("peerforum", $peerforum);

    if (! $module = $DB->get_record("modules", array("name" => "peerforum"))) {
        echo $OUTPUT->notification("Could not find peerforum module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $peerforum->id;
    $mod->section = 0;
    include_once("$CFG->dirroot/course/lib.php");
    if (! $mod->coursemodule = add_course_module($mod) ) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
    return $DB->get_record("peerforum", array("id" => "$peerforum->id"));
}


/**
 * Given the data about a posting, builds up the HTML to display it and
 * returns the HTML in a string.  This is designed for sending via HTML email.
 *
 * @global object
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $userform
 * @param object $userto
 * @param bool $ownpost
 * @param bool $reply
 * @param bool $link
 * @param bool $ratepeer
 * @param string $footer
 * @return string
 */
function peerforum_make_mail_post($course, $cm, $peerforum, $discussion, $post, $userfrom, $userto,
                              $ownpost=false, $reply=false, $link=false, $ratepeer=false, $footer="") {

    global $CFG, $OUTPUT;

    $modcontext = context_module::instance($cm->id);

    if (!isset($userto->viewfullnames[$peerforum->id])) {
        $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
    } else {
        $viewfullnames = $userto->viewfullnames[$peerforum->id];
    }

    // add absolute file links
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_peerforum', 'post', $post->id);

    // format the post body
    $options = new stdClass();
    $options->para = true;
    $formattedtext = format_text($post->message, $post->messageformat, $options, $course->id);

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="peerforumpost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT->user_picture($userfrom, array('courseid'=>$course->id));
    $output .= '</td>';

    if ($post->parent) {
        $output .= '<td class="topic">';
    } else {
        $output .= '<td class="topic starter">';
    }
    $output .= '<div class="subject">'.format_string($post->subject).'</div>';

    $fullname = fullname($userfrom, $viewfullnames);
    $by = new stdClass();
    $by->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userfrom->id.'&amp;course='.$course->id.'">'.$fullname.'</a>';
    $by->date = userdate($post->modified, '', $userto->timezone);
    $output .= '<div class="author">'.get_string('bynameondate', 'peerforum', $by).'</div>';

    $output .= '</td></tr>';

    $output .= '<tr><td class="left side" valign="top">';

    if (isset($userfrom->groups)) {
        $groups = $userfrom->groups[$peerforum->id];
    } else {
        $groups = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
    }

    if ($groups) {
        $output .= print_group_picture($groups, $course->id, false, true, true);
    } else {
        $output .= '&nbsp;';
    }

    $output .= '</td><td class="content">';

    $attachments = peerforum_print_attachments($post, $cm, 'html');
    if ($attachments !== '') {
        $output .= '<div class="attachments">';
        $output .= $attachments;
        $output .= '</div>';
    }

    $output .= $formattedtext;

// Commands
    $commands = array();

    if ($post->parent) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.
                      $post->discussion.'&amp;parent='.$post->parent.'">'.get_string('parent', 'peerforum').'</a>';
    }

    if ($reply) {
        $commands[] = '<a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/post.php?reply='.$post->id.'">'.
                      get_string('reply', 'peerforum').'</a>';
    }

    $output .= '<div class="commands">';
    $output .= implode(' | ', $commands);
    $output .= '</div>';

// Context link to post if required
    if ($link) {
        $output .= '<div class="link">';
        $output .= '<a target="_blank" href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'#p'.$post->id.'">'.
                     get_string('postincontext', 'peerforum').'</a>';
        $output .= '</div>';
    }

    if ($footer) {
        $output .= '<div class="footer">'.$footer.'</div>';
    }
    $output .= '</td></tr></table>'."\n\n";

    return $output;
}

/**
 * Print a peerforum post
 *
 * @global object
 * @global object
 * @uses PEERFORUM_MODE_THREADED
 * @uses PORTFOLIO_FORMAT_PLAINHTML
 * @uses PORTFOLIO_FORMAT_FILE
 * @uses PORTFOLIO_FORMAT_RICHHTML
 * @uses PORTFOLIO_ADD_TEXT_LINK
 * @uses CONTEXT_MODULE
 * @param object $post The post to print.
 * @param object $discussion
 * @param object $peerforum
 * @param object $cm
 * @param object $course
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param boolean $dummyifcantsee When peerforum_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @return void
 */
function peerforum_print_post($post, $discussion, $peerforum, &$cm, $course, $ownpost=false, $reply=false, $link=false,
                          $footer="", $highlight="", $postisread=null, $dummyifcantsee=true, $istracked=null, $return=false,
                          $peergrade=true, $showincontext=false, $to_peergrade_block=true, $url_block=null) {
    global $USER, $CFG, $OUTPUT, $DB, $PAGE;

    require_once($CFG->libdir . '/filelib.php');

    $allowpeergrade = $DB->get_record('peerforum', array('id' => $peerforum->id));

     if($allowpeergrade){
         $peergrade = true;
     }
     if(!$allowpeergrade){
         $peergrade = false;
     }

    // String cache
    static $str;

    $modcontext = context_module::instance($cm->id);

    $post->course = $course->id;
    $post->peerforum  = $peerforum->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_peerforum', 'post', $post->id);
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        $post->message .= plagiarism_get_links(array('userid' => $post->userid,
            'content' => $post->message,
            'cmid' => $cm->id,
            'course' => $post->course,
            'peerforum' => $post->peerforum));
    }

    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/peerforum:viewdiscussion']   = has_capability('mod/peerforum:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/peerforum:editanypost']      = has_capability('mod/peerforum:editanypost', $modcontext);
        $cm->cache->caps['mod/peerforum:splitdiscussions'] = has_capability('mod/peerforum:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/peerforum:deleteownpost']    = has_capability('mod/peerforum:deleteownpost', $modcontext);
        $cm->cache->caps['mod/peerforum:deleteanypost']    = has_capability('mod/peerforum:deleteanypost', $modcontext);
        $cm->cache->caps['mod/peerforum:viewanyratingpeer']    = has_capability('mod/peerforum:viewanyratingpeer', $modcontext);
        $cm->cache->caps['mod/peerforum:exportpost']       = has_capability('mod/peerforum:exportpost', $modcontext);
        $cm->cache->caps['mod/peerforum:exportownpost']    = has_capability('mod/peerforum:exportownpost', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = coursemodule_visible_for_user($cm);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = peerforum_tp_is_post_read($USER->id, $post);
    }

    if (!peerforum_user_can_see_post($peerforum, $discussion, $post, NULL, $cm)) {
        $output = '';
        if (!$dummyifcantsee) {
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
        $output .= html_writer::start_tag('div', array('class'=>'peerforumpost clearfix',
                                                       'role' => 'region',
                                                       'aria-label' => get_string('hiddenpeerforumpost', 'peerforum')));
        $output .= html_writer::start_tag('div', array('class'=>'row header'));
        $output .= html_writer::tag('div', '', array('class'=>'left picture')); // Picture
        if ($post->parent) {
            $output .= html_writer::start_tag('div', array('class'=>'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class'=>'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('peerforumsubjecthidden','peerforum'), array('class' => 'subject',
                                                                                           'role' => 'header')); // Subject.
        $output .= html_writer::tag('div', get_string('peerforumauthorhidden', 'peerforum'), array('class' => 'author',
                                                                                           'role' => 'header')); // Author.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class'=>'row'));
        $output .= html_writer::tag('div', '&nbsp;', array('class'=>'left side')); // Groups
        $output .= html_writer::tag('div', get_string('peerforumbodyhidden','peerforum'), array('class'=>'content')); // Content
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::end_tag('div'); // peerforumpost

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str)) {
        $str = new stdClass;
        $str->edit         = get_string('edit', 'peerforum');
        $str->delete       = get_string('delete', 'peerforum');
        $str->reply        = get_string('reply', 'peerforum');
        $str->parent       = get_string('parent', 'peerforum');
        $str->pruneheading = get_string('pruneheading', 'peerforum');
        $str->prune        = get_string('prune', 'peerforum');
        $str->displaymode  = get_user_preferences('peerforum_displaymode', $CFG->peerforum_displaymode);
        $str->markread     = get_string('markread', 'peerforum');
        $str->markunread   = get_string('markunread', 'peerforum');
        $str->peergrade    = get_string('peergrade', 'peerforum');
        $str->post         = get_string('showpost', 'peerforum');
    }

    $discussionlink = new moodle_url('/mod/peerforum/discuss.php', array('d'=>$post->discussion));

    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postuser->profilelink = new moodle_url('/user/view.php', array('id'=>$post->userid, 'course'=>$course->id));

    // Prepare the groups the posting user belongs to
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }

    // Prepare the attachements for the post, files then images
    list($attachments, $attachedimages) = peerforum_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post
    $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->peerforum_longpost));


    // Prepare an array of commands
    $commands = array();

    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG->peerforum_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
        $text = $str->markunread;
        if (!$postisread) {
            $url->param('mark', 'read');
            $text = $str->markread;
        }
        if ($str->displaymode == PEERFORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->id);
        }
        $commands[] = array('url'=>$url, 'text'=>$text);
    }

    // Zoom in to the parent specifically
    if ($post->parent) {
        $url = new moodle_url($discussionlink);
        if ($str->displaymode == PEERFORUM_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->parent);
        }
        $commands[] = array('url'=>$url, 'text'=>$str->parent);
    }

    if($showincontext){
            $url = new moodle_url($discussionlink);
            if ($str->displaymode == PEERFORUM_MODE_THREADED) {
                $url->param('parent', $post->id);
            } else {
                $url->set_anchor('p'.$post->id);
            }
            $commands[] = array('url'=>$url, 'text'=>$str->post);
    }

    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->created;
    if (!$post->parent && $peerforum->type == 'news' && $discussion->timestart > time()) {
        $age = 0;
    }

    if ($peerforum->type == 'single' and $discussion->firstpost == $post->id) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the peerforum description.
            $commands[] = array('url'=>new moodle_url('/course/modedit.php', array('update'=>$cm->id, 'sesskey'=>sesskey(), 'return'=>1)), 'text'=>$str->edit);
        }
    } else if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/peerforum:editanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/peerforum/post.php', array('edit'=>$post->id)), 'text'=>$str->edit);
    }

    if ($cm->cache->caps['mod/peerforum:splitdiscussions'] && $post->parent && $peerforum->type != 'single') {
        $commands[] = array('url'=>new moodle_url('/mod/peerforum/post.php', array('prune'=>$post->id)), 'text'=>$str->prune, 'title'=>$str->pruneheading);
    }

    if ($peerforum->type == 'single' and $discussion->firstpost == $post->id) {
        // Do not allow deleting of first post in single simple type.
    } else if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/peerforum:deleteownpost']) || $cm->cache->caps['mod/peerforum:deleteanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/peerforum/post.php', array('delete'=>$post->id)), 'text'=>$str->delete);
    }

    if ($reply && !$showincontext) {
        $commands[] = array('url'=>new moodle_url('/mod/peerforum/post.php#mformpeerforum', array('reply'=>$post->id)), 'text'=>$str->reply);
    }

    if ($allowpeergrade) {
        $admins = get_admins();
        $isadmin = false;

        foreach ($admins as $admin) {
            if ($post->userid == $admin->id) {
                $isadmin = true;
                break;
            }
        }
            if (!($isadmin) && ($USER->id != $post->userid )) {
                $coursecontext = context_course::instance($course->id, MUST_EXIST);
                //    $commands[] = array('url'=>new moodle_url('/mod/peerforum/post.php#mformpeergrade', array('peergrade'=>$post->id)), 'text'=>$str->peergrade);
            }
    }

    if ($CFG->enableportfolios && ($cm->cache->caps['mod/peerforum:exportpost'] || ($ownpost && $cm->cache->caps['mod/peerforum:exportownpost']))) {
        $p = array('postid' => $post->id);
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('peerforum_portfolio_caller', array('postid' => $post->id), 'mod_peerforum');
        if (empty($attachments)) {
            $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        } else {
            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
        }

        $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
        if (!empty($porfoliohtml)) {
            $commands[] = $porfoliohtml;
        }
    }
    // Finished building commands


    // Begin output

    $output  = '';

    if ($istracked) {
        if ($postisread) {
            $peerforumpostclass = ' read';
        } else {
            $peerforumpostclass = ' unread';
            $output .= html_writer::tag('a', '', array('name'=>'unread'));
        }
    } else {
        // ignore trackign status if not tracked or tracked param missing
        $peerforumpostclass = '';
    }

    $topicclass = '';
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
    }

    $postbyuser = new stdClass;
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postuser->fullname;
    $discussionbyuser = get_string('postbyuser', 'peerforum', $postbyuser);
    $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
    $output .= html_writer::start_tag('div', array('class'=>'peerforumpost clearfix'.$peerforumpostclass.$topicclass,
                                                   'role' => 'region',
                                                   'aria-label' => $discussionbyuser));
    $output .= html_writer::start_tag('div', array('class'=>'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left picture'));
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id));
    $output .= html_writer::end_tag('div');


    $output .= html_writer::start_tag('div', array('class'=>'topic'.$topicclass));

    $postsubject = $post->subject;
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $output .= html_writer::tag('div', $postsubject, array('class'=>'subject',
                                                           'role' => 'heading',
                                                           'aria-level' => '2'));

    $by = new stdClass();
    $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    $by->date = userdate($post->modified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'peerforum', $by), array('class'=>'author',
                                                                                       'role' => 'heading',
                                                                                       'aria-level' => '2'));

    $output .= html_writer::end_tag('div'); //topic
    $output .= html_writer::end_tag('div'); //row

    $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course->id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));

    $output .= html_writer::end_tag('div'); //left side
    $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
    $output .= html_writer::start_tag('div', array('class'=>'content'));
    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class'=>'attachments'));
    }

    $options = new stdClass;
    $options->para    = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;
    if ($shortenpost) {
        // Prepare shortened version by filtering the text then shortening it.
        $postclass    = 'shortenedpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options);
        $postcontent  = shorten_text($postcontent, $CFG->peerforum_shortpost);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'peerforum'));
        $postcontent .= html_writer::tag('div', '('.get_string('numwords', 'moodle', count_words($post->message)).')',
            array('class'=>'post-word-count'));
    } else {
        // Prepare whole post
        $postclass    = 'fullpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options, $course->id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($peerforum->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class'=>'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class'=>'attachedimages'));
    }

    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class'=>'posting '.$postclass));
    $output .= html_writer::end_tag('div'); // Content
    $output .= html_writer::end_tag('div'); // Content mask
    $output .= html_writer::end_tag('div'); // Row

    $output .= html_writer::start_tag('div', array('class'=>'row side'));
    $output .= html_writer::tag('div','&nbsp;', array('class'=>'left'));
    $output .= html_writer::start_tag('div', array('class'=>'options clearfix'));

    if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        print_object('Post id: '.$post->id);
    }

    // Output ratingpeers
    if (!empty($post->ratingpeer)) {
        $renderer = $PAGE->get_renderer('mod_peerforum');
        $output .= html_writer::tag('div', $renderer->render_ratingpeer($post->ratingpeer), array('class'=>'peerforum-post-ratingpeer'));
    }

    // Output peergrades PEERGRADE
    if (!empty($post->peergrade)) {
        $output .= html_writer::tag('br', ''); // Should produce <br />
        $post->peergrade->to_peergrade_block = $to_peergrade_block;
        $post->peergrade->returnurl = $url_block;
        $post->peergrade->peerforum = $post->peerforum;
        $post->peergrade->userid = $postuser->id;

        $renderer = $PAGE->get_renderer('mod_peerforum');
        $output .= html_writer::tag('div', $renderer->render_peergrade($post->peergrade), array('class'=>'peerforum-post-peergrade'));
    }

    // Output the commands
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));

    // Output link to post if required
    if ($link && peerforum_user_can_post($peerforum, $discussion, $USER, $cm, $course, $modcontext)) {
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'peerforum', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'peerforum', $post->replies);
        }

        $output .= html_writer::start_tag('div', array('class'=>'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'peerforum'));
        $output .= '&nbsp;('.$replystring.')';
        $output .= html_writer::end_tag('div'); // link
    }

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class'=>'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div'); // content
    $output .= html_writer::end_tag('div'); // row
    $output .= html_writer::end_tag('div'); // peerforumpost

    // Mark the peerforum post as read if required
    if ($istracked && !$CFG->peerforum_usermarksread && !$postisread) {
        peerforum_tp_mark_post_read($USER->id, $post, $peerforum->id);
    }

    if ($return) {
        return $output;
    }
    echo $output;
    return;
}

/**
 * Return ratingpeer related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's ratingpeer permissions
 */
function peerforum_ratingpeer_permissions($contextid, $component, $ratingpeerarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_peerforum' || $ratingpeerarea != 'post') {
        // We don't know about this component/ratingpeerarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view'    => has_capability('mod/peerforum:viewratingpeer', $context),
        'viewany' => has_capability('mod/peerforum:viewanyratingpeer', $context),
        'viewall' => has_capability('mod/peerforum:viewallratingpeers', $context),
        'ratepeer'    => has_capability('mod/peerforum:ratepeer', $context)
    );
}

/**
 * Validates a submitted ratingpeer
 * @param array $params submitted data
 *            context => object the context in which the ratedpeer items exists [required]
 *            component => The component for this module - should always be mod_peerforum [required]
 *            ratingpeerarea => object the context in which the ratedpeer items exists [required]
 *            itemid => int the ID of the object being ratedpeer [required]
 *            scaleid => int the scale from which the user can select a ratingpeer. Used for bounds checking. [required]
 *            ratingpeer => int the submitted ratingpeer [required]
 *            ratedpeeruserid => int the id of the user whose items have been ratedpeer. NOT the user who submitted the ratingpeers. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATINGPEER_AGGREGATE_AVERAGE [required]
 * @return boolean true if the ratingpeer is valid. Will throw ratingpeer_exception if not
 */
function peerforum_ratingpeer_validate($params) {
    global $DB, $USER;

    // Check the component is mod_peerforum
    if ($params['component'] != 'mod_peerforum') {
        throw new ratingpeer_exception('invalidcomponent');
    }

    // Check the ratingpeerarea is post (the only ratingpeer area in peerforum)
    if ($params['ratingpeerarea'] != 'post') {
        throw new ratingpeer_exception('invalidratingpeerarea');
    }

    // Check the ratedpeeruserid is not the current user .. you can't ratepeer your own posts
    if ($params['ratedpeeruserid'] == $USER->id) {
        throw new ratingpeer_exception('nopermissiontoratepeer');
    }

    // Fetch all the related records ... we need to do this anyway to call peerforum_user_can_see_post
    $post = $DB->get_record('peerforum_posts', array('id' => $params['itemid'], 'userid' => $params['ratedpeeruserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $peerforum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id , false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the peerforum
    if ($context->id != $params['context']->id) {
        throw new ratingpeer_exception('invalidcontext');
    }

    if ($peerforum->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new ratingpeer_exception('invalidscaleid');
    }

    // check the item we're ratingpeer was created in the assessable time window
    if (!empty($peerforum->assesstimestart) && !empty($peerforum->assesstimefinish)) {
        if ($post->created < $peerforum->assesstimestart || $post->created > $peerforum->assesstimefinish) {
            throw new ratingpeer_exception('notavailable');
        }
    }

    //check that the submitted ratingpeer is valid for the scale

    // lower limit
    if ($params['ratingpeer'] < 0  && $params['ratingpeer'] != RATINGPEER_UNSET_RATINGPEER) {
        throw new ratingpeer_exception('invalidnum');
    }

    // upper limit
    if ($peerforum->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$peerforum->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['ratingpeer'] > count($scalearray)) {
                throw new ratingpeer_exception('invalidnum');
            }
        } else {
            throw new ratingpeer_exception('invalidscaleid');
        }
    } else if ($params['ratingpeer'] > $peerforum->scale) {
        //if its numeric and submitted ratingpeer is above maximum
        throw new ratingpeer_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're ratingpeer
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new ratingpeer_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow ratingpeer of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new ratingpeer_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!peerforum_user_can_see_post($peerforum, $discussion, $post, $USER, $cm)) {
        throw new ratingpeer_exception('nopermissiontoratepeer');
    }

    return true;
}

/**
 * Return a pair of spans containing classes to allow the subscribe and
 * unsubscribe icons to be pre-loaded by a browser.
 *
 * @return string The generated markup
 */
function peerforum_get_discussion_subscription_icon_preloaders() {
    $o = '';
    $o .= html_writer::span('&nbsp;', 'preload-subscribe');
    $o .= html_writer::span('&nbsp;', 'preload-unsubscribe');
    return $o;
}

/**
 * Return the markup for the discussion subscription toggling icon.
 *
 * @param stdClass $peerforum The peerforum object.
 * @param int $discussionid The discussion to create an icon for.
 * @return string The generated markup.
 */
function peerforum_get_discussion_subscription_icon($peerforum, $discussionid, $returnurl = null, $includetext = false) {
    global $USER, $OUTPUT, $PAGE;

    if ($returnurl === null && $PAGE->url) {
        $returnurl = $PAGE->url->out();
    }

    $o = '';
    $subscriptionstatus = \mod_peerforum\subscriptions::is_subscribed($USER->id, $peerforum, $discussionid);
    $subscriptionlink = new moodle_url('/mod/peerforum/subscribe.php', array(
        'sesskey' => sesskey(),
        'id' => $peerforum->id,
        'd' => $discussionid,
        'returnurl' => $returnurl,
    ));

    if ($includetext) {
        $o .= $subscriptionstatus ? get_string('subscribed', 'mod_peerforum') : get_string('notsubscribed', 'mod_peerforum');
    }

    if ($subscriptionstatus) {
        $output = $OUTPUT->pix_icon('t/subscribed', get_string('clicktounsubscribe', 'peerforum'), 'mod_peerforum');
        if ($includetext) {
            $output .= get_string('subscribed', 'mod_peerforum');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktounsubscribe', 'peerforum'),
                'class' => 'discussiontoggle iconsmall',
                'data-peerforumid' => $peerforum->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));

    } else {
        $output = $OUTPUT->pix_icon('t/unsubscribed', get_string('clicktosubscribe', 'peerforum'), 'mod_peerforum');
        if ($includetext) {
            $output .= get_string('notsubscribed', 'mod_peerforum');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktosubscribe', 'peerforum'),
                'class' => 'discussiontoggle iconsmall',
                'data-peerforumid' => $peerforum->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));
    }
}

/**
 * This function prints the overview of a discussion in the peerforum listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: peerforum_print_latest_discussions()
 *
 * @global object
 * @global object
 * @param object $post The post object (passed by reference for speed).
 * @param object $peerforum The peerforum object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this peerforum.
 * @param boolean $peerforumtracked Is the user tracking this peerforum.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 */
function peerforum_print_discussion_header(&$post, $peerforum, $group=-1, $datestring="",
                                        $cantrack=true, $peerforumtracked=true, $canviewparticipants=true, $modcontext=NULL) {

    global $USER, $CFG, $OUTPUT;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm->id);
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'peerforum');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post->subject = format_string($post->subject,true);

    echo "\n\n";
    echo '<tr class="discussion r'.$rowcount.'">';

    // Topic
    echo '<td class="topic starter">';
    echo '<a href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'">'.$post->subject.'</a>';
    echo "</td>\n";

    // Picture
    $postuser = new stdClass();
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    echo '<td class="picture">';
    echo $OUTPUT->user_picture($postuser, array('courseid'=>$peerforum->course));
    echo "</td>\n";

    // User name
    $fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->userid.'&amp;course='.$peerforum->course.'">'.$fullname.'</a>';
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group->picture) and empty($group->hidepicture)) {
            print_group_picture($group, $peerforum->course, false, false, true);
        } else if (isset($group->id)) {
            if($canviewparticipants) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$peerforum->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/peerforum:viewdiscussion', $modcontext)) {   // Show the column with replies
        echo '<td class="replies">';
        echo '<a href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'">';
        echo $post->replies.'</a>';
        echo "</td>\n";

        if ($cantrack) {
            echo '<td class="replies">';
            if ($peerforumtracked) {
                if ($post->unread > 0) {
                    echo '<span class="unread">';
                    echo '<a href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.'#unread">';
                    echo $post->unread;
                    echo '</a>';
                    echo '<a title="'.$strmarkalldread.'" href="'.$CFG->wwwroot.'/mod/peerforum/markposts.php?f='.
                         $peerforum->id.'&amp;d='.$post->discussion.'&amp;mark=read&amp;returnpage=view.php">' .
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.$strmarkalldread.'" /></a>';
                    echo '</span>';
                } else {
                    echo '<span class="read">';
                    echo $post->unread;
                    echo '</span>';
                }
            } else {
                echo '<span class="read">';
                echo '-';
                echo '</span>';
            }
            echo "</td>\n";
        }
    }

    echo '<td class="lastpost">';
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    $parenturl = '';
    $usermodified = new stdClass();
    $usermodified->id = $post->usermodified;
    $usermodified = username_load_fields_from_object($usermodified, $post, 'um');

    // Show link to last poster and their post if user can see them.
    if ($canviewparticipants) {
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->usermodified.'&amp;course='.$peerforum->course.'">'.
             fullname($usermodified).'</a><br />';
        $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent='.$post->lastpostid;
    }

    echo '<a href="'.$CFG->wwwroot.'/mod/peerforum/discuss.php?d='.$post->discussion.$parenturl.'">'.
          userdate($usedate, $datestring).'</a>';
    echo "</td>\n";

    echo "</tr>\n\n";

}

/**
 * This function is now deprecated. Use shorten_text($message, $CFG->peerforum_shortpost) instead.
 *
 * Given a post object that we already know has a long message
 * this function truncates the message nicely to the first
 * sane place between $CFG->peerforum_longpost and $CFG->peerforum_shortpost
 *
 * @deprecated since Moodle 2.6
 * @see shorten_text()
 * @todo finalise deprecation in 2.8 in MDL-40851
 * @global object
 * @param string $message
 * @return string
 */
function peerforum_shorten_post($message) {
   global $CFG;
   debugging('peerforum_shorten_post() is deprecated since Moodle 2.6. Please use shorten_text($message, $CFG->peerforum_shortpost) instead.', DEBUG_DEVELOPER);
   return shorten_text($message, $CFG->peerforum_shortpost);
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 *
 * @param int $id peerforum id if $peerforumtype is 'single',
 *              discussion id for any other peerforum type
 * @param mixed $mode peerforum layout mode
 * @param string $peerforumtype optional
 */
function peerforum_print_mode_form($id, $mode, $peerforumtype='') {
    global $OUTPUT;
    if ($peerforumtype == 'single') {
        $select = new single_select(new moodle_url("/mod/peerforum/view.php", array('f'=>$id)), 'mode', peerforum_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'peerforum'), array('class' => 'accesshide'));
        $select->class = "peerforummode";
    } else {
        $select = new single_select(new moodle_url("/mod/peerforum/discuss.php", array('d'=>$id)), 'mode', peerforum_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'peerforum'), array('class' => 'accesshide'));
    }
    echo $OUTPUT->render($select);
}

/**
 * @global object
 * @param object $course
 * @param string $search
 * @return string
 */
function peerforum_search_form($course, $search='') {
    global $CFG, $OUTPUT;

    $output  = '<div class="peerforumsearch">';
    $output .= '<form action="'.$CFG->wwwroot.'/mod/peerforum/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= $OUTPUT->help_icon('search');
    $output .= '<label class="accesshide" for="search" >'.get_string('search', 'peerforum').'</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="'.s($search, true).'" />';
    $output .= '<label class="accesshide" for="searchpeerforums" >'.get_string('searchpeerforums', 'peerforum').'</label>';
    $output .= '<input id="searchpeerforums" value="'.get_string('searchpeerforums', 'peerforum').'" type="submit" />';
    $output .= '<input name="id" type="hidden" value="'.$course->id.'" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}


/**
 * @global object
 * @global object
 */
function peerforum_set_return() {
    global $CFG, $SESSION;

    if (! isset($SESSION->fromdiscussion)) {
        $referer = clean_param($_SERVER['HTTP_REFERER'], PARAM_LOCALURL);
        // If the referer is NOT a login screen then save it.
        if (! strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $referer;
        }
    }
}


/**
 * @global object
 * @param string $default
 * @return string
 */
function peerforum_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $peerforumto,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new peerforum directory.
 *
 * @global object
 * @param object $discussion
 * @param int $peerforumfrom source peerforum id
 * @param int $peerforumto target peerforum id
 * @return bool success
 */
function peerforum_move_attachments($discussion, $peerforumfrom, $peerforumto) {
    global $DB;

    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('peerforum', $peerforumto);
    $oldcm = get_coursemodule_from_instance('peerforum', $peerforumfrom);

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('peerforum_posts', array('discussion'=>$discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_peerforum', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_peerforum', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it
                $post->attachment = '1';
                $DB->update_record('peerforum_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it
                $post->attachment = '';
                $DB->update_record('peerforum_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function peerforum_print_attachments($post, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'peerforum');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && (has_capability('mod/peerforum:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/peerforum:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir.'/portfoliolib.php');
    }

    $files = $fs->get_area_files($context->id, 'mod_peerforum', 'attachment', $post->id, "timemodified", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_peerforum/attachment/'.$post->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                if ($canexport) {
                    $button->set_callback_options('peerforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_peerforum');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('peerforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_peerforum');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    if ($canexport) {
                        $button->set_callback_options('peerforum_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_peerforum');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $post->course,
                    'peerforum' => $post->peerforum));
                $output .= '<br />';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_peerforum
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function peerforum_get_file_areas($course, $cm, $context) {
    return array(
        'attachment' => get_string('areaattachment', 'mod_peerforum'),
        'post' => get_string('areapost', 'mod_peerforum'),
    );
}

/**
 * File browsing support for peerforum module.
 *
 * @package  mod_peerforum
 * @category files
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function peerforum_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that peerforum_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda peerforum type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/peerforum:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot.'/mod/peerforum/locallib.php');
        return new peerforum_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and peerforum. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('peerforum_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['peerforum']) && $cached['peerforum']->id == $cm->instance) {
        $peerforum = $cached['peerforum'];
    } else if ($peerforum = $DB->get_record('peerforum', array('id' => $cm->instance))) {
        $cached['peerforum'] = $peerforum;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_peerforum', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!peerforum_user_can_see_post($peerforum, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the peerforum attachments. Implements needed access control ;-)
 *
 * @package  mod_peerforum
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function peerforum_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = peerforum_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB->get_record('peerforum_posts', array('id'=>$postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('peerforum_discussions', array('id'=>$post->discussion))) {
        return false;
    }

    if (!$peerforum = $DB->get_record('peerforum', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_peerforum/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!peerforum_user_can_see_post($peerforum, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file
 *
 * @global object
 * @param object $post is a full post record, including course and peerforum
 * @param object $peerforum
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @return bool
 */
function peerforum_add_attachment($post, $peerforum, $cm, $mform=null, $unused=null) {
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true;   // Nothing to do
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount']>0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_peerforum', 'attachment', $post->id,
            mod_peerforum_post_form::attachment_options($peerforum));

    $DB->set_field('peerforum_posts', 'attachment', $present, array('id'=>$post->id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return int
 */
function peerforum_add_new_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion));
    $peerforum      = $DB->get_record('peerforum', array('id' => $discussion->peerforum));
    $cm         = get_coursemodule_from_instance('peerforum', $peerforum->id);
    $context    = context_module::instance($cm->id);

    $post->created    = $post->modified = time();
    $post->mailed     = PEERFORUM_MAILED_PENDING;
    $post->userid     = $USER->id;
    $post->attachment = "";
    $post->peergraders = 0;


    $post->id = $DB->insert_record("peerforum_posts", $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_peerforum', 'post', $post->id,
            mod_peerforum_post_form::editor_options($context, null), $post->message);
    $DB->set_field('peerforum_posts', 'message', $post->message, array('id'=>$post->id));
    peerforum_add_attachment($post, $peerforum, $cm, $mform, $message);

    // Update discussion modified date
    $DB->set_field("peerforum_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
    $DB->set_field("peerforum_discussions", "usermodified", $post->userid, array("id" => $post->discussion));

    if (peerforum_tp_can_track_peerforums($peerforum) && peerforum_tp_is_tracked($peerforum)) {
        peerforum_tp_mark_post_read($post->userid, $post, $post->peerforum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    peerforum_trigger_content_uploaded_event($post, $cm, 'peerforum_add_new_post');

    return $post->id;
}

/**
 * Update a post
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return bool
 */
function peerforum_update_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion));
    $peerforum      = $DB->get_record('peerforum', array('id' => $discussion->peerforum));
    $cm         = get_coursemodule_from_instance('peerforum', $peerforum->id);
    $context    = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('peerforum_posts', $post);

    $discussion->timemodified = $post->modified; // last modified tracking
    $discussion->usermodified = $post->userid;   // last modified tracking

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $discussion->name      = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend   = $post->timeend;
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_peerforum', 'post', $post->id,
            mod_peerforum_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('peerforum_posts', 'message', $post->message, array('id'=>$post->id));

    $DB->update_record('peerforum_discussions', $discussion);

    peerforum_add_attachment($post, $peerforum, $cm, $mform, $message);

    if (peerforum_tp_can_track_peerforums($peerforum) && peerforum_tp_is_tracked($peerforum)) {
        peerforum_tp_mark_post_read($post->userid, $post, $post->peerforum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    peerforum_trigger_content_uploaded_event($post, $cm, 'peerforum_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @return object
 */
function peerforum_add_discussion($discussion, $mform=null, $unused=null, $userid=null) {
    global $USER, $CFG, $DB;

    $timenow = time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $peerforum = $DB->get_record('peerforum', array('id'=>$discussion->peerforum));
    $cm    = get_coursemodule_from_instance('peerforum', $peerforum->id);

    $post = new stdClass();
    $post->discussion    = 0;
    $post->parent        = 0;
    $post->userid        = $userid;
    $post->created       = $timenow;
    $post->modified      = $timenow;
    $post->mailed        = PEERFORUM_MAILED_PENDING;
    $post->subject       = $discussion->name;
    $post->message       = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust  = $discussion->messagetrust;
    $post->attachments   = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->peerforum         = $peerforum->id;     // speedup
    $post->course        = $peerforum->course; // speedup
    $post->mailnow       = $discussion->mailnow;
    $post->peergraders      = 0;

    $post->id = $DB->insert_record("peerforum_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_peerforum', 'post', $post->id,
                mod_peerforum_post_form::editor_options($context, null), $post->message);
        $DB->set_field('peerforum_posts', 'message', $text, array('id'=>$post->id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion->firstpost    = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid       = $userid;

    $post->discussion = $DB->insert_record("peerforum_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("peerforum_posts", "discussion", $post->discussion, array("id"=>$post->id));

    if (!empty($cm->id)) {
        peerforum_add_attachment($post, $peerforum, $cm, $mform, $unused);
    }

    if (peerforum_tp_can_track_peerforums($peerforum) && peerforum_tp_is_tracked($peerforum)) {
        peerforum_tp_mark_post_read($post->userid, $post, $post->peerforum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm->id)) {
        peerforum_trigger_content_uploaded_event($post, $cm, 'peerforum_add_discussion');
    }

    return $post->discussion;
}


/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire peerforum
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $peerforum PeerForum
 * @return bool
 */
function peerforum_delete_discussion($discussion, $fulldelete, $course, $cm, $peerforum) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records("peerforum_posts", array("discussion" => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->peerforum  = $discussion->peerforum;
            if (!peerforum_delete_post($post, 'ignore', $course, $cm, $peerforum, $fulldelete)) {
                $result = false;
            }
        }
    }

    peerforum_tp_delete_read_records(-1, -1, $discussion->id);

    if (!$DB->delete_records("peerforum_discussions", array("id"=>$discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
           ($peerforum->completiondiscussions || $peerforum->completionreplies || $peerforum->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}


/**
 * Deletes a single peerforum post.
 *
 * @global object
 * @param object $post PeerForum post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $peerforum PeerForum
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire peerforum anyway.
 * @return bool
 */
function peerforum_delete_post($post, $children, $course, $cm, $peerforum, $skipcompletion=false) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('peerforum_posts', array('parent'=>$post->id)))) {
       if ($children) {
           foreach ($childposts as $childpost) {
               peerforum_delete_post($childpost, true, $course, $cm, $peerforum, $skipcompletion);
           }
       } else {
           return false;
       }
    }

    // Delete ratingpeers.
    require_once($CFG->dirroot.'/ratingpeer/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_peerforum';
    $delopt->ratingpeerarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new ratingpeer_manager();
    $rm->delete_ratingpeers($delopt);

    //Delete peergrades.
    $pm = new peergrade_manager();
    $pm->delete_peergrades($delopt);

    // Delete attachments.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_peerforum', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_peerforum', 'post', $post->id);

    // Delete cached RSS feeds.
    if (!empty($CFG->enablerssfeeds)) {
        require_once($CFG->dirroot.'/mod/peerforum/rsslib.php');
        peerforum_rss_delete_file($peerforum);
    }

    if ($DB->delete_records("peerforum_posts", array("id" => $post->id))) {

        peerforum_tp_delete_read_records(-1, $post->id);

    // Just in case we are deleting the last post
        peerforum_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
               ($peerforum->completiondiscussions || $peerforum->completionreplies || $peerforum->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post PeerForum post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
*/
function peerforum_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_peerforum', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'discussionid' => $post->discussion,
            'pathnamehashes' => array_keys($files),
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_peerforum\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function peerforum_count_replies($post, $children=true) {
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB->get_records('peerforum_posts', array('parent' => $post->id))) {
           foreach ($childposts as $childpost) {
               $count ++;                   // For this child
               $count += peerforum_count_replies($childpost, true);
           }
        }
    } else {
        $count += $DB->count_records('peerforum_posts', array('parent' => $post->id));
    }

    return $count;
}


/**
 * @global object
 * @param int $peerforumid
 * @param mixed $value
 * @return bool
 */
function peerforum_forcesubscribe($peerforumid, $value=1) {
    global $DB;
    return $DB->set_field("peerforum", "forcesubscribe", $value, array("id" => $peerforumid));
}

/**
 * @global object
 * @param object $peerforum
 * @return bool
 */
function peerforum_is_forcesubscribed($peerforum) {
    global $DB;
    if (isset($peerforum->forcesubscribe)) {    // then we use that
        return ($peerforum->forcesubscribe == PEERFORUM_FORCESUBSCRIBE);
    } else {   // Check the database
       return ($DB->get_field('peerforum', 'forcesubscribe', array('id' => $peerforum)) == PEERFORUM_FORCESUBSCRIBE);
    }
}

function peerforum_get_forcesubscribed($peerforum) {
    global $DB;
    if (isset($peerforum->forcesubscribe)) {    // then we use that
        return $peerforum->forcesubscribe;
    } else {   // Check the database
        return $DB->get_field('peerforum', 'forcesubscribe', array('id' => $peerforum));
    }
}

/**
 * @global object
 * @param int $userid
 * @param object $peerforum
 * @return bool
 */
function peerforum_is_subscribed($userid, $peerforum) {
    global $DB;
    if (is_numeric($peerforum)) {
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforum));
    }
    // If peerforum is force subscribed and has allowforcesubscribe, then user is subscribed.
    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id);
    if (peerforum_is_forcesubscribed($peerforum) && $cm &&
            has_capability('mod/peerforum:allowforcesubscribe', context_module::instance($cm->id), $userid)) {
        return true;
    }
    return $DB->record_exists("peerforum_subscriptions", array("userid" => $userid, "peerforum" => $peerforum->id));
}

function peerforum_get_subscribed_peerforums($course) {
    global $USER, $CFG, $DB;
    $sql = "SELECT f.id
              FROM {peerforum} f
                   LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = ?)
             WHERE f.course = ?
                   AND (f.forcesubscribe = ".PEERFORUM_FORCESUBSCRIBE." OR fs.id IS NOT NULL)";
    if ($subscribed = $DB->get_records_sql($sql, array($USER->id, $course->id))) {
        foreach ($subscribed as $s) {
            $subscribed[$s->id] = $s->id;
        }
        return $subscribed;
    } else {
        return array();
    }
}

/**
 * Returns an array of peerforums that the current user is subscribed to and is allowed to unsubscribe from
 *
 * @return array An array of unsubscribable peerforums
 */
function peerforum_get_optional_subscribed_peerforums() {
    global $USER, $DB;

    // Get courses that $USER is enrolled in and can see
    $courses = enrol_get_my_courses();
    if (empty($courses)) {
        return array();
    }

    $courseids = array();
    foreach($courses as $course) {
        $courseids[] = $course->id;
    }
    list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

    // get all peerforums from the user's courses that they are subscribed to and which are not set to forced
    $sql = "SELECT f.id, cm.id as cm, cm.visible
              FROM {peerforum} f
                   JOIN {course_modules} cm ON cm.instance = f.id
                   JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                   LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = :userid)
             WHERE f.forcesubscribe <> :forcesubscribe AND fs.id IS NOT NULL
                   AND cm.course $coursesql";
    $params = array_merge($courseparams, array('modulename'=>'peerforum', 'userid'=>$USER->id, 'forcesubscribe'=>PEERFORUM_FORCESUBSCRIBE));
    if (!$peerforums = $DB->get_records_sql($sql, $params)) {
        return array();
    }

    $unsubscribablepeerforums = array(); // Array to return

    foreach($peerforums as $peerforum) {

        if (empty($peerforum->visible)) {
            // the peerforum is hidden
            $context = context_module::instance($peerforum->cm);
            if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                // the user can't see the hidden peerforum
                continue;
            }
        }

        // subscribe.php only requires 'mod/peerforum:managesubscriptions' when
        // unsubscribing a user other than yourself so we don't require it here either

        // A check for whether the peerforum has subscription set to forced is built into the SQL above

        $unsubscribablepeerforums[] = $peerforum;
    }

    return $unsubscribablepeerforums;
}

/**
 * Adds user to the subscriber list
 *
 * @global object
 * @param int $userid
 * @param int $peerforumid
 */
function peerforum_subscribe($userid, $peerforumid) {
    global $DB;

    if ($DB->record_exists("peerforum_subscriptions", array("userid"=>$userid, "peerforum"=>$peerforumid))) {
        return true;
    }

    $sub = new stdClass();
    $sub->userid  = $userid;
    $sub->peerforum = $peerforumid;

    return $DB->insert_record("peerforum_subscriptions", $sub);
}

/**
 * Removes user from the subscriber list
 *
 * @global object
 * @param int $userid
 * @param int $peerforumid
 */
function peerforum_unsubscribe($userid, $peerforumid) {
    global $DB;
    return ($DB->delete_records('peerforum_digests', array('userid' => $userid, 'peerforum' => $peerforumid))
            && $DB->delete_records('peerforum_subscriptions', array('userid' => $userid, 'peerforum' => $peerforumid)));
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @global objec
 * @param object $post
 * @param object $peerforum
 */
function peerforum_post_subscription($post, $peerforum) {

    global $USER;

    $action = '';
    $subscribed = peerforum_is_subscribed($USER->id, $peerforum);

    if ($peerforum->forcesubscribe == PEERFORUM_FORCESUBSCRIBE) { // database ignored
        return "";

    } elseif (($peerforum->forcesubscribe == PEERFORUM_DISALLOWSUBSCRIBE)
        && !has_capability('moodle/course:manageactivities', context_course::instance($peerforum->course), $USER->id)) {
        if ($subscribed) {
            $action = 'unsubscribe'; // sanity check, following MDL-14558
        } else {
            return "";
        }

    } else { // go with the user's choice
        if (isset($post->subscribe)) {
            // no change
            if ((!empty($post->subscribe) && $subscribed)
                || (empty($post->subscribe) && !$subscribed)) {
                return "";

            } elseif (!empty($post->subscribe) && !$subscribed) {
                $action = 'subscribe';

            } elseif (empty($post->subscribe) && $subscribed) {
                $action = 'unsubscribe';
            }
        }
    }

    $info = new stdClass();
    $info->name  = fullname($USER);
    $info->peerforum = format_string($peerforum->name);

    switch ($action) {
        case 'subscribe':
            peerforum_subscribe($USER->id, $post->peerforum);
            return "<p>".get_string("nowsubscribed", "peerforum", $info)."</p>";
        case 'unsubscribe':
            peerforum_unsubscribe($USER->id, $post->peerforum);
            return "<p>".get_string("nownotsubscribed", "peerforum", $info)."</p>";
    }
}

/**
 * Generate and return the subscribe or unsubscribe link for a peerforum.
 *
 * @param object $peerforum the peerforum. Fields used are $peerforum->id and $peerforum->forcesubscribe.
 * @param object $context the context object for this peerforum.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param bool $cantaccessagroup
 * @param bool $fakelink
 * @param bool $backtoindex
 * @param array $subscribed_peerforums
 * @return string
 */
function peerforum_get_subscribe_link($peerforum, $context, $messages = array(), $cantaccessagroup = false, $fakelink=true, $backtoindex=false, $subscribed_peerforums=null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'peerforum'),
        'unsubscribed' => get_string('subscribe', 'peerforum'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'peerforum'),
        'cantsubscribe' => get_string('disallowsubscribe','peerforum')
    );
    $messages = $messages + $defaultmessages;

    if (peerforum_is_forcesubscribed($peerforum)) {
        return $messages['forcesubscribed'];
    } else if ($peerforum->forcesubscribe == PEERFORUM_DISALLOWSUBSCRIBE && !has_capability('mod/peerforum:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }
        if (is_null($subscribed_peerforums)) {
            $subscribed = peerforum_is_subscribed($USER->id, $peerforum);
        } else {
            $subscribed = !empty($subscribed_peerforums[$peerforum->id]);
        }
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'peerforum');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'peerforum');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $PAGE->requires->js('/mod/peerforum/peerforum.js');
            $PAGE->requires->js_function_call('peerforum_produce_subscribe_link', array($peerforum->id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $peerforum->id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/peerforum/subscribe.php', $options);
        $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}


/**
 * Generate and return the track or no track link for a peerforum.
 *
 * @global object
 * @global object
 * @global object
 * @param object $peerforum the peerforum. Fields used are $peerforum->id and $peerforum->forcesubscribe.
 * @param array $messages
 * @param bool $fakelink
 * @return string
 */
function peerforum_get_tracking_link($peerforum, $messages=array(), $fakelink=true) {
    global $CFG, $USER, $PAGE, $OUTPUT;

    static $strnotrackpeerforum, $strtrackpeerforum;

    if (isset($messages['trackpeerforum'])) {
         $strtrackpeerforum = $messages['trackpeerforum'];
    }
    if (isset($messages['notrackpeerforum'])) {
         $strnotrackpeerforum = $messages['notrackpeerforum'];
    }
    if (empty($strtrackpeerforum)) {
        $strtrackpeerforum = get_string('trackpeerforum', 'peerforum');
    }
    if (empty($strnotrackpeerforum)) {
        $strnotrackpeerforum = get_string('notrackpeerforum', 'peerforum');
    }

    if (peerforum_tp_is_tracked($peerforum)) {
        $linktitle = $strnotrackpeerforum;
        $linktext = $strnotrackpeerforum;
    } else {
        $linktitle = $strtrackpeerforum;
        $linktext = $strtrackpeerforum;
    }

    $link = '';
    if ($fakelink) {
        $PAGE->requires->js('/mod/peerforum/peerforum.js');
        $PAGE->requires->js_function_call('peerforum_produce_tracking_link', Array($peerforum->id, $linktext, $linktitle));
        // use <noscript> to print button in case javascript is not enabled
        $link .= '<noscript>';
    }
    $url = new moodle_url('/mod/peerforum/settracking.php', array(
            'id' => $peerforum->id,
            'sesskey' => sesskey(),
        ));
    $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));

    if ($fakelink) {
        $link .= '</noscript>';
    }

    return $link;
}



/**
 * Returns true if user created new discussion already
 *
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int $userid
 * @return bool
 */
function peerforum_user_has_posted_discussion($peerforumid, $userid) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {peerforum_discussions} d, {peerforum_posts} p
             WHERE d.peerforum = ? AND p.discussion = d.id AND p.parent = 0 and p.userid = ?";

    return $DB->record_exists_sql($sql, array($peerforumid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int $userid
 * @return array
 */
function peerforum_discussions_user_has_posted_in($peerforumid, $userid) {
    global $CFG, $DB;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {peerforum_posts} p,
                            {peerforum_discussions} d
                      WHERE p.discussion = d.id
                        AND d.peerforum = ?
                        AND p.userid = ?";

    return $DB->get_records_sql($haspostedsql, array($peerforumid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int $did
 * @param int $userid
 * @return bool
 */
function peerforum_user_has_posted($peerforumid, $did, $userid) {
    global $DB;

    if (empty($did)) {
        // posted in any peerforum discussion?
        $sql = "SELECT 'x'
                  FROM {peerforum_posts} p
                  JOIN {peerforum_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.peerforum = :peerforumid";
        return $DB->record_exists_sql($sql, array('peerforumid'=>$peerforumid,'userid'=>$userid));
    } else {
        return $DB->record_exists('peerforum_posts', array('discussion'=>$did,'userid'=>$userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @global object $DB
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 */
function peerforum_get_user_posted_time($did, $userid) {
    global $DB;

    $posttime = $DB->get_field('peerforum_posts', 'MIN(created)', array('userid'=>$userid, 'discussion'=>$did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @global object
 * @param object $peerforum
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 */
function peerforum_user_can_post_discussion($peerforum, $currentgroup=null, $unused=-1, $cm=NULL, $context=NULL) {
// $peerforum is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($peerforum->type == 'news') {
        $capname = 'mod/peerforum:addnews';
    } else if ($peerforum->type == 'qanda') {
        $capname = 'mod/peerforum:addquestion';
    } else {
        $capname = 'mod/peerforum:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($peerforum->type == 'single') {
        return false;
    }

    if ($peerforum->type == 'eachuser') {
        if (peerforum_user_has_posted_discussion($peerforum->id, $USER->id)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a peerforum
 * discussion. Use peerforum_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $peerforum peerforum object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 */
function peerforum_user_can_post($peerforum, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $peerforum->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user->id) and !is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($peerforum->type == 'news') {
        $capname = 'mod/peerforum:replynews';
    } else {
        $capname = 'mod/peerforum:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}

/**
 * Checks to see if a user can view a particular post.
 *
 * @deprecated since Moodle 2.4 use peerforum_user_can_see_post() instead
 *
 * @param object $post
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $user
 * @return boolean
 */
function peerforum_user_can_view_post($post, $course, $cm, $peerforum, $discussion, $user=null){
    debugging('peerforum_user_can_view_post() is deprecated. Please use peerforum_user_can_see_post() instead.', DEBUG_DEVELOPER);
    return peerforum_user_can_see_post($peerforum, $discussion, $post, $user, $cm);
}

/**
* Check to ensure a user can view a timed discussion.
*
* @param object $discussion
* @param object $user
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function peerforum_user_can_see_timed_discussion($discussion, $user, $context) {
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG->peerforum_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time)
            || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/peerforum:viewhiddentimedposts', $context, $user->id)) {
                return false;
            }
        }
    }

    return true;
}

/**
* Check to ensure a user can view a group discussion.
*
* @param object $discussion
* @param object $cm
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function peerforum_user_can_see_group_discussion($discussion, $cm, $context) {

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @param object $peerforum
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 */
function peerforum_user_can_see_discussion($peerforum, $discussion, $context, $user=NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($peerforum)) {
        debugging('missing full peerforum', DEBUG_DEVELOPER);
        if (!$peerforum = $DB->get_record('peerforum',array('id'=>$peerforum))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('peerforum_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/peerforum:viewdiscussion', $context)) {
        return false;
    }

    if (!peerforum_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!peerforum_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    if ($peerforum->type == 'qanda' &&
            !peerforum_user_has_posted($peerforum->id, $discussion->id, $user->id) &&
            !has_capability('mod/peerforum:viewqandawithoutposting', $context)) {
        return false;
    }
    return true;
}

/**
 * @global object
 * @global object
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 */
function peerforum_user_can_see_post($peerforum, $discussion, $post, $user=NULL, $cm=NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($peerforum)) {
        debugging('missing full peerforum', DEBUG_DEVELOPER);
        if (!$peerforum = $DB->get_record('peerforum',array('id'=>$peerforum))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('peerforum_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('peerforum_posts',array('id'=>$post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm->cache->caps['mod/peerforum:viewdiscussion']) || has_capability('mod/peerforum:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!coursemodule_visible_for_user($cm, $user->id)) {
            return false;
        }
    }

    if (!peerforum_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!peerforum_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if ($peerforum->type == 'qanda') {
        $firstpost = peerforum_get_firstpost_from_discussion($discussion->id);
        $userfirstpost = peerforum_get_user_posted_time($discussion->id, $user->id);

        return (($userfirstpost !== false && (time() - $userfirstpost >= $CFG->maxeditingtime)) ||
                $firstpost->id == $post->id || $post->userid == $user->id || $firstpost->userid == $user->id ||
                has_capability('mod/peerforum:viewqandawithoutposting', $modcontext, $user->id));
    }
    return true;
}


/**
 * Prints the discussion view screen for a peerforum.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $peerforum PeerForum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the peerforum (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 *
 */
function peerforum_print_latest_discussions($course, $peerforum, $maxdiscussions=-1, $displayformat='plain', $sort='',
                                        $currentgroup=-1, $groupmode=-1, $page=-1, $perpage=100, $cm=NULL,
                                        $to_peergrade_block=true, $url_block=null) {
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = "d.timemodified DESC";
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }


// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache

// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.

    $canstart = peerforum_user_can_post_discussion($peerforum, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $peerforum->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    if ($canstart) {
        echo '<div class="singlebutton peerforumaddnew">';
        echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/peerforum/post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"peerforum\" value=\"$peerforum->id\" />";
        switch ($peerforum->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'peerforum');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'peerforum');
                break;
            default:
                $buttonadd = get_string('addanewdiscussion', 'peerforum');
                break;
        }
        echo '<input type="submit" value="'.$buttonadd.'" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";

    } else if (isguestuser() or !isloggedin() or $peerforum->type == 'news' or
        $peerforum->type == 'qanda' and !has_capability('mod/peerforum:addquestion', $context) or
        $peerforum->type != 'qanda' and !has_capability('mod/peerforum:startdiscussion', $context)) {
        // no button and no info

    } else if ($groupmode and !has_capability('moodle/site:accessallgroups', $context)) {
        // inform users why they can not post new discussion
        if (!$currentgroup) {
            echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'peerforum'));
        } else if (!groups_is_member($currentgroup)) {
            echo $OUTPUT->notification(get_string('cannotadddiscussion', 'peerforum'));
        }
    }

// Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');

    if (! $discussions = peerforum_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage) ) {
        echo '<div class="peerforumnodiscuss">';
        if ($peerforum->type == 'news') {
            echo '('.get_string('nonews', 'peerforum').')';
        } else if ($peerforum->type == 'qanda') {
            echo '('.get_string('noquestions','peerforum').')';
        } else {
            echo '('.get_string('nodiscussions', 'peerforum').')';
        }
        echo "</div>\n";
        return;
    }

// If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = peerforum_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$peerforum->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large peerforums
            $replies = peerforum_count_discussion_replies($peerforum->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = peerforum_count_discussion_replies($peerforum->id);
        }

    } else {
        $replies = peerforum_count_discussion_replies($peerforum->id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);

    $strdatestring = get_string('strftimerecentfull');

    // Check if the peerforum is tracked.
    if ($cantrack = peerforum_tp_can_track_peerforums($peerforum)) {
        $peerforumtracked = peerforum_tp_is_tracked($peerforum);
    } else {
        $peerforumtracked = false;
    }

    if ($peerforumtracked) {
        $unreads = peerforum_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    if ($displayformat == 'header') {
        echo '<table cellspacing="0" class="peerforumheaderlist">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="header topic" scope="col">'.get_string('discussion', 'peerforum').'</th>';
        echo '<th class="header author" colspan="2" scope="col">'.get_string('startedby', 'peerforum').'</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">'.get_string('group').'</th>';
        }
        if (has_capability('mod/peerforum:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">'.get_string('replies', 'peerforum').'</th>';
            // If the peerforum can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">'.get_string('unread', 'peerforum');
                if ($peerforumtracked) {
                    echo '<a title="'.get_string('markallread', 'peerforum').
                         '" href="'.$CFG->wwwroot.'/mod/peerforum/markposts.php?f='.
                         $peerforum->id.'&amp;mark=read&amp;returnpage=view.php">'.
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.get_string('markallread', 'peerforum').'" /></a>';
                }
                echo '</th>';
            }
        }
        echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'peerforum').'</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    foreach ($discussions as $discussion) {
        if ($peerforum->type == 'qanda' && !has_capability('mod/peerforum:viewqandawithoutposting', $context) &&
            !peerforum_user_has_posted($peerforum->id, $discussion->discussion, $USER->id)) {
            $canviewparticipants = false;
        }

        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$peerforumtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                peerforum_print_discussion_header($discussion, $peerforum, $group, $strdatestring, $cantrack, $peerforumtracked,
                    $canviewparticipants, $context);
            break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = peerforum_user_can_see_discussion($peerforum, $discussion, $modcontext, $USER);
                }

                $discussion->peerforum = $peerforum->id;

                peerforum_print_post($discussion, $discussion, $peerforum, $cm, $course, $ownpost, 0, $link, false,
                        '', null, true, $peerforumtracked, false, true, false, $to_peergrade_block, $url_block);
            break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    }

    if ($olddiscussionlink) {
        if ($peerforum->type == 'news') {
            $strolder = get_string('oldertopics', 'peerforum');
        } else {
            $strolder = get_string('olderdiscussions', 'peerforum');
        }
        echo '<div class="peerforumolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/peerforum/view.php?f='.$peerforum->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }

    if ($page != -1) { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$peerforum->id");
    }
}


/**
 * Prints a peerforum discussion
 *
 * @uses CONTEXT_MODULE
 * @uses PEERFORUM_MODE_FLATNEWEST
 * @uses PEERFORUM_MODE_FLATOLDEST
 * @uses PEERFORUM_MODE_THREADED
 * @uses PEERFORUM_MODE_NESTED
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $peerforum
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canratepeer
 */
function peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post, $mode, $canreply=NULL, $canratepeer=false, $cangrade=false, $showincontext=false, $to_peergrade_block=true, $url_block=null, $index=null) {
    global $USER, $CFG;

    require_once($CFG->dirroot.'/ratingpeer/lib.php');

    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === NULL) {
        $reply = peerforum_user_can_post($peerforum, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for peerforum functions
    $cm->cache = new stdClass;
    $cm->cache->groups      = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    if ($mode == PEERFORUM_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }

    $peerforumtracked = peerforum_tp_is_tracked($peerforum);
    $posts = peerforum_get_all_discussion_posts($discussion->id, $sort, $peerforumtracked);
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    //load ratingpeers
    if ($peerforum->assessed != RATINGPEER_AGGREGATE_NONE) {
        $ratingpeeroptions = new stdClass;
        $ratingpeeroptions->context = $modcontext;
        $ratingpeeroptions->component = 'mod_peerforum';
        $ratingpeeroptions->ratingpeerarea = 'post';
        $ratingpeeroptions->items = $posts;
        $ratingpeeroptions->aggregate = $peerforum->assessed;//the aggregation method
        $ratingpeeroptions->scaleid = $peerforum->scale;
        $ratingpeeroptions->userid = $USER->id;
        if ($peerforum->type == 'single' or !$discussion->id) {
            $ratingpeeroptions->returnurl = "$CFG->wwwroot/mod/peerforum/view.php?id=$cm->id";
        } else {
            $ratingpeeroptions->returnurl = "$CFG->wwwroot/mod/peerforum/discuss.php?d=$discussion->id";
        }
        $ratingpeeroptions->assesstimestart = $peerforum->assesstimestart;
        $ratingpeeroptions->assesstimefinish = $peerforum->assesstimefinish;

        $rm = new ratingpeer_manager();
        $posts = $rm->get_ratingpeers($ratingpeeroptions);
    }

    //load peergrades
    if ($peerforum->peergradeassessed != PEERGRADE_AGGREGATE_NONE) {

        $peergradeoptions = new stdClass;
        $peergradeoptions->context = $modcontext;
        $peergradeoptions->component = 'mod_peerforum';
        $peergradeoptions->peergradearea = 'post';
        $peergradeoptions->items = $posts;
        $peergradeoptions->aggregate = $peerforum->peergradeassessed;//the aggregation method
        $peergradeoptions->scaleid = $peerforum->scale;
        $peergradeoptions->peergradescaleid = $peerforum->peergradescale;
        $peergradeoptions->userid = $USER->id;
        $peergradeoptions->feedback = 'null_feedback';

        if ($peerforum->type == 'single' or !$discussion->id) {
            $peergradeoptions->returnurl = "$CFG->wwwroot/mod/peerforum/view.php?id=$cm->id";
        } else {
            if(!$to_peergrade_block){
                $peergradeoptions->returnurl = "$CFG->wwwroot/mod/peerforum/discuss.php?d=$discussion->id";
            }
            if($to_peergrade_block){
                $peergradeoptions->returnurl = $url_block;

            }
        }
        $peergradeoptions->assesstimestart = $peerforum->assesstimestart;
        $peergradeoptions->assesstimefinish = $peerforum->assesstimefinish;

        $pm = new peergrade_manager();
        $posts = $pm->get_peergrades($peergradeoptions);
    }


    $post->peerforum = $peerforum->id;   // Add the peerforum id to the post object, later used by peerforum_print_post
    $post->peerforumtype = $peerforum->type;

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);
    $post->discussionname = format_string($discussion->name);
    $post->peerforumname = format_string($peerforum->name);

    peerforum_print_post($post, $discussion, $peerforum, $cm, $course, $ownpost, $reply, false,
                         '', '', $postread, true, $peerforumtracked, false, true, $showincontext, $to_peergrade_block, $url_block);

    if(!isset($index)){
        switch ($mode) {
            case PEERFORUM_MODE_FLATOLDEST :
            case PEERFORUM_MODE_FLATNEWEST :
            default:
                peerforum_print_posts_flat($course, $cm, $peerforum, $discussion, $post, $mode, $reply, $peerforumtracked, $posts, $showincontext, $to_peergrade_block, $url_block);
                break;

            case PEERFORUM_MODE_THREADED :
                peerforum_print_posts_threaded($course, $cm, $peerforum, $discussion, $post, 0, $reply, $peerforumtracked, $posts, $showincontext, $to_peergrade_block, $url_block);
                break;

            case PEERFORUM_MODE_NESTED :
                peerforum_print_posts_nested($course, $cm, $peerforum, $discussion, $post, $reply, $peerforumtracked, $posts, $showincontext, $to_peergrade_block, $url_block);
                break;
        }
    }
}


/**
 * @global object
 * @global object
 * @uses PEERFORUM_MODE_FLATNEWEST
 * @param object $course
 * @param object $cm
 * @param object $peerforum
 * @param object $discussion
 * @param object $post
 * @param object $mode
 * @param bool $reply
 * @param bool $peerforumtracked
 * @param array $posts
 * @return void
 */
function peerforum_print_posts_flat($course, &$cm, $peerforum, $discussion, $post, $mode, $reply, $peerforumtracked, $posts, $showincontext=false, $to_peergrade_block=true, $url_block=null) {
    global $USER, $CFG;

    $link  = false;

    if ($mode == PEERFORUM_MODE_FLATNEWEST) {
        $sort = "ORDER BY created DESC";
    } else {
        $sort = "ORDER BY created ASC";
    }

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        peerforum_print_post($post, $discussion, $peerforum, $cm, $course, $ownpost, $reply, $link,
                             '', '', $postread, true, $peerforumtracked,  false, true, $showincontext, $to_peergrade_block, $url_block);
    }
}

/**
 * @todo Document this function
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return void
 */
function peerforum_print_posts_threaded($course, &$cm, $peerforum, $discussion, $parent, $depth, $reply, $peerforumtracked, $posts, $showincontext=false, $to_peergrade_block=true, $url_block=null) {
    global $USER, $CFG;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext       = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                peerforum_print_post($post, $discussion, $peerforum, $cm, $course, $ownpost, $reply, $link,
                                     '', '', $postread, true, $peerforumtracked, false, true, $showincontext, $to_peergrade_block, $url_block);
            } else {
                if (!peerforum_user_can_see_post($peerforum, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by->name = fullname($post, $canviewfullnames);
                $by->date = userdate($post->modified);

                if ($peerforumtracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="peerforumthread read">';
                    } else {
                        $style = '<span class="peerforumthread unread">';
                    }
                } else {
                    $style = '<span class="peerforumthread">';
                }
                echo $style."<a name=\"$post->id\"></a>".
                     "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a> ";
                print_string("bynameondate", "peerforum", $by);
                echo "</span>";
            }

            peerforum_print_posts_threaded($course, $cm, $peerforum, $discussion, $post, $depth-1, $reply, $peerforumtracked, $posts, $showincontext, $to_peergrade_block, $url_block);
            echo "</div>\n";
        }
    }
}

/**
 * @todo Document this function
 * @global object
 * @global object
 * @return void
 */
function peerforum_print_posts_nested($course, &$cm, $peerforum, $discussion, $parent, $reply, $peerforumtracked, $posts, $showincontext=false, $to_peergrade_block=true, $url_block=null) {
    global $USER, $CFG;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            peerforum_print_post($post, $discussion, $peerforum, $cm, $course, $ownpost, $reply, $link,
                                 '', '', $postread, true, $peerforumtracked, false, true, $showincontext, $to_peergrade_block, $url_block);
            peerforum_print_posts_nested($course, $cm, $peerforum, $discussion, $post, $reply, $peerforumtracked, $posts, $showincontext, $to_peergrade_block, $url_block);
            echo "</div>\n";
        }
    }
}

/**
 * Returns all peerforum posts since a given time in specified peerforum.
 *
 * @todo Document this functions args
 * @global object
 * @global object
 * @global object
 * @global object
 */
function peerforum_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND d.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS peerforumtype, d.peerforum, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {peerforum_posts} p
                                              JOIN {peerforum_discussions} d ON d.id = p.discussion
                                              JOIN {peerforum} f             ON f.id = d.peerforum
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // order by initial posting date
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/peerforum:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->peerforum_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name,true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'peerforum';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id         = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject    = format_string($post->subject);
        $tmpactivity->content->parent     = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * @todo Document this function
 * @global object
 */
function peerforum_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="peerforum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    echo "</td><td class=\"$class\">";

    echo '<div class="title">';
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"" . $OUTPUT->pix_url('icon', $activity->type) . "\" ".
             "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/peerforum/discuss.php?d={$activity->content->discussion}"
         ."#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';
      echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 *
 * @global object
 * @param int $postid
 * @param int $discussionid
 * @return bool
 */
function peerforum_change_discussionid($postid, $discussionid) {
    global $DB;
    $DB->set_field('peerforum_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('peerforum_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            peerforum_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $peerforumid
 * @return string
 */
function peerforum_update_subscriptions_button($courseid, $peerforumid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/peerforum/subscribers.php\">".
           "<input type=\"hidden\" name=\"id\" value=\"$peerforumid\" />".
           "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />".
           "<input type=\"submit\" value=\"$string\" /></form>";
}

/**
 * This function gets run whenever user is enrolled into course
 *
 * @deprecated deprecating this function as we will be using \mod_peerforum\observer::role_assigned()
 * @param stdClass $cp
 * @return void
 */
function peerforum_user_enrolled($cp) {
    global $DB;

    // NOTE: this has to be as fast as possible - we do not want to slow down enrolments!
    //       Originally there used to be 'mod/peerforum:initialsubscriptions' which was
    //       introduced because we did not have enrolment information in earlier versions...

    $sql = "SELECT f.id
              FROM {peerforum} f
         LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = :userid)
             WHERE f.course = :courseid AND f.forcesubscribe = :initial AND fs.id IS NULL";
    $params = array('courseid'=>$cp->courseid, 'userid'=>$cp->userid, 'initial'=>PEERFORUM_INITIALSUBSCRIBE);

    $peerforums = $DB->get_records_sql($sql, $params);
    foreach ($peerforums as $peerforum) {
        peerforum_subscribe($cp->userid, $peerforum->id);
    }
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @global object
 * @global object
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function peerforum_tp_mark_posts_read($user, $postids) {
    global $CFG, $DB;

    if (!peerforum_tp_can_track_peerforums(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->peerforum_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = peerforum_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $postidparams) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'postid');

    $insertparams = array(
        'userid1' => $user->id,
        'userid2' => $user->id,
        'userid3' => $user->id,
        'firstread' => $now,
        'lastread' => $now,
        'cutoffdate' => $cutoffdate,
    );
    $params = array_merge($postidparams, $insertparams);

    if ($CFG->peerforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".PEERFORUM_TRACKING_FORCED."
                        OR (f.trackingtype = ".PEERFORUM_TRACKING_OPTIONAL." AND tf.id IS NULL))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".PEERFORUM_TRACKING_OPTIONAL."  OR f.trackingtype = ".PEERFORUM_TRACKING_FORCED.")
                            AND tf.id IS NULL)";
    }

    // First insert any new entries.
    $sql = "INSERT INTO {peerforum_read} (userid, postid, discussionid, peerforumid, firstread, lastread)

            SELECT :userid1, p.id, p.discussion, d.peerforum, :firstread, :lastread
                FROM {peerforum_posts} p
                    JOIN {peerforum_discussions} d       ON d.id = p.discussion
                    JOIN {peerforum} f                   ON f.id = d.peerforum
                    LEFT JOIN {peerforum_track_prefs} tf ON (tf.userid = :userid2 AND tf.peerforumid = f.id)
                    LEFT JOIN {peerforum_read} fr        ON (
                            fr.userid = :userid3
                        AND fr.postid = p.id
                        AND fr.discussionid = d.id
                        AND fr.peerforumid = f.id
                    )
                WHERE p.id $usql
                    AND p.modified >= :cutoffdate
                    $trackingsql
                    AND fr.id IS NULL";

    $status = $DB->execute($sql, $params) && $status;

    // Then update all records.
    $updateparams = array(
        'userid' => $user->id,
        'lastread' => $now,
    );
    $params = array_merge($postidparams, $updateparams);
    $status = $DB->set_field_select('peerforum_read', 'lastread', $now, '
                userid      =  :userid
            AND lastread    <> :lastread
            AND postid      ' . $usql,
            $params) && $status;

    return $status;
}

/**
 * Mark post as read.
 * @global object
 * @global object
 * @param int $userid
 * @param int $postid
 */
function peerforum_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG->peerforum_oldpostdays * 24 * 3600);

    if (!$DB->record_exists('peerforum_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {peerforum_read} (userid, postid, discussionid, peerforumid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.peerforum, ?, ?
                  FROM {peerforum_posts} p
                       JOIN {peerforum_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = "UPDATE {peerforum_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * Returns all records in the 'peerforum_read' table matching the passed keys, indexed
 * by userid.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $peerforumid
 * @return array
 */
function peerforum_tp_get_read_records($userid=-1, $postid=-1, $discussionid=-1, $peerforumid=-1) {
    global $DB;
    $select = '';
    $params = array();

    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($peerforumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'peerforumid = ?';
        $params[] = $peerforumid;
    }

    return $DB->get_records_select('peerforum_read', $select, $params);
}

/**
 * Returns all read records for the provided user and discussion, indexed by postid.
 *
 * @global object
 * @param inti $userid
 * @param int $discussionid
 */
function peerforum_tp_get_discussion_read_records($userid, $discussionid) {
    global $DB;
    $select = 'userid = ? AND discussionid = ?';
    $fields = 'postid, firstread, lastread';
    return $DB->get_records_select('peerforum_read', $select, array($userid, $discussionid), '', $fields);
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function peerforum_tp_mark_post_read($userid, $post, $peerforumid) {
    if (!peerforum_tp_is_post_old($post)) {
        return peerforum_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole peerforum as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $peerforumid
 * @param int|bool $groupid
 * @return bool
 */
function peerforum_tp_mark_peerforum_read($user, $peerforumid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->peerforum_oldpostdays*24*60*60);

    $groupsel = "";
    $params = array($user->id, $peerforumid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {peerforum_posts} p
                   LEFT JOIN {peerforum_discussions} d ON d.id = p.discussion
                   LEFT JOIN {peerforum_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.peerforum = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return peerforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $discussionid
 * @return bool
 */
function peerforum_tp_mark_discussion_read($user, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->peerforum_oldpostdays*24*60*60);

    $sql = "SELECT p.id
              FROM {peerforum_posts} p
                   LEFT JOIN {peerforum_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return peerforum_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @global object
 * @param int $userid
 * @param object $post
 */
function peerforum_tp_is_post_read($userid, $post) {
    global $DB;
    return (peerforum_tp_is_post_old($post) ||
            $DB->record_exists('peerforum_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function peerforum_tp_is_post_old($post, $time=null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->peerforum_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return bool
 */
function peerforum_tp_count_discussion_read_records($userid, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = isset($CFG->peerforum_oldpostdays) ? (time() - ($CFG->peerforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(DISTINCT p.id) '.
           'FROM {peerforum_discussions} d '.
           'LEFT JOIN {peerforum_read} r ON d.id = r.discussionid AND r.userid = ? '.
           'LEFT JOIN {peerforum_posts} p ON p.discussion = d.id '.
                'AND (p.modified < ? OR p.id = r.postid) '.
           'WHERE d.id = ? ';

    return ($DB->count_records_sql($sql, array($userid, $cutoffdate, $discussionid)));
}

/**
 * Returns the count of records for the provided user and discussion.
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $discussionid
 * @return int
 */
function peerforum_tp_count_discussion_unread_posts($userid, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = isset($CFG->peerforum_oldpostdays) ? (time() - ($CFG->peerforum_oldpostdays*24*60*60)) : 0;

    $sql = 'SELECT COUNT(p.id) '.
           'FROM {peerforum_posts} p '.
           'LEFT JOIN {peerforum_read} r ON r.postid = p.id AND r.userid = ? '.
           'WHERE p.discussion = ? '.
                'AND p.modified >= ? AND r.id is NULL';

    return $DB->count_records_sql($sql, array($userid, $discussionid, $cutoffdate));
}

/**
 * Returns the count of posts for the provided peerforum and [optionally] group.
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int|bool $groupid
 * @return int
 */
function peerforum_tp_count_peerforum_posts($peerforumid, $groupid=false) {
    global $CFG, $DB;
    $params = array($peerforumid);
    $sql = 'SELECT COUNT(*) '.
           'FROM {peerforum_posts} fp,{peerforum_discussions} fd '.
           'WHERE fd.peerforum = ? AND fp.discussion = fd.id';
    if ($groupid !== false) {
        $sql .= ' AND (fd.groupid = ? OR fd.groupid = -1)';
        $params[] = $groupid;
    }
    $count = $DB->count_records_sql($sql, $params);


    return $count;
}

/**
 * Returns the count of records for the provided user and peerforum and [optionally] group.
 * @global object
 * @global object
 * @param int $userid
 * @param int $peerforumid
 * @param int|bool $groupid
 * @return int
 */
function peerforum_tp_count_peerforum_read_records($userid, $peerforumid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->peerforum_oldpostdays*24*60*60);

    $groupsel = '';
    $params = array($userid, $peerforumid, $cutoffdate);
    if ($groupid !== false) {
        $groupsel = "AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT COUNT(p.id)
              FROM  {peerforum_posts} p
                    JOIN {peerforum_discussions} d ON d.id = p.discussion
                    LEFT JOIN {peerforum_read} r   ON (r.postid = p.id AND r.userid= ?)
              WHERE d.peerforum = ?
                    AND (p.modified < $cutoffdate OR (p.modified >= ? AND r.id IS NOT NULL))
                    $groupsel";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function peerforum_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG, $DB;

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->peerforum_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid);

    if (!empty($CFG->peerforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->peerforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".PEERFORUM_TRACKING_FORCED."
                            OR (f.trackingtype = ".PEERFORUM_TRACKING_OPTIONAL." AND tf.id IS NULL
                                AND (SELECT trackforums FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".PEERFORUM_TRACKING_OPTIONAL." OR f.trackingtype = ".PEERFORUM_TRACKING_FORCED.")
                            AND tf.id IS NULL
                            AND (SELECT trackforums FROM {user} WHERE id = ?) = 1)";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {peerforum_posts} p
                   JOIN {peerforum_discussions} d       ON d.id = p.discussion
                   JOIN {peerforum} f                   ON f.id = d.peerforum
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {peerforum_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {peerforum_track_prefs} tf ON (tf.userid = ? AND tf.peerforumid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and peerforum and [optionally] group.
 *
 * @global object
 * @global object
 * @global object
 * @param object $cm
 * @param object $course
 * @return int
 */
function peerforum_tp_count_peerforum_unread_posts($cm, $course) {
    global $CFG, $USER, $DB;

    static $readcache = array();

    $peerforumid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = peerforum_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$peerforumid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$peerforumid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$peerforumid];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->peerforum_oldpostdays*24*60*60);
    $params = array($USER->id, $peerforumid, $cutoffdate);

    if (!empty($CFG->peerforum_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {peerforum_posts} p
                   JOIN {peerforum_discussions} d ON p.discussion = d.id
                   LEFT JOIN {peerforum_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.peerforum = ?
                   AND p.modified >= ? AND r.id is NULL
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $peerforumid
 * @return bool
 */
function peerforum_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $peerforumid=-1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($peerforumid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'peerforumid = ?';
        $params[] = $peerforumid;
    }
    if ($select == '') {
        return false;
    }
    else {
        return $DB->delete_records_select('peerforum_read', $select, $params);
    }
}
/**
 * Get a list of peerforums not tracked by the user.
 *
 * @global object
 * @global object
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by peerforum id, or false.
 */
function peerforum_tp_get_untracked_peerforums($userid, $courseid) {
    global $CFG, $DB;

    if ($CFG->peerforum_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".PEERFORUM_TRACKING_OFF."
                            OR (f.trackingtype = ".PEERFORUM_TRACKING_OPTIONAL." AND (ft.id IS NOT NULL
                                OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = ".PEERFORUM_TRACKING_OFF."
                            OR ((f.trackingtype = ".PEERFORUM_TRACKING_OPTIONAL." OR f.trackingtype = ".PEERFORUM_TRACKING_FORCED.")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {peerforum} f
                   LEFT JOIN {peerforum_track_prefs} ft ON (ft.peerforumid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($peerforums = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($peerforums as $peerforum) {
            $peerforums[$peerforum->id] = $peerforum;
        }
        return $peerforums;

    } else {
        return array();
    }
}

/**
 * Determine if a user can track peerforums and optionally a particular peerforum.
 * Checks the site settings, the user settings and the peerforum settings (if
 * requested).
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $peerforum The peerforum object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function peerforum_tp_can_track_peerforums($peerforum=false, $user=false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->peerforum_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($peerforum === false) {
        if ($CFG->peerforum_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific peerforum.
            return true;
        } else {
            return (bool)$user->trackforums;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($peerforum)) {
        debugging('Better use proper peerforum object.', DEBUG_DEVELOPER);
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforum), '', 'id,trackingtype');
    }

    $peerforumallows = ($peerforum->trackingtype == PEERFORUM_TRACKING_OPTIONAL);
    $peerforumforced = ($peerforum->trackingtype == PEERFORUM_TRACKING_FORCED);

    if ($CFG->peerforum_allowforcedreadtracking) {
        // If we allow forcing, then forced peerforums takes procidence over user setting.
        return ($peerforumforced || ($peerforumallows  && (!empty($user->trackforums) && (bool)$user->trackforums)));
    } else {
        // If we don't allow forcing, user setting trumps.
        return ($peerforumforced || $peerforumallows)  && !empty($user->trackforums);
    }
}

/**
 * Tells whether a specific peerforum is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $peerforum If int, the id of the peerforum being checked; if object, the peerforum object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function peerforum_tp_is_tracked($peerforum, $user=false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($peerforum)) {
        debugging('Better use proper peerforum object.', DEBUG_DEVELOPER);
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforum));
    }

    if (!peerforum_tp_can_track_peerforums($peerforum, $user)) {
        return false;
    }

    $peerforumallows = ($peerforum->trackingtype == PEERFORUM_TRACKING_OPTIONAL);
    $peerforumforced = ($peerforum->trackingtype == PEERFORUM_TRACKING_FORCED);
    $userpref = $DB->get_record('peerforum_track_prefs', array('userid' => $user->id, 'peerforumid' => $peerforum->id));

    if ($CFG->peerforum_allowforcedreadtracking) {
        return $peerforumforced || ($peerforumallows && $userpref === false);
    } else {
        return  ($peerforumallows || $peerforumforced) && $userpref === false;
    }
}

/**
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int $userid
 */
function peerforum_tp_start_tracking($peerforumid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('peerforum_track_prefs', array('userid' => $userid, 'peerforumid' => $peerforumid));
}

/**
 * @global object
 * @global object
 * @param int $peerforumid
 * @param int $userid
 */
function peerforum_tp_stop_tracking($peerforumid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('peerforum_track_prefs', array('userid' => $userid, 'peerforumid' => $peerforumid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->peerforumid = $peerforumid;
        $DB->insert_record('peerforum_track_prefs', $track_prefs);
    }

    return peerforum_tp_delete_read_records($userid, -1, -1, $peerforumid);
}


/**
 * Clean old records from the peerforum_read table.
 * @global object
 * @global object
 * @return void
 */
function peerforum_tp_clean_read_records() {
    global $CFG, $DB;

    if (!isset($CFG->peerforum_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the peerforum_read table.
    $cutoffdate = time() - ($CFG->peerforum_oldpostdays*24*60*60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {peerforum_posts} fp
                   JOIN {peerforum_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {peerforum_read}
             WHERE postid IN (SELECT fp.id
                                FROM {peerforum_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB->execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @global object
 * @global object
 * @param into $discussionid
 * @return bool|int
 **/
function peerforum_discussion_update_last_post($discussionid) {
    global $CFG, $DB;

// Check the given discussion exists
    if (!$DB->record_exists('peerforum_discussions', array('id' => $discussionid))) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {peerforum_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

// Lets go find the last post
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject->id           = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('peerforum_discussions', $discussionobject);
        return $lastpost->id;
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}


/**
 * @return array
 */
function peerforum_get_view_actions() {
    return array('view discussion', 'search', 'peerforum', 'peerforums', 'subscribers', 'view peerforum');
}

/**
 * @return array
 */
function peerforum_get_post_actions() {
    return array('add discussion','add post','delete discussion','delete post','move discussion','prune post','update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $peerforum the peerforum id or the peerforum object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function peerforum_check_throttling($peerforum, $cm = null) {
    global $CFG, $DB, $USER;

    if (is_numeric($peerforum)) {
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforum), '*', MUST_EXIST);
    }

    if (!is_object($peerforum)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course, false, MUST_EXIST);
    }

    if (empty($peerforum->blockafter)) {
        return false;
    }

    if (empty($peerforum->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/peerforum:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $peerforum->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {peerforum_posts} p
                                        JOIN {peerforum_discussions} d
                                        ON p.discussion = d.id WHERE d.peerforum = ?
                                        AND p.userid = ? AND p.created > ?', array($peerforum->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $peerforum->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime'.$peerforum->blockperiod);

    if ($peerforum->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'peerforumblockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/peerforum/view.php?f=' . $peerforum->id;

        return $warning;
    }

    if ($peerforum->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'peerforumblockingalmosttoomanyposts';
        $warning->module = 'peerforum';
        $warning->additional = $a;
        $warning->link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @since Moodle 2.5
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function peerforum_check_throttling.
 */
function peerforum_check_blocking_threshold($thresholdwarning) {
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode,
                    $thresholdwarning->module,
                    $thresholdwarning->link,
                    $thresholdwarning->additional);
    }
}


/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional
 */
function peerforum_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {peerforum} f, {course_modules} cm, {modules} m
             WHERE m.name='peerforum' AND m.id=cm.module AND cm.instance=f.id AND f.course=? $wheresql";

    if ($peerforums = $DB->get_records_sql($sql, $params)) {
        foreach ($peerforums as $peerforum) {
            peerforum_grade_item_update($peerforum, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified peerforum
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function peerforum_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/ratingpeer/lib.php');

    $componentstr = get_string('modulenameplural', 'peerforum');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql     = "";
    if (!empty($data->reset_peerforum_all)) {
        $removeposts = true;
        $typesstr    = get_string('resetpeerforumsall', 'peerforum');
        $types       = array();
    } else if (!empty($data->reset_peerforum_types)){
        $removeposts = true;
        $typesql     = "";
        $types       = array();
        $peerforum_types_all = peerforum_get_peerforum_types_all();
        foreach ($data->reset_peerforum_types as $type) {
            if (!array_key_exists($type, $peerforum_types_all)) {
                continue;
            }
            $typesql .= " AND f.type=?";
            $types[] = $peerforum_types_all[$type];
            $params[] = $type;
        }
        $typesstr = get_string('resetpeerforums', 'peerforum').': '.implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {peerforum_discussions} fd, {peerforum} f
                           WHERE f.course=? AND f.id=fd.peerforum";

    $allpeerforumssql      = "SELECT f.id
                            FROM {peerforum} f
                           WHERE f.course=?";

    $allpostssql       = "SELECT fp.id
                            FROM {peerforum_posts} fp, {peerforum_discussions} fd, {peerforum} f
                           WHERE f.course=? AND f.id=fd.peerforum AND fd.id=fp.discussion";

    $peerforumssql = $peerforums = $rm = null;

    if( $removeposts || !empty($data->reset_peerforum_ratingpeers) ) {
        $peerforumssql      = "$allpeerforumssql $typesql";
        $peerforums = $peerforums = $DB->get_records_sql($peerforumssql, $params);
        $rm = new ratingpeer_manager();
        $ratingpeerdeloptions = new stdClass;
        $ratingpeerdeloptions->component = 'mod_peerforum';
        $ratingpeerdeloptions->ratingpeerarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql       = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($peerforums) {
            foreach ($peerforums as $peerforumid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('peerforum', $peerforumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_peerforum', 'attachment');
                $fs->delete_area_files($context->id, 'mod_peerforum', 'post');

                //remove ratingpeers
                $ratingpeerdeloptions->contextid = $context->id;
                $rm->delete_ratingpeers($ratingpeerdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('peerforum_read', "peerforumid IN ($peerforumssql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('peerforum_track_prefs', "peerforumid IN ($peerforumssql)", $params);

        // remove posts from queue
        $DB->delete_records_select('peerforum_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion peerforums
        $DB->delete_records_select('peerforum_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('peerforum_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple

        // finally all discussions except single simple peerforums
        $DB->delete_records_select('peerforum_discussions', "peerforum IN ($peerforumssql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                peerforum_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    peerforum_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

    // remove all ratingpeers in this course's peerforums
    if (!empty($data->reset_peerforum_ratingpeers)) {
        if ($peerforums) {
            foreach ($peerforums as $peerforumid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('peerforum', $peerforumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratingpeers
                $ratingpeerdeloptions->contextid = $context->id;
                $rm->delete_ratingpeers($ratingpeerdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            peerforum_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_peerforum_digests)) {
        $DB->delete_records_select('peerforum_digests', "peerforum IN ($allpeerforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'peerforum'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_peerforum_subscriptions)) {
        $DB->delete_records_select('peerforum_subscriptions', "peerforum IN ($allpeerforumssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resetsubscriptions','peerforum'), 'error'=>false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_peerforum_track_prefs)) {
        $DB->delete_records_select('peerforum_track_prefs', "peerforumid IN ($allpeerforumssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resettrackprefs','peerforum'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('peerforum', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function peerforum_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'peerforumheader', get_string('modulenameplural', 'peerforum'));

    $mform->addElement('checkbox', 'reset_peerforum_all', get_string('resetpeerforumsall','peerforum'));

    $mform->addElement('select', 'reset_peerforum_types', get_string('resetpeerforums', 'peerforum'), peerforum_get_peerforum_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_peerforum_types');
    $mform->disabledIf('reset_peerforum_types', 'reset_peerforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_peerforum_digests', get_string('resetdigests','peerforum'));
    $mform->setAdvanced('reset_peerforum_digests');

    $mform->addElement('checkbox', 'reset_peerforum_subscriptions', get_string('resetsubscriptions','peerforum'));
    $mform->setAdvanced('reset_peerforum_subscriptions');

    $mform->addElement('checkbox', 'reset_peerforum_track_prefs', get_string('resettrackprefs','peerforum'));
    $mform->setAdvanced('reset_peerforum_track_prefs');
    $mform->disabledIf('reset_peerforum_track_prefs', 'reset_peerforum_all', 'checked');

    $mform->addElement('checkbox', 'reset_peerforum_ratingpeers', get_string('deleteallratingpeers'));
    $mform->disabledIf('reset_peerforum_ratingpeers', 'reset_peerforum_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function peerforum_reset_course_form_defaults($course) {
    return array('reset_peerforum_all'=>1, 'reset_peerforum_digests' => 0, 'reset_peerforum_subscriptions'=>0, 'reset_peerforum_track_prefs'=>0, 'reset_peerforum_ratingpeers'=>1);
}

/**
 * Converts a peerforum to use the Roles System
 *
 * @global object
 * @global object
 * @param object $peerforum        a peerforum object with the same attributes as a record
 *                        from the peerforum database table
 * @param int $peerforummodid   the id of the peerforum module, from the modules table
 * @param array $teacherroles array of roles that have archetype teacher
 * @param array $studentroles array of roles that have archetype student
 * @param array $guestroles   array of roles that have archetype guest
 * @param int $cmid         the course_module id for this peerforum instance
 * @return boolean      peerforum was converted or not
 */
function peerforum_convert_to_roles($peerforum, $peerforummodid, $teacherroles=array(),
                                $studentroles=array(), $guestroles=array(), $cmid=NULL) {

    global $CFG, $DB, $OUTPUT;

    if (!isset($peerforum->open) && !isset($peerforum->assesspublic)) {
        // We assume that this peerforum has already been converted to use the
        // Roles System. Columns peerforum.open and peerforum.assesspublic get dropped
        // once the peerforum module has been upgraded to use Roles.
        return false;
    }

    if ($peerforum->type == 'teacher') {

        // Teacher peerforums should be converted to normal peerforums that
        // use the Roles System to implement the old behavior.
        // Note:
        //   Seems that teacher peerforums were never backed up in 1.6 since they
        //   didn't have an entry in the course_modules table.
        require_once($CFG->dirroot.'/course/lib.php');

        if ($DB->count_records('peerforum_discussions', array('peerforum' => $peerforum->id)) == 0) {
            // Delete empty teacher peerforums.
            $DB->delete_records('peerforum', array('id' => $peerforum->id));
        } else {
            // Create a course module for the peerforum and assign it to
            // section 0 in the course.
            $mod = new stdClass();
            $mod->course = $peerforum->course;
            $mod->module = $peerforummodid;
            $mod->instance = $peerforum->id;
            $mod->section = 0;
            $mod->visible = 0;     // Hide the peerforum
            $mod->visibleold = 0;  // Hide the peerforum
            $mod->groupmode = 0;

            if (!$cmid = add_course_module($mod)) {
                print_error('cannotcreateinstanceforteacher', 'peerforum');
            } else {
                $sectionid = course_add_cm_to_section($peerforum->course, $mod->coursemodule, 0);
            }

            // Change the peerforum type to general.
            $peerforum->type = 'general';
            $DB->update_record('peerforum', $peerforum);

            $context = context_module::instance($cmid);

            // Create overrides for default student and guest roles (prevent).
            foreach ($studentroles as $studentrole) {
                assign_capability('mod/peerforum:viewdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:viewhiddentimedposts', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:replypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:viewratingpeer', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:viewanyratingpeer', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:ratepeer', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:createattachment', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:deleteownpost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:deleteanypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:splitdiscussions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:movediscussions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:editanypost', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:viewqandawithoutposting', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:viewsubscribers', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:managesubscriptions', CAP_PREVENT, $studentrole->id, $context->id);
                assign_capability('mod/peerforum:postwithoutthrottling', CAP_PREVENT, $studentrole->id, $context->id);
            }
            foreach ($guestroles as $guestrole) {
                assign_capability('mod/peerforum:viewdiscussion', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:viewhiddentimedposts', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:startdiscussion', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:replypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:viewratingpeer', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:viewanyratingpeer', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:ratepeer', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:createattachment', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:deleteownpost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:deleteanypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:splitdiscussions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:movediscussions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:editanypost', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:viewqandawithoutposting', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:viewsubscribers', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:managesubscriptions', CAP_PREVENT, $guestrole->id, $context->id);
                assign_capability('mod/peerforum:postwithoutthrottling', CAP_PREVENT, $guestrole->id, $context->id);
            }
        }
    } else {
        // Non-teacher peerforum.

        if (empty($cmid)) {
            // We were not given the course_module id. Try to find it.
            if (!$cm = get_coursemodule_from_instance('peerforum', $peerforum->id)) {
                echo $OUTPUT->notification('Could not get the course module for the peerforum');
                return false;
            } else {
                $cmid = $cm->id;
            }
        }
        $context = context_module::instance($cmid);

        // $peerforum->open defines what students can do:
        //   0 = No discussions, no replies
        //   1 = No discussions, but replies are allowed
        //   2 = Discussions and replies are allowed
        switch ($peerforum->open) {
            case 0:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                    assign_capability('mod/peerforum:replypost', CAP_PREVENT, $studentrole->id, $context->id);
                }
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:startdiscussion', CAP_PREVENT, $studentrole->id, $context->id);
                    assign_capability('mod/peerforum:replypost', CAP_ALLOW, $studentrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:startdiscussion', CAP_ALLOW, $studentrole->id, $context->id);
                    assign_capability('mod/peerforum:replypost', CAP_ALLOW, $studentrole->id, $context->id);
                }
                break;
        }

        // $peerforum->assessed defines whether peerforum ratingpeer is turned
        // on (1 or 2) and who can ratepeer posts:
        //   1 = Everyone can ratepeer posts
        //   2 = Only teachers can ratepeer posts
        switch ($peerforum->assessed) {
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:ratepeer', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/peerforum:ratepeer', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:ratepeer', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/peerforum:ratepeer', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }

        // $peerforum->assesspublic defines whether students can see
        // everybody's ratingpeers:
        //   0 = Students can only see their own ratingpeers
        //   1 = Students can see everyone's ratingpeers
        switch ($peerforum->assesspublic) {
            case 0:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:viewanyratingpeer', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/peerforum:viewanyratingpeer', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('mod/peerforum:viewanyratingpeer', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('mod/peerforum:viewanyratingpeer', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }

        if (empty($cm)) {
            $cm = $DB->get_record('course_modules', array('id' => $cmid));
        }

        // $cm->groupmode:
        // 0 - No groups
        // 1 - Separate groups
        // 2 - Visible groups
        switch ($cm->groupmode) {
            case 0:
                break;
            case 1:
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
            case 2:
                foreach ($studentroles as $studentrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $studentrole->id, $context->id);
                }
                foreach ($teacherroles as $teacherrole) {
                    assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $teacherrole->id, $context->id);
                }
                break;
        }
    }
    return true;
}

/**
 * Returns array of peerforum layout modes
 *
 * @return array
 */
function peerforum_get_layout_modes() {
    return array (PEERFORUM_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'peerforum'),
                  PEERFORUM_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'peerforum'),
                  PEERFORUM_MODE_THREADED   => get_string('modethreaded', 'peerforum'),
                  PEERFORUM_MODE_NESTED     => get_string('modenested', 'peerforum'));
}

/**
 * Returns array of peerforum types chooseable on the peerforum editing form
 *
 * @return array
 */
function peerforum_get_peerforum_types() {
    return array ('general'  => get_string('generalpeerforum', 'peerforum'),
                  'eachuser' => get_string('eachuserpeerforum', 'peerforum'),
                  'single'   => get_string('singlepeerforum', 'peerforum'),
                  'qanda'    => get_string('qandapeerforum', 'peerforum'),
                  'blog'     => get_string('blogpeerforum', 'peerforum'));
}

/**
 * Returns array of all peerforum layout modes
 *
 * @return array
 */
function peerforum_get_peerforum_types_all() {
    return array ('news'     => get_string('namenews','peerforum'),
                  'social'   => get_string('namesocial','peerforum'),
                  'general'  => get_string('generalpeerforum', 'peerforum'),
                  'eachuser' => get_string('eachuserpeerforum', 'peerforum'),
                  'single'   => get_string('singlepeerforum', 'peerforum'),
                  'qanda'    => get_string('qandapeerforum', 'peerforum'),
                  'blog'     => get_string('blogpeerforum', 'peerforum'));
}

/**
 * Returns array of peerforum open modes
 *
 * @return array
 */
function peerforum_get_open_modes() {
    return array ('2' => get_string('openmode2', 'peerforum'),
                  '1' => get_string('openmode1', 'peerforum'),
                  '0' => get_string('openmode0', 'peerforum') );
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function peerforum_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'mod/peerforum:viewratingpeer', 'mod/peerforum:viewanyratingpeer', 'mod/peerforum:viewallratingpeer', 'mod/peerforum:rateratingpeer');
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $peerforumnode The node to add module settings to
 */
function peerforum_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $peerforumnode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $peerforumobject = $DB->get_record("peerforum", array("id" => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage  = has_capability('mod/peerforum:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = peerforum_get_forcesubscribed($peerforumobject);
    $cansubscribe = ($activeenrolled && $subscriptionmode != PEERFORUM_FORCESUBSCRIBE && ($subscriptionmode != PEERFORUM_DISALLOWSUBSCRIBE || $canmanage));

    if ($canmanage) {
        $mode = $peerforumnode->add(get_string('subscriptionmode', 'peerforum'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'peerforum'), new moodle_url('/mod/peerforum/subscribe.php', array('id'=>$peerforumobject->id, 'mode'=>PEERFORUM_CHOOSESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode->add(get_string("subscriptionforced", "peerforum"), new moodle_url('/mod/peerforum/subscribe.php', array('id'=>$peerforumobject->id, 'mode'=>PEERFORUM_FORCESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string("subscriptionauto", "peerforum"), new moodle_url('/mod/peerforum/subscribe.php', array('id'=>$peerforumobject->id, 'mode'=>PEERFORUM_INITIALSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode->add(get_string('subscriptiondisabled', 'peerforum'), new moodle_url('/mod/peerforum/subscribe.php', array('id'=>$peerforumobject->id, 'mode'=>PEERFORUM_DISALLOWSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case PEERFORUM_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                break;
            case PEERFORUM_FORCESUBSCRIBE : // 1
                $forceforever->action = null;
                $forceforever->add_class('activesetting');
                break;
            case PEERFORUM_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                break;
            case PEERFORUM_DISALLOWSUBSCRIBE : // 3
                $disallowchoice->action = null;
                $disallowchoice->add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case PEERFORUM_CHOOSESUBSCRIBE : // 0
                $notenode = $peerforumnode->add(get_string('subscriptionoptional', 'peerforum'));
                break;
            case PEERFORUM_FORCESUBSCRIBE : // 1
                $notenode = $peerforumnode->add(get_string('subscriptionforced', 'peerforum'));
                break;
            case PEERFORUM_INITIALSUBSCRIBE : // 2
                $notenode = $peerforumnode->add(get_string('subscriptionauto', 'peerforum'));
                break;
            case PEERFORUM_DISALLOWSUBSCRIBE : // 3
                $notenode = $peerforumnode->add(get_string('subscriptiondisabled', 'peerforum'));
                break;
        }
    }

    if ($cansubscribe) {
        if (peerforum_is_subscribed($USER->id, $peerforumobject)) {
            $linktext = get_string('unsubscribe', 'peerforum');
        } else {
            $linktext = get_string('subscribe', 'peerforum');
        }
        $url = new moodle_url('/mod/peerforum/subscribe.php', array('id'=>$peerforumobject->id, 'sesskey'=>sesskey()));
        $peerforumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
    }

    if (has_capability('mod/peerforum:viewsubscribers', $PAGE->cm->context)){
        $url = new moodle_url('/mod/peerforum/subscribers.php', array('id'=>$peerforumobject->id));
        $peerforumnode->add(get_string('showsubscribers', 'peerforum'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && peerforum_tp_can_track_peerforums($peerforumobject)) { // keep tracking info for users with suspended enrolments
        if ($peerforumobject->trackingtype == PEERFORUM_TRACKING_OPTIONAL
                || ((!$CFG->peerforum_allowforcedreadtracking) && $peerforumobject->trackingtype == PEERFORUM_TRACKING_FORCED)) {
            if (peerforum_tp_is_tracked($peerforumobject)) {
                $linktext = get_string('notrackpeerforum', 'peerforum');
            } else {
                $linktext = get_string('trackpeerforum', 'peerforum');
            }
            $url = new moodle_url('/mod/peerforum/settracking.php', array(
                    'id' => $peerforumobject->id,
                    'sesskey' => sesskey(),
                ));
            $peerforumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->peerforum_enablerssfeeds);

    if ($enablerssfeeds && $peerforumobject->rsstype && $peerforumobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($peerforumobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions','peerforum');
        } else {
            $string = get_string('rsssubscriberssposts','peerforum');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, "mod_peerforum", $peerforumobject->id));
        $peerforumnode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Abstract class used by peerforum subscriber selection controls
 * @package mod-peerforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class peerforum_subscriber_selector_base extends user_selector_base {

    /**
     * The id of the peerforum this selector is being used for
     * @var int
     */
    protected $peerforumid = null;
    /**
     * The context of the peerforum this selector is being used for
     * @var object
     */
    protected $context = null;
    /**
     * The id of the current group
     * @var int
     */
    protected $currentgroup = null;

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        $options['accesscontext'] = $options['context'];
        parent::__construct($name, $options);
        if (isset($options['context'])) {
            $this->context = $options['context'];
        }
        if (isset($options['currentgroup'])) {
            $this->currentgroup = $options['currentgroup'];
        }
        if (isset($options['peerforumid'])) {
            $this->peerforumid = $options['peerforumid'];
        }
    }

    /**
     * Returns an array of options to seralise and store for searches
     *
     * @return array
     */
    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] =  substr(__FILE__, strlen($CFG->dirroot.'/'));
        $options['context'] = $this->context;
        $options['currentgroup'] = $this->currentgroup;
        $options['peerforumid'] = $this->peerforumid;
        return $options;
    }

}

/**
 * A user selector control for potential subscribers to the selected peerforum
 * @package mod-peerforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peerforum_potential_subscriber_selector extends peerforum_subscriber_selector_base {
    /**
     * If set to true EVERYONE in this course is force subscribed to this peerforum
     * @var bool
     */
    protected $forcesubscribed = false;
    /**
     * Can be used to store existing subscribers so that they can be removed from
     * the potential subscribers list
     */
    protected $existingsubscribers = array();

    /**
     * Constructor method
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
        if (isset($options['forcesubscribed'])) {
            $this->forcesubscribed=true;
        }
    }

    /**
     * Returns an arary of options for this control
     * @return array
     */
    protected function get_options() {
        $options = parent::get_options();
        if ($this->forcesubscribed===true) {
            $options['forcesubscribed']=1;
        }
        return $options;
    }

    /**
     * Finds all potential users
     *
     * Potential subscribers are all enroled users who are not already subscribed.
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        $whereconditions = array();
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        if ($wherecondition) {
            $whereconditions[] = $wherecondition;
        }

        if (!$this->forcesubscribed) {
            $existingids = array();
            foreach ($this->existingsubscribers as $group) {
                foreach ($group as $user) {
                    $existingids[$user->id] = 1;
                }
            }
            if ($existingids) {
                list($usertest, $userparams) = $DB->get_in_or_equal(
                        array_keys($existingids), SQL_PARAMS_NAMED, 'existing', false);
                $whereconditions[] = 'u.id ' . $usertest;
                $params = array_merge($params, $userparams);
            }
        }

        if ($whereconditions) {
            $wherecondition = 'WHERE ' . implode(' AND ', $whereconditions);
        }

        list($esql, $eparams) = get_enrolled_sql($this->context, '', $this->currentgroup, true);
        $params = array_merge($params, $eparams);

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';

        $sql = " FROM {user} u
                 JOIN ($esql) je ON je.id = u.id
                      $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        // If not, show them.
        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }

        if ($this->forcesubscribed) {
            return array(get_string("existingsubscribers", 'peerforum') => $availableusers);
        } else {
            return array(get_string("potentialsubscribers", 'peerforum') => $availableusers);
        }
    }

    /**
     * Sets the existing subscribers
     * @param array $users
     */
    public function set_existing_subscribers(array $users) {
        $this->existingsubscribers = $users;
    }

    /**
     * Sets this peerforum as force subscribed or not
     */
    public function set_force_subscribed($setting=true) {
        $this->forcesubscribed = true;
    }
}

/**
 * User selector control for removing subscribed users
 * @package mod-peerforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peerforum_existing_subscriber_selector extends peerforum_subscriber_selector_base {

    /**
     * Finds all subscribed users
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['peerforumid'] = $this->peerforumid;

        // only active enrolled or everybody on the frontpage
        list($esql, $eparams) = get_enrolled_sql($this->context, '', $this->currentgroup, true);
        $fields = $this->required_fields_sql('u');
        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $params = array_merge($params, $eparams, $sortparams);

        $subscribers = $DB->get_records_sql("SELECT $fields
                                               FROM {user} u
                                               JOIN ($esql) je ON je.id = u.id
                                               JOIN {peerforum_subscriptions} s ON s.userid = u.id
                                              WHERE $wherecondition AND s.peerforum = :peerforumid
                                           ORDER BY $sort", $params);

        return array(get_string("existingsubscribers", 'peerforum') => $subscribers);
    }

}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function peerforum_cm_info_view(cm_info $cm) {
    global $CFG;

    if (peerforum_tp_can_track_peerforums()) {
        if ($unread = peerforum_tp_count_peerforum_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->get_url() . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'peerforum');
            } else {
                $out .= get_string('unreadpostsnumber', 'peerforum', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function peerforum_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $peerforum_pagetype = array(
        'mod-peerforum-*'=>get_string('page-mod-peerforum-x', 'peerforum'),
        'mod-peerforum-view'=>get_string('page-mod-peerforum-view', 'peerforum'),
        'mod-peerforum-discuss'=>get_string('page-mod-peerforum-discuss', 'peerforum')
    );
    return $peerforum_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a peerforum.
 *
 * @global moodle_database $DB The database connection
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 */
function peerforum_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null) {
    global $DB;

    // If we are only after discussions we need only look at the peerforum_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the peerforum_posts table.
    if (!$discussionsonly) {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {peerforum_discussions} fd
                         JOIN {peerforum_posts} fp ON fp.discussion = fd.id
                        WHERE fp.userid = :userid )";
    } else {
        $subquery= "(SELECT DISTINCT fd.course
                         FROM {peerforum_discussions} fd
                        WHERE fd.userid = :userid )";
    }

    $params = array('userid' => $user->id);

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a peerforum will be returned.
    $sql = "SELECT c.* $ctxselect
            FROM {course} c
            $ctxjoin
            WHERE c.id IN ($subquery)";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
    }
    return $courses;
}

/**
 * Gets all of the peerforums a user has posted in for one or more courses.
 *
 * @global moodle_database $DB
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only peerforums where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of peerforums the user has posted within in the provided courses
 */
function peerforum_get_peerforums_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null) {
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course '.$coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
    $params['peerforum'] = 'peerforum';

    if ($discussionsonly) {
        $join = 'JOIN {peerforum_discussions} ff ON ff.peerforum = f.id';
    } else {
        $join = 'JOIN {peerforum_discussions} fd ON fd.peerforum = f.id
                 JOIN {peerforum_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {peerforum} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {peerforum} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :peerforum
                 {$coursewhere}";

    $coursepeerforums = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $coursepeerforums;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and peerforum is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @global moodle_database $DB
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->peerforums: An array of peerforums relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 */
function peerforum_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->peerforums = array();  // The peerforums that the current user can access that contain posts
    $return->posts = array();   // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'peerforum');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'peerforum');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'peerforum');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B peerforum. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
              && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot."/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the peerforum accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the peerforums that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which peerforums we can search by testing accessibility.
    $peerforums = peerforum_get_peerforums_user_posted_in($user, array_keys($return->courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $peerforumsearchwhere = array();
    // Will be used to store the where condition params for the search
    $peerforumsearchparams = array();
    // Will record peerforums where the user can freely access everything
    $peerforumsearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the peerforums the user has posted in
    // and providing the current user can access the peerforum create a search condition
    // for the peerforum to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the peerforums
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['peerforum'])) {
            // hmmm, no peerforums? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('peerforum') as $peerforumid => $cm) {
            if (!$cm->uservisible or !isset($peerforums[$peerforumid])) {
                continue;
            }
            // Get the peerforum in question
            $peerforum = $peerforums[$peerforumid];

            // This is needed for functionality later on in the peerforum code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link peerforum_print_post()}.
            $peerforum->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $peerforum->cm->$key = $value;
            }

            // Check that either the current user can view the peerforum, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/peerforum:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/peerforum:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain peerforum specific where clauses
            $peerforumsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps'.$peerforumid.'_');
                    $peerforumsearchparams = array_merge($peerforumsearchparams, $groupid_params);
                    $peerforumsearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->peerforum_enabletimedposts) && !has_capability('mod/peerforum:viewhiddentimedposts', $cm->context)) {
                    $peerforumsearchselect[] = "(d.userid = :userid{$peerforumid} OR (d.timestart < :timestart{$peerforumid} AND (d.timeend = 0 OR d.timeend > :timeend{$peerforumid})))";
                    $peerforumsearchparams['userid'.$peerforumid] = $user->id;
                    $peerforumsearchparams['timestart'.$peerforumid] = $now;
                    $peerforumsearchparams['timeend'.$peerforumid] = $now;
                }

                // qanda access
                if ($peerforum->type == 'qanda' && !has_capability('mod/peerforum:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda peerforum.
                    $discussionspostedin = peerforum_discussions_user_has_posted_in($peerforum->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        $peerforumonlydiscussions = array();  // Holds discussion ids for the discussions the user is allowed to see in this peerforum.
                        foreach ($discussionspostedin as $d) {
                            $peerforumonlydiscussions[] = $d->id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($peerforumonlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$peerforumid.'_');
                        $peerforumsearchparams = array_merge($peerforumsearchparams, $discussionid_params);
                        $peerforumsearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $peerforumsearchselect[] = "p.parent = 0";
                    }

                }

                if (count($peerforumsearchselect) > 0) {
                    $peerforumsearchwhere[] = "(d.peerforum = :peerforum{$peerforumid} AND ".implode(" AND ", $peerforumsearchselect).")";
                    $peerforumsearchparams['peerforum'.$peerforumid] = $peerforumid;
                } else {
                    $peerforumsearchfullaccess[] = $peerforumid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $peerforumsearchfullaccess[] = $peerforumid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any peerforums where
    // the user has full access then we just return the default.
    if (empty($peerforumsearchwhere) && empty($peerforumsearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access peerforums.
    if (count($peerforumsearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($peerforumsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $peerforumsearchparams = array_merge($peerforumsearchparams, $fullidparams);
        $peerforumsearchwhere[] = "(d.peerforum $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we peerforum_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.peerforum, d.name AS discussionname, '.$userfields.' ';
    $wheresql = implode(" OR ", $peerforumsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND ('.$wheresql.')';
        }
    }

    $sql = "FROM {peerforum_posts} p
            JOIN {peerforum_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $peerforumsearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql.$sql, $peerforumsearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql.$sql.$orderby, $peerforumsearchparams, $limitfrom, $limitnum);

    // We need to build an array of peerforums for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these peerforums posts. Given we have the peerforums already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->peerforum, $return->peerforums)) {
            $return->peerforums[$post->peerforum] = $peerforums[$post->peerforum];
        }
    }

    return $return;
}

/**
 * Set the per-peerforum maildigest option for the specified user.
 *
 * @param stdClass $peerforum The peerforum to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function peerforum_set_user_maildigest($peerforum, $maildigest, $user = null) {
    global $DB, $USER;

    if (is_number($peerforum)) {
        $peerforum = $DB->get_record('peerforum', array('id' => $peerforum));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course  = $DB->get_record('course', array('id' => $peerforum->course), '*', MUST_EXIST);
    $cm      = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this peerforum.
    require_capability('mod/peerforum:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = peerforum_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_peerforum');
    }

    // Attempt to retrieve any existing peerforum digest record.
    $subscription = $DB->get_record('peerforum_digests', array(
        'userid' => $user->id,
        'peerforum' => $peerforum->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('peerforum_digests', array('peerforum' => $peerforum->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('peerforum_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->peerforum = $peerforum->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('peerforum_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified peerforum.
 *
 * @param Array $digests An array of peerforums and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $peerforumid The ID of the peerforum to check.
 * @return int The calculated maildigest setting for this user and peerforum.
 */
function peerforum_get_user_maildigest_bulk($digests, $user, $peerforumid) {
    if (isset($digests[$peerforumid]) && isset($digests[$peerforumid][$user->id])) {
        $maildigest = $digests[$peerforumid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Determine the current context if one was not already specified.
 *
 * If a context of type context_module is specified, it is immediately
 * returned and not checked.
 *
 * @param int $peerforumid The ID of the peerforum
 * @param context_module $context The current context.
 * @return context_module The context determined
 */
function peerforum_get_context($peerforumid, $context = null) {
    global $PAGE;

    if (!$context || !($context instanceof context_module)) {
        // Find out peerforum context. First try to take current page context to save on DB query.
        if ($PAGE->cm && $PAGE->cm->modname === 'peerforum' && $PAGE->cm->instance == $peerforumid
                && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id) {
            $context = $PAGE->context;
        } else {
            $cm = get_coursemodule_from_instance('peerforum', $peerforumid);
            $context = \context_module::instance($cm->id);
        }
    }

    return $context;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $peerforum   peerforum object
 * @param  stdClass $course  course object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 * @since Moodle 2.9
 */
function peerforum_view($peerforum, $course, $cm, $context) {

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger course_module_viewed event.

    $params = array(
        'context' => $context,
        'objectid' => $peerforum->id
    );

    $event = \mod_peerforum\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('peerforum', $peerforum);
    $event->trigger();
}

/**
 * Trigger the discussion viewed event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $peerforum      peerforum object
 * @param  stdClass $discussion discussion object
 * @since Moodle 2.9
 */
function peerforum_discussion_view($modcontext, $peerforum, $discussion) {
    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
    );

    $event = \mod_peerforum\event\discussion_viewed::create($params);
    $event->add_record_snapshot('peerforum_discussions', $discussion);
    $event->add_record_snapshot('peerforum', $peerforum);
    $event->trigger();
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function peerforum_get_user_digest_options($user = null) {
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0']  = get_string('emaildigestoffshort', 'mod_peerforum');
    $digestoptions['1']  = get_string('emaildigestcompleteshort', 'mod_peerforum');
    $digestoptions['2']  = get_string('emaildigestsubjectsshort', 'mod_peerforum');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_peerforum',
            $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function mod_peerforum_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (isguestuser($user)) {
        // The guest user cannot post, so it is not possible to view any posts.
        // May as well just bail aggressively here.
        return false;
    }
    $postsurl = new moodle_url('/mod/peerforum/user.php', array('id' => $user->id));
    if (!empty($course)) {
        $postsurl->param('course', $course->id);
    }
    $string = get_string('peerforumposts', 'mod_peerforum');
    $node = new core_user\output\myprofile\node('miscellaneous', 'peerforumposts', $string, null, $postsurl);
    $tree->add_node($node);

    $discussionssurl = new moodle_url('/mod/peerforum/user.php', array('id' => $user->id, 'mode' => 'discussions'));
    if (!empty($course)) {
        $discussionssurl->param('course', $course->id);
    }
    $string = get_string('myprofileotherdis', 'mod_peerforum');
    $node = new core_user\output\myprofile\node('miscellaneous', 'peerforumdiscussions', $string, null,
        $discussionssurl);
    $tree->add_node($node);

    return true;
}
