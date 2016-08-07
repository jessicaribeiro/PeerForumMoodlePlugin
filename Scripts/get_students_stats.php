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

print "Student".$sep."#Posts to grade".$sep."#Posts graded".$sep."#Posts blocked".$sep."#Posts expired".$sep."User blocked".$sep."URL".$lb;

/*

*/

$sql="SELECT ppu.iduser, ppu.userblocked, ppu.poststopeergrade, ppu.postspeergradedone, ppu.postsblocked, ppu.postsexpired, CONCAT(u.firstname,' ',u.lastname) AS userfullname
		FROM mdl_peerforum_peergrade_users as ppu
		INNER JOIN mdl_user as u ON u.id = ppu.iduser
		ORDER BY ppu.iduser ASC";
        $sql.=";";

if ($result = $db->query($sql)) {
    /* fetch associative array */
  while ($row = $result->fetch_assoc()) {
	  $poststopeergrade = $row['poststopeergrade'];
	  $poststopeergrade = explode(';', $poststopeergrade);
	  $poststopeergrade = array_filter($poststopeergrade);
	  $num_poststopeergrade = count($poststopeergrade);

	  $postspeergradedone = $row['postspeergradedone'];
	  $postspeergradedone = explode(';', $postspeergradedone);
	  $postspeergradedone = array_filter($postspeergradedone);
	  $num_postspeergradedone = count($postspeergradedone);

	  $postsblocked = $row['postsblocked'];
	  $postsblocked = explode(';', $postsblocked);
	  $postsblocked = array_filter($postsblocked);
	  $num_postsblocked = count($postsblocked);

	  $postsexpired = $row['postsexpired'];
	  $postsexpired = explode(';', $postsexpired);
	  $postsexpired = array_filter($postsexpired);
	  $num_postsexpired = count($postsexpired);

      print  $row['userfullname'].$sep.$num_poststopeergrade.$sep.$num_postspeergradedone.$sep.$num_postsblocked.$sep.$num_postsexpired.$sep.$row['userblocked'].$sep."user/profile.php?id=".$row['iduser'].$lb;
    }

    /* free result set */
    $result->free();
}



/* close connection */
$db->close();
//print "</table>";

?>
