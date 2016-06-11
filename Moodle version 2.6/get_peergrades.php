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


//separator CSV
//$sep = "\t";
//$lb="<br/>";
$sep = "\t";
$lb="\n";

// t = time stamp parameter given
// u = user id parameter given

//$sep = "</th><th>";
//$lb="</th></tr>";
//print "<table border='1'><tr><th>";
print "Time Created".$sep."Course".$sep."Grader".$sep."PeerForum".$sep."Topic".$sep."PeerGrade".$sep."Feedback".$sep."URL".$lb;


$sql="SELECT  mdl_peerforum_posts.created, mdl_course.shortname, CONCAT(mdl_user.firstname,' ',mdl_user.lastname) AS userfullname, mdl_peerforum.name, mdl_peerforum_posts.subject, mdl_peerforum_peergrade.peergrade, mdl_peerforum_peergrade.feedback, mdl_peerforum_discussions.id, mdl_peerforum_peergrade.itemid
        FROM mdl_peerforum_peergrade
        INNER JOIN mdl_peerforum_posts
        ON mdl_peerforum_posts.id = mdl_peerforum_peergrade.itemid
        INNER JOIN mdl_peerforum_discussions
        ON mdl_peerforum_discussions.id = mdl_peerforum_posts.discussion
        INNER JOIN mdl_course
        ON mdl_course.id = mdl_peerforum_discussions.course
        INNER JOIN mdl_peerforum
        ON mdl_peerforum.id = mdl_peerforum_discussions.peerforum
        INNER JOIN mdl_user
        ON mdl_user.id = mdl_peerforum_peergrade.userid";
		if(isset($_REQUEST['c'])) $sql.=" WHERE mdl_peerforum.course=".$_REQUEST['c'];
		$sql.=";";


//if (!$db->query($sql)) {
//    printf("Errormessage: %s\n", $mysqli->error);
//}
if ($result = $db->query($sql)) {


//$sep = "</td><td>";
//$lb="</td></tr>";

    /* fetch associative array */
  while ($row = $result->fetch_assoc()) {

//print "<tr><td>";

        print date('d F Y, h:i A', $row['created']).$sep.$row['shortname'].$sep.$row['userfullname'].$sep.$row['name'].$sep.$row['subject'].$sep.$row['peergrade'].$sep.$row['feedback'].$sep."discuss.php?d=".$row['id']."#p".$row['itemid'].$lb;

    }

    /* free result set */
    $result->free();
}

/* close connection */
$db->close();
//print "</table>";

?>
