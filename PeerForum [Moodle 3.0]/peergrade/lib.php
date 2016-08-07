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
 * A class representing a single peergrade and containing some static methods for manipulating peergrades
 *
 * @package    core_peergrade
 * @subpackage peergrade
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The peergrade class represents a single peergrade by a single user
 *
 * @package   core_peergrade
 * @category  peergrade
 * @copyright 2010 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */

 /**
  * Additional functions for peergrading in PeerForums
  *
  * @package    core_peergrade
  * @author     2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */


 define('PEERGRADE_UNSET_PEERGRADE', -999);
 define('PEERGRADE_UNSET_FEEDBACK', '');
 define ('PEERGRADE_AGGREGATE_NONE', 0); // No peergrades.
 define ('PEERGRADE_AGGREGATE_AVERAGE', 1);
 define ('PEERGRADE_AGGREGATE_COUNT', 2);
 define ('PEERGRADE_AGGREGATE_MAXIMUM', 3);
 define ('PEERGRADE_AGGREGATE_MINIMUM', 4);
 define ('PEERGRADE_AGGREGATE_SUM', 5);
 define ('PEERGRADE_DEFAULT_SCALE', 5);
 define ('UNSET_STUDENT', -1);
 define ('UNSET_STUDENT_SELECT', -2);

 if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
 {
     require_once($CFG->dirroot.'/mod/peerforum/lib.php');
 } else {
     return;
 }

function update_post_peergraders($postid, $peergraders){
     global $DB;

     $post = $DB->get_record('peerforum_posts', array('id' => $postid));
     $post_graders = $post->peergraders;

     $post_graders = explode(';', $post_graders);
     $post_graders = array_filter($post_graders);


     $peergraders = array_filter($peergraders);

     foreach ($peergraders as $key => $value) {
         array_push($post_graders, $peergraders[$key]);
     }

     $post_graders = array_filter($post_graders);
     $post_graders_upd = implode(';', $post_graders);

     $data = new stdClass();
     $data->id = $postid;
     $data->peergraders = $post_graders_upd;

     $DB->update_record('peerforum_posts', $data);

 }

 function update_graders($array_peergraders, $postid, $courseid) {
     global $DB;

     foreach($array_peergraders as $i => $value){
         $userid = $array_peergraders[$i];
         $existing_info = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser'=>$userid));

         $existing_posts = $existing_info->poststopeergrade;

         $data = new stdClass;
         $data->courseid = $courseid;
         $data->iduser = $userid;

         if(empty($existing_info)){
             $data->poststopeergrade = $postid;
             $data->postspeergradedone = NULL;
             $data->postsblocked = NULL;
             $data->postsexpired = NULL;

             $data->numpostsassigned = 1;

             $DB->insert_record('peerforum_peergrade_users', $data);
         }
         else{
             $array_posts = array();
             $posts = explode(';', $existing_posts);
             $posts = array_filter($posts);

             adjust_database();

             foreach($posts as $post => $value){
                 array_push($array_posts, $posts[$post]);
             }

             array_push($array_posts, $postid);


             $array_posts = array_filter($array_posts);
             $posts = implode(';', $array_posts);

             $data->poststopeergrade = $posts;
             $data->id = $existing_info->id;

             $DB->update_record('peerforum_peergrade_users', $data);
         }
     }
  }

 function assign_random($courseid, $array_users, $postauthor, $postid, $peerid){
     global $DB;
     $array_peergraders = array();

     $peers = $DB->get_record('peerforum_posts', array('id' => $postid))->peergraders;
     $peers = explode(';', $peers);
     $peers = array_filter($peers);


     if(in_array($peerid, $array_users)){
         $keyy = array_search($peerid, $array_users);
         unset($array_users[$keyy]);
         $array_users = array_filter($array_users);
     }

     if(in_array($postauthor, $array_users)){
         $keyy = array_search($postauthor, $array_users);
         unset($array_users[$keyy]);
         $array_users = array_filter($array_users);
     }

     $array_users = array_values($array_users);
     $count_peers = count($array_users);

     $random = rand(0, $count_peers-1);

     if(!empty($array_users)){

         $peer = $array_users[$random];

         $conflicts = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));

         $conflict = 0;

         foreach ($conflicts as $id => $value) {
             $students = explode(';', $conflicts[$id]->idstudents);
             $students = array_filter($students);

             if(in_array(-1, $students)){
                 $a = array_search(-1, $students);
                 unset($students[$a]);
                 $sts = implode(';', $students);
                 $data = new stdClass();
                 $data->id = $conflicts[$id]->id;
                 $data->idstudents= $sts;
                 $DB->update_record('peerforum_peergrade_conflict', $data);
             }

             if(in_array($peer, $students) && in_array($postauthor, $students)){
                 $conflict = 1;
                 break;
             }
         }

         if($conflict == 0){
             $key = array_search($peer, $array_users);
             unset($array_users[$key]);
             $array_users_upd = array_filter($array_users);

             assign_random($courseid, $array_users_upd, $postauthor, $postid, $peerid);
         }
         if ($conflict == 1){
             $key = array_search($peer, $array_users);
             unset($array_users[$key]);
             $array_users_upd = array_filter($array_users);

             assign_random($courseid, $array_users_upd, $postauthor, $postid, $peerid);
         }
 }

     update_graders($array_peergraders, $postid, $courseid);
     update_post_peergraders($postid, $array_peergraders);
 }

 function assign_one_peergrader($postid, $courseid, $peerid){
     global $DB, $PAGE;

     $post = $DB->get_record('peerforum_posts', array('id' => $postid));
     $postauthor = $post->userid;

     $enroledusers = get_students_enroled($courseid);

     if(in_array($postauthor, $enroledusers)){
         $key = array_search($postauthor, $enroledusers);
         unset($enroledusers[$key]);
         $enroledusers = array_filter($enroledusers);
     }

     $array_users = array();

     foreach($enroledusers as $id => $value){
         array_push($array_users, $id);
     }

     $count_peers = count($array_users);
     assign_random($courseid, $array_users, $postauthor, $postid, $peerid);
 }

class peergrade implements renderable {

    /**
     * @var stdClass The context in which this peergrade exists
     */
    public $context;

    /**
     * @var string The component using peergrades. For example "mod_forum"
     */
    public $component;

    /**
     * @var string The peergrade area to associate this peergrade with
     *             This allows a plugin to ratepeer more than one thing by specifying different peergrade areas
     */
    public $peergradearea = null;

    /**
     * @var int The id of the item (forum post, glossary item etc) being ratedpeer
     */
    public $itemid;

    /**
     * @var int The id peergradescale (1-5, 0-100) that was in use when the peergrade was submitted
     */
    public $peergradescaleid;

    /**
     * @var int The id of the user who submitted the peergrade
     */
    public $userid;

    /**
     * @var stdclass settings for this peergrade. Necessary to render the peergrade.
     */
    public $settings;

    /**
     * @var int The Id of this peergrade within the peergrade table. This is only set if the peergrade already exists
     */
    public $id = null;

    /**
     * @var int The aggregate of the combined peergrades for the associated item. This is only set if the peergrade already exists
     */
    public $aggregate = null;

    /**
     * @var int The total number of peergrades for the associated item. This is only set if the peergrade already exists
     */
    public $count = 0;

    /**
     * @var int The peergrade the associated user gave the associated item. This is only set if the peergrade already exists
     */
    public $peergrade = null;

    /**
     * @var int The time the associated item was created
     */
    public $itemtimecreated = null;

    /**
     * @var int The id of the user who submitted the peergrade
     */
    public $itemuserid = null;

    /**
     * @var int The feedback of a peergraded post
     */
    public $feedback = null;

    /**
     * Constructor.
     *
     * @param stdClass $options {
     *            context => context context to use for the peergrade [required]
     *            component => component using peergrades ie mod_forum [required]
     *            peergradearea => peergradearea to associate this peergrade with [required]
     *            itemid  => int the id of the associated item (forum post, glossary item etc) [required]
     *            peergradescaleid => int The peergradescale in use when the peergrade was submitted [required]
     *            userid  => int The id of the user who submitted the peergrade [required]
     *            settings => Settings for the peergrade object [optional]
     *            id => The id of this peergrade (if the peergrade is from the db) [optional]
     *            aggregate => The aggregate for the peergrade [optional]
     *            count => The number of peergrades [optional]
     *            peergrade => The peergrade given by the user [optional]
     * }
     */
    public function __construct($options) {
        $this->context = $options->context;
        $this->component = $options->component;
        $this->peergradearea = $options->peergradearea;
        $this->itemid = $options->itemid;
        $this->userid = $options->userid;
        $this->peergradescaleid = $options->peergradescaleid;

        if (isset($options->settings)) {
            $this->settings = $options->settings;
        }
        if (isset($options->id)) {
            $this->id = $options->id;
        }
        if (isset($options->aggregate)) {
            $this->aggregate = $options->aggregate;
        }
        if (isset($options->count)) {
            $this->count = $options->count;
        }
        if (isset($options->peergrade)) {
            $this->peergrade = $options->peergrade;
        }
        if (isset($options->feedback)) {
            $this->feedback = $options->feedback;
        }
        if (isset($options->peergraders)) {
            $this->peergraders = $options->peergraders;
        }

    }

