<?php
require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/manageconflits.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

//if(has_capability('mod/peerforum:viewpanelpeergrades', $context){


    if(isset($_POST['addconflit'])){
        $data = new stdClass();
        $data->courseid = $courseid;
        $data->conflitgroup = -1;
        $data->idstudents = -1;
        $data->namestudents = -1;


        $DB->insert_record('peerforum_peergrade_conflits', $data);

    } else if (isset($_POST['removeconflit'])){
        if(isset($_POST['conflitselect'])){
            $conflit_value = $_POST['conflitselect'];

            foreach ($conflit_value as $key => $value) {
                $conflit = explode(':', $conflit_value[$key]);

                if($conflit[0] == 'conflit'){
                    $conflit_id = $conflit[1];

                    $DB->delete_records('peerforum_peergrade_conflits', array('id' => $conflit_id));
                }
                if($conflit[0] == 'student'){
                    //$erro =  $OUTPUT->error_text(get_string('error:noconflitselected', 'peerforum'));
                    $erro = $OUTPUT->notification(get_string('error:noconflitselected', 'peerforum'), 'notifyproblem');

                    $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                    redirect($returnurl, $erro, 10);
                }
            }
        } else {
            //$erro =  $OUTPUT->error_text(get_string('error:noconflitselected', 'peerforum'));
            $erro = $OUTPUT->notification(get_string('error:noconflitselected', 'peerforum'), 'notifyproblem');


            $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
            redirect($returnurl, $erro, 10);
        }


    } else if (isset($_POST['addall'])) {
        //all group members are mutually exclusive
        $currentgroups = $DB->get_records('peerforum_groups', array('courseid' => $courseid));

        if(!empty($currentgroups)){
            $DB->delete_records('peerforum_peergrade_conflits', array('courseid' => $courseid));
        }

        foreach ($currentgroups as $id => $value) {
            $students = $currentgroups[$id]->studentsid;

            $data = new stdClass();
            $data->courseid = $courseid;
            $data->conflitgroup = -1;
            $data->idstudents = $students;
            $data->namestudents = -1;


            $DB->insert_record('peerforum_peergrade_conflits', $data);

        }

    } else if (isset($_POST['removeall'])) {
        //remove all conflits
        $conflits = $DB->get_records('peerforum_peergrade_conflits', array('courseid' => $courseid));
        if(!empty($conflits)){
            $DB->delete_records('peerforum_peergrade_conflits', array('courseid' => $courseid));
        }

    } else if (isset($_POST['addstudent'])) {

        if(isset($_POST['conflitselect'])){
            $conflit_value = $_POST['conflitselect'];

            //add student to multiple conflits
            foreach ($conflit_value as $key => $value) {
                $conflit = explode(':', $conflit_value[$key]);

                if($conflit[0] == 'conflit'){
                    $conflit_id = $conflit[1];
                    $conflit_id = str_replace(".","",$conflit_id);

                    if(isset($_POST['addselect'])){
                        $selected = $_POST['addselect'];

                       $students_id = array();

                        foreach($selected as $key => $value){
                            $id = $selected[$key];
                            $id = str_replace(".","",$id);

                            $students_id[$id] = $id;

                        }

                        $students_exist = $DB->get_record('peerforum_peergrade_conflits', array('id' => $conflit_id))->idstudents;
                        $students_exist = explode(';', $students_exist);
                        $students_exist = array_filter($students_exist);

                        foreach ($students_exist as $key => $value) {
                            $id = $students_exist[$key];
                            if(!in_array($id, $students_id)){
                                $students_id[$id] = $id;
                            }
                        }

                        $stds_id = array_filter($students_id);
                        $stds_id = implode(';', $stds_id);

                        $data = new stdClass();
                        $data->id = $conflit_id;
                        $data->courseid = $courseid;
                        $data->idstudents = $stds_id;
                        $data->conflitgroup = $conflit_id;

                        $DB->update_record('peerforum_peergrade_conflits', $data);

                        //$DB->insert_record('peerforum_peergrade_conflits', $data);
                    } else {
                        //$erro =  $OUTPUT->error_text(get_string('error:nostudentselected', 'peerforum'));
                        $erro = $OUTPUT->notification(get_string('error:nostudentselected', 'peerforum'), 'notifyproblem');

                        $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                        redirect($returnurl, $erro, 10);
                    }
                }
                if($conflit[0] == 'student'){
                    //$erro =  $OUTPUT->error_text(get_string('error:noconflitselected', 'peerforum'));
                    $erro = $OUTPUT->notification(get_string('error:noconflitselected', 'peerforum'), 'notifyproblem');

                    $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                    redirect($returnurl, $erro, 10);

                }
            }

        } else {
            //$erro =  $OUTPUT->error_text(get_string('error:noconflitselected', 'peerforum'));
            $erro = $OUTPUT->notification(get_string('error:noconflitselected', 'peerforum'), 'notifyproblem');

            $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
            redirect($returnurl, $erro, 10);
        }

    } else if (isset($_POST['removestudent'])) {
        //remove students from conflits
        if(isset($_POST['conflitselect'])){
            $selected = $_POST['conflitselect'];

            foreach ($selected as $key => $value) {
                $info = explode('|', $selected[$key]);

                $conflit_type = explode(':', $info[0]);

                if($conflit_type[0] == 'student'){
                    $conflit_id = $conflit_type[1];
                    $student_id = $info[1];
                    $student = str_replace(".","",$student_id);

                    $conflit = $DB->get_record('peerforum_peergrade_conflits', array('id' => $conflit_id));

                    $stds_conflit_id = explode(';', $conflit->idstudents);
                    $stds_conflit_id = array_filter($stds_conflit_id);

                    $std_id = $student;

                    if(in_array($std_id, $stds_conflit_id)){
                        $key = array_search($std_id, $stds_conflit_id);
                        unset($stds_conflit_id[$key]);
                        $topeergrade = array_filter($stds_conflit_id);
                        $stds_conflit_id_upd = implode(';', $stds_conflit_id);

                        $data = new stdClass();
                        $data->id = $conflit_id;
                        $data->idstudents = $stds_conflit_id_upd;

                        $DB->update_record('peerforum_peergrade_conflits', $data);
                    }


                    //$conflit = $DB->get_record('peerforum_peergrade_conflits', array('courseid' => $courseid, 'id' => $id_conflit));

                    //if(empty($conflit->idstudents)){
                    //    $DB->delete_records('peerforum_peergrade_conflits', array('courseid' => $courseid, 'id' => $id_conflit));
                    //}

                }
                if($conflit_type[0] == 'conflit'){
                    //$erro =  $OUTPUT->error_text(get_string('error:nostudentselected', 'peerforum'));
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
/*} else {
    print_error('sectionpermissiondenied', 'peergrade');
}*/

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
?>
