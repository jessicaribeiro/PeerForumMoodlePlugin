<?php

/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');

$contextid         = required_param('contextid', PARAM_INT);
$component         = required_param('component', PARAM_COMPONENT);
$peergradearea     = required_param('peergradearea', PARAM_AREA);
$itemid            = required_param('itemid', PARAM_INT);
$peergradescaleid  = required_param('peergradescaleid', PARAM_INT);
$peergradeduserid  = required_param('peergradeduserid', PARAM_INT); // The user being ratedpeer. Required to update their grade.
$aggregationmethod = optional_param('aggregation', PEERGRADE_AGGREGATE_NONE, PARAM_INT); // Used to calculate the aggregate to return.
$feedback      = required_param('feedback', PARAM_TEXT);
$peerforumid = required_param('peerforumid', PARAM_INT);

$result = new stdClass;
$result->success = true;

$grade1 = $_POST['menu1peergradecriteria'.$itemid];
$grade2 = $_POST['menu2peergradecriteria'.$itemid];
$grade3 = $_POST['menu3peergradecriteria'.$itemid];

$result->grade1 = $grade1;
$result->grade2 = $grade2;
$result->grade3 = $grade3;

echo json_encode($result);