    /**
     * Update this peergrade in the database
     *
     * @param int $peergrade the integer value of this peergrade
     */
    public function update_peergrade($peergrade, $feedback=null) {
        global $DB;

        $time = time();

        $data = new stdClass;
        $data->peergrade       = $peergrade;
        $data->timemodified = $time;
        $data->feedback = $feedback;

        $item = new stdclass();
        $item->id = $this->itemid;
        $items = array($item);

        $peergradeoptions = new stdClass;
        $peergradeoptions->context = $this->context;
        $peergradeoptions->component = $this->component;
        $peergradeoptions->peergradearea = $this->peergradearea;
        $peergradeoptions->items = $items;
        $peergradeoptions->aggregate = PEERGRADE_AGGREGATE_AVERAGE; // We dont actually care what aggregation method is applied.
        $peergradeoptions->peergradescaleid = $this->peergradescaleid;
        $peergradeoptions->userid = $this->userid;
        $peergradeoptions->feedback = $feedback;


        $pm = new peergrade_manager();
        $items = $pm->get_peergrades($peergradeoptions);
        $firstitem = $items[0]->peergrade;


        if(!empty($firstitem->id)){
            $peergrader = $pm->get_post_peergrader($firstitem->id);
        }

            //obj does not exist in DB
            if (empty($firstitem->id)) {
                // Insert a new peergrade.
                $data->contextid    = $this->context->id;
                $data->component    = $this->component;
                $data->peergradearea = $this->peergradearea;
                $data->peergrade     = $peergrade;
                $data->peergradescaleid  = $this->peergradescaleid;
                $data->peergradescale  = 0;
                $data->userid       = $this->userid;
                $data->itemid       = $this->itemid;
                $data->feedback       = $feedback;
                $data->timecreated  = $time;
                $data->timemodified = $time;
                $data->peergraderid = 0;
                $data->scaleid      = 0;

                $DB->insert_record('peerforum_peergrade', $data);
            }

        if(!empty($firstitem->id) && $peergrader == $this->userid) {
            // Update the peergrade.
            $data->id  = $firstitem->id;
            $DB->update_record('peerforum_peergrade', $data);
        }
    }




    /**
     * Retreive the integer value of this peergrade
     *
     * @return int the integer value of this peergrade object
     */
    public function get_peergrade() {
        return $this->peergrade;
    }

    /**
     * Returns this peergrades aggregate value as a string.
     *
     * @return string peergrades aggregate value
     */
    public function get_aggregate_string() {

        $aggregate = $this->aggregate;

        $method = $this->settings->aggregationmethod;

        // Only display aggregate if aggregation method isn't COUNT.
        $aggregatestr = '';


        if ($aggregate && $method != PEERGRADE_AGGREGATE_COUNT) {
            if ($method != PEERGRADE_AGGREGATE_SUM && !$this->settings->peergradescale->isnumeric) {

                // Round aggregate as we're using it as an index.
                $aggregatestr .= $this->settings->peergradescale->peergradescaleitems[round($aggregate)];
            } else { // Aggregation is SUM or the peergradescale is numeric.
                $aggregatestr .= round($aggregate, 1);
            }
        }

        return $aggregatestr;
    }

    /**
     * Returns the number of peergraders per post.
     *
     * @return
     */
    public function get_num_peergrades($postid){
        global $DB;

        $sql = "SELECT p.itemid, COUNT(p.peergrade) AS countpeergraders
                  FROM {peerforum_peergrade} p
                 WHERE p.itemid = $postid AND p.feedback != ''";
        $num = $DB->get_records_sql($sql);

        $count = $num[$postid]->countpeergraders;

        return $count;

    }

    /**
     * Returns the number of peergraders per post.
     *
     * @return
     */
    public function get_time_modified($id){
        global $DB;

        $sql = "SELECT p.id, p.timemodified
                  FROM {peerforum_peergrade} p
                 WHERE p.id = $id AND p.feedback != '' OR p.feedback != NULL ";
        $num = $DB->get_records_sql($sql);

        if(!empty($num)){
            $time = $num[$id]->timemodified;
            return $time;

        }

        return;

    }

    /**
     * Returns the number of peergraders per post.
     *
     * @return
     */
    public function exists_feedback($postid){
        global $DB;

    /*    $sql = "SELECT p.itemid
                  FROM {peergrade} p
                 WHERE p.itemid = $postid AND p.feedback != ''";
        $post = $DB->get_records_sql($sql);*/

        $sql = "SELECT p.id, p.feedback, p.userid, p.peergrade, p.itemid
                FROM {peerforum_peergrade} p
                WHERE p.itemid = $postid";
            $feedback = $DB->get_records_sql($sql);

    /*    if(empty($feedback)){
            return false;
        }else {return true;}
        */
        return $feedback;

    }



