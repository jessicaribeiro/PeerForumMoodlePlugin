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

$course = $_GET['course'];

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);

$PAGE->set_url('/peergrading/importgroups.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["filegroups"]["name"]);
$uploadOk = 1;
$error = 0;
$imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

if(isset($_POST["filegroupssubmit"])) {

    if ($_FILES["filegroups"]["size"] > 500000) {
        echo "Sorry, your file is too large.";
        $error = 1;
        $uploadOk = 0;
    }
    // Allow certain file formats
    if($imageFileType != "csv" ) {
        echo "Sorry, only CSV files are allowed.";
        $error = 1;
        $uploadOk = 0;
    }

    if($error == 0){
        //checks for errors and //checks that file is uploaded
        if ($_FILES['filegroups']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['filegroups']['tmp_name'])) {
            $content =  file_get_contents($_FILES['filegroups']['tmp_name']);
        }

        if(!empty($content)){
            //clear data from database
            $info = $DB->get_records('peerforum_groups', array('courseid' => $courseid));
            if(!empty($info)){
                $DB->delete_records('peerforum_groups', array('courseid' => $courseid));
            }

            $conflicts = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
            if(!empty($conflicts)){
                $DB->delete_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
            }

            // read from csv
            $groups = explode("\n", $content);
            $group_students = array();

            foreach ($groups as $key => $value) {
                $students = explode(",", $groups[$key]);
                $group_students[$key] = $students;
            }

            foreach ($group_students as $idgroup => $value) {
                $group_updated[$idgroup] = array();
                if(!empty($group_students[$idgroup])){
                    foreach ($group_students[$idgroup] as $idstudent => $value) {
                        $student = $group_students[$idgroup][$idstudent];
                        if(!empty($student)){
                            $name = explode(" ", $student);

                            $id = $DB->get_record('user', array('firstname' => $name[0], 'lastname' => $name[1]))->id;

                            $data = new stdClass();
                            $data->id = $id;
                            $data->name = $name[0]. ' ' .$name[1];

                            $group_updated[$idgroup][$idstudent] = $data;
                        }
                    }
                }
            }

            foreach ($group_updated as $key => $value) {
                $group_array[$key] = array();
                if(!empty($group_updated[$key])){
                    $std_array = array();
                    foreach ($group_updated[$key] as $obj => $value) {
                        $std = $group_updated[$key][$obj];
                        $std_array[$std->id] = $std->name;
                    }
                    $group_array[$key] = $std_array;
                }
            }

            foreach ($group_array as $g => $value) {
                if(!empty($group_array[$g])){
                    $data = new stdClass();
                    $data->courseid = $courseid;
                    $data->groupid = $g + 1;
                    $data->studentsid = implode(';', array_keys($group_array[$g]));
                    $data->studentsname = implode(';', $group_array[$g]);

                    $DB->insert_record('peerforum_groups', $data);
                }
            }
        }
    }
}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
