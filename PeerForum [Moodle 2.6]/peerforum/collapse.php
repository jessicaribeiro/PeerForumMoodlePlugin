<?php

/**
 * This page receives ajax peergrade submissions
 *
 * @package    mod
 * @subpackage peerforum
 * @copyright  2016 Jessica Ribeiro
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');

$PAGE->set_url('/mod/peerforum/collapse.php', array());

require_login(null, false, null, false, true);
require_sesskey();

$result = true;
echo $OUTPUT->header();

echo json_encode(array('result' => $result));