    /**
     * Returns this peergrades aggregate value as a string.
     *
     * @return string peergrades aggregate value
     */
    public function get_feedback($peergrade) {

        global $DB;


        $postid = $peergrade->itemid;

        $sql = "SELECT p.itemid, p.feedback
                  FROM {peerforum_peergrade} p
                 WHERE p.itemid = $postid AND p.feedback != ''";
        $feedback = $DB->get_records_sql($sql);


         return $feedback[$postid]->feedback;

}



/**
 * Returns this peergrades aggregate value as a string.
 *
 * @return string peergrades aggregate value
 */
public function post_already_peergraded_by_user($userid, $postid, $courseid) {

    global $DB;

    $sql = "SELECT p.iduser, p.postspeergradedone
              FROM {peerforum_peergrade_users} p
             WHERE p.iduser = $userid AND p.courseid = $courseid ";
    $posts = $DB->get_records_sql($sql);

    $done = '0';
    if(!empty($posts)){
        $all_posts = explode(";", $posts[$userid]->postspeergradedone);
        $all_posts = array_filter($all_posts);

        if(in_array($postid, $all_posts)){
            $done = '1';

        } else {
            $done = '0';
        }
    }
    return $done;

}

public function post_already_peergraded($postid, $userid) {

    global $DB;

    $sql = "SELECT p.id
              FROM {peerforum_peergrade} p
              WHERE p.itemid = $postid AND p.userid=$userid";
    $post = $DB->get_records_sql($sql);

        if(!empty($post)){

            return 1;
        }
        else {
            return 0;
        }


}

/**
 * Returns this peergrades aggregate value as a string.
 *
 * @return string peergrades aggregate value
 */
public function get_time_created($postid) {

    global $DB;

    $sql = "SELECT p.id, p.created
              FROM {peerforum_posts} p
             WHERE p.id = $postid ";
    $post_time_created = $DB->get_records_sql($sql);

    return $post_time_created[$postid]->created;

}
/*
public function get_time_assigned($postid, $userid) {
    global $DB;

    $time = $DB->get_record('peerforum_time_assigned', array('postid' => $postid, 'userid' => $userid));

    if(!empty($time)){
        return $time->timeassigned;
    } else {
        return null;
    }
}
*/
public function get_time_modified_peergrade($postid) {
    global $DB;

    $time = $DB->get_record('peerforum_time_assigned', array('postid' => $postid, 'userid' => $userid));

    return $time->timemodified;
}

public function verify_end_peergrade_post($postid, $peerforum){
    global $DB;

    $finish_peergrade = $peerforum->finishpeergrade;

    if($finish_peergrade){
        $peergrades = $DB->get_records('peerforum_peergrade', array('itemid' => $postid));

        $num_peergrades = count($peergrades);

        $num_ends_peergrade = $peerforum->minpeergraders;

        if($num_peergrades == $num_ends_peergrade){
            //peergrade ends to this post
            return 1;
        } else {
            //do not end peergrade to this post
            return 0;
        }
    }
    //do not end peergrade to this post
    return 0;
}

/**
 * Returns true if the user is able to peergrade this peergrade object
 *
 * @param int $userid Current user assumed if left empty
 * @return bool true if the user is able to ratepeer this peergrade object
 */
public function can_peergrade_this_post($userid, $postid, $courseid) {
    global $DB;

    if (empty($userid)) {
        global $USER;
        $userid = $USER->id;
    }
    // You can't peergrade your item.
    if ($this->itemuserid == $userid) {
        return false;
    }

    // You can't peergrade if you don't have the system cap.
    if (!$this->settings->permissions->peergrade) {
        return false;
    }

    // You can't peergrade if the item was outside of the assessment times.
    $timestart = $this->settings->assesstimestart;
    $timefinish = $this->settings->assesstimefinish;
    $timecreated = $this->itemtimecreated;
    if (!empty($timestart) && !empty($timefinish) && ($timecreated < $timestart || $timecreated > $timefinish)) {
        return false;
    }

    $sql = "SELECT p.id, p.peergraders
              FROM {peerforum_posts} p
             WHERE p.id = $postid AND p.peergraders != ''";
    $peergraders = $DB->get_records_sql($sql);

    if(!empty($peergraders)){
        $users_can_peer = array();
        $users_can_peer = explode(';', $peergraders[$postid]->peergraders);
        $users_can_peer = array_filter($users_can_peer);

        if(in_array($userid, $users_can_peer)){
            $blocked = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser'=>$userid));
            if(!empty($blocked)){
                $posts_blocked = explode(';', $blocked->postsblocked);
                $posts_blocked = array_filter($posts_blocked);

                if(!in_array($postid, $posts_blocked)){
                    return true;
                } else {return false;}
            } else {return true;}
        }
        else {
            return false;
        }

    }else {
        return false;
    }
}

public function time_to_see_feedbacks($peerforum, $userid, $postid, $grader, $post_expired){
    global $DB, $PAGE;

    if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        if($peerforum->whenfeedback == 'always'){
            return 1;
        } else if ($peerforum->whenfeedback == 'after peergrade ends'){

            //if post expired, everyone can see
            if($peerforum->expirepeergrade == 1 && $post_expired){
                return 1;
            }

            $peergrade = $DB->get_record('peerforum_peergrade', array('itemid' => $postid, 'userid' => $userid));

            if($grader == $userid){
                return 1;
            } else {
                $count = $DB->count_records('peerforum_peergrade', array ('itemid'=>$postid));
                $min = $peerforum->minpeergraders;

                if($count >= $min){
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }
    if (has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        return 1;
    }
}

public function time_to_see_grades($peerforum, $userid, $postid, $grader, $post_expired){
    global $DB, $PAGE;

    if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        if($peerforum->whenpeergrades == 'always'){
            return 1;
        } else if ($peerforum->whenpeergrades == 'after peergrade ends'){

            //if post expired, everyone can see
            if($peerforum->expirepeergrade == 1 && $post_expired){
                return 1;
            }

            $peergrade = $DB->get_record('peerforum_peergrade', array('itemid' => $postid, 'userid' => $userid));

            if($grader == $userid){
                return 1;
            } else {
                $count = $DB->count_records('peerforum_peergrade', array ('itemid'=>$postid));
                $min = $peerforum->minpeergraders;

                if($count >= $min){
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }
    if (has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        return 1;
    }
}

public function can_see_grades($peerforum, $userid, $postid, $grader, $post_expired){
    global $DB, $PAGE;

    if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        if($peerforum->peergradesvisibility == 'onlyprofessor'){

            if($grader == $userid){
                return 1;
            } else {
                return 0;
            }
        } else {
            if($peerforum->peergradesvisibility == 'public'){
                return 1;
            }
            else if($peerforum->peergradesvisibility == 'private'){

                //if post expired, everyone can see
                if($peerforum->expirepeergrade == 1 && $post_expired){
                    return 1;
                }

                $postauthor = $DB->get_record('peerforum_posts', array('id' => $postid))->userid;

                //can see if post author
                if($postauthor == $userid){
                    return 1;
                }
                else if(!empty($grader)){
                    //can see if peergrade author
                    if($grader == $userid){
                        return 1;
                    } else {
                        return 0;
                    }
                }
            } else {
                return 0;
            }
        }
    }
    if (has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        return 1;
    }
}

public function can_see_feedbacks($peerforum, $userid, $postid, $grader, $post_expired){
    global $DB, $PAGE;

    if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){

        if($peerforum->feedbackvisibility == 'onlyprofessor'){
            $peergrade = $DB->get_record('peerforum_peergrade', array('itemid' => $postid, 'userid' => $userid));

            if($grader == $userid){
                return 1;
            } else {
                return 0;
            }
        } else {
            if($peerforum->feedbackvisibility == 'public'){
                return 1;
            }
            else if($peerforum->feedbackvisibility == 'private'){

                //if post expired, everyone can see
                if($peerforum->expirepeergrade == 1 && $post_expired){
                    return 1;
                }

                $postauthor = $DB->get_record('peerforum_posts', array('id' => $postid))->userid;

                //can see if post author
                if($postauthor == $userid){
                    return 1;
                }
                //can see if peergrade author
                else if($grader == $userid){
                    return 1;
                }
                else {
                    return 0;
                }
            } else {
                return 0;
            }
        }
    }
    if (has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
        return 1;
    }
}

public function verify_exclusivity($postauthor, $grader, $courseid){
    global $DB;

    $conflicts = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
    $conflict = 0;

    foreach ($conflicts as $id => $value) {
        $students = explode(';', $conflicts[$id]->idstudents);
        $students = array_filter($students);

        if(in_array($grader, $students) && in_array($postauthor, $students)){
            $conflict = 1;
            break;
        }
    }
    return $conflict;
}
    /**
     * Returns true if the user is able to ratepeer this peergrade object
     *
     * @param int $userid Current user assumed if left empty
     * @return bool true if the user is able to ratepeer this peergrade object
     */
    public function user_can_peergrade($userid = null) {
        if (empty($userid)) {
            global $USER;
            $userid = $USER->id;
        }
        // You can't ratepeer your item.
        if ($this->itemuserid == $userid) {
            return false;
        }
        // You can't ratepeer if you don't have the system cap.
        if (!$this->settings->permissions->peergrade) {
            return false;
        }

        // You can't peergrade if the item was outside of the assessment times.
        $timestart = $this->settings->assesstimestart;
        $timefinish = $this->settings->assesstimefinish;
        $timecreated = $this->itemtimecreated;
        if (!empty($timestart) && !empty($timefinish) && ($timecreated < $timestart || $timecreated > $timefinish)) {
            return false;
        }
        return true;
    }

    /**
     * Returns true if the user is able to view the aggregate for this peergrade object.
     *
     * @param int|null $userid If left empty the current user is assumed.
     * @return bool true if the user is able to view the aggregate for this peergrade object
     */
    public function user_can_view_aggregate($userid = null) {
        if (empty($userid)) {
            global $USER;
            $userid = $USER->id;
        }

        // If the item doesnt belong to anyone or its another user's items and they can see the aggregate on items they don't own.
        // Note that viewany doesnt mean you can see the aggregate or peergrades of your own items.
        if ((empty($this->itemuserid) or $this->itemuserid != $userid)
            && $this->settings->permissions->viewany
            && $this->settings->pluginpermissions->viewany ) {

            return true;
        }

        // If its the current user's item and they have permission to view the aggregate on their own items.
        if ($this->itemuserid == $userid
            && $this->settings->permissions->view
            && $this->settings->pluginpermissions->view) {

            return true;
        }

        return false;
    }

    /**
     * Returns a URL to view all of the peergrades for the item this peergrade is for.
     *
     * If this is a peergrade of a post then this URL will take the user to a page that shows all of the peergrades for the post
     * (this one included).
     *
     * @param bool $popup whether of not the URL should be loaded in a popup
     * @return moodle_url URL to view all of the peergrades for the item this peergrade is for.
     */
    public function get_view_peergrades_url($popup = false) {
        $attributes = array(
            'contextid'  => $this->context->id,
            'component'  => $this->component,
            'peergradearea' => $this->peergradearea,
            'itemid'     => $this->itemid,
            'peergradescaleid'    => $this->settings->peergradescale->id

        );
        if ($popup) {
            $attributes['popup'] = 1;
        }
        return new moodle_url('/peergrade/index.php', $attributes);
    }

    /**
     * Returns a URL that can be used to ratepeer the associated item.
     *
     * @param int|null          $peergrade    The peergrade to give the item, if null then no peergrade param is added.
     * @param moodle_url|string $returnurl The URL to return to.
     * @return moodle_url can be used to ratepeer the associated item.
     */
    public function get_peergrade_url($peergrade = null, $returnurl = null, $iscriteria = null) {
        if (empty($returnurl)) {
            if (!empty($this->settings->returnurl)) {
                $returnurl = $this->settings->returnurl;
            } else {
                global $PAGE;
                $returnurl = $PAGE->url;
            }
        }

        //$returnurl = $returnurl.'&page='.$currentpage;

        global $DB;
        $peerforumid = $DB->get_record('course_modules', array('id' => $this->context->instanceid))->instance;

        $args = array(
            'contextid'   => $this->context->id,
            'component'   => $this->component,
            'peergradearea'  => $this->peergradearea,
            'itemid'      => $this->itemid,
            'feedback'      => $this->feedback,
            'peergradescaleid'     => $this->settings->peergradescale->id,
            'returnurl'   => $returnurl,
            'peergradeduserid' => $this->itemuserid,
            'aggregation' => $this->settings->aggregationmethod,
            'sesskey'     => sesskey(),
            'peerforumid' => $peerforumid
        );
        if (!empty($peergrade)) {
            $args['peergrade'] = $peergrade;
        }
        if(!$iscriteria){
            $url = new moodle_url('/peergrade/peergrade.php', $args);
        } else if ($iscriteria) {
            $url = new moodle_url('/peergradecriteria/peergradecriteria.php', $args);
        }
        return $url;
    }


    /**
     * Returns a URL that can be used to feedback the associated item.
     *
     * @param int|null          $peergrade    The peergrade to give the item, if null then no peergrade param is added.
     * @param moodle_url|string $returnurl The URL to return to.
     * @return moodle_url can be used to ratepeer the associated item.
     */
    public function get_submitfeedback_url($feedback = null, $returnurl = null) {
        if (empty($returnurl)) {
            if (!empty($this->settings->returnurl)) {
                $returnurl = $this->settings->returnurl;
            } else {
                global $PAGE;
                $returnurl = $PAGE->url;
            }
        }
        $args = array(
            'contextid'   => $this->context->id,
            'component'   => $this->component,
            'peergradearea'  => $this->peergradearea,
            'itemid'      => $this->itemid,
            'feedback'      => $this->feedback,
            'peergradescaleid'     => $this->settings->peergradescale->id,
            'returnurl'   => $returnurl,
            'peergradeduserid' => $this->itemuserid,
            'aggregation' => $this->settings->aggregationmethod,
            'sesskey'     => sesskey()
        );
        if (!empty($feedback)) {
            $args['feedback'] = $feedback;
        }
        //$CFG->dirroot . '/mod/peerforum/submitfeedback.php'
        $url = new moodle_url('/mod/peerforum/submitfeedback.php', $args);
        return $url;
    }


} // End peergrade class definition.


/**
 * The peergrade_manager class provides the ability to retrieve sets of peergrades from the database
 *
 * @package   core_peergrade
 * @category  peergrade
 * @copyright 2010 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class peergrade_manager {

    /**
     * @var array An array of calculated scale options to save us genepeergrade them for each request.
     */
    protected $scales = array();

    /**
     * Delete one or more peergrades. Specify either a peergrade id, an item id or just the context id.
     *
     * @global moodle_database $DB
     * @param stdClass $options {
     *            contextid => int the context in which the peergrades exist [required]
     *            peergradeid => int the id of an individual peergrade to delete [optional]
     *            userid => int delete the peergrades submitted by this user. May be used in conjuction with itemid [optional]
     *            itemid => int delete all peergrades attached to this item [optional]
     *            component => string The component to delete peergrades from [optional]
     *            peergradearea => string The peergradearea to delete peergrades from [optional]
     * }
     */
    public function delete_peergrades($options) {
        global $DB, $COURSE;

        if (empty($options->contextid)) {
            throw new coding_exception('The context option is a required option when deleting peergrades.');
        }

        $conditions = array('contextid' => $options->contextid);
        $possibleconditions = array(
            'peergradeid'   => 'id',
            'userid'     => 'userid',
            'itemid'     => 'itemid',
            'component'  => 'component',
            'peergradearea' => 'peergradearea'
        );
        foreach ($possibleconditions as $option => $field) {
            if (isset($options->{$option})) {
                $conditions[$field] = $options->{$option};
            }
        }
        $DB->delete_records('peerforum_peergrade', $conditions);

        $this->delete_post_peergrade($options->itemid, $COURSE->id);

    }

    public function delete_post_peergrade($postid, $courseid){
        global $DB;

        $sql = "SELECT p.iduser, p.id, p.postspeergradedone, p.poststopeergrade, p.postsblocked, p.numpostsassigned
                  FROM {peerforum_peergrade_users} p
                  WHERE p.courseid = $courseid";

        $posts = $DB->get_records_sql($sql);

        $DB->delete_records("peerforum_time_assigned", array('postid' => $postid, 'courseid' => $courseid));

        $DB->delete_records('peerforum_users_assigned', array('postid' => $postid));

        foreach ($posts as $user => $value) {
            $posts_to_grade = explode(";", $posts[$user]->poststopeergrade);
            $posts_to_grade = array_filter($posts_to_grade);

            $posts_done_grade = explode(";", $posts[$user]->postspeergradedone);
            $posts_done_grade = array_filter($posts_done_grade);

            $posts_blocked = explode(";", $posts[$user]->postsblocked);
            $posts_blocked = array_filter($posts_blocked);

            $numpostsassigned = $posts[$user]->numpostsassigned;

            $data = new stdClass();
            $data->id = $posts[$user]->id;

            if(!empty($posts_to_grade)){
                if(in_array($postid, $posts_to_grade)){
                    $key = array_search($postid, $posts_to_grade);
                    unset($posts_to_grade[$key]);
                    $posts_to_grade = array_filter($posts_to_grade);

                    $numposts = $numpostsassigned - 1;

                    $posts_to_grade_updated = implode(';', $posts_to_grade);
                    $data->poststopeergrade = $posts_to_grade_updated;
                    $data->numpostsassigned = $numposts;
                    $DB->update_record("peerforum_peergrade_users", $data);


                }
            }

            if(!empty($posts_done_grade)){
                if(in_array($postid, $posts_done_grade)){
                    $key = array_search($postid, $posts_done_grade);
                    unset($posts_done_grade[$key]);
                    $posts_done_grade = array_filter($posts_done_grade);

                    $numposts = $numpostsassigned - 1;

                    $posts_done_grade_updated = implode(';', $posts_done_grade);
                    $data->postspeergradedone = $posts_done_grade_updated;
                    $data->numpostsassigned = $numposts;

                    $DB->update_record("peerforum_peergrade_users", $data);
                }
            }

            if(!empty($posts_blocked)){
                if(in_array($postid, $posts_blocked)){
                    $key = array_search($postid, $posts_blocked);
                    unset($posts_blocked[$key]);
                    $posts_blocked = array_filter($posts_blocked);

                    $posts_blocked_updated = implode(';', $posts_blocked);
                    $data->postsblocked = $posts_blocked_updated;
                    $DB->update_record("peerforum_peergrade_users", $data);


                }
            }
    }
}

    public function delete_peergrade_done($postid, $userid, $courseid) {
        global $DB;

        $sql = "SELECT p.iduser, p.id, p.postspeergradedone, p.poststopeergrade
                  FROM {peerforum_peergrade_users} p
                 WHERE p.iduser = $userid AND p.courseid = $courseid";

        $posts = $DB->get_records_sql($sql);

        //remove from postspeergradedone
        $donepeergrade = explode(';', $posts[$userid]->postspeergradedone);
        $donepeergrade = array_filter($donepeergrade);

        if(in_array($postid, $donepeergrade)){
            $key = array_search($postid, $donepeergrade);
            unset($donepeergrade[$key]);
            $donepeergrade = array_filter($donepeergrade);

            $donepeergrade_updated = implode(';', $donepeergrade);
        }

        //insert into poststopeergrade
        $topeergrade = explode(';', $posts[$userid]->poststopeergrade);
        $topeergrade = array_filter($topeergrade);

        if(!in_array($postid, $topeergrade)){
            array_push($topeergrade, $postid);
        }
        $topeergrade = array_filter($topeergrade);
        $topeergrade_updated = implode(';', $topeergrade);

        $data = new stdClass();
        $data->id = $posts[$userid]->id;
        $data->postspeergradedone = $donepeergrade_updated;
        $data->poststopeergrade = $topeergrade_updated;

        $DB->update_record("peerforum_peergrade_users", $data);

    }



    /**
     * Returns this peergrades aggregate value as a string.
     *
     * @return string peergrades aggregate value
     */
    public function exist_post_peergraded($postid) {
        global $DB;

        $sql = "SELECT p.itemid
                  FROM {peerforum_peergrade} p
                 WHERE p.itemid = $postid";
        $exist = $DB->get_records_sql($sql);


        if(empty($exist)){
            return false;
        }
        else {

            return true;
        }
    }

    public function get_id() {

        global $DB;

        $sql = "SELECT p.id AS id_max
                  FROM {peerforum_peergrade} p
                 ORDER BY p.id DESC LIMIT 1";
        $id = $DB->get_records_sql($sql);

        if(empty($id)){
            return 0;
        }
        else{
            return $id['0']->id_max;

        }
        }

    /**
     * Returns this peergrades aggregate value as a string.
     *
     * @return string peergrades aggregate value
     */
    public function get_post_peergrader($id) {

        global $DB;

        $sql = "SELECT p.id, p.userid
                  FROM {peerforum_peergrade} p
                 WHERE p.id = $id";
        $post = $DB->get_records_sql($sql);


        return $post[$id]->userid;
        }


        /**
         * @param
         *
         * @return
         */

        public function update_peergrader_posts($userid, $postid, $courseid){
            global $DB, $PAGE;

            $sql = "SELECT p.iduser, p.postspeergradedone, p.id
                      FROM {peerforum_peergrade_users} p
                     WHERE p.iduser = $userid AND p.courseid = $courseid";
            $posts = $DB->get_records_sql($sql);


            if(empty($posts)){
                $data_prof = new stdClass();
                $data_prof->iduser = $userid;
                $data_prof->courseid = $courseid;
                $data_prof->poststopeergrade = NULL;
                $data_prof->postspeergradedone = NULL;
                $data_prof->postsblocked = NULL;
                $data_prof->postsexpired = NULL;
                $data_prof->numpostsassigned = 0;

                $DB->insert_record('peerforum_peergrade_users', $data_prof);

                $sql = "SELECT p.iduser, p.postspeergradedone, p.id
                          FROM {peerforum_peergrade_users} p
                         WHERE p.iduser = $userid AND p.courseid = $courseid";
                $posts = $DB->get_records_sql($sql);
            }

            adjust_database();

            $all_posts = array();

            if(empty($posts[$userid]->postspeergradedone)){
                array_push($all_posts, $postid);
            } else{
                    $all_posts = explode(';',$posts[$userid]->postspeergradedone);
                    if(!in_array($postid ,$all_posts)){
                        array_push($all_posts, $postid);
                    }
            }

            $all_posts = array_filter($all_posts);
            $posts_updated = implode(';', $all_posts);


            $data = new stdClass();
            $data->id = $posts[$userid]->id;
            $data->postspeergradedone = $posts_updated;

            $DB->update_record("peerforum_peergrade_users", $data);

            //remove from posts to peergrade
            $sql2 = "SELECT p.iduser, p.poststopeergrade, p.id
                      FROM {peerforum_peergrade_users} p
                     WHERE p.iduser = $userid AND p.courseid = $courseid";
            $posts2 = $DB->get_records_sql($sql2);


            if(!empty($posts2[$userid]->poststopeergrade)){
                $all_posts2 = array();
                $all_posts2 = explode(';',$posts2[$userid]->poststopeergrade);
                $all_posts2 = array_filter($all_posts2);


                if(in_array($postid, $all_posts2)){
                    $key = array_search($postid, $all_posts2);
                    unset($all_posts2[$key]);
                    $all_posts2 = array_filter($all_posts2);

                    $posts_updated2 = implode(';', $all_posts2);

                    $data2 = new stdClass();
                    $data2->id = $posts2[$userid]->id;
                    $data2->poststopeergrade = $posts_updated2;

                    $DB->update_record("peerforum_peergrade_users", $data2);
                }
            }

        }



    /**
     * Returns an array of peergrades for a given item (forum post, glossary entry etc).
     *
     * This returns all users peergrades for a single item
     *
     * @param stdClass $options {
     *            context => context the context in which the peergrades exists [required]
     *            component => component using peergrades ie mod_forum [required]
     *            peergradearea => peergradearea to associate this peergrade with [required]
     *            itemid  =>  int the id of the associated item (forum post, glossary item etc) [required]
     *            sort    => string SQL sort by clause [optional]
     * }
     * @return array an array of peergrades
     */
    public function get_all_peergrades_for_item($options) {
        global $DB;

        if (!isset($options->context)) {
            throw new coding_exception('The context option is a required option when getting peergrades for an item.');
        }
        if (!isset($options->itemid)) {
            throw new coding_exception('The itemid option is a required option when getting peergrades for an item.');
        }
        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when getting peergrades for an item.');
        }
        if (!isset($options->peergradearea)) {
            throw new coding_exception('The peergradearea option is now a required option when getting peergrades for an item.');
        }

        $sortclause = '';
        if (!empty($options->sort)) {
            $sortclause = "ORDER BY $options->sort";
        }

        $params = array(
            'contextid'  => $options->context->id,
            'itemid'     => $options->itemid,
            'component'  => $options->component,
            'peergradearea' => $options->peergradearea,
        );
        $userfields = user_picture::fields('u', null, 'userid');
        $sql = "SELECT p.id, p.peergrade, p.itemid, p.userid, p.timemodified, p.component, p.peergradearea, $userfields
                  FROM {peerforum_peergrade} p
             LEFT JOIN {user} u ON p.userid = u.id
                 WHERE p.contextid = :contextid AND
                       p.itemid  = :itemid AND
                       p.component = :component AND
                       p.peergradearea = :peergradearea
                       {$sortclause}";

        return $DB->get_records_sql($sql, $params);
    }



