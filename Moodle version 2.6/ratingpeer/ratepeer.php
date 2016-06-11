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
 * This page receives non-ajax ratingpeer submissions
 *
 * It is similar to ratepeer_ajax.php. Unlike ratepeer_ajax.php a return url is required.
 *
 * @package    core_ratingpeer
 * @category   ratingpeer
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/ratingpeer/lib.php');

$contextid   = required_param('contextid', PARAM_INT);
$component   = required_param('component', PARAM_COMPONENT);
$ratingpeerarea  = required_param('ratingpeerarea', PARAM_AREA);
$itemid      = required_param('itemid', PARAM_INT);
$scaleid     = required_param('scaleid', PARAM_INT);
$userratingpeer  = required_param('ratingpeer', PARAM_INT);
$ratedpeeruserid = required_param('ratedpeeruserid', PARAM_INT);//which user is being ratedpeer. Required to update their grade
$returnurl   = required_param('returnurl', PARAM_LOCALURL);//required for non-ajax requests

$result = new stdClass;

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$contextid = null;//now we have a context object throw away the id from the user
$PAGE->set_context($context);
$PAGE->set_url('/ratingpeer/ratepeer.php', array('contextid' => $context->id));

if (!confirm_sesskey() || !has_capability('mod/peerforum:rateratingpeer',$context)) {
    echo $OUTPUT->header();
    echo get_string('ratepeerpermissiondenied', 'ratingpeer');
    echo $OUTPUT->footer();
    die();
}

$rm = new ratingpeer_manager();

//check the module ratingpeer permissions
//doing this check here rather than within ratingpeer_manager::get_ratingpeers() so we can return a json error response
$pluginpermissionsarray = $rm->get_plugin_permissions_array($context->id, $component, $ratingpeerarea);

if (!$pluginpermissionsarray['ratepeer']) {
    $result->error = get_string('ratepeerpermissiondenied', 'ratingpeer');
    echo json_encode($result);
    die();
} else {
    $params = array(
        'context'     => $context,
        'component'   => $component,
        'ratingpeerarea'  => $ratingpeerarea,
        'itemid'      => $itemid,
        'scaleid'     => $scaleid,
        'ratingpeer'      => $userratingpeer,
        'ratedpeeruserid' => $ratedpeeruserid
    );
    if (!$rm->check_ratingpeer_is_valid($params)) {
        echo $OUTPUT->header();
        echo get_string('ratingpeerinvalid', 'ratingpeer');
        echo $OUTPUT->footer();
        die();
    }
}

if ($userratingpeer != RATINGPEER_UNSET_RATINGPEER) {
    $ratingpeeroptions = new stdClass;
    $ratingpeeroptions->context = $context;
    $ratingpeeroptions->component = $component;
    $ratingpeeroptions->ratingpeerarea = $ratingpeerarea;
    $ratingpeeroptions->itemid  = $itemid;
    $ratingpeeroptions->scaleid = $scaleid;
    $ratingpeeroptions->userid  = $USER->id;

    $ratingpeer = new ratingpeer($ratingpeeroptions);
    $ratingpeer->update_ratingpeer($userratingpeer);
} else { //delete the ratingpeer if the user set to Rate...
    $options = new stdClass;
    $options->contextid = $context->id;
    $options->component = $component;
    $options->ratingpeerarea = $ratingpeerarea;
    $options->userid = $USER->id;
    $options->itemid = $itemid;

    $rm->delete_ratingpeers($options);
}

//todo add a setting to turn grade updating off for those who don't want them in gradebook
//note that this needs to be done in both ratepeer.php and ratepeer_ajax.php
if (!empty($cm) && $context->contextlevel == CONTEXT_MODULE) {
    //tell the module that its grades have changed
    $modinstance = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    $modinstance->cmidnumber = $cm->id; //MDL-12961
    $functionname = $cm->modname.'_update_grades';
    require_once($CFG->dirroot."/mod/{$cm->modname}/lib.php");
    if (function_exists($functionname)) {
        $functionname($modinstance, $ratedpeeruserid);
    }
}

redirect($returnurl);
