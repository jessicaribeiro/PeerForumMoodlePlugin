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
 * The module peerforums external functions unit tests
 *
 * @package    mod_peerforum
 * @category   external
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class mod_peerforum_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;

        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_peerforum\subscriptions::reset_peerforum_cache();

        require_once($CFG->dirroot . '/mod/peerforum/externallib.php');
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_peerforum\subscriptions::reset_peerforum_cache();
    }

    /**
     * Test get peerforums
     */
    public function test_mod_peerforum_get_peerforums_by_courses() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Create a user.
        $user = self::getDataGenerator()->create_user();

        // Set to the user.
        self::setUser($user);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First peerforum.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course1->id;
        $peerforum1 = self::getDataGenerator()->create_module('peerforum', $record);

        // Second peerforum.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course2->id;
        $peerforum2 = self::getDataGenerator()->create_module('peerforum', $record);

        // Add discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->peerforum = $peerforum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        // Expect one discussion.
        $peerforum1->numdiscussions = 1;
        $peerforum1->cancreatediscussions = true;

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user->id;
        $record->peerforum = $peerforum2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        $discussion3 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        // Expect two discussions.
        $peerforum2->numdiscussions = 2;
        // Default limited role, no create discussion capability enabled.
        $peerforum2->cancreatediscussions = false;

        // Check the peerforum was correctly created.
        $this->assertEquals(2, $DB->count_records_select('peerforum', 'id = :peerforum1 OR id = :peerforum2',
                array('peerforum1' => $peerforum1->id, 'peerforum2' => $peerforum2->id)));

        // Enrol the user in two courses.
        // DataGenerator->enrol_user automatically sets a role for the user with the permission mod/form:viewdiscussion.
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, null, 'manual');
        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user->id);

        // Assign capabilities to view peerforums for peerforum 2.
        $cm2 = get_coursemodule_from_id('peerforum', $peerforum2->cmid, 0, false, MUST_EXIST);
        $context2 = context_module::instance($cm2->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $roleid2 = $this->assignUserCapability('mod/peerforum:viewdiscussion', $context2->id, $newrole);

        // Create what we expect to be returned when querying the two courses.
        unset($peerforum1->displaywordcount);
        unset($peerforum2->displaywordcount);

        $expectedpeerforums = array();
        $expectedpeerforums[$peerforum1->id] = (array) $peerforum1;
        $expectedpeerforums[$peerforum2->id] = (array) $peerforum2;

        // Call the external function passing course ids.
        $peerforums = mod_peerforum_external::get_peerforums_by_courses(array($course1->id, $course2->id));
        $peerforums = external_api::clean_returnvalue(mod_peerforum_external::get_peerforums_by_courses_returns(), $peerforums);
        $this->assertCount(2, $peerforums);
        foreach ($peerforums as $peerforum) {
            $this->assertEquals($expectedpeerforums[$peerforum['id']], $peerforum);
        }

        // Call the external function without passing course id.
        $peerforums = mod_peerforum_external::get_peerforums_by_courses();
        $peerforums = external_api::clean_returnvalue(mod_peerforum_external::get_peerforums_by_courses_returns(), $peerforums);
        $this->assertCount(2, $peerforums);
        foreach ($peerforums as $peerforum) {
            $this->assertEquals($expectedpeerforums[$peerforum['id']], $peerforum);
        }

        // Unenrol user from second course and alter expected peerforums.
        $enrol->unenrol_user($instance2, $user->id);
        unset($expectedpeerforums[$peerforum2->id]);

        // Call the external function without passing course id.
        $peerforums = mod_peerforum_external::get_peerforums_by_courses();
        $peerforums = external_api::clean_returnvalue(mod_peerforum_external::get_peerforums_by_courses_returns(), $peerforums);
        $this->assertCount(1, $peerforums);
        $this->assertEquals($expectedpeerforums[$peerforum1->id], $peerforums[0]);
        $this->assertTrue($peerforums[0]['cancreatediscussions']);

        // Change the type of the peerforum, the user shouldn't be able to add discussions.
        $DB->set_field('peerforum', 'type', 'news', array('id' => $peerforum1->id));
        $peerforums = mod_peerforum_external::get_peerforums_by_courses();
        $peerforums = external_api::clean_returnvalue(mod_peerforum_external::get_peerforums_by_courses_returns(), $peerforums);
        $this->assertFalse($peerforums[0]['cancreatediscussions']);

        // Call for the second course we unenrolled the user from.
        $peerforums = mod_peerforum_external::get_peerforums_by_courses(array($course2->id));
        $peerforums = external_api::clean_returnvalue(mod_peerforum_external::get_peerforums_by_courses_returns(), $peerforums);
        $this->assertCount(0, $peerforums);
    }

    /**
     * Test get peerforum discussions
     */
    public function test_mod_peerforum_get_peerforum_discussions() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track peerforums.
        $CFG->peerforum_trackreadposts = true;

        // Create a user who can track peerforums.
        $record = new stdClass();
        $record->trackforums = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First peerforum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = PEERFORUM_TRACKING_OFF;
        $peerforum1 = self::getDataGenerator()->create_module('peerforum', $record);

        // Second peerforum of type 'qanda' with tracking enabled.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->type = 'qanda';
        $record->trackingtype = PEERFORUM_TRACKING_FORCED;
        $peerforum2 = self::getDataGenerator()->create_module('peerforum', $record);

        // Add discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user2->id;
        $record->peerforum = $peerforum2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // Add two replies to discussion 2 from different users.
        $record = new stdClass();
        $record->discussion = $discussion2->id;
        $record->parent = $discussion2->firstpost;
        $record->userid = $user1->id;
        $discussion2reply1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $record->parent = $discussion2reply1->id;
        $record->userid = $user3->id;
        $discussion2reply2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // Check the peerforums were correctly created.
        $this->assertEquals(2, $DB->count_records_select('peerforum', 'id = :peerforum1 OR id = :peerforum2',
                array('peerforum1' => $peerforum1->id, 'peerforum2' => $peerforum2->id)));

        // Check the discussions were correctly created.
        $this->assertEquals(2, $DB->count_records_select('peerforum_discussions', 'peerforum = :peerforum1 OR peerforum = :peerforum2',
                                                            array('peerforum1' => $peerforum1->id, 'peerforum2' => $peerforum2->id)));

        // Check the posts were correctly created, don't forget each discussion created also creates a post.
        $this->assertEquals(7, $DB->count_records_select('peerforum_posts', 'discussion = :discussion1 OR discussion = :discussion2',
                array('discussion1' => $discussion1->id, 'discussion2' => $discussion2->id)));

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/peerforum:viewdiscussion on all peerforums of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        // Now enrol into the second course.
        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user1->id);

        // Assign capabilities to view discussions for peerforum 2.
        $cm = get_coursemodule_from_id('peerforum', $peerforum2->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/peerforum:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the peerforums.
        $expecteddiscussions = array();
        $expecteddiscussions[] = array(
                'id' => $discussion1->id,
                'course' => $discussion1->course,
                'peerforum' => $discussion1->peerforum,
                'name' => $discussion1->name,
                'firstpost' => $discussion1->firstpost,
                'userid' => $discussion1->userid,
                'groupid' => $discussion1->groupid,
                'assessed' => $discussion1->assessed,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'firstuserfullname' => fullname($user1),
                'firstuserimagealt' => $user1->imagealt,
                'firstuserpicture' => $user1->picture,
                'firstuseremail' => $user1->email,
                'subject' => $discussion1->name,
                'numreplies' => 3,
                'numunread' => '',
                'lastpost' => $discussion1reply3->id,
                'lastuserid' => $user4->id,
                'lastuserfullname' => fullname($user4),
                'lastuserimagealt' => $user4->imagealt,
                'lastuserpicture' => $user4->picture,
                'lastuseremail' => $user4->email
            );
        $expecteddiscussions[] = array(
                'id' => $discussion2->id,
                'course' => $discussion2->course,
                'peerforum' => $discussion2->peerforum,
                'name' => $discussion2->name,
                'firstpost' => $discussion2->firstpost,
                'userid' => $discussion2->userid,
                'groupid' => $discussion2->groupid,
                'assessed' => $discussion2->assessed,
                'timemodified' => $discussion2reply2->created,
                'usermodified' => $discussion2reply2->userid,
                'timestart' => $discussion2->timestart,
                'timeend' => $discussion2->timeend,
                'firstuserfullname' => fullname($user2),
                'firstuserimagealt' => $user2->imagealt,
                'firstuserpicture' => $user2->picture,
                'firstuseremail' => $user2->email,
                'subject' => $discussion2->name,
                'numreplies' => 2,
                'numunread' => 3,
                'lastpost' => $discussion2reply2->id,
                'lastuserid' => $user3->id,
                'lastuserfullname' => fullname($user3),
                'lastuserimagealt' => $user3->imagealt,
                'lastuserpicture' => $user3->picture,
                'lastuseremail' => $user3->email
            );

        // Call the external function passing peerforum ids.
        $discussions = mod_peerforum_external::get_peerforum_discussions(array($peerforum1->id, $peerforum2->id));
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_returns(), $discussions);
        $this->assertEquals($expecteddiscussions, $discussions);
        // Some debugging is going to be produced, this is because we switch PAGE contexts in the get_peerforum_discussions function,
        // the switch happens when the validate_context function is called inside a foreach loop.
        // See MDL-41746 for more information.
        $this->assertDebuggingCalled();

        // Remove the users post from the qanda peerforum and ensure they can still see the discussion.
        $DB->delete_records('peerforum_posts', array('id' => $discussion2reply1->id));
        $discussions = mod_peerforum_external::get_peerforum_discussions(array($peerforum2->id));
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_returns(), $discussions);
        $this->assertEquals(1, count($discussions));

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/peerforum:viewdiscussion', null, null, $course1->id);
        try {
            mod_peerforum_external::get_peerforum_discussions(array($peerforum1->id));
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        $this->assertDebuggingCalled();

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_peerforum_external::get_peerforum_discussions(array($peerforum2->id));
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get peerforum posts
     */
    public function test_mod_peerforum_get_peerforum_discussion_posts() {
        global $CFG, $PAGE;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track peerforums.
        $CFG->peerforum_trackreadposts = true;

        // Create a user who can track peerforums.
        $record = new stdClass();
        $record->trackforums = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();

        // PeerForum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = PEERFORUM_TRACKING_OFF;
        $peerforum1 = self::getDataGenerator()->create_module('peerforum', $record);
        $peerforum1context = context_module::instance($peerforum1->cmid);

        // Add discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->peerforum = $peerforum1->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add 2 replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // Enrol the user in the  course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/peerforum:viewdiscussion on all peerforums of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Delete one user, to test that we still receive posts by this user.
        delete_user($user3);

        // Create what we expect to be returned when querying the discussion.
        $expectedposts = array(
            'posts' => array(),
            'warnings' => array(),
        );

        // User pictures are initially empty, we should get the links once the external function is called.
        $expectedposts['posts'][] = array(
            'id' => $discussion1reply2->id,
            'discussion' => $discussion1reply2->discussion,
            'parent' => $discussion1reply2->parent,
            'userid' => (int) $discussion1reply2->userid,
            'created' => $discussion1reply2->created,
            'modified' => $discussion1reply2->modified,
            'mailed' => $discussion1reply2->mailed,
            'subject' => $discussion1reply2->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply2->message, 'pluginfile.php',
                    $peerforum1context->id, 'mod_peerforum', 'post', $discussion1reply2->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply2->messagetrust,
            'attachment' => $discussion1reply2->attachment,
            'totalscore' => $discussion1reply2->totalscore,
            'mailnow' => $discussion1reply2->mailnow,
            'children' => array(),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user3),
            'userpictureurl' => ''
        );

        $expectedposts['posts'][] = array(
            'id' => $discussion1reply1->id,
            'discussion' => $discussion1reply1->discussion,
            'parent' => $discussion1reply1->parent,
            'userid' => (int) $discussion1reply1->userid,
            'created' => $discussion1reply1->created,
            'modified' => $discussion1reply1->modified,
            'mailed' => $discussion1reply1->mailed,
            'subject' => $discussion1reply1->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply1->message, 'pluginfile.php',
                    $peerforum1context->id, 'mod_peerforum', 'post', $discussion1reply1->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply1->messagetrust,
            'attachment' => $discussion1reply1->attachment,
            'totalscore' => $discussion1reply1->totalscore,
            'mailnow' => $discussion1reply1->mailnow,
            'children' => array($discussion1reply2->id),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user2),
            'userpictureurl' => ''
        );

        // Test a discussion with two additional posts (total 3 posts).
        $posts = mod_peerforum_external::get_peerforum_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));

        // Generate here the pictures because we need to wait to the external function to init the theme.
        $userpicture = new user_picture($user3);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user2);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][1]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        // Unset the initial discussion post.
        array_pop($posts['posts']);
        $this->assertEquals($expectedposts, $posts);

        // Test discussion without additional posts. There should be only one post (the one created by the discussion).
        $posts = mod_peerforum_external::get_peerforum_discussion_posts($discussion2->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

    }

    /**
     * Test get peerforum posts (qanda peerforum)
     */
    public function test_mod_peerforum_get_peerforum_discussion_posts_qanda() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $record = new stdClass();
        $user1 = self::getDataGenerator()->create_user($record);
        $user2 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // PeerForum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->type = 'qanda';
        $peerforum1 = self::getDataGenerator()->create_module('peerforum', $record);
        $peerforum1context = context_module::instance($peerforum1->cmid);

        // Add discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->peerforum = $peerforum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add 1 reply (not the actual user).
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // We still see only the original post.
        $posts = mod_peerforum_external::get_peerforum_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

        // Add a new reply, the user is going to be able to see only the original post and their new post.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user1->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $posts = mod_peerforum_external::get_peerforum_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussion_posts_returns(), $posts);
        $this->assertEquals(2, count($posts['posts']));

        // Now, we can fake the time of the user post, so he can se the rest of the discussion posts.
        $discussion1reply2->created -= $CFG->maxeditingtime * 2;
        $DB->update_record('peerforum_posts', $discussion1reply2);

        $posts = mod_peerforum_external::get_peerforum_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));
    }

    /**
     * Test get peerforum discussions paginated
     */
    public function test_mod_peerforum_get_peerforum_discussions_paginated() {
        global $USER, $CFG, $DB, $PAGE;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track peerforums.
        $CFG->peerforum_trackreadposts = true;

        // Create a user who can track peerforums.
        $record = new stdClass();
        $record->trackforums = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();

        // First peerforum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = PEERFORUM_TRACKING_OFF;
        $peerforum1 = self::getDataGenerator()->create_module('peerforum', $record);

        // Add discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');

        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course1->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance1 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance1, $user1->id);

        // Delete one user.
        delete_user($user4);

        // Assign capabilities to view discussions for peerforum 1.
        $cm = get_coursemodule_from_id('peerforum', $peerforum1->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/peerforum:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the peerforums.

        $post1 = $DB->get_record('peerforum_posts', array('id' => $discussion1->firstpost), '*', MUST_EXIST);

        // User pictures are initially empty, we should get the links once the external function is called.
        $expecteddiscussions = array(
                'id' => $discussion1->firstpost,
                'name' => $discussion1->name,
                'groupid' => $discussion1->groupid,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'discussion' => $discussion1->id,
                'parent' => 0,
                'userid' => $discussion1->userid,
                'created' => $post1->created,
                'modified' => $post1->modified,
                'mailed' => $post1->mailed,
                'subject' => $post1->subject,
                'message' => $post1->message,
                'messageformat' => $post1->messageformat,
                'messagetrust' => $post1->messagetrust,
                'attachment' => $post1->attachment,
                'totalscore' => $post1->totalscore,
                'mailnow' => $post1->mailnow,
                'userfullname' => fullname($user1),
                'usermodifiedfullname' => fullname($user4),
                'userpictureurl' => '',
                'usermodifiedpictureurl' => '',
                'numreplies' => 3,
                'numunread' => 0
            );

        // Call the external function passing peerforum id.
        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum1->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);
        $expectedreturn = array(
            'discussions' => array($expecteddiscussions),
            'warnings' => array()
        );

        // Wait the theme to be loaded (the external_api call does that) to generate the user profiles.
        $userpicture = new user_picture($user1);
        $userpicture->size = 1; // Size f1.
        $expectedreturn['discussions'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user4);
        $userpicture->size = 1; // Size f1.
        $expectedreturn['discussions'][0]['usermodifiedpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $this->assertEquals($expectedreturn, $discussions);

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/peerforum:viewdiscussion', $context->id, $newrole);
        try {
            mod_peerforum_external::get_peerforum_discussions_paginated($peerforum1->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('noviewdiscussionspermission', $e->errorcode);
        }

        // Unenrol user from second course.
        $enrol->unenrol_user($instance1, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_peerforum_external::get_peerforum_discussions_paginated($peerforum1->id);
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get peerforum discussions paginated (qanda peerforums)
     */
    public function test_mod_peerforum_get_peerforum_discussions_paginated_qanda() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First peerforum with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'qanda';
        $peerforum = self::getDataGenerator()->create_module('peerforum', $record);

        // Add discussions to the peerforums.
        $discussionrecord = new stdClass();
        $discussionrecord->course = $course->id;
        $discussionrecord->userid = $user2->id;
        $discussionrecord->peerforum = $peerforum->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($discussionrecord);

        self::setAdminUser();
        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

    }

    /**
     * Test add_discussion_post
     */
    public function test_add_discussion_post() {
        global $CFG;

        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        $otheruser = self::getDataGenerator()->create_user();

        self::setAdminUser();

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));

        // PeerForum with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $peerforum = self::getDataGenerator()->create_module('peerforum', $record);
        $cm = get_coursemodule_from_id('peerforum', $peerforum->cmid, 0, false, MUST_EXIST);
        $peerforumcontext = context_module::instance($peerforum->cmid);

        // Add discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->peerforum = $peerforum->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Try to post (user not enrolled).
        self::setUser($user);
        try {
            mod_peerforum_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->getDataGenerator()->enrol_user($otheruser->id, $course->id);

        $post = mod_peerforum_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
        $post = external_api::clean_returnvalue(mod_peerforum_external::add_discussion_post_returns(), $post);

        $posts = mod_peerforum_external::get_peerforum_discussion_posts($discussion->id);
        $posts = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussion_posts_returns(), $posts);
        // We receive the discussion and the post.
        $this->assertEquals(2, count($posts['posts']));

        $tested = false;
        foreach ($posts['posts'] as $postel) {
            if ($post['postid'] == $postel['id']) {
                $this->assertEquals('some subject', $postel['subject']);
                $this->assertEquals('some text here...', $postel['message']);
                $tested = true;
            }
        }
        $this->assertTrue($tested);

        // Check not posting in groups the user is not member of.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group->id, $otheruser->id);

        $peerforum = self::getDataGenerator()->create_module('peerforum', $record, array('groupmode' => SEPARATEGROUPS));
        $record->peerforum = $peerforum->id;
        $record->userid = $otheruser->id;
        $record->groupid = $group->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        try {
            mod_peerforum_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
            $this->fail('Exception expected due to invalid permissions for posting.');
        } catch (moodle_exception $e) {
            // Expect debugging since we are switching context, and this is something WS_SERVER mode don't like.
            $this->assertDebuggingCalled();
            $this->assertEquals('nopostpeerforum', $e->errorcode);
        }

    }

    /*
     * Test add_discussion. A basic test since all the API functions are already covered by unit tests.
     */
    public function test_add_discussion() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First peerforum with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'news';
        $peerforum = self::getDataGenerator()->create_module('peerforum', $record);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        try {
            mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...');
            $this->fail('Exception expected due to invalid permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        self::setAdminUser();
        $discussion = mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_peerforum_external::add_discussion_returns(), $discussion);

        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        $this->assertEquals($discussion['discussionid'], $discussions['discussions'][0]['discussion']);
        $this->assertEquals(-1, $discussions['discussions'][0]['groupid']);
        $this->assertEquals('the subject', $discussions['discussions'][0]['subject']);
        $this->assertEquals('some text here...', $discussions['discussions'][0]['message']);

    }

    /**
     * Test adding discussions in a course with gorups
     */
    public function test_add_discussion_in_course_with_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));
        $user = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // PeerForum forcing separate gropus.
        $record = new stdClass();
        $record->course = $course->id;
        $peerforum = self::getDataGenerator()->create_module('peerforum', $record, array('groupmode' => SEPARATEGROUPS));

        // Try to post (user not enrolled).
        self::setUser($user);

        // The user is not enroled in any group, try to post in a peerforum with separate groups.
        try {
            mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...');
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        try {
            mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...', 0);
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Create a group.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        // Try to post in a group the user is not enrolled.
        try {
            mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...', $group->id);
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Add the user to a group.
        groups_add_member($group->id, $user->id);

        // Try to post in a group the user is not enrolled.
        try {
            mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...', $group->id + 1);
            $this->fail('Exception expected due to invalid group.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Nost add the discussion using a valid group.
        $discussion = mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...', $group->id);
        $discussion = external_api::clean_returnvalue(mod_peerforum_external::add_discussion_returns(), $discussion);

        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($discussion['discussionid'], $discussions['discussions'][0]['discussion']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);

        // Now add a discussions without indicating a group. The function should guess the correct group.
        $discussion = mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_peerforum_external::add_discussion_returns(), $discussion);

        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);

        $this->assertCount(2, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][1]['groupid']);

        // Enrol the same user in other group.
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group2->id, $user->id);

        // Now add a discussions without indicating a group. The function should guess the correct group (the first one).
        $discussion = mod_peerforum_external::add_discussion($peerforum->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_peerforum_external::add_discussion_returns(), $discussion);

        $discussions = mod_peerforum_external::get_peerforum_discussions_paginated($peerforum->id);
        $discussions = external_api::clean_returnvalue(mod_peerforum_external::get_peerforum_discussions_paginated_returns(), $discussions);

        $this->assertCount(3, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][1]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][2]['groupid']);

    }

    /*
     * Test can_add_discussion. A basic test since all the API functions are already covered by unit tests.
     */
    public function test_can_add_discussion() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();

        // First peerforum with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'news';
        $peerforum = self::getDataGenerator()->create_module('peerforum', $record);

        // User with no permissions to add in a news peerforum.
        self::setUser($user);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $result = mod_peerforum_external::can_add_discussion($peerforum->id);
        $result = external_api::clean_returnvalue(mod_peerforum_external::can_add_discussion_returns(), $result);
        $this->assertFalse($result['status']);

        self::setAdminUser();
        $result = mod_peerforum_external::can_add_discussion($peerforum->id);
        $result = external_api::clean_returnvalue(mod_peerforum_external::can_add_discussion_returns(), $result);
        $this->assertTrue($result['status']);

    }

}