    /**
     * Adds peergrade objects to an array of items (forum posts, glossary entries etc). peergrade objects are available at $item->peergrade
     *
     * @param stdClass $options {
     *      context          => context the context in which the peergrades exists [required]
     *      component        => the component name ie mod_forum [required]
     *      peergradearea       => the peergradearea we are interested in [required]
     *      items            => array items like forum posts or glossary items. Each item needs an 'id' ie $items[0]->id [required]
     *      aggregate        => int aggregation method to apply. PEERGRADE_AGGREGATE_AVERAGE, PEERGRADE_AGGREGATE_MAXIMUM etc [required]
     *      peergradescaleid          => int the scale from which the user can select a peergrade [required]
     *      userid           => int the id of the current user [optional]
     *      returnurl        => string the url to return the user to after submitting a peergrade. Null for ajax requests [optional]
     *      assesstimestart  => int only allow peergrade of items created after this timestamp [optional]
     *      assesstimefinish => int only allow peergrade of items created before this timestamp [optional]
     * @return array the array of items with their peergrades attached at $items[0]->peergrade
     */
    public function get_peergrades($options) {
        global $DB, $USER;

        if (!isset($options->context)) {
            throw new coding_exception('The context option is a required option when getting peergrades.');
        }

        if (!isset($options->component)) {
            throw new coding_exception('The component option is a required option when getting peergrades.');
        }

        if (!isset($options->peergradearea)) {
            throw new coding_exception('The peergradearea option is a required option when getting peergrades.');
        }

        if (!isset($options->peergradescaleid)) {
            throw new coding_exception('The peergradescaleid option is a required option when getting peergrades.');
        }

        //if (!isset($options->feedback)) {
        //    throw new coding_exception('The feedback option is a required option when getting peergrades.');
        //}

        if (!isset($options->items)) {
            throw new coding_exception('The items option is a required option when getting peergrades.');
        } else if (empty($options->items)) {
            return array();
        }

        if (!isset($options->aggregate)) {
            throw new coding_exception('The aggregate option is a required option when getting peergrades.');
        } else if ($options->aggregate == PEERGRADE_AGGREGATE_NONE) {
            // peergrades are not enabled.
            return $options->items;
        }

        $aggregatestr = $this->get_aggregation_method($options->aggregate);

        // Default the userid to the current user if it is not set.
        if (empty($options->userid)) {
            $userid = $USER->id;
        } else {
            $userid = $options->userid;
        }

        // Get the item table name, the item id field, and the item user field for the given peergrade item
        // from the related component.
        list($type, $name) = core_component::normalize_component($options->component);
        $default = array(null, 'id', 'userid');
        list($itemtablename, $itemidcol, $itemuseridcol) = plugin_callback($type,
                                                                           $name,
                                                                           'peergrade',
                                                                           'get_item_fields',
                                                                           array($options),
                                                                           $default);

        // Create an array of item IDs.
        $itemids = array();
        foreach ($options->items as $item) {
            $itemids[] = $item->{$itemidcol};
        }

        // Get the items from the database.
        list($itemidtest, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $params['contextid'] = $options->context->id;
        $params['userid']    = $userid;
        $params['component']    = $options->component;
        $params['peergradearea'] = $options->peergradearea;

        $sql = "SELECT p.id, p.itemid, p.userid, p.peergradescaleid, p.feedback, p.peergrade AS userspeergrade
                  FROM {peerforum_peergrade} p
                 WHERE p.userid = :userid AND
                       p.contextid = :contextid AND
                       p.itemid {$itemidtest} AND
                       p.component = :component AND
                       p.peergradearea = :peergradearea
              ORDER BY p.itemid";
        $userpeergrades = $DB->get_records_sql($sql, $params);



        $sql = "SELECT p.itemid, $aggregatestr(p.peergrade) AS aggrpeergrade, COUNT(p.peergrade) AS numpeergrades
                  FROM {peerforum_peergrade} p
                 WHERE p.contextid = :contextid AND
                       p.itemid {$itemidtest} AND
                       p.component = :component AND
                       p.peergradearea = :peergradearea
              GROUP BY p.itemid, p.component, p.peergradearea, p.contextid
              ORDER BY p.itemid";
        $aggregatepeergrades = $DB->get_records_sql($sql, $params);


        $peergradeoptions = new stdClass;
        $peergradeoptions->context = $options->context;
        $peergradeoptions->component = $options->component;
        $peergradeoptions->peergradearea = $options->peergradearea;
        $peergradeoptions->settings = $this->generate_peergrade_settings_object($options);
        foreach ($options->items as $item) {
            $founduserpeergrade = false;
            foreach ($userpeergrades as $userpeergrade) {
                // Look for an existing peergrade from this user of this item.
                if ($item->{$itemidcol} == $userpeergrade->itemid) {
                    // Note: rec->scaleid = the id of scale at the time the peergrade was submitted.
                    // It may be different from the current scale id.
                    $peergradeoptions->peergradescaleid = $userpeergrade->peergradescaleid;
                    $peergradeoptions->userid = $userpeergrade->userid;
                    $peergradeoptions->feedback = $userpeergrade->feedback;
                    $peergradeoptions->id = $userpeergrade->id;
                    $peergradeoptions->peergrade = min($userpeergrade->userspeergrade, $peergradeoptions->settings->peergradescale->max);

                    $founduserpeergrade = true;
                    break;
                }
            }
            if (!$founduserpeergrade) {
                $peergradeoptions->peergradescaleid = null;
                $peergradeoptions->userid = null;
                $peergradeoptions->feedback = null;
                $peergradeoptions->id = null;
                $peergradeoptions->peergrade = null;

            }

            if (array_key_exists($item->{$itemidcol}, $aggregatepeergrades)) {
                $rec = $aggregatepeergrades[$item->{$itemidcol}];
                $peergradeoptions->itemid = $item->{$itemidcol};
                $peergradeoptions->aggregate = min($rec->aggrpeergrade, $peergradeoptions->settings->peergradescale->max);
                $peergradeoptions->count = $rec->numpeergrades;

            } else {
                $peergradeoptions->itemid = $item->{$itemidcol};
                $peergradeoptions->aggregate = null;
                $peergradeoptions->count = 0;
            }


            $peergrade = new peergrade($peergradeoptions);
            $peergrade->itemtimecreated = $this->get_item_time_created($item);
            if (!empty($item->{$itemuseridcol})) {
                $peergrade->itemuserid = $item->{$itemuseridcol};
            }
            $item->peergrade = $peergrade;
        }

        return $options->items;
    }

    /**
     * Generates a peergrade settings object based upon the options it is provided.
     *
     * @param stdClass $options {
     *      context           => context the context in which the peergrades exists [required]
     *      component         => string The component the items belong to [required]
     *      peergradearea        => string The peergradearea the items belong to [required]
     *      aggregate         => int Aggregation method to apply. PEERGRADE_AGGREGATE_AVERAGE, PEERGRADE_AGGREGATE_MAXIMUM etc [required]
     *      peergradescaleid           => int the peergradescale from which the user can select a peergrade [required]
     *      returnurl         => string the url to return the user to after submitting a peergrade. Null for ajax requests [optional]
     *      assesstimestart   => int only allow peergrade of items created after this timestamp [optional]
     *      assesstimefinish  => int only allow peergrade of items created before this timestamp [optional]
     *      plugintype        => string plugin type ie 'mod' Used to find the permissions callback [optional]
     *      pluginname        => string plugin name ie 'forum' Used to find the permissions callback [optional]
     * }
     * @return stdClass peergrade settings object
     */
    protected function generate_peergrade_settings_object($options) {

        if (!isset($options->context)) {
            throw new coding_exception('The context option is a required option when genepeergrade a peergrade settings object.');
        }
        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when genepeergrade a peergrade settings object.');
        }
        if (!isset($options->peergradearea)) {
            throw new coding_exception('The peergradearea option is now a required option when genepeergrade a peergrade settings object.');
        }
        if (!isset($options->aggregate)) {
            throw new coding_exception('The aggregate option is now a required option when genepeergrade a peergrade settings object.');
        }
        if (!isset($options->peergradescaleid)) {
            throw new coding_exception('The peergradescaleid option2 is now a required option when genepeergrade a peergrade settings object.');
        }

        global $DB;
        $peerforumid = $DB->get_record('course_modules', array('id' => $options->context->instanceid))->instance;


        // Settings that are common to all peergrades objects in this context.
        $settings = new stdClass;
        $settings->peergradescale  = $this->generate_peergrade_peergradescale_object($options->peergradescaleid, $peerforumid); // The peergradescale to use now.
        $settings->aggregationmethod = $options->aggregate;
        $settings->assesstimestart   = null;
        $settings->assesstimefinish  = null;

        // Collect options into the settings object.
        if (!empty($options->assesstimestart)) {
            $settings->assesstimestart = $options->assesstimestart;
        }
        if (!empty($options->assesstimefinish)) {
            $settings->assesstimefinish = $options->assesstimefinish;
        }
        if (!empty($options->returnurl)) {
            $settings->returnurl = $options->returnurl;
        }

        // Check site capabilities.
        $settings->permissions = new stdClass;
        // Can view the aggregate of peergrades of their own items.
        $settings->permissions->view    = has_capability('mod/peerforum:view', $options->context);
        // Can view the aggregate of peergrades of other people's items.
        $settings->permissions->viewany = has_capability('mod/peerforum:viewany', $options->context);
        // Can view individual peergrades.
        $settings->permissions->viewall = has_capability('mod/peerforum:viewall', $options->context);
        // Can submit peergrades.
        $settings->permissions->peergrade = has_capability('mod/peerforum:peergrade', $options->context);

        // Check module capabilities
        // This is mostly for backwards compatability with old modules that previously implemented their own peergrades.
        $pluginpermissionsarray = $this->get_plugin_permissions_array($options->context->id,
                                                                      $options->component,
                                                                      $options->peergradearea);
        $settings->pluginpermissions = new stdClass;
        $settings->pluginpermissions->view    = $pluginpermissionsarray['view'];
        $settings->pluginpermissions->viewany = $pluginpermissionsarray['viewany'];
        $settings->pluginpermissions->viewall = $pluginpermissionsarray['viewall'];
        $settings->pluginpermissions->peergrade  = $pluginpermissionsarray['peergrade'];


        return $settings;
    }

