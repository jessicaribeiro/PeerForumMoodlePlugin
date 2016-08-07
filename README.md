# PeerForumMoodlePlugin

### Installation

* Place the '**peergrade**', '**peergrading**', '**ratingpeer**' and '**peergradecriteria**' folders into the /moodleXX folder of the Moodle
directory.

* Place the '**peerforum**' folder into the /moodleXX/mod folder of the Moodle
directory.

* Place the '**peerblock**' folder into the /moodleXX/blocks folder of the Moodle
directory.

* Go to Site Administration > Notifications and install the peerforum and peerblock plugins (click on 'Upgrade Moodle database now').

* Go to Site Administration > Plugins >  Plugins overview
and you should find that this activity and block have been added to the list of
installed modules.

* Add the '**PeerForum**' activity to your course.

* Add the '**Peer Grade**' block to your course.


### Uninstall	

* To unistall the plugins go to Site Administration > Plugins > Plugins overview.

* First unistall the '**Peer Grade panel**' and then the '**PeerForum**'.

### PHP Scripts

* The script '**get_ratings.php**' retrieve all the PeerForum ratings of students from database.

* The script '**get_peergrades**' retrieve all the PeerForum peergrades of students given by each peer from database. 

* The script '**get_final_peergrade**' retrieve all the PeerForum posts peergrades given by the students from database.

* The script '**get_students_stats**' retrieve the statistics about all the students participating in a PeerForum activity.





