<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');

$PAGE->set_url('/mod/peerforum/collapse.php', array());

require_login(null, false, null, false, true);
require_sesskey();

$result = true;
echo $OUTPUT->header();

echo json_encode(array('result' => $result));
