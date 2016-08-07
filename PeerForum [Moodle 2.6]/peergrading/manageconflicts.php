<?php

/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/manageconflicts.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

if(isset($_POST['addconflict'])){
    $data = new stdClass();
    $data->courseid = $courseid;
    $data->conflictgroup = NULL;
    $data->idstudents = NULL;
    $data->namestudents = NULL;

    $DB->insert_record('peerforum_peergrade_conflict', $data);

} else if (isset($_POST['removeconflict'])){
    if(isset($_POST['conflictselect'])){
        $conflict_value = $_POST['conflictselect'];

        foreach ($conflict_value as $key => $value) {
            $conflict = explode(':', $conflict_value[$key]);

            if($conflict[0] == 'conflict'){
                $conflict_id = $conflict[1];

                $DB->delete_records('peerforum_peergrade_conflict', array('id' => $conflict_id));
            }
            if($conflict[0] == 'student'){
                //$erro =  $OUTPUT->error_text(get_string('error:noconflictselected', 'peerforum'));
                $erro = $OUTPUT->notification(get_string('error:noconflictselected', 'peerforum'), 'notifyproblem');

                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);
            }
        }
    } else {
        $erro = $OUTPUT->notification(get_string('error:noconflictselected', 'peerforum'), 'notifyproblem');


        $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        redirect($returnurl, $erro, 10);
    }


} else if (isset($_POST['addall'])) {
    //all group members are mutually exclusive
    $currentgroups = $DB->get_records('peerforum_groups', array('courseid' => $courseid));

    if(!empty($currentgroups)){
        $DB->delete_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
    }

    foreach ($currentgroups as $id => $value) {
        $students = $currentgroups[$id]->studentsid;

        $data = new stdClass();
        $data->courseid = $courseid;
        $data->conflictgroup = NULL;
        $data->idstudents = $students;
        $data->namestudents = NULL;

        $DB->insert_record('peerforum_peergrade_conflict', $data);

        $students = explode(';', $students);
        $students = array_filter($students);

        foreach ($students as $key => $value) {
            $i = $key + 1;
            $posts = $DB->get_records('peerforum_posts', array('userid' => $students[$key]));

            if(!empty($posts)){
                foreach ($posts as $k => $value) {
                    $peergraders = $posts[$k]->peergraders;
                    $peergraders = explode(';', $peergraders);
                    $peergraders = array_filter($peergraders);

                    $new_students = $students;

                    $a = array_search($students[$key], $new_students);
                    unset($new_students[$a]);
                    $new_students = array_filter($new_students);
                    //$stds = implode(';', $new_students);

                    foreach ($new_students as $s => $value) {
                        if(in_array($new_students[$s], $peergraders)){
                            $r = array_search($new_students[$s], $peergraders);
                            unset($peergraders[$r]);
                            $sts = implode(';', $peergraders);
                        }
                    }

                    $peergraders_new = array_filter($peergraders);
                    $peergraders_new = implode(';', $peergraders_new);

                    $data2 = new stdClass();
                    $data2->id = $posts[$k]->id;
                    $data2->peergraders = $peergraders_new;

                    $DB->update_record('peerforum_posts', $data2);
                }
            }
        }
    }

} else if (isset($_POST['removeall'])) {
    //remove all conflicts
    $conflicts = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
    if(!empty($conflicts)){
        $DB->delete_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
    }

} else if (isset($_POST['addstudent'])) {

    if(isset($_POST['conflictselect'])){
        $conflict_value = $_POST['conflictselect'];

        //add student to multiple conflicts
        foreach ($conflict_value as $key => $value) {
            $conflict = explode(':', $conflict_value[$key]);

            if($conflict[0] == 'conflict'){
                $conflict_id = $conflict[1];
                $conflict_id = str_replace(".","",$conflict_id);

                if(isset($_POST['addselect'])){
                    $selected = $_POST['addselect'];

                   $students_id = array();

                    foreach($selected as $key => $value){
                        $id = $selected[$key];
                        $id = str_replace(".","",$id);

                        $students_id[$id] = $id;
                    }

                    $students_exist = $DB->get_record('peerforum_peergrade_conflict', array('id' => $conflict_id))->idstudents;
                    $students_exist = explode(';', $students_exist);
                    $students_exist = array_filter($students_exist);

                    if(in_array(-1, $students_exist)){
                        $a = array_search(-1, $students_exist);
                        unset($students_exist[$a]);
                        $sts = implode(';', $students_exist);
                        $data = new stdClass();
                        $data->id = $conflict_id;
                        $data->idstudents= $sts;
                        $DB->update_record('peerforum_peergrade_conflict', $data);
                    }

                    foreach ($students_exist as $key => $value) {
                        $id = $students_exist[$key];
                        if(!in_array($id, $students_id)){
                            $students_id[$id] = $id;
                        }
                    }

                    $stds_id = array_filter($students_id);
                    $stds_id = implode(';', $stds_id);

                    $data = new stdClass();
                    $data->id = $conflict_id;
                    $data->courseid = $courseid;
                    $data->idstudents = $stds_id;
                    $data->conflictgroup = $conflict_id;

                    $DB->update_record('peerforum_peergrade_conflict', $data);

                } else {
                    $erro = $OUTPUT->notification(get_string('error:nostudentselected', 'peerforum'), 'notifyproblem');

                    $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                    redirect($returnurl, $erro, 10);
                }
            }
            if($conflict[0] == 'student'){
                $erro = $OUTPUT->notification(get_string('error:noconflictselected', 'peerforum'), 'notifyproblem');

                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);

            }
        }

    } else {
        $erro = $OUTPUT->notification(get_string('error:noconflictselected', 'peerforum'), 'notifyproblem');

        $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        redirect($returnurl, $erro, 10);
    }

} else if (isset($_POST['removestudent'])) {
    //remove students from conflicts
    if(isset($_POST['conflictselect'])){
        $selected = $_POST['conflictselect'];

        foreach ($selected as $key => $value) {
            $info = explode('|', $selected[$key]);

            $conflict_type = explode(':', $info[0]);

            if($conflict_type[0] == 'student'){
                $conflict_id = $conflict_type[1];
                $student_id = $info[1];
                $student = str_replace(".","",$student_id);

                $conflict = $DB->get_record('peerforum_peergrade_conflict', array('id' => $conflict_id));

                $stds_conflict_id = explode(';', $conflict->idstudents);
                $stds_conflict_id = array_filter($stds_conflict_id);

                if(in_array(-1, $stds_conflict_id)){
                    $a = array_search(-1, $stds_conflict_id);
                    unset($stds_conflict_id[$a]);
                    $sts = implode(';', $stds_conflict_id);
                    $data = new stdClass();
                    $data->id = $conflict_id;
                    $data->idstudents= $sts;
                    $DB->update_record('peerforum_peergrade_conflict', $data);

                }

                $std_id = $student;

                if(in_array($std_id, $stds_conflict_id)){
                    $key = array_search($std_id, $stds_conflict_id);
                    unset($stds_conflict_id[$key]);
                    $topeergrade = array_filter($stds_conflict_id);
                    $stds_conflict_id_upd = implode(';', $stds_conflict_id);

                    $data = new stdClass();
                    $data->id = $conflict_id;
                    $data->idstudents = $stds_conflict_id_upd;

                    $DB->update_record('peerforum_peergrade_conflict', $data);
                }
            }
            if($conflict_type[0] == 'conflict'){
                $erro = $OUTPUT->notification(get_string('error:nostudentselected', 'peerforum'), 'notifyproblem');

                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);
            }
        }
    }
} else {
    $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
    redirect($returnurl);
}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
?>