    /**
     * Generates a scale object that can be returned
     *
     * @global moodle_database $DB moodle database object
     * @param int $scaleid scale-type identifier
     * @return stdClass scale for peergrades
     */
    protected function generate_peergrade_peergradescale_object($peergradescaleid, $peerforumid) {
        global $CFG, $DB, $PAGE;

        if (!array_key_exists('s'.$peergradescaleid, $this->scales)) {
            $peergradescale = new stdClass;
            $peergradescale->id = $peergradescaleid;
            $peergradescale->name = null;
            $peergradescale->courseid = null;
            $peergradescale->peergradescaleitems = array();
            $peergradescale->isnumeric = true;
            $peergradescale->max = $peergradescaleid;

            if ($peergradescaleid < 0) {
                // It is a proper scale (not numeric).
                $peergradescalerecord = $DB->get_record('peergradescale', array('id' => abs($peergradescaleid)));

                if ($peergradescalerecord) {
                    // We need to generate an array with string keys starting at 1.
                    $peergradescalearray = explode(',', $peergradescalerecord->peergradescale);
                    $c = count($peergradescalearray);
                    for ($i = 0; $i < $c; $i++) {
                        // Treat index as a string to allow sorting without changing the value.
                        $peergradescale->peergradescaleitems[(string)($i + 1)] = $peergradescalearray[$i];
                    }
                    krsort($peergradescale->peergradescaleitems); // Have the highest grade scale item appear first.
                    $peergradescale->isnumeric = false;
                    $peergradescale->name = $peergradescalerecord->name;
                    $peergradescale->courseid = $peergradescalerecord->courseid;
                    $peergradescale->max = count($peergradescale->peergradescaleitems);
                }
            } else {
                // Generate an array of values for numeric scales.

                //$peerforumid = 4;

                $peergradescalerecord = $DB->get_record('peerforum', array('id' => $peerforumid))->peergradescale;

                $DB->set_field('peerforum', 'peergradescale', $peergradescalerecord, null);

                $peergradescale->id = $peergradescalerecord;

                for ($i = 0; $i <= (int)$peergradescaleid; $i++) {
                    $peergradescale->peergradescaleitems[(string)$i] = $i;
                }
            }
            $this->scales['s'.$peergradescaleid] = $peergradescale;
        }

        return $this->scales['s'.$peergradescaleid];
    }

