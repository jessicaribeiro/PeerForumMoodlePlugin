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
 * Strings for component 'PeerForum', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   mod_peerforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityoverview'] = 'There are new PeerForum posts';
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['addanewquestion'] = 'Add a new question';
$string['addanewtopic'] = 'Add a new topic';
$string['advancedsearch'] = 'Advanced search';
$string['allpeerforums'] = 'All PeerForums';
$string['allowdiscussions'] = 'Can a {$a} post to this PeerForum?';
$string['allowsallsubscribe'] = 'This PeerForum allows everyone to choose whether to subscribe or not';
$string['allowsdiscussions'] = 'This PeerForum allows each person to start one discussion topic.';
$string['allsubscribe'] = 'Subscribe to all PeerForums';
$string['allunsubscribe'] = 'Unsubscribe from all PeerForums';
$string['alreadyfirstpost'] = 'This is already the first post in the discussion';
$string['anyfile'] = 'Any file';
$string['areaattachment'] = 'Attachments';
$string['areapost'] = 'Messages';
$string['attachment'] = 'Attachment';
$string['attachment_help'] = 'You can optionally attach one or more files to a PeerForum post. If you attach an image, it will be displayed after the message.';
$string['attachmentnopost'] = 'You cannot export attachments without a post id';
$string['attachments'] = 'Attachments';
$string['attachmentswordcount'] = 'Attachments and word count';
$string['blockafter'] = 'Post threshold for blocking';
$string['blockafter_help'] = 'This setting specifies the maximum number of posts which a user can post in the given time period. Users with the capability mod/peerforum:postwithoutthrottling are exempt from post limits.';
$string['blockperiod'] = 'Time period for blocking';
$string['blockperiod_help'] = 'Students can be blocked from posting more than a given number of posts in a given time period. Users with the capability mod/peerforum:postwithoutthrottling are exempt from post limits.';
$string['blockperioddisabled'] = 'Don\'t block';
$string['blogpeerforum'] = 'Standard PeerForum displayed in a blog-like format';
$string['bynameondate'] = 'by {$a->name} - {$a->date}';
$string['cannotadd'] = 'Could not add the discussion for this PeerForum';
$string['cannotadddiscussion'] = 'Adding discussions to this PeerForum requires group membership.';
$string['cannotadddiscussionall'] = 'You do not have permission to add a new discussion topic for all participants.';
$string['cannotaddsubscriber'] = 'Could not add subscriber with id {$a} to this PeerForum!';
$string['cannotaddteacherpeerforumto'] = 'Could not add converted teacher PeerForum instance to section 0 in the course';
$string['cannotcreatediscussion'] = 'Could not create new discussion';
$string['cannotcreateinstanceforteacher'] = 'Could not create new course module instance for the teacher PeerForum';
$string['cannotdeletepost'] = 'You can\'t delete this post!';
$string['cannoteditposts'] = 'You can\'t edit other people\'s posts!';
$string['cannotfinddiscussion'] = 'Could not find the discussion in this PeerForum';
$string['cannotfindfirstpost'] = 'Could not find the first post in this PeerForum';
$string['cannotfindorcreatepeerforum'] = 'Could not find or create a main news PeerForum for the site';
$string['cannotfindparentpost'] = 'Could not find top parent of post {$a}';
$string['cannotmovefromsinglepeerforum'] = 'Cannot move discussion from a simple single discussion PeerForum';
$string['cannotmovenotvisible'] = 'Peer PeerForum not visible';
$string['cannotmovetonotexist'] = 'You can\'t move to that PeerForum - it doesn\'t exist!';
$string['cannotmovetonotfound'] = 'Target PeerForum not found in this course.';
$string['cannotmovetosinglepeerforum'] = 'Cannot move discussion to a simple single discussion PeerForum';
$string['cannotpurgecachedrss'] = 'Could not purge the cached RSS feeds for the source and/or destination PeerForum(s) - check your file permissionspeerforums';
$string['cannotremovesubscriber'] = 'Could not remove subscriber with id {$a} from this PeerForum!';
$string['cannotreply'] = 'You cannot reply to this post';
$string['cannotsplit'] = 'Discussions from this PeerForum cannot be split';
$string['cannotsubscribe'] = 'Sorry, but you must be a group member to subscribe.';
$string['cannottrack'] = 'Could not stop tracking that PeerForum';
$string['cannotunsubscribe'] = 'Could not unsubscribe you from that PeerForum';
$string['cannotupdatepost'] = 'You can not update this post';
$string['cannotviewpostyet'] = 'You cannot read other students questions in this discussion yet because you haven\'t posted';
$string['cannotviewusersposts'] = 'There are no posts made by this user that you are able to view.';
$string['cleanreadtime'] = 'Mark old posts as read hour';
$string['clicktounsubscribe'] = 'You are subscribed to this discussion. Click to unsubscribe.';
$string['clicktosubscribe'] = 'You are not subscribed to this discussion. Click to subscribe.';
$string['completiondiscussions'] = 'Student must create discussions:';
$string['completiondiscussionsgroup'] = 'Require discussions';
$string['completiondiscussionshelp'] = 'requiring discussions to complete';
$string['completionposts'] = 'Student must post discussions or replies:';
$string['completionpostsgroup'] = 'Require posts';
$string['completionpostshelp'] = 'requiring discussions or replies to complete';
$string['completionreplies'] = 'Student must post replies:';
$string['completionrepliesgroup'] = 'Require replies';
$string['completionreplieshelp'] = 'requiring replies to complete';
$string['configcleanreadtime'] = 'The hour of the day to clean old posts from the \'read\' table.';
$string['configdigestmailtime'] = 'People who choose to have emails sent to them in digest form will be emailed the digest daily. This setting controls which time of day the daily mail will be sent (the next cron that runs after this hour will send it).';
$string['configdisplaymode'] = 'The default display mode for discussions if one isn\'t set.';
$string['configenablerssfeeds'] = 'This switch will enable the possibility of RSS feeds for all PeerForums.  You will still need to turn feeds on manually in the settings for each PeerForum.';
$string['configenabletimedposts'] = 'Set to \'yes\' if you want to allow setting of display periods when posting a new PeerForum discussion (Experimental as not yet fully tested)';
$string['configlongpost'] = 'Any post over this length (in characters not including HTML) is considered long. Posts displayed on the site front page, social format course pages, or user profiles are shortened to a natural break somewhere between the peerforum_shortpost and peerforum_longpost values.';
$string['configmanydiscussions'] = 'Maximum number of discussions shown in a PeerForum per page';
$string['configmaxattachments'] = 'Default maximum number of attachments allowed per post.';
$string['configmaxbytes'] = 'Default maximum size for all PeerForum attachments on the site (subject to course limits and other local settings)';
$string['configoldpostdays'] = 'Number of days old any post is considered read.';
$string['configreplytouser'] = 'When a PeerForum post is mailed out, should it contain the user\'s email address so that recipients can reply personally rather than via the PeerForum? Even if set to \'Yes\' users can choose in their profile to keep their email address secret.';
$string['configrsstypedefault'] = 'If RSS feeds are enabled, sets the default activity type.';
$string['configrssarticlesdefault'] = 'If RSS feeds are enabled, sets the default number of articles (either discussions or posts).';
$string['configshortpost'] = 'Any post under this length (in characters not including HTML) is considered short (see below).';
$string['configtrackingtype'] = 'Default setting for read tracking.';
$string['configtrackreadposts'] = 'Set to \'yes\' if you want to track read/unread for each user.';
$string['configusermarksread'] = 'If \'yes\', the user must manually mark a post as read. If \'no\', when the post is viewed it is marked as read.';
$string['confirmsubscribediscussion'] = 'Do you really want to subscribe to discussion \'{$a->discussion}\' in PeerForum \'{$a->peerforum}\'?';
$string['confirmunsubscribediscussion'] = 'Do you really want to unsubscribe from discussion \'{$a->discussion}\' in PeerForum \'{$a->peerforum}\'?';
$string['confirmsubscribe'] = 'Do you really want to subscribe to PeerForum \'{$a}\'?';
$string['confirmunsubscribe'] = 'Do you really want to unsubscribe from PeerForum \'{$a}\'?';
$string['couldnotadd'] = 'Could not add your post due to an unknown error';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';
$string['couldnotupdate'] = 'Could not update your post due to an unknown error';
$string['crontask'] = 'Peer PeerForum mailings and maintenance jobs';
$string['delete'] = 'Delete';
$string['deleteddiscussion'] = 'The discussion topic has been deleted';
$string['deletedpost'] = 'The post has been deleted';
$string['deletedposts'] = 'Those posts have been deleted';
$string['deletesure'] = 'Are you sure you want to delete this post?';
$string['deletesureplural'] = 'Are you sure you want to delete this post and all replies? ({$a} posts)';
$string['digestmailheader'] = 'This is your daily digest of new posts from the {$a->sitename} PeerForums. To change your default PeerForum email preferences, go to {$a->userprefs}.';
$string['digestmailpost'] = 'Change your PeerForum digest preferences';
$string['digestmailpostlink'] = 'Change your PeerForum digest preferences: {$a}';
$string['digestmailprefs'] = 'your user profile';
$string['digestmailsubject'] = '{$a}: PeerForum digest';
$string['digestmailtime'] = 'Hour to send digest emails';
$string['digestsentusers'] = 'Email digests successfully sent to {$a} users.';
$string['disallowsubscribe'] = 'Subscriptions not allowed';
$string['disallowsubscription'] = 'Subscription';
$string['disallowsubscription_help'] = 'This PeerForum has been configured so that you cannot subscribe to discussions.';
$string['disallowsubscribeteacher'] = 'Subscriptions not allowed (except for teachers)';
$string['discussion'] = 'Discussion';
$string['discussionmoved'] = 'This discussion has been moved to \'{$a}\'.';
$string['discussionmovedpost'] = 'This discussion has been moved to <a href="{$a->discusshref}">here</a> in the PeerForum <a href="{$a->peerforumhref}">{$a->peerforumname}</a>';
$string['discussionname'] = 'Discussion name';
$string['discussionnownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->discussion}\' of \'{$a->peerforum}\'';
$string['discussionnowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->discussion}\' of \'{$a->peerforum}\'';
$string['discussionsubscribestop'] = 'I don\'t want to be notified of new posts in this discussion';
$string['discussionsubscribestart'] = 'Send me notifications of new posts in this discussion';
$string['discussionsubscription'] = 'Discussion subscription';
$string['discussionsubscription_help'] = 'Subscribing to a discussion means you will receive notifications of new posts to that discussion.';
$string['discussions'] = 'Discussions';
$string['discussionsstartedby'] = 'Discussions started by {$a}';
$string['discussionsstartedbyrecent'] = 'Discussions recently started by {$a}';
$string['discussionsstartedbyuserincourse'] = 'Discussions started by {$a->fullname} in {$a->coursename}';
$string['discussthistopic'] = 'Discuss this topic';
$string['displayend'] = 'Display end';
$string['displayend_help'] = 'This setting specifies whether a PeerForum post should be hidden after a certain date. Note that administrators can always view PeerForum posts.';
$string['displaymode'] = 'Display mode';
$string['displayperiod'] = 'Display period';
$string['displaystart'] = 'Display start';
$string['displaystart_help'] = 'This setting specifies whether a PeerForum post should be displayed from a certain date. Note that administrators can always view PeerForum posts.';
$string['displaywordcount'] = 'Display word count';
$string['displaywordcount_help'] = 'This setting specifies whether the word count of each post should be displayed or not.';
$string['eachuserpeerforum'] = 'Each person posts one discussion';
$string['edit'] = 'Edit';
$string['editedby'] = 'Edited by {$a->name} - original submission {$a->date}';
$string['editedpostupdated'] = '{$a}\'s post was updated';
$string['editing'] = 'Editing';
$string['eventcoursesearched'] = 'Course searched';
$string['eventdiscussioncreated'] = 'Discussion created';
$string['eventdiscussionupdated'] = 'Discussion updated';
$string['eventdiscussiondeleted'] = 'Discussion deleted';
$string['eventdiscussionmoved'] = 'Discussion moved';
$string['eventdiscussionviewed'] = 'Discussion viewed';
$string['eventdiscussionsubscriptioncreated'] = 'Discussion subscription created';
$string['eventdiscussionsubscriptiondeleted'] = 'Discussion subscription deleted';
$string['eventuserreportviewed'] = 'User report viewed';
$string['eventpostcreated'] = 'Post created';
$string['eventpostdeleted'] = 'Post deleted';
$string['eventpostupdated'] = 'Post updated';
$string['eventreadtrackingdisabled'] = 'Read tracking disabled';
$string['eventreadtrackingenabled'] = 'Read tracking enabled';
$string['eventsubscribersviewed'] = 'Subscribers viewed';
$string['eventsubscriptioncreated'] = 'Subscription created';
$string['eventsubscriptiondeleted'] = 'Subscription deleted';
$string['emaildigestcompleteshort'] = 'Complete posts';
$string['emaildigestdefault'] = 'Default ({$a})';
$string['emaildigestoffshort'] = 'No digest';
$string['emaildigestsubjectsshort'] = 'Subjects only';
$string['emaildigesttype'] = 'Email digest options';
$string['emaildigesttype_help'] = 'The type of notification that you will receive for each PeerForum.

* Default - follow the digest setting found in your user profile. If you update your profile, then that change will be reflected here too;
* No digest - you will receive one e-mail per PeerForum post;
* Digest - complete posts - you will receive one digest e-mail per day containing the complete contents of each PeerForum post;
* Digest - subjects only - you will receive one digest e-mail per day containing just the subject of each PeerForum post.
';
$string['emaildigestupdated'] = 'The e-mail digest option was changed to \'{$a->maildigesttitle}\' for the PeerForum \'{$a->peerforum}\'. {$a->maildigestdescription}';
$string['emaildigestupdated_default'] = 'Your default profile setting of \'{$a->maildigesttitle}\' was used for the PeerForum \'{$a->peerforum}\'. {$a->maildigestdescription}.';
$string['emaildigest_0'] = 'You will receive one e-mail per PeerForum post.';
$string['emaildigest_1'] = 'You will receive one digest e-mail per day containing the complete contents of each PeerForum post.';
$string['emaildigest_2'] = 'You will receive one digest e-mail per day containing the subject of each PeerForum post.';
$string['emptymessage'] = 'Something was wrong with your post. Perhaps you left it blank, or the attachment was too big. Your changes have NOT been saved.';
$string['erroremptymessage'] = 'Post message cannot be empty';
$string['erroremptysubject'] = 'Post subject cannot be empty.';
$string['errorenrolmentrequired'] = 'You must be enrolled in this course to access this content';
$string['errorwhiledelete'] = 'An error occurred while deleting record.';
$string['eventassessableuploaded'] = 'Some content has been posted.';
$string['everyonecanchoose'] = 'Everyone can choose to be subscribed';
$string['everyonecannowchoose'] = 'Everyone can now choose to be subscribed';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this PeerForum';
$string['everyoneissubscribed'] = 'Everyone is subscribed to this PeerForum';
$string['existingsubscribers'] = 'Existing subscribers';
$string['exportdiscussion'] = 'Export whole discussion to portfolio';
$string['forcedreadtracking'] = 'Allow forced read tracking';
$string['forcedreadtracking_desc'] = 'Allows PeerForums to be set to forced read tracking. Will result in decreased performance for some users, particularly on courses with many PeerForums and posts. When off, any PeerForums previously set to Forced are treated as optional.';
$string['forcesubscribed_help'] = 'This PeerForum has been configured so that you cannot unsubscribe from discussions.';
$string['forcesubscribed'] = 'This PeerForum forces everyone to be subscribed';
$string['peerforum'] = 'Peer PeerForum';
$string['peerforum:addinstance'] = 'Add a new PeerForum';
$string['peerforum:addnews'] = 'Add news';
$string['peerforum:addquestion'] = 'Add question';
$string['peerforum:allowforcesubscribe'] = 'Allow force subscribe';
$string['peerforumauthorhidden'] = 'Author (hidden)';
$string['peerforumblockingalmosttoomanyposts'] = 'You are approaching the posting threshold. You have posted {$a->numposts} times in the last {$a->blockperiod} and the limit is {$a->blockafter} posts.';
$string['peerforumbodyhidden'] = 'This post cannot be viewed by you, probably because you have not posted in the discussion, the maximum editing time hasn\'t passed yet, the discussion has not started or the discussion has expired.';
$string['peerforum:canposttomygroups'] = 'Can post to all groups you have access to';
$string['peerforum:createattachment'] = 'Create attachments';
$string['peerforum:deleteanypost'] = 'Delete any posts (anytime)';
$string['peerforum:deleteownpost'] = 'Delete own posts (within deadline)';
$string['peerforum:editanypost'] = 'Edit any post';
$string['peerforum:exportdiscussion'] = 'Export whole discussion';
$string['peerforum:exportownpost'] = 'Export own post';
$string['peerforum:exportpost'] = 'Export post';
$string['peerforumintro'] = 'Description';
$string['peerforum:managesubscriptions'] = 'Manage subscriptions';
$string['peerforum:movediscussions'] = 'Move discussions';
$string['peerforum:postwithoutthrottling'] = 'Exempt from post threshold';
$string['peerforumname'] = 'PeerForum name';
$string['peerforumposts'] = 'PeerForum posts';
$string['peerforum:rate'] = 'Rate posts';
$string['peerforum:replynews'] = 'Reply to news';
$string['peerforum:replypost'] = 'Reply to posts';
$string['peerforums'] = 'PeerForums';
$string['peerforum:splitdiscussions'] = 'Split discussions';
$string['peerforum:startdiscussion'] = 'Start new discussions';
$string['peerforum:peergradepost'] = 'Peer grade a post';
$string['peerforum:peergrade'] = 'Allow peer grade in PeerForum';
$string['peerforumsubjecthidden'] = 'Subject (hidden)';
$string['peerforumtracked'] = 'Unread posts are being tracked';
$string['peerforumtrackednot'] = 'Unread posts are not being tracked';
$string['peerforumtype'] = 'PeerForum type';
$string['peerforumtype_help'] = 'There are 5 PeerForum types:

* A single simple discussion - A single discussion topic which everyone can reply to (cannot be used with separate groups)
* Each person posts one discussion - Each student can post exactly one new discussion topic, which everyone can then reply to
* Q and A PeerForum - Students must first post their perspectives before viewing other students\' posts
* Standard PeerForum displayed in a blog-like format - An open PeerForum where anyone can start a new discussion at any time, and in which discussion topics are displayed on one page with "Discuss this topic" links
* Standard PeerForum for general use - An open PeerForum where anyone can start a new discussion at any time';
$string['peerforum:viewallratings'] = 'View all raw ratings given by individuals';
$string['peerforum:viewanyrating'] = 'View total ratings that anyone received';
$string['peerforum:viewdiscussion'] = 'View discussions';
$string['peerforum:viewhiddentimedposts'] = 'View hidden timed posts';
$string['peerforum:viewqandawithoutposting'] = 'Always see Q and A posts';
$string['peerforum:viewrating'] = 'View the total rating you received';
$string['peerforum:viewsubscribers'] = 'View subscribers';
$string['generalpeerforum'] = 'Standard PeerForum for general use';
$string['generalpeerforums'] = 'General PeerForums';
$string['hiddenpeerforumpost'] = 'Hidden PeerForum post';
$string['inpeerforum'] = 'in {$a}';
$string['introblog'] = 'The posts in this PeerForum were copied here automatically from blogs of users in this course because those blog entries are no longer available';
$string['intronews'] = 'General news and announcements';
$string['introsocial'] = 'An open PeerForum for chatting about anything you want to';
$string['introteacher'] = 'A PeerForum for teacher-only notes and discussion';
$string['invalidaccess'] = 'This page was not accessed correctly';
$string['invaliddiscussionid'] = 'Discussion ID was incorrect or no longer exists';
$string['invaliddigestsetting'] = 'An invalid mail digest setting was provided';
$string['invalidforcesubscribe'] = 'Invalid force subscription mode';
$string['invalidpeerforumid'] = 'PeerForum ID was incorrect';
$string['invalidparentpostid'] = 'Parent post ID was incorrect';
$string['invalidpostid'] = 'Invalid post ID - {$a}';
$string['lastpost'] = 'Last post';
$string['learningpeerforums'] = 'Learning PeerForums';
$string['longpost'] = 'Long post';
$string['mailnow'] = 'Mail now';
$string['manydiscussions'] = 'Discussions per page';
$string['markalldread'] = 'Mark all posts in this discussion read.';
$string['markallread'] = 'Mark all posts in this PeerForum read.';
$string['markread'] = 'Mark read';
$string['markreadbutton'] = 'Mark<br />read';
$string['markunread'] = 'Mark unread';
$string['markunreadbutton'] = 'Mark<br />unread';
$string['maxattachments'] = 'Maximum number of attachments';
$string['maxattachments_help'] = 'This setting specifies the maximum number of files that can be attached to a PeerForum post.';
$string['maxattachmentsize'] = 'Maximum attachment size';
$string['maxattachmentsize_help'] = 'This setting specifies the largest size of file that can be attached to a PeerForum post.';
$string['maxtimehaspassed'] = 'Sorry, but the maximum time for editing this post ({$a}) has passed!';
$string['message'] = 'Message';
$string['messageinboundattachmentdisallowed'] = 'Unable to post your reply, since it includes an attachment and the PeerForum doesn\'t allow attachments.';
$string['messageinboundfilecountexceeded'] = 'Unable to post your reply, since it includes more than the maximum number of attachments allowed for the PeerForum ({$a->peerforum->maxattachments}).';
$string['messageinboundfilesizeexceeded'] = 'Unable to post your reply, since the total attachment size ({$a->filesize}) is greater than the maximum size allowed for the PeerForum ({$a->maxbytes}).';
$string['messageinboundpeerforumhidden'] = 'Unable to post your reply, since the PeerForum is currently unavailable.';
$string['messageinboundnopostpeerforum'] = 'Unable to post your reply, since you do not have permission to post in the {$a->peerforum->name} PeerForum.';
$string['messageinboundthresholdhit'] = 'Unable to post your reply.  You have exceeded the posting threshold set for this PeerForum';
$string['messageprovider:digests'] = 'Subscribed PeerForum digests';
$string['messageprovider:posts'] = 'Subscribed PeerForum posts';
$string['missingsearchterms'] = 'The following search terms occur only in the HTML markup of this message:';
$string['modeflatnewestfirst'] = 'Display replies flat, with newest first';
$string['modeflatoldestfirst'] = 'Display replies flat, with oldest first';
$string['modenested'] = 'Display replies in nested form';
$string['modethreaded'] = 'Display replies in threaded form';
$string['modulename'] = 'PeerForum';
$string['modulename_help'] = 'The PeerForum activity module enables participants to have asynchronous discussions i.e. discussions that take place over an extended period of time.

There are several PeerForum types to choose from, such as a standard PeerForum where anyone can start a new discussion at any time; a PeerForum where each student can post exactly one discussion; or a question and answer PeerForum where students must first post before being able to view other students\' posts. A teacher can allow files to be attached to PeerForum posts. Attached images are displayed in the PeerForum post.

Participants can subscribe to a PeerForum to receive notifications of new PeerForum posts. A teacher can set the subscription mode to optional, forced or auto, or prevent subscription completely. If required, students can be blocked from posting more than a given number of posts in a given time period; this can prevent individuals from dominating discussions.

PeerForum posts can be rated by teachers or students (peer evaluation). Ratings can be aggregated to form a final grade which is recorded in the gradebook.

PeerForums have many uses, such as

* A social space for students to get to know each other
* For course announcements (using a news PeerForum with forced subscription)
* For discussing course content or reading materials
* For continuing online an issue raised previously in a face-to-face session
* For teacher-only discussions (using a hidden PeerForum)
* A help centre where tutors and students can give advice
* A one-on-one support area for private student-teacher communications (using a PeerForum with separate groups and with one student per group)
* For extension activities, for example ‘brain teasers’ for students to ponder and suggest solutions to';
$string['modulename_link'] = 'mod/peerforum/view';
$string['modulenameplural'] = 'PeerForums';
$string['more'] = 'more';
$string['movedmarker'] = '(Moved)';
$string['movethisdiscussionto'] = 'Move this discussion to ...';
$string['mustprovidediscussionorpost'] = 'You must provide either a discussion id or post id to export';
$string['myprofileownpost'] = 'My PeerForum posts';
$string['myprofileowndis'] = 'My PeerForum discussions';
$string['myprofileotherdis'] = 'PeerForum discussions';
$string['namenews'] = 'News PeerForum';
$string['namenews_help'] = 'The news PeerForum is a special PeerForum for announcements that is automatically created when a course is created. A course can have only one news PeerForum. Only teachers and administrators can post in the news PeerForum. The "Latest news" block will display recent discussions from the news PeerForum.';
$string['namesocial'] = 'Social PeerForum';
$string['nameteacher'] = 'Teacher PeerForum';
$string['nextdiscussiona'] = 'Next discussion: {$a}';
$string['newpeerforumposts'] = 'New PeerForum posts';
$string['noattachments'] = 'There are no attachments to this post';
$string['nodiscussions'] = 'There are no discussion topics yet in this PeerForum';
$string['nodiscussionsstartedby'] = '{$a} has not started any discussions';
$string['nodiscussionsstartedbyyou'] = 'You haven\'t started any discussions yet';
$string['noguestpost'] = 'Sorry, guests are not allowed to post.';
$string['noguestsubscribe'] = 'Sorry, guests are not allowed to subscribe.';
$string['noguesttracking'] = 'Sorry, guests are not allowed to set tracking options.';
$string['nomorepostscontaining'] = 'No more posts containing \'{$a}\' were found';
$string['nonews'] = 'No news has been posted yet';
$string['noonecansubscribenow'] = 'Subscriptions are now disallowed';
$string['nopermissiontosubscribe'] = 'You do not have the permission to view PeerForum subscribers';
$string['nopermissiontoview'] = 'You do not have permissions to view this post';
$string['nopostpeerforum'] = 'Sorry, you are not allowed to post to this PeerForum';
$string['noposts'] = 'No posts';
$string['nopostsmadebyuser'] = '{$a} has made no posts';
$string['nopostsmadebyyou'] = 'You haven\'t made any posts';
$string['noquestions'] = 'There are no questions yet in this PeerForum';
$string['nosubscribers'] = 'There are no subscribers yet for this PeerForum';
$string['notsubscribed'] = 'Subscribe';
$string['notexists'] = 'Discussion no longer exists';
$string['nothingnew'] = 'Nothing new for {$a}';
$string['notingroup'] = 'Sorry, but you need to be part of a group to see this PeerForum.';
$string['notinstalled'] = 'The PeerForum module is not installed';
$string['notpartofdiscussion'] = 'This post is not part of a discussion!';
$string['notrackpeerforum'] = 'Don\'t track unread posts';
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this PeerForum';
$string['nowallsubscribed'] = 'All PeerForums in {$a} are subscribed.';
$string['nowallunsubscribed'] = 'All PeerForums in {$a} are not subscribed.';
$string['nownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->peerforum}\'';
$string['nownottracking'] = '{$a->name} is no longer tracking \'{$a->peerforum}\'.';
$string['nowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->peerforum}\'';
$string['nowtracking'] = '{$a->name} is now tracking \'{$a->peerforum}\'.';
$string['numposts'] = '{$a} posts';
$string['olderdiscussions'] = 'Older discussions';
$string['oldertopics'] = 'Older topics';
$string['oldpostdays'] = 'Read after days';
$string['overviewnumpostssince'] = '{$a} posts since last login';
$string['overviewnumunread'] = '{$a} total unread';
$string['page-mod-peerforum-x'] = 'Any PeerForum module page';
$string['page-mod-peerforum-view'] = 'PeerForum module main page';
$string['page-mod-peerforum-discuss'] = 'PeerForum module discussion thread page';
$string['parent'] = 'Show parent';
$string['parentofthispost'] = 'Parent of this post';
$string['posttomygroups'] = 'Post a copy to all groups';
$string['posttomygroups_help'] = 'Posts a copy of this message to all groups you have access to. Participants in groups you do not have access to will not see this post';
$string['prevdiscussiona'] = 'Previous discussion: {$a}';
$string['pluginadministration'] = 'PeerForum administration';
$string['pluginname'] = 'PeerForum';
$string['postadded'] = '<p>Your post was successfully added.</p> <p>You have {$a} to edit it if you want to make any changes.</p>';
$string['postaddedsuccess'] = 'Your post was successfully added.';
$string['postaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
$string['postbymailsuccess'] = 'Congratulations, your PeerForum post with subject "{$a->subject}" was successfully added. You can view it at {$a->discussionurl}.';
$string['postbymailsuccess_html'] = 'Congratulations, your <a href="{$a->discussionurl}">PeerForum post</a> with subject "{$a->subject}" was successfully posted.';
$string['postbyuser'] = '{$a->post} by {$a->user}';
$string['postincontext'] = 'See this post in context';
$string['postmailinfo'] = 'This is a copy of a message posted on the {$a} website.

To reply click on this link:';
$string['postmailinfolink'] = 'This is a copy of a message posted in {$a->coursename}.

To reply click on this link: {$a->replylink}';
$string['postmailnow'] = '<p>This post will be mailed out immediately to all PeerForum subscribers.</p>';
$string['postmailsubject'] = '{$a->courseshortname}: {$a->subject}';
$string['postrating1'] = 'Mostly separate knowing';
$string['postrating2'] = 'Separate and connected';
$string['postrating3'] = 'Mostly connected knowing';
$string['posts'] = 'Posts';
$string['postsmadebyuser'] = 'Posts made by {$a}';
$string['postsmadebyuserincourse'] = 'Posts made by {$a->fullname} in {$a->coursename}';
$string['posttopeerforum'] = 'Post to PeerForum';
$string['postupdated'] = 'Your post was updated';
$string['potentialsubscribers'] = 'Potential subscribers';
$string['processingdigest'] = 'Processing email digest for user {$a}';
$string['processingpost'] = 'Processing post {$a}';
$string['prune'] = 'Split';
$string['prunedpost'] = 'A new discussion has been created from that post';
$string['pruneheading'] = 'Split the discussion and move this post to a new discussion';
$string['qandapeerforum'] = 'Q and A PeerForum';
$string['qandanotify'] = 'This is a question and answer PeerForum. In order to see other responses to these questions, you must first post your answer';
$string['re'] = 'Re:';
$string['readtherest'] = 'Read the rest of this topic';
$string['replies'] = 'Replies';
$string['repliesmany'] = '{$a} replies so far';
$string['repliesone'] = '{$a} reply so far';
$string['reply'] = 'Reply';
$string['replypeerforum'] = 'Reply to PeerForum';
$string['replytopostbyemail'] = 'You can reply to this via email.';
$string['replytouser'] = 'Use email address in reply';
$string['reply_handler'] = 'Reply to PeerForum posts via email';
$string['reply_handler_name'] = 'Reply to PeerForum posts';
$string['resetpeerforums'] = 'Delete posts from';
$string['resetpeerforumsall'] = 'Delete all posts';
$string['resetdigests'] = 'Delete all per-user PeerForum digest preferences';
$string['resetsubscriptions'] = 'Delete all PeerForum subscriptions';
$string['resettrackprefs'] = 'Delete all PeerForum tracking preferences';
$string['rsssubscriberssdiscussions'] = 'RSS feed of discussions';
$string['rsssubscriberssposts'] = 'RSS feed of posts';
$string['rssarticles'] = 'Number of RSS recent articles';
$string['rssarticles_help'] = 'This setting specifies the number of articles (either discussions or posts) to include in the RSS feed. Between 5 and 20 generally acceptable.';
$string['rsstype'] = 'RSS feed for this activity';
$string['rsstype_help'] = 'To enable the RSS feed for this activity, select either discussions or posts to be included in the feed.';
$string['rsstypedefault'] = 'RSS feed type';
$string['search'] = 'Search';
$string['searchdatefrom'] = 'Posts must be newer than this';
$string['searchdateto'] = 'Posts must be older than this';
$string['searchpeerforumintro'] = 'Please enter search terms into one or more of the following fields:';
$string['searchpeerforums'] = 'Search PeerForums';
$string['searchfullwords'] = 'These words should appear as whole words';
$string['searchnotwords'] = 'These words should NOT be included';
$string['searcholderposts'] = 'Search older posts...';
$string['searchphrase'] = 'This exact phrase must appear in the post';
$string['searchresults'] = 'Search results';
$string['searchsubject'] = 'These words should be in the subject';
$string['searchuser'] = 'This name should match the author';
$string['searchuserid'] = 'The Moodle ID of the author';
$string['searchwhichpeerforums'] = 'Choose which PeerForums to search';
$string['searchwords'] = 'These words can appear anywhere in the post';
$string['seeallposts'] = 'See all posts made by this user';
$string['shortpost'] = 'Short post';
$string['showsubscribers'] = 'Show/edit current subscribers';
$string['singlepeerforum'] = 'A single simple discussion';
$string['smallmessage'] = '{$a->user} posted in {$a->peerforumname}';
$string['startedby'] = 'Started by';
$string['subject'] = 'Subject';
$string['subscribe'] = 'Subscribe to this PeerForum';
$string['subscribediscussion'] = 'Subscribe to this discussion';
$string['subscribeall'] = 'Subscribe everyone to this PeerForum';
$string['subscribeenrolledonly'] = 'Sorry, only enrolled users are allowed to subscribe to PeerForum post notifications.';
$string['subscribed'] = 'Subscribed';
$string['subscribenone'] = 'Unsubscribe everyone from this PeerForum';
$string['subscribers'] = 'Subscribers';
$string['subscriberstowithcount'] = 'Subscribers to "{$a->name}" ({$a->count})';
$string['subscribestart'] = 'Send me notifications of new posts in this PeerForum';
$string['subscribestop'] = 'I don\'t want to be notified of new posts in this PeerForum';
$string['subscription'] = 'Subscription';
$string['subscription_help'] = 'If you are subscribed to a PeerForum it means you will receive notification of new PeerForum posts. Usually you can choose whether you wish to be subscribed, though sometimes subscription is forced so that everyone receives notifications.';
$string['subscriptionandtracking'] = 'Subscription and tracking';
$string['subscriptionmode'] = 'Subscription mode';
$string['subscriptionmode_help'] = 'When a participant is subscribed to a PeerForum it means they will receive PeerForum post notifications. There are 4 subscription mode options:

* Optional subscription - Participants can choose whether to be subscribed
* Forced subscription - Everyone is subscribed and cannot unsubscribe
* Auto subscription - Everyone is subscribed initially but can choose to unsubscribe at any time
* Subscription disabled - Subscriptions are not allowed

Note: Any subscription mode changes will only affect users who enrol in the course in the future, and not existing users.';
$string['subscriptionoptional'] = 'Optional subscription';
$string['subscriptionforced'] = 'Forced subscription';
$string['subscriptionauto'] = 'Auto subscription';
$string['subscriptiondisabled'] = 'Subscription disabled';
$string['subscriptions'] = 'Subscriptions';
$string['thispeerforumisthrottled'] = 'This PeerForum has a limit to the number of PeerForum postings you can make in a given time period - this is currently set at {$a->blockafter} posting(s) in {$a->blockperiod}';
$string['timedhidden'] = 'Timed status: Hidden from students';
$string['timedposts'] = 'Timed posts';
$string['timedvisible'] = 'Timed status: Visible to all users';
$string['timestartenderror'] = 'Display end date cannot be earlier than the start date';
$string['trackpeerforum'] = 'Track unread posts';
$string['tracking'] = 'Track';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['trackingtype_help'] = 'If enabled, participants can track read and unread posts in the PeerForum and in discussions. There are three options:

* Optional - Participants can choose whether to turn tracking on or off via a link in the administration block. PeerForum tracking must also be enabled in the user\'s profile settings.
* Forced - Tracking is always on, regardless of user setting. Available depending on administrative setting.
* Off - Read and unread posts are not tracked.';
$string['unread'] = 'Unread';
$string['unreadposts'] = 'Unread posts';
$string['unreadpostsnumber'] = '{$a} unread posts';
$string['unreadpostsone'] = '1 unread post';
$string['unsubscribe'] = 'Unsubscribe from this PeerForum';
$string['unsubscribelink'] = 'Unsubscribe from this PeerForum: {$a}';
$string['unsubscribediscussion'] = 'Unsubscribe from this discussion';
$string['unsubscribediscussionlink'] = 'Unsubscribe from this discussion: {$a}';
$string['unsubscribeall'] = 'Unsubscribe from all PeerForums';
$string['unsubscribeallconfirm'] = 'You are currently subscribed to {$a->peerforums} PeerForums, and {$a->discussions} discussions. Do you really want to unsubscribe from all PeerForums and discussions, and disable discussion auto-subscription?';
$string['unsubscribeallconfirmpeerforums'] = 'You are currently subscribed to {$a->peerforums} PeerForums. Do you really want to unsubscribe from all PeerForums and disable discussion auto-subscription?';
$string['unsubscribeallconfirmdiscussions'] = 'You are currently subscribed to {$a->discussions} discussions. Do you really want to unsubscribe from all discussions and disable discussion auto-subscription?';
$string['unsubscribealldone'] = 'All optional PeerForum subscriptions were removed. You will still receive notifications from PeerForums with forced subscription. To manage PeerForum notifications go to Messaging in My Profile Settings.';
$string['unsubscribeallempty'] = 'You are not subscribed to any PeerForums. To disable all notifications from this server go to Messaging in My Profile Settings.';
$string['unsubscribed'] = 'Unsubscribed';
$string['unsubscribeshort'] = 'Unsubscribe';
$string['usermarksread'] = 'Manual message read marking';
$string['viewalldiscussions'] = 'View all discussions';
$string['warnafter'] = 'Post threshold for warning';
$string['warnafter_help'] = 'Students can be warned as they approach the maximum number of posts allowed in a given period. This setting specifies after how many posts they are warned. Users with the capability mod/peerforum:postwithoutthrottling are exempt from post limits.';
$string['warnformorepost'] = 'Warning! There is more than one discussion in this PeerForum - using the most recent';
$string['yournewquestion'] = 'Your new question';
$string['yournewtopic'] = 'Your new discussion topic';
$string['yourreply'] = 'Your reply';


$string['aggregatetype'] = 'Aggregate type';
$string['aggregateavg'] = 'Average of ratings';
$string['aggregatecount'] = 'Count of ratings';
$string['aggregatemax'] = 'Maximum rating';
$string['aggregatemin'] = 'Minimum rating';
$string['aggregatenone'] = 'No ratings';
$string['aggregatesum'] = 'Sum of ratings';
$string['aggregatetype_help'] = 'The aggregate type defines how ratings are combined to form the final grade in the gradebook.';

/* Average of ratings - The mean of all ratings
* Count of ratings - The number of rated items becomes the final grade. Note that the total cannot exceed the maximum grade for the activity.
* Maximum - The highest rating becomes the final grade
* Minimum - The smallest rating becomes the final grade
* Sum - All ratings are added together. Note that the total cannot exceed the maximum grade for the activity.
*/
//If "No ratings" is selected, then the activity will not appear in the gradebook.';
$string['allowratings'] = 'Allow items to be rated?';
$string['allratingsforitem'] = 'All submitted ratings';
$string['capabilitychecknotavailable'] = 'Capability check not available until activity is saved';
$string['couldnotdeleteratings'] = 'Sorry, that cannot be deleted as people have already rated it';
$string['norate'] = 'Rating of items not allowed!';
$string['noratings'] = 'No ratings submitted';
$string['noviewanyrate'] = 'You can only look at results for items that you made';
$string['noviewrate'] = 'You do not have the capability to view item ratings';
$string['rate'] = 'Rate';
$string['ratepermissiondenied'] = 'You do not have permission to rate this item';
$string['rating'] = 'Rating';
$string['ratinginvalid'] = 'Rating is invalid';
$string['ratingtime'] = 'Restrict ratings to items with dates in this range:';
$string['ratings'] = 'Ratings';
$string['rolewarning'] = 'Roles with permission to rate';
$string['rolewarning_help'] = 'To submit ratings users require the moodle/rating:rate capability and any module specific capabilities. Users assigned the following roles should be able to rate items. The list of roles may be amended via the permissions link in the administration block.';
$string['scaleselectionrequired'] = 'When selecting a ratings aggregate type you must also select to use either a scale or set a maximum points.';


// Deprecated since Moodle 3.0.
$string['subscribersto'] = 'Subscribers to "{$a->name}"';

//Peer grade
$string['peergrade'] = 'Peer grade';
$string['peergradesettings'] = 'Peer grade settings';
$string['configpeergradesettings'] = 'Peer grade settings';
$string['enablepeergrade'] = 'Enable peer grade';
$string['allowpeergrade'] = 'Enable peer grade';
$string['grade'] = 'Grade';
$string['assignagrade'] = 'Assign a grade';
$string['peergrading'] = 'Peer grade';
$string['yourgradeandfeedback'] = 'Your grade and feedback';
$string['givefeedback'] = 'Give feedback';
$string['selectpeergrade'] = 'Select a grade';
$string['givegrade'] = 'Give grade';
$string['peergradescale'] = 'Select peer grade scale';
$string['peergradescale_help'] = 'This setting specifies the maximum grade a student can give to a PeerForum post.';
$string['configpeergradescale'] = 'Default grade scale to peer grade PeerForum posts.';
$string['admin'] = ' - Admin';
$string['peerforum:grade'] = 'Peer grade posts';
$string['peerforum:viewallgrades'] = 'View all peer grades';
$string['peerforum:viewanygrade'] = 'View any peer grade';
$string['peerforum:viewgrade'] = 'View peer grade';
$string['grader'] = 'Grader';
$string['givefeedback'] = 'Give feedback';
$string['writefeedback'] = 'Write your feedback here ...';
$string['submit'] = 'Submit';
$string['rating'] = 'Rating';
$string['configpeergrade'] = 'Set to \'no\' if you do not want to enable peer grade on PeerForum';
$string['peergradingconfig'] = 'Peer grade Configurations';
$string['configsetglobalscale'] = 'Set to \'no\' if you do not want to use this grade scale in all PeerForum instances';
$string['setglobalscale'] = 'Set as global peer grade scale';
$string['useglobalscale'] = 'Use global peer grade scale';
$string['allpeergradesforitem'] = 'All submitted peer grades';
$string['nopeergrades'] = 'No peer grades';
$string['peergradeinvalid'] = 'Peer grade is invalid';
$string['configfeedback'] = 'Set to \'no\' if you do not want to allow written feedback on PeerForum posts';
$string['enablefeedback'] = 'Enable written feedback';
$string['remainanonymous'] = 'Maintain the peer grader anonymous';
$string['configanonymous'] = 'Set to \'yes\' if you do want to show the peer grader identity in PeerForum posts';
$string['remainanonymous_help'] = 'Set to \'yes\' if you do want to show the peer grader identity in PeerForum posts';
$string['showpost'] = 'Show post in context';
$string['minpeergraders'] = 'Minimum number of peer grades per post';
$string['configminpeergraders'] = 'Minimum number of peer graders to validate peer grade';
$string['timetopeergrade'] = 'Time period to peer grade (in days)';
$string['configtimetopeergrade'] = 'Time period to peer grade a post in PeerForum (in days)';
$string['timetopeergrade_help'] = 'Time period to peer grade a post in PeerForum (in days)';
$string['configselectpeergraders'] = 'Number of peer graders to peer grade a PeerForum post';
$string['selectpeergraders'] = 'Select number of peer graders per post';
$string['selectuseglobalconfigurations'] = 'Use global configurations';
$string['selectuseglobalconfigurations_help'] = 'Set to \'yes\' if you want to use the peer grade global configurations in PeerForum posts.';
$string['enablefeedback_help'] = 'Set to \'no\' if you do not want to allow written feedback on PeerForum posts';
$string['remainanonymous_help'] = 'Set to \'yes\' if you do want to show the peer grader identity in PeerForum posts';
$string['selectpeergraders_help'] = 'Number of peer graders to peer grade a PeerForum post';
$string['minpeergraders_help'] = 'Minimum number of peer graders to validate peergrading';
$string['peergradescale'] = 'Peer grade scale';

$string['peeraggregatetype'] = 'Aggregate type';
$string['peeraggregateavg'] = 'Average of peer grades';
$string['peeraggregatecount'] = 'Count of peer grades';
$string['peeraggregatemax'] = 'Maximum peer grade';
$string['peeraggregatemin'] = 'Minimum peer grade';
$string['peeraggregatenone'] = 'No peer grades';
$string['peeraggregatesum'] = 'Sum of peer grades';
$string['peeraggregatetype_help'] = 'The aggregate type defines how peer grades are combined to form the final grade in the gradebook.';

$string['finalgrademode'] = 'Final grade mode';
$string['configfinalgrademode'] = 'Select the mode to final grade';
$string['finalgrademode_help'] = 'Select the mode to final grade';

$string['onlyrating'] = 'Only rate grades (professors)';
$string['onlypeergrade'] = 'Only peer grades (students)';
$string['ratingpeergrade'] = 'Rate and Peer grades (professors and students)';

/* Average of peergrades - The mean of all peergrades
* Count of peergrades - The number of peergraded items becomes the final grade. Note that the total cannot exceed the maximum grade for the activity.
* Maximum - The highest peergrade becomes the final grade
* Minimum - The smallest peergrade becomes the final grade
* Sum - All peergrades are added together. Note that the total cannot exceed the maximum grade for the activity.*/

$string['allowpeergrades'] = 'Allow items to be peer graded?';
$string['allpeergradesforitem'] = 'All submitted peer grades';
$string['couldnotdeleteratings'] = 'Sorry, that cannot be deleted as people have already rated it';
$string['nopeergrade'] = 'Peer grade of items not allowed!';
$string['nopeergrades'] = 'No peer grades submitted';
$string['noviewanypeergrade'] = 'You can only look at results for items that you made';
$string['noviewpeergrade'] = 'You do not have the capability to view item peer grades';
$string['peergradepermissiondenied'] = 'You do not have permission to peer grade this item';
$string['peergradeinvalid'] = 'Peer grade is invalid';
$string['peergradetime'] = 'Restrict peer grades to items with dates in this range:';
$string['rolewarning'] = 'Roles with permission to peer grade';
$string['rolewarning_help'] = 'To submit peer grades users require the moodle/peergrade:peergrade capability and any module specific capabilities. Users assigned the following roles should be able to peergrade items. The list of roles may be amended via the permissions link in the administration block.';
$string['scaleselectionrequired'] = 'When selecting a peer grades aggregate type you must also select to use either a scale or set a maximum points.';

$string['sectionpermissiondenied'] = 'You do not have permission to view this section';


$string['gradeitemrate'] = '{$a->peerforumname} (rate)';
$string['gradeitemstudentpeergrade'] = '{$a->peerforumname} (students peer grade)';
$string['gradeitemprofessorpeergrade'] = '{$a->peerforumname} (professors peer grade)';

$string['editpeergrade'] = 'Edit peergrade';

$string['showfeedback'] = 'Show feedback';
$string['showfeedback_help'] = 'Set \'yes\' if you want the written feedback of PeerForum posts to be public.';
$string['always'] = 'Always';
$string['afterpeergradeends'] = 'After peergrade ends';
$string['when'] = 'Visible when:';
$string['when_help'] = 'Select when you want the written feedback to appear. If \'Always\' is chosen, the feedback will be always visible and public. If \'After peergrade ends\' is chosen, the feedback will only be visible when a minimum number of peergraders submit a feedback to a post in the PeerForum.';
$string['feedbackvisibility'] = 'Feedback visibility';
$string['feedbackvisibility_help'] = 'Select \'private\' if you do not want the written feedbacks of PeerForum posts to be public. Only the user whose post was peergraded can see the feedbacks';
$string['whenpeergrades_help'] = 'Select when you want the peergrades to appear. If \'Always\' is chosen, the peergrades will be always visible and public. If \'After peergrade ends\' is chosen, the peergrades will only be visible when a minimum number of peergraders submit a peergrade to a post in the PeerForum.';

$string['peergradesvisibility'] = 'Peergrades visibility';
$string['peergradesvisibility_help'] = 'Select \'private\' if you do not want the peergrades of PeerForum posts to be public. Only the user whose post was peergraded can see the peergrades';
$string['showpeergrades'] = 'Show peergrades';
$string['showpeergrades_help'] = 'Set \'yes\' if you want the peergrades of PeerForum posts to be public.';
$string['public'] = 'Public';
$string['private'] = 'Private';

$string['graderates'] = 'Percentage for [professor] peer grading';
$string['gradepeergrades'] = 'Percentage for [student] peer grading';
$string['professorpercentage_help'] = 'Default maximum grade for peergrades assigned by professors in PeerForums';
$string['studentpercentage_help'] = 'Default maximum grade for peer grades in peerforums';

$string['studentpercentage'] = 'Percentage for [student] peer grading';
$string['professorpercentage'] = 'Percentage for [professor] peer grading';

$string['graderate'] = '{$a->peerforum} (rate)';
$string['gradepeergrade'] = '{$a->peerforum} (peergrade)';


$string['onlyprofessorpeergrade'] = 'Only professor(s) peergrades';
$string['onlystudentpeergrade'] = 'Only students peergrades';
$string['professorstudentpeergrade'] = 'Professor(s) and students peergrades';

$string['assignrandompeer'] = 'Assign random peer';
$string['assignpeer'] = 'Assign peer';
$string['removepeer'] = 'Remove peer';


$string['selectstudentrandom'] = 'Randomly';
$string['selectstudent'] = 'Select student';
$string['onlyprofessor'] = 'Only professor';

$string['existinggroups'] = 'Existing groups: {$a}';
$string['exclusive'] = 'Mutually exclusive students: {$a}';
$string['numconflicts'] = 'Number of conflicts of mutually exclusive students: {$a}';

$string['addall'] = 'All member groups mutually exclusive';
$string['removeall'] = 'Remove all conflicts';

$string['addconflict'] = 'Add conflict';
$string['removeconflict'] = 'Remove conflict ';
$string['addstudent'] = 'Add student';
$string['removestudent'] = 'Remove student ';

$string['error:noconflictselected'] = 'Warning: No conflict selected.';
$string['error:nostudentselected'] = 'Warning: No student selected.';

$string['error:nofeedback'] = 'Warning: You must write a feedback text. Please try again.';
$string['error:nopeergrade'] = 'Warning: You must select a grade. Please try again.';

$string['submited:peergrade'] = 'Your peer grade was submitted with success.';
$string['submited:peergradeupdated'] = 'Your peer grade was updated with success.';

$string['finishpeergrade'] = 'Peer grading ends when minimum grades are given per post';
$string['finishpeergrade_help'] = 'Peer grading ends in this course when the minimum number of peer grades per post are done';
$string['peerforumpanels'] = '{$a}';
$string['peergradeaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
