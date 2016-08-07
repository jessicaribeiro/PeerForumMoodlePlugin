<?php

// choose production (sigma) or development (localhost)
$p=1;
// for my localhost in mac
if(!$p) date_default_timezone_set('Europe/Lisbon');

// force error reporting - sigma have this disabled :S
ini_set('display_errors', 1);
error_reporting(E_ALL);

if($p){
	require_once("GameCourse/mdl_conf.php") ;
}else{

// for my localhost in mac
$mdl_dbserver="localhost";
$mdl_dbuser="root";
$mdl_dbpass="jpfa1100";
$mdl_db="moodle";
$mdl_dbport="3306";

}

// connect to the server
$db = new mysqli($mdl_dbserver, $mdl_dbuser, $mdl_dbpass, $mdl_db, $mdl_dbport) or die("not connecting");

// check connection
if ($db->connect_errno) {
    echo "Failed to connect to MySQL: " . $db->connect_error;
}

$sep = "\t";
$lb="\n";

print "Time Created".$sep."Course".$sep."PeerForum".$sep."Topic".$sep."Final Peergrade".$sep."URL".$lb;

$sql="SELECT pp.created, pd.course, pd.peerforum, pp.id as postid, SUM(ppg.peergrade) as peergrade, c.shortname, pf.name, pp.subject, pd.id
        FROM   mdl_peerforum_posts as pp
        INNER JOIN mdl_peerforum_discussions as pd ON pd.id = pp.discussion
        INNER JOIN mdl_course as c ON c.id = pd.course
        INNER JOIN mdl_peerforum as pf ON pf.id = pd.peerforum
        INNER JOIN mdl_peerforum_peergrade as ppg ON ppg.itemid = pp.id
        GROUP BY ppg.itemid
        ORDER BY pp.id ASC";
        $sql.=";";

if ($result = $db->query($sql)) {
    /* fetch associative array */
  while ($row = $result->fetch_assoc()) {
        print date('d F Y, h:i A', $row['created']).$sep.$row['shortname'].$sep.$row['name'].$sep.$row['subject'].$sep.$row['peergrade'].$sep."discuss.php?d=".$row['id']."#p".$row['postid'].$lb;
    }

    /* free result set */
    $result->free();
}



/* close connection */
$db->close();
//print "</table>";

?>