    /**
     * Gets the time the given item was created
     *
     * TODO: MDL-31511 - Find a better solution for this, its not ideal to test for fields really we should be
     * asking the component the item belongs to what field to look for or even the value we
     * are looking for.
     *
     * @param stdClass $item
     * @return int|null return null if the created time is unavailable, otherwise return a timestamp
     */
    protected function get_item_time_created($item) {
        if (!empty($item->created)) {
            return $item->created; // The forum_posts table has created instead of timecreated.
        } else if (!empty($item->timecreated)) {
            return $item->timecreated;
        } else {
            return null;
        }
    }

    /**
     * Returns an array of grades calculated by aggregating item peergrades.
     *
     * @param stdClass $options {
     *      userid => int the id of the user whose items were ratedpeer, NOT the user who submitted peergrades. 0 to update all. [required]
     *      aggregationmethod => int the aggregation method to apply when calculating grades ie PEERGRADE_AGGREGATE_AVERAGE [required]
     *      peergradescaleid => int the peergradescale from which the user can select a peergrade. Used for bounds checking. [required]
     *      itemtable => int the table containing the items [required]
     *      itemtableusercolum => int the column of the user table containing the item owner's user id [required]
     *      component => The component for the peergrades [required]
     *      peergradearea => The peergradearea for the peergrades [required]
     *      contextid => int the context in which the ratedpeer items exist [optional]
     *      modulename => string the name of the module [optional]
     *      moduleid => int the id of the module instance [optional]
     * }
     * @return array the array of the user's grades
     */
    public function get_user_students_peergrades($options) {
        global $DB;

        $contextid = null;

        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when getting user grades from peergrades.');
        }
        if (!isset($options->peergradearea)) {
            throw new coding_exception('The peergradearea option is now a required option when getting user grades from peergrades.');
        }

