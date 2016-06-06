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
 * The module peerforums tests
 *
 * @package    mod_peerforum
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mod_peerforum_lib_testcase extends advanced_testcase {

    public function test_peerforum_trigger_content_uploaded_event() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $peerforum = $this->getDataGenerator()->create_module('peerforum', array('course' => $course->id));
        $context = context_module::instance($peerforum->cmid);

        $this->setUser($user->id);
        $fakepost = (object) array('id' => 123, 'message' => 'Yay!', 'discussion' => 100);
        $cm = get_coursemodule_from_instance('peerforum', $peerforum->id);

        $fs = get_file_storage();
        $dummy = (object) array(
            'contextid' => $context->id,
            'component' => 'mod_peerforum',
            'filearea' => 'attachment',
            'itemid' => $fakepost->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.pdf'
        );
        $fi = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);

        $data = new stdClass();
        $sink = $this->redirectEvents();
        peerforum_trigger_content_uploaded_event($fakepost, $cm, 'some triggered from value');
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_peerforum\event\assessable_uploaded', $event);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($fakepost->id, $event->objectid);
        $this->assertEquals($fakepost->message, $event->other['content']);
        $this->assertEquals($fakepost->discussion, $event->other['discussionid']);
        $this->assertCount(1, $event->other['pathnamehashes']);
        $this->assertEquals($fi->get_pathnamehash(), $event->other['pathnamehashes'][0]);
        $expected = new stdClass();
        $expected->modulename = 'peerforum';
        $expected->name = 'some triggered from value';
        $expected->cmid = $peerforum->cmid;
        $expected->itemid = $fakepost->id;
        $expected->courseid = $course->id;
        $expected->userid = $user->id;
        $expected->content = $fakepost->message;
        $expected->pathnamehashes = array($fi->get_pathnamehash());
        $this->assertEventLegacyData($expected, $event);
    }

    public function test_peerforum_get_courses_user_posted_in() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // Create 3 peerforums, one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $peerforum1 = $this->getDataGenerator()->create_module('peerforum', $record);

        $record = new stdClass();
        $record->course = $course2->id;
        $peerforum2 = $this->getDataGenerator()->create_module('peerforum', $record);

        $record = new stdClass();
        $record->course = $course3->id;
        $peerforum3 = $this->getDataGenerator()->create_module('peerforum', $record);

        // Add a second peerforum in course 1.
        $record = new stdClass();
        $record->course = $course1->id;
        $peerforum4 = $this->getDataGenerator()->create_module('peerforum', $record);

        // Add discussions to course 1 started by user1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum4->id;
        $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add discussions to course2 started by user1.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum2->id;
        $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add discussions to course 3 started by user2.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user2->id;
        $record->peerforum = $peerforum3->id;
        $discussion3 = $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add post to course 3 by user1.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user1->id;
        $record->peerforum = $peerforum3->id;
        $record->discussion = $discussion3->id;
        $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // User 3 hasn't posted anything, so shouldn't get any results.
        $user3courses = peerforum_get_courses_user_posted_in($user3);
        $this->assertEmpty($user3courses);

        // User 2 has only posted in course3.
        $user2courses = peerforum_get_courses_user_posted_in($user2);
        $this->assertCount(1, $user2courses);
        $user2course = array_shift($user2courses);
        $this->assertEquals($course3->id, $user2course->id);
        $this->assertEquals($course3->shortname, $user2course->shortname);

        // User 1 has posted in all 3 courses.
        $user1courses = peerforum_get_courses_user_posted_in($user1);
        $this->assertCount(3, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id, $course3->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname,
                $course3->shortname));

        }

        // User 1 has only started a discussion in course 1 and 2 though.
        $user1courses = peerforum_get_courses_user_posted_in($user1, true);
        $this->assertCount(2, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname));
        }
    }

    /**
     * Test user_enrolment_deleted observer.
     */
    public function test_user_enrolment_deleted_observer() {
        global $DB;

        $this->resetAfterTest();

        $metaplugin = enrol_get_plugin('meta');
        $user1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $student = $DB->get_record('role', array('shortname' => 'student'));

        $e1 = $metaplugin->add_instance($course2, array('customint1' => $course1->id));
        $enrol1 = $DB->get_record('enrol', array('id' => $e1));

        // Enrol user.
        $metaplugin->enrol_user($enrol1, $user1->id, $student->id);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));

        // Unenrol user and capture event.
        $sink = $this->redirectEvents();
        $metaplugin->unenrol_user($enrol1, $user1->id);
        $events = $sink->get_events();
        $sink->close();
        $event = array_pop($events);

        $this->assertEquals(0, $DB->count_records('user_enrolments'));
        $this->assertInstanceOf('\core\event\user_enrolment_deleted', $event);
        $this->assertEquals('user_unenrolled', $event->get_legacy_eventname());
    }

    /**
     * Test the logic in the peerforum_tp_can_track_peerforums() function.
     */
    public function test_peerforum_tp_can_track_peerforums() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OFF); // Off.
        $peerforumoff = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_FORCED); // On.
        $peerforumforce = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OPTIONAL); // Optional.
        $peerforumoptional = $this->getDataGenerator()->create_module('peerforum', $options);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        // User on, peerforum off, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, peerforum on, should be on.
        $result = peerforum_tp_can_track_peerforums($peerforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, peerforum optional, should be on.
        $result = peerforum_tp_can_track_peerforums($peerforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, peerforum off, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, peerforum force, should be on.
        $result = peerforum_tp_can_track_peerforums($peerforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, peerforum optional, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        // User on, peerforum off, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, peerforum on, should be on.
        $result = peerforum_tp_can_track_peerforums($peerforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, peerforum optional, should be on.
        $result = peerforum_tp_can_track_peerforums($peerforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, peerforum off, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, peerforum force, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, peerforum optional, should be off.
        $result = peerforum_tp_can_track_peerforums($peerforumoptional, $useroff);
        $this->assertEquals(false, $result);

    }

    /**
     * Test the logic in the test_peerforum_tp_is_tracked() function.
     */
    public function test_peerforum_tp_is_tracked() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OFF); // Off.
        $peerforumoff = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_FORCED); // On.
        $peerforumforce = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OPTIONAL); // Optional.
        $peerforumoptional = $this->getDataGenerator()->create_module('peerforum', $options);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        // User on, peerforum off, should be off.
        $result = peerforum_tp_is_tracked($peerforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, peerforum force, should be on.
        $result = peerforum_tp_is_tracked($peerforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, peerforum optional, should be on.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, peerforum off, should be off.
        $result = peerforum_tp_is_tracked($peerforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, peerforum force, should be on.
        $result = peerforum_tp_is_tracked($peerforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, peerforum optional, should be off.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        // User on, peerforum off, should be off.
        $result = peerforum_tp_is_tracked($peerforumoff, $useron);
        $this->assertEquals(false, $result);

        // User on, peerforum force, should be on.
        $result = peerforum_tp_is_tracked($peerforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, peerforum optional, should be on.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, peerforum off, should be off.
        $result = peerforum_tp_is_tracked($peerforumoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, peerforum force, should be off.
        $result = peerforum_tp_is_tracked($peerforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, peerforum optional, should be off.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        peerforum_tp_stop_tracking($peerforumforce->id, $useron->id);
        peerforum_tp_stop_tracking($peerforumoptional->id, $useron->id);
        peerforum_tp_stop_tracking($peerforumforce->id, $useroff->id);
        peerforum_tp_stop_tracking($peerforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        // User on, preference off, peerforum force, should be on.
        $result = peerforum_tp_is_tracked($peerforumforce, $useron);
        $this->assertEquals(true, $result);

        // User on, preference off, peerforum optional, should be on.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, peerforum force, should be on.
        $result = peerforum_tp_is_tracked($peerforumforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, preference off, peerforum optional, should be off.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        // User on, preference off, peerforum force, should be on.
        $result = peerforum_tp_is_tracked($peerforumforce, $useron);
        $this->assertEquals(false, $result);

        // User on, preference off, peerforum optional, should be on.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, peerforum force, should be off.
        $result = peerforum_tp_is_tracked($peerforumforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, preference off, peerforum optional, should be off.
        $result = peerforum_tp_is_tracked($peerforumoptional, $useroff);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the peerforum_tp_get_course_unread_posts() function.
     */
    public function test_peerforum_tp_get_course_unread_posts() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OFF); // Off.
        $peerforumoff = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_FORCED); // On.
        $peerforumforce = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OPTIONAL); // Optional.
        $peerforumoptional = $this->getDataGenerator()->create_module('peerforum', $options);

        // Add discussions to the tracking off peerforum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->peerforum = $peerforumoff->id;
        $discussionoff = $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add discussions to the tracking forced peerforum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->peerforum = $peerforumforce->id;
        $discussionforce = $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Add post to the tracking forced discussion.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useroff->id;
        $record->peerforum = $peerforumforce->id;
        $record->discussion = $discussionforce->id;
        $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_post($record);

        // Add discussions to the tracking optional peerforum.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->peerforum = $peerforumoptional->id;
        $discussionoptional = $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        $result = peerforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));
        $this->assertEquals(2, $result[$peerforumforce->id]->unread);
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));
        $this->assertEquals(1, $result[$peerforumoptional->id]->unread);

        $result = peerforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));
        $this->assertEquals(2, $result[$peerforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$peerforumoptional->id]));

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        $result = peerforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));
        $this->assertEquals(2, $result[$peerforumforce->id]->unread);
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));
        $this->assertEquals(1, $result[$peerforumoptional->id]->unread);

        $result = peerforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(false, isset($result[$peerforumforce->id]));
        $this->assertEquals(false, isset($result[$peerforumoptional->id]));

        // Stop tracking so we can test again.
        peerforum_tp_stop_tracking($peerforumforce->id, $useron->id);
        peerforum_tp_stop_tracking($peerforumoptional->id, $useron->id);
        peerforum_tp_stop_tracking($peerforumforce->id, $useroff->id);
        peerforum_tp_stop_tracking($peerforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        $result = peerforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));
        $this->assertEquals(2, $result[$peerforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$peerforumoptional->id]));

        $result = peerforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));
        $this->assertEquals(2, $result[$peerforumforce->id]->unread);
        $this->assertEquals(false, isset($result[$peerforumoptional->id]));

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        $result = peerforum_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(false, isset($result[$peerforumforce->id]));
        $this->assertEquals(false, isset($result[$peerforumoptional->id]));

        $result = peerforum_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$peerforumoff->id]));
        $this->assertEquals(false, isset($result[$peerforumforce->id]));
        $this->assertEquals(false, isset($result[$peerforumoptional->id]));
    }

    /**
     * Test the logic in the test_peerforum_tp_get_untracked_peerforums() function.
     */
    public function test_peerforum_tp_get_untracked_peerforums() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackforums' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackforums' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OFF); // Off.
        $peerforumoff = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_FORCED); // On.
        $peerforumforce = $this->getDataGenerator()->create_module('peerforum', $options);

        $options = array('course' => $course->id, 'trackingtype' => PEERFORUM_TRACKING_OPTIONAL); // Optional.
        $peerforumoptional = $this->getDataGenerator()->create_module('peerforum', $options);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        // On user with force on.
        $result = peerforum_tp_get_untracked_peerforums($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));

        // Off user with force on.
        $result = peerforum_tp_get_untracked_peerforums($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        // On user with force off.
        $result = peerforum_tp_get_untracked_peerforums($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));

        // Off user with force off.
        $result = peerforum_tp_get_untracked_peerforums($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));

        // Stop tracking so we can test again.
        peerforum_tp_stop_tracking($peerforumforce->id, $useron->id);
        peerforum_tp_stop_tracking($peerforumoptional->id, $useron->id);
        peerforum_tp_stop_tracking($peerforumforce->id, $useroff->id);
        peerforum_tp_stop_tracking($peerforumoptional->id, $useroff->id);

        // Allow force.
        $CFG->peerforum_allowforcedreadtracking = 1;

        // On user with force on.
        $result = peerforum_tp_get_untracked_peerforums($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));

        // Off user with force on.
        $result = peerforum_tp_get_untracked_peerforums($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));

        // Don't allow force.
        $CFG->peerforum_allowforcedreadtracking = 0;

        // On user with force off.
        $result = peerforum_tp_get_untracked_peerforums($useron->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));

        // Off user with force off.
        $result = peerforum_tp_get_untracked_peerforums($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$peerforumoff->id]));
        $this->assertEquals(true, isset($result[$peerforumoptional->id]));
        $this->assertEquals(true, isset($result[$peerforumforce->id]));
    }

    /**
     * Test subscription using automatic subscription on create.
     */
    public function test_peerforum_auto_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_INITIALSUBSCRIBE); // Automatic Subscription.
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        $result = peerforum_subscribed_users($course, $peerforum);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(peerforum_is_subscribed($user->id, $peerforum));
        }
    }

    /**
     * Test subscription using forced subscription on create.
     */
    public function test_peerforum_forced_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_FORCESUBSCRIBE); // Forced subscription.
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        $result = peerforum_subscribed_users($course, $peerforum);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(peerforum_is_subscribed($user->id, $peerforum));
        }
    }

    /**
     * Test subscription using optional subscription on create.
     */
    public function test_peerforum_optional_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_CHOOSESUBSCRIBE); // Subscription optional.
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        $result = peerforum_subscribed_users($course, $peerforum);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(peerforum_is_subscribed($user->id, $peerforum));
        }
    }

    /**
     * Test subscription using disallow subscription on create.
     */
    public function test_peerforum_disallow_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_DISALLOWSUBSCRIBE); // Subscription prevented.
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        $result = peerforum_subscribed_users($course, $peerforum);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(peerforum_is_subscribed($user->id, $peerforum));
        }
    }
}