        // If the calling code doesn't supply a context id we'll have to figure it out.
        if (!empty($options->contextid)) {
            $contextid = $options->contextid;
        } else if (!empty($options->modulename) && !empty($options->moduleid)) {
            $modulename = $options->modulename;
            $moduleid   = intval($options->moduleid);

            // Going direct to the db for the context id seems wrong.
            $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
            $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel)";
            $sql = "SELECT cm.* $ctxselect
                      FROM {course_modules} cm
                 LEFT JOIN {modules} mo ON mo.id = cm.module
                 LEFT JOIN {{$modulename}} m ON m.id = cm.instance $ctxjoin
                     WHERE mo.name=:modulename AND
                           m.id=:moduleid";
            $params = array('modulename' => $modulename, 'moduleid' => $moduleid, 'contextlevel' => CONTEXT_MODULE);
            $contextrecord = $DB->get_record_sql($sql, $params, '*', MUST_EXIST);
            $contextid = $contextrecord->ctxid;
        }


        $params = array();
        $params['contextid']  = $contextid;
        $params['component']  = $options->component;
        $params['peergradearea'] = $options->peergradearea;
        $itemtable            = $options->itemtable;
        $itemtableusercolumn  = $options->itemtableusercolumn;
        $peergradescaleid              = $options->peergradescaleid;
        $aggregationstring    = $this->get_aggregation_method($options->aggregationmethod);

        // If userid is not 0 we only want the grade for a single user.
        $singleuserwhere = '';
        if ($options->userid != 0) {
            $params['userid1'] = intval($options->userid);
            $singleuserwhere = "AND i.{$itemtableusercolumn} = :userid1";
        }

        // MDL-24648 The where line used to be "WHERE (r.contextid is null or r.contextid=:contextid)".
        // r.contextid will be null for users who haven't been ratedpeer yet.
        // No longer including users who haven't been ratedpeer to reduce memory requirements.
        $sql = "SELECT u.id as id, u.id AS userid, $aggregationstring(p.peergrade) AS rawgrade
                  FROM {user} u
             LEFT JOIN {{$itemtable}} i ON u.id=i.{$itemtableusercolumn}
             LEFT JOIN {peerforum_peergrade} p ON p.itemid=i.id
             LEFT JOIN {role_assignments} ra ON ra.userid = p.userid
                 WHERE ra.roleid = 5 AND
                       p.contextid = :contextid AND
                       p.component = :component AND
                       p.peergradearea = :peergradearea
                       $singleuserwhere
              GROUP BY u.id";
        $results = $DB->get_records_sql($sql, $params);


        if ($results) {

            $peergradescale = null;
            $max = 0;
            if ($options->peergradescaleid >= 0) {
                // Numeric.
                $max = $options->peergradescaleid;
            } else {
                // Custom scales.
                $peergradescale = $DB->get_record('peergradescale', array('id' => -$options->peergradescaleid));
                if ($peergradescale) {
                    $peergradescale = explode(',', $peergradescale->peergradescale);
                    $max = count($peergradescale);
                } else {
                    debugging('peergrade_manager::get_user_students_peergrades() received a peergradescale ID that doesnt exist');
                }
            }

            // It could throw off the grading if count and sum returned a rawgrade higher than scale
            // so to prevent it we review the results and ensure that rawgrade does not exceed the scale.
            // If it does we set rawgrade = scale (i.e. full credit).

            foreach ($results as $rid => $result) {
                if ($options->peergradescaleid >= 0) {
                    // Numeric.
                    if ($result->rawgrade > $options->peergradescaleid) {
                        $results[$rid]->rawgrade = $options->peergradescaleid;
                    }
                } else {
                    // Scales.
                    if (!empty($peergradescale) && $result->rawgrade > $max) {
                        $results[$rid]->rawgrade = $max;
                    }
                }
            }
        }

        return $results;
    }


    public function get_user_professors_peergrades($options) {
        global $DB;

        $contextid = null;

        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when getting user grades from peergrades.');
        }
        if (!isset($options->peergradearea)) {
            throw new coding_exception('The peergradearea option is now a required option when getting user grades from peergrades.');
        }

        // If the calling code doesn't supply a context id we'll have to figure it out.
        if (!empty($options->contextid)) {
            $contextid = $options->contextid;
        } else if (!empty($options->modulename) && !empty($options->moduleid)) {
            $modulename = $options->modulename;
            $moduleid   = intval($options->moduleid);

            // Going direct to the db for the context id seems wrong.
            $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
            $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel)";
            $sql = "SELECT cm.* $ctxselect
                      FROM {course_modules} cm
                 LEFT JOIN {modules} mo ON mo.id = cm.module
                 LEFT JOIN {{$modulename}} m ON m.id = cm.instance $ctxjoin
                     WHERE mo.name=:modulename AND
                           m.id=:moduleid";
            $params = array('modulename' => $modulename, 'moduleid' => $moduleid, 'contextlevel' => CONTEXT_MODULE);
            $contextrecord = $DB->get_record_sql($sql, $params, '*', MUST_EXIST);
            $contextid = $contextrecord->ctxid;
        }

        $params = array();
        $params['contextid']  = $contextid;
        $params['component']  = $options->component;
        $params['peergradearea'] = $options->peergradearea;
        $itemtable            = $options->itemtable;
        $itemtableusercolumn  = $options->itemtableusercolumn;
        $peergradescaleid      = $options->peergradescaleid;
        $aggregationstring    = $this->get_aggregation_method($options->aggregationmethod);

        // If userid is not 0 we only want the grade for a single user.
        $singleuserwhere = '';
        if ($options->userid != 0) {
            $params['userid1'] = intval($options->userid);
            $singleuserwhere = "AND i.{$itemtableusercolumn} = :userid1";
        }

        // MDL-24648 The where line used to be "WHERE (r.contextid is null or r.contextid=:contextid)".
        // r.contextid will be null for users who haven't been ratedpeer yet.
        // No longer including users who haven't been ratedpeer to reduce memory requirements.


        $sql = "SELECT u.id as id, u.id AS userid, $aggregationstring(p.peergrade) AS rawgrade
                  FROM {user} u
             LEFT JOIN {{$itemtable}} i ON u.id=i.{$itemtableusercolumn}
             LEFT JOIN {peerforum_peergrade} p ON p.itemid=i.id
             LEFT JOIN {role_assignments} ra ON ra.userid = p.userid
                 WHERE ra.roleid != 5 AND
                       p.contextid = :contextid AND
                       p.component = :component AND
                       p.peergradearea = :peergradearea
                       $singleuserwhere
              GROUP BY u.id";
        $results = $DB->get_records_sql($sql, $params);


        if ($results) {

            $peergradescale = null;
            $max = 0;
            if ($options->peergradescaleid >= 0) {
                // Numeric.
                $max = $options->peergradescaleid;
            } else {
                // Custom scales.
                $peergradescale = $DB->get_record('peergradescale', array('id' => -$options->peergradescaleid));
                if ($peergradescale) {
                    $peergradescale = explode(',', $peergradescale->peergradescale);
                    $max = count($peergradescale);
                } else {
                    debugging('peergrade_manager::get_user_professors_peergrades() received a peergradescale ID that doesnt exist');
                }
            }

            // It could throw off the grading if count and sum returned a rawgrade higher than scale
            // so to prevent it we review the results and ensure that rawgrade does not exceed the scale.
            // If it does we set rawgrade = scale (i.e. full credit).

            foreach ($results as $rid => $result) {
                if ($options->peergradescaleid >= 0) {
                    // Numeric.
                    if ($result->rawgrade > $options->peergradescaleid) {
                        $results[$rid]->rawgrade = $options->peergradescaleid;
                    }
                } else {
                    // Scales.
                    if (!empty($peergradescale) && $result->rawgrade > $max) {
                        $results[$rid]->rawgrade = $max;
                    }
                }
            }
        }

        return $results;
    }


    /**
     * Returns array of aggregate types. Used by peergrades.
     *
     * @return array aggregate types
     */
    public function get_aggregate_types() {
        return array (PEERGRADE_AGGREGATE_NONE     => get_string('peeraggregatenone', 'peerforum'),
                      PEERGRADE_AGGREGATE_AVERAGE  => get_string('peeraggregateavg', 'peerforum'),
                      PEERGRADE_AGGREGATE_COUNT    => get_string('peeraggregatecount', 'peerforum'),
                      PEERGRADE_AGGREGATE_MAXIMUM  => get_string('peeraggregatemax', 'peerforum'),
                      PEERGRADE_AGGREGATE_MINIMUM  => get_string('peeraggregatemin', 'peerforum'),
                      PEERGRADE_AGGREGATE_SUM      => get_string('peeraggregatesum', 'peerforum'));
    }

    /**
     * Converts an aggregation method constant into something that can be included in SQL
     *
     * @param int $aggregate An aggregation constant. For example, PEERGRADE_AGGREGATE_AVERAGE.
     * @return string an SQL aggregation method
     */
    public function get_aggregation_method($aggregate) {
        $aggregatestr = null;
        switch($aggregate){
            case PEERGRADE_AGGREGATE_AVERAGE:
                $aggregatestr = 'AVG';
                break;
            case PEERGRADE_AGGREGATE_COUNT:
                $aggregatestr = 'COUNT';
                break;
            case PEERGRADE_AGGREGATE_MAXIMUM:
                $aggregatestr = 'MAX';
                break;
            case PEERGRADE_AGGREGATE_MINIMUM:
                $aggregatestr = 'MIN';
                break;
            case PEERGRADE_AGGREGATE_SUM:
                $aggregatestr = 'SUM';
                break;
            default:
                $aggregatestr = 'AVG'; // Default to this to avoid real breakage - MDL-22270.
                debugging('Incorrect call to get_aggregation_method(), incorrect aggregate method ' . $aggregate, DEBUG_DEVELOPER);
        }
        return $aggregatestr;
    }

    /**
     * Looks for a callback like forum_peergrade_permissions() to retrieve permissions from the plugin whose items are being ratedpeer
     *
     * @param int $contextid The current context id
     * @param string $component the name of the component that is using peergrades ie 'mod_forum'
     * @param string $peergradearea The area the peergrade is associated with
     * @return array peergrade related permissions
     */
    public function get_plugin_permissions_array($contextid, $component, $peergradearea) {
        $pluginpermissionsarray = null;
        // Deny by default.
        $defaultpluginpermissions = array('peergrade' => true, 'view' => true, 'viewany' => true, 'viewall' => true);
        if (!empty($component)) {
            list($type, $name) = core_component::normalize_component($component);
            $pluginpermissionsarray = plugin_callback($type,
                                                      $name,
                                                      'peergrade',
                                                      'permissions',
                                                      array($contextid, $component, $peergradearea),
                                                      $defaultpluginpermissions);
        } else {
            $pluginpermissionsarray = $defaultpluginpermissions;
        }
        return $pluginpermissionsarray;
    }

    /**
     * Validates a submitted peergrade
     *
     * @param array $params submitted data
     *      context => object the context in which the ratedpeer items exists [required]
     *      component => The component the peergrade belongs to [required]
     *      peergradearea => The peergradearea the peergrade is associated with [required]
     *      itemid => int the ID of the object being ratedpeer [required]
     *      peergradescaleid => int the peergradescale from which the user can select a peergrade. Used for bounds checking. [required]
     *      peergrade => int the submitted peergrade
     *      ratedpeeruserid => int the id of the user whose items have been ratedpeer. 0 to update all. [required]
     *      aggregation => int the aggregation method to apply when calculating grades ie PEERGRADE_AGGREGATE_AVERAGE [optional]
     * @return boolean true if the peergrade is valid, false if callback not found, throws peergrade_exception if peergrade is invalid
     */
    public function check_peergrade_is_valid($params) {

        if (!isset($params['context'])) {
            throw new coding_exception('The context option is a required option when checking peergrade validity.');
        }
        if (!isset($params['component'])) {
            throw new coding_exception('The component option is now a required option when checking peergrade validity');
        }
        if (!isset($params['peergradearea'])) {
            throw new coding_exception('The peergradearea option is now a required option when checking peergrade validity');
        }
        if (!isset($params['itemid'])) {
            throw new coding_exception('The itemid option is now a required option when checking peergrade validity');
        }
        if (!isset($params['peergradescaleid'])) {
            throw new coding_exception('The peergradescaleid option1 is now a required option when checking peergrade validity');
        }
        if (!isset($params['peergradeduserid'])) {
            throw new coding_exception('The peergradeduserid option is now a required option when checking peergrade validity');
        }
        if (!isset($params['feedback'])) {
            throw new coding_exception('The feedback option is now a required option when checking peergrade validity');
        }

        list($plugintype, $pluginname) = core_component::normalize_component($params['component']);

        // This looks for a function like peerforum_peergrade_validate() in mod_forum lib.php
        // wrapping the params array in another array as call_user_func_array() expands arrays into multiple arguments.
        $isvalid = plugin_callback($plugintype, $pluginname, 'peergrade', 'validate', array($params), null);

        // If null then the callback does not exist.
        if ($isvalid === null) {
            $isvalid = false;
            debugging('peergrade validation callback not found for component '.  clean_param($component, PARAM_ALPHANUMEXT));
        }
        return $isvalid;
    }

    public function initialise_assignpeer_javascript(moodle_page $page) {
        global $CFG;

        // Only needs to be initialized once.
        static $done = false;
        if ($done) {
            return true;
        }

        $module = array('name'     => 'core_peerforum_assignpeer',
                        'fullpath' => '/mod/peerforum/assignpeer.js',
                        'requires' => array('node', 'event', 'overlay', 'io-base', 'json'));

        $page->requires->js_init_call('M.core_peerforum_assignpeer.init', null, false, $module);
        $done = true;

        return true;
    }

    public function initialise_assignpeersparent_javascript(moodle_page $page) {
        global $CFG;

        // Only needs to be initialized once.
        static $done = false;
        if ($done) {
            return true;
        }

        $module = array('name'     => 'core_peerforum_assignpeersparent',
                        'fullpath' => '/mod/peerforum/assignpeersparent.js',
                        'requires' => array('node', 'event', 'overlay', 'io-base', 'json'));

        $page->requires->js_init_call('M.core_peerforum_assignpeersparent.init', null, false, $module);
        $done = true;

        return true;
    }

    public function initialise_removepeer_javascript(moodle_page $page) {
        global $CFG;

        // Only needs to be initialized once.
        static $done = false;
        if ($done) {
            return true;
        }

        $module = array('name'     => 'core_peerforum_removepeer',
                        'fullpath' => '/mod/peerforum/removepeer.js',
                        'requires' => array('node', 'event', 'overlay', 'io-base', 'json'));

        $page->requires->js_init_call('M.core_peerforum_removepeer.init', null, false, $module);
        $done = true;

        return true;
    }

    /**
     * Initialises JavaScript to enable AJAX peergrades on the provided page
     *
     * @param moodle_page $page
     * @return true always returns true
     */
    public function initialise_peergrade_javascript(moodle_page $page) {
        global $CFG;

        // Only needs to be initialized once.
        static $done = false;
        if ($done) {
            return true;
        }

        $module = array('name'     => 'core_peergrade',
                        'fullpath' => '/peergrade/module.js',
                        'requires' => array('node', 'event', 'overlay', 'io-base', 'json'));

        $page->requires->js_init_call('M.core_peergrade.init', null, false, $module);
        $done = true;

        return true;
    }

    public function initialise_peergradecriteria_javascript(moodle_page $page) {
        global $CFG;

        // Only needs to be initialized once.
        static $done = false;
        if ($done) {
            return true;
        }

        $module = array('name'     => 'core_peergradecriteria',
                        'fullpath' => '/peergradecriteria/module.js',
                        'requires' => array('node', 'event', 'overlay', 'io-base', 'json'));

        $page->requires->js_init_call('M.core_peergradecriteria.init', null, false, $module);
        $done = true;

        return true;
    }


    /**
     * Returns a string that describes the aggregation method that was provided.
     *
     * @param string $aggregationmethod
     * @return string describes the aggregation method that was provided
     */
    public function get_aggregate_label($aggregationmethod) {
        $aggregatelabel = '';
        switch ($aggregationmethod) {
            case PEERGRADE_AGGREGATE_AVERAGE :
                $aggregatelabel .= get_string("peeraggregateavg", "peerforum");
                break;
            case PEERGRADE_AGGREGATE_COUNT :
                $aggregatelabel .= get_string("peeraggregatecount", "peerforum");
                break;
            case PEERGRADE_AGGREGATE_MAXIMUM :
                $aggregatelabel .= get_string("peeraggregatemax", "peerforum");
                break;
            case PEERGRADE_AGGREGATE_MINIMUM :
                $aggregatelabel .= get_string("peeraggregatemin", "peerforum");
                break;
            case PEERGRADE_AGGREGATE_SUM :
                $aggregatelabel .= get_string("peeraggregatesum", "peerforum");
                break;
        }
        $aggregatelabel .= get_string('labelsep', 'langconfig');
        return $aggregatelabel;
    }

} // End peergrade_manager class definition.

/**
 * The peergrade_exception class for exceptions specific to the peergrades system
 *
 * @package   core_peergrade
 * @category  peergrade
 * @copyright 2010 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class peergrade_exception extends moodle_exception {
    /**
     * @var string The message to accompany the thrown exception
     */
    public $message;
    /**
     * Generate exceptions that can be easily identified as coming from the peergrades system
     *
     * @param string $errorcode the error code to generate
     */
    public function __construct($errorcode) {
        $this->errorcode = $errorcode;
        $this->message = get_string($errorcode, 'error');
    }
}
