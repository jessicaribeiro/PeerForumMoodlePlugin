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
 * The peerforum module mail generation tests.
 *
 * @package    mod_peerforum
 * @category   external
 * @copyright  2013 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

class mod_peerforum_mail_testcase extends advanced_testcase {

    protected $helper;

    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_peerforum\subscriptions::reset_peerforum_cache();
        \mod_peerforum\subscriptions::reset_discussion_cache();

        global $CFG;
        require_once($CFG->dirroot . '/mod/peerforum/lib.php');

        $helper = new stdClass();

        // Messaging is not compatible with transactions...
        $this->preventResetByRollback();

        // Catch all messages.
        $helper->messagesink = $this->redirectMessages();
        $helper->mailsink = $this->redirectEmails();

        // Confirm that we have an empty message sink so far.
        $messages = $helper->messagesink->get_messages();
        $this->assertEquals(0, count($messages));

        $messages = $helper->mailsink->get_messages();
        $this->assertEquals(0, count($messages));

        // Forcibly reduce the maxeditingtime to a second in the past to
        // ensure that messages are sent out.
        $CFG->maxeditingtime = -1;

        $this->helper = $helper;
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_peerforum\subscriptions::reset_peerforum_cache();

        $this->helper->messagesink->clear();
        $this->helper->messagesink->close();

        $this->helper->mailsink->clear();
        $this->helper->mailsink->close();
    }

    /**
     * Perform message inbound setup for the mod_peerforum reply handler.
     */
    protected function helper_spoof_message_inbound_setup() {
        global $CFG, $DB;
        // Setup the default Inbound Message mailbox settings.
        $CFG->messageinbound_domain = 'example.com';
        $CFG->messageinbound_enabled = true;

        // Must be no longer than 15 characters.
        $CFG->messageinbound_mailbox = 'moodlemoodle123';

        $record = $DB->get_record('messageinbound_handlers', array('classname' => '\mod_peerforum\message\inbound\reply_handler'));
        $record->enabled = true;
        $record->id = $DB->update_record('messageinbound_handlers', $record);
    }

    /**
     * Helper to create the required number of users in the specified
     * course.
     * Users are enrolled as students.
     *
     * @param stdClass $course The course object
     * @param integer $count The number of users to create
     * @return array The users created
     */
    protected function helper_create_users($course, $count) {
        $users = array();

        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Create a new discussion and post within the specified peerforum, as the
     * specified author.
     *
     * @param stdClass $peerforum The peerforum to post in
     * @param stdClass $author The author to post as
     * @param array $fields any other fields in discussion (name, message, messageformat, ...)
     * @param array An array containing the discussion object, and the post object
     */
    protected function helper_post_to_peerforum($peerforum, $author, $fields = array()) {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_peerforum');

        // Create a discussion in the peerforum, and then add a post to that discussion.
        $record = (object)$fields;
        $record->course = $peerforum->course;
        $record->userid = $author->id;
        $record->peerforum = $peerforum->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the post which was created by create_discussion.
        $post = $DB->get_record('peerforum_posts', array('discussion' => $discussion->id));

        return array($discussion, $post);
    }

    /**
     * Update the post time for the specified post by $factor.
     *
     * @param stdClass $post The post to update
     * @param int $factor The amount to update by
     */
    protected function helper_update_post_time($post, $factor) {
        global $DB;

        // Update the post to have a created in the past.
        $DB->set_field('peerforum_posts', 'created', $post->created + $factor, array('id' => $post->id));
    }

    /**
     * Update the subscription time for the specified user/discussion by $factor.
     *
     * @param stdClass $user The user to update
     * @param stdClass $discussion The discussion to update for this user
     * @param int $factor The amount to update by
     */
    protected function helper_update_subscription_time($user, $discussion, $factor) {
        global $DB;

        $sub = $DB->get_record('peerforum_discussion_subs', array('userid' => $user->id, 'discussion' => $discussion->id));

        // Update the subscription to have a preference in the past.
        $DB->set_field('peerforum_discussion_subs', 'preference', $sub->preference + $factor, array('id' => $sub->id));
    }

    /**
     * Create a new post within an existing discussion, as the specified author.
     *
     * @param stdClass $peerforum The peerforum to post in
     * @param stdClass $discussion The discussion to post in
     * @param stdClass $author The author to post as
     * @return stdClass The peerforum post
     */
    protected function helper_post_to_discussion($peerforum, $discussion, $author) {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_peerforum');

        // Add a post to the discussion.
        $record = new stdClass();
        $record->course = $peerforum->course;
        $record->userid = $author->id;
        $record->peerforum = $peerforum->id;
        $record->discussion = $discussion->id;
        $record->mailnow = 1;

        $post = $generator->create_post($record);

        return $post;
    }

    /**
     * Run the peerforum cron, and check that the specified post was sent the
     * specified number of times.
     *
     * @param stdClass $post The peerforum post object
     * @param integer $expected The number of times that the post should have been sent
     * @return array An array of the messages caught by the message sink
     */
    protected function helper_run_cron_check_count($post, $expected) {

        // Clear the sinks before running cron.
        $this->helper->messagesink->clear();
        $this->helper->mailsink->clear();

        // Cron daily uses mtrace, turn on buffering to silence output.
        $this->expectOutputRegex("/{$expected} users were sent post {$post->id}, '{$post->subject}'/");
        peerforum_cron();

        // Now check the results in the message sink.
        $messages = $this->helper->messagesink->get_messages();

        // There should be the expected number of messages.
        $this->assertEquals($expected, count($messages));

        return $messages;
    }

    /**
     * Run the peerforum cron, and check that the specified posts were sent the
     * specified number of times.
     *
     * @param stdClass $post The peerforum post object
     * @param integer $expected The number of times that the post should have been sent
     * @return array An array of the messages caught by the message sink
     */
    protected function helper_run_cron_check_counts($posts, $expected) {

        // Clear the sinks before running cron.
        $this->helper->messagesink->clear();
        $this->helper->mailsink->clear();

        // Cron daily uses mtrace, turn on buffering to silence output.
        foreach ($posts as $post) {
            $this->expectOutputRegex("/{$post['count']} users were sent post {$post['id']}, '{$post['subject']}'/");
        }
        peerforum_cron();

        // Now check the results in the message sink.
        $messages = $this->helper->messagesink->get_messages();

        // There should be the expected number of messages.
        $this->assertEquals($expected, count($messages));

        return $messages;
    }

    public function test_forced_subscription() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_FORCESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // We expect both users to receive this post.
        $expected = 2;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we saw messages for both users.
        $this->assertTrue($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_subscription_disabled() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_DISALLOWSUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // We expect both users to receive this post.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // A user with the manageactivities capability within the course can subscribe.
        $expected = 1;
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleids['student'], context_course::instance($course->id));
        \mod_peerforum\subscriptions::subscribe_user($recipient->id, $peerforum);

        $this->assertEquals($expected, $DB->count_records('peerforum_subscriptions', array(
            'userid'        => $recipient->id,
            'peerforum'         => $peerforum->id,
        )));

        // Run cron and check that the expected number of users received the notification.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $recipient);
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // Unsubscribe the user again.
        \mod_peerforum\subscriptions::unsubscribe_user($recipient->id, $peerforum);

        $expected = 0;
        $this->assertEquals($expected, $DB->count_records('peerforum_subscriptions', array(
            'userid'        => $recipient->id,
            'peerforum'         => $peerforum->id,
        )));

        // Run cron and check that the expected number of users received the notification.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // Subscribe the user to the discussion.
        \mod_peerforum\subscriptions::subscribe_user_to_discussion($recipient->id, $discussion);
        $this->helper_update_subscription_time($recipient, $discussion, -60);

        $reply = $this->helper_post_to_discussion($peerforum, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        $messages = $this->helper_run_cron_check_count($reply, $expected);
    }

    public function test_automatic() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_INITIALSUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // We expect both users to receive this post.
        $expected = 2;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we saw messages for both users.
        $this->assertTrue($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_CHOOSESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // We expect both users to receive this post.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);
    }

    public function test_automatic_with_unsubscribed_user() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_INITIALSUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Unsubscribe the 'author' user from the peerforum.
        \mod_peerforum\subscriptions::unsubscribe_user($author->id, $peerforum);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional_with_subscribed_user() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_CHOOSESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Subscribe the 'recipient' user from the peerforum.
        \mod_peerforum\subscriptions::subscribe_user($recipient->id, $peerforum);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_automatic_with_unsubscribed_discussion() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_INITIALSUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // Unsubscribe the 'author' user from the discussion.
        \mod_peerforum\subscriptions::unsubscribe_user_from_discussion($author->id, $discussion);

        $this->assertFalse(\mod_peerforum\subscriptions::is_subscribed($author->id, $peerforum, $discussion->id));
        $this->assertTrue(\mod_peerforum\subscriptions::is_subscribed($recipient->id, $peerforum, $discussion->id));

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional_with_subscribed_discussion() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_CHOOSESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);
        $this->helper_update_post_time($post, -90);

        // Subscribe the 'recipient' user to the discussion.
        \mod_peerforum\subscriptions::subscribe_user_to_discussion($recipient->id, $discussion);
        $this->helper_update_subscription_time($recipient, $discussion, -60);

        // Initially we don't expect any user to receive this post as you cannot subscribe to a discussion until after
        // you have read it.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        // Have a user reply to the discussion.
        $reply = $this->helper_post_to_discussion($peerforum, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        // We expect only one user to receive this post.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($reply, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_automatic_with_subscribed_discussion_in_unsubscribed_peerforum() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_INITIALSUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);
        $this->helper_update_post_time($post, -90);

        // Unsubscribe the 'author' user from the peerforum.
        \mod_peerforum\subscriptions::unsubscribe_user($author->id, $peerforum);

        // Then re-subscribe them to the discussion.
        \mod_peerforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion);
        $this->helper_update_subscription_time($author, $discussion, -60);

        // We expect just the user subscribed to the peerforum to receive this post at the moment as the discussion
        // subscription time is after the post time.
        $expected = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we only saw one user.
        $this->assertFalse($seenauthor);
        $this->assertTrue($seenrecipient);

        // Now post a reply to the original post.
        $reply = $this->helper_post_to_discussion($peerforum, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        // We expect two users to receive this post.
        $expected = 2;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($reply, $expected);

        $seenauthor = false;
        $seenrecipient = false;
        foreach ($messages as $message) {
            // They should both be from our user.
            $this->assertEquals($author->id, $message->useridfrom);

            if ($message->useridto == $author->id) {
                $seenauthor = true;
            } else if ($message->useridto = $recipient->id) {
                $seenrecipient = true;
            }
        }

        // Check we saw both users.
        $this->assertTrue($seenauthor);
        $this->assertTrue($seenrecipient);
    }

    public function test_optional_with_unsubscribed_discussion_in_subscribed_peerforum() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_CHOOSESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create two users enrolled in the course as students.
        list($author, $recipient) = $this->helper_create_users($course, 2);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);

        // Unsubscribe the 'recipient' user from the discussion.
        \mod_peerforum\subscriptions::subscribe_user($recipient->id, $peerforum);

        // Then unsubscribe them from the discussion.
        \mod_peerforum\subscriptions::unsubscribe_user_from_discussion($recipient->id, $discussion);

        // We don't expect any users to receive this post.
        $expected = 0;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, $expected);
    }

    /**
     * Test that a user unsubscribed from a peerforum who has subscribed to a discussion, only receives posts made after
     * they subscribed to the discussion.
     */
    public function test_peerforum_discussion_subscription_peerforum_unsubscribed_discussion_subscribed_after_post() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_CHOOSESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        $expectedmessages = array();

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);
        $this->helper_update_post_time($post, -90);

        $expectedmessages[] = array(
            'id' => $post->id,
            'subject' => $post->subject,
            'count' => 0,
        );

        // Then subscribe the user to the discussion.
        $this->assertTrue(\mod_peerforum\subscriptions::subscribe_user_to_discussion($author->id, $discussion));
        $this->helper_update_subscription_time($author, $discussion, -60);

        // Then post a reply to the first discussion.
        $reply = $this->helper_post_to_discussion($peerforum, $discussion, $author);
        $this->helper_update_post_time($reply, -30);

        $expectedmessages[] = array(
            'id' => $reply->id,
            'subject' => $reply->subject,
            'count' => 1,
        );

        $expectedcount = 1;

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_counts($expectedmessages, $expectedcount);
    }

    public function test_peerforum_message_inbound_multiple_posts() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_FORCESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create a user enrolled in the course as a student.
        list($author) = $this->helper_create_users($course, 1);

        $expectedmessages = array();

        // Post a discussion to the peerforum.
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author);
        $this->helper_update_post_time($post, -90);

        $expectedmessages[] = array(
            'id' => $post->id,
            'subject' => $post->subject,
            'count' => 0,
        );

        // Then post a reply to the first discussion.
        $reply = $this->helper_post_to_discussion($peerforum, $discussion, $author);
        $this->helper_update_post_time($reply, -60);

        $expectedmessages[] = array(
            'id' => $reply->id,
            'subject' => $reply->subject,
            'count' => 1,
        );

        $expectedcount = 2;

        // Ensure that messageinbound is enabled and configured for the peerforum handler.
        $this->helper_spoof_message_inbound_setup();

        $author->emailstop = '0';
        set_user_preference('message_provider_mod_peerforum_posts_loggedoff', 'email', $author);
        set_user_preference('message_provider_mod_peerforum_posts_loggedin', 'email', $author);

        // Run cron and check that the expected number of users received the notification.
        // Clear the mailsink, and close the messagesink.
        $this->helper->mailsink->clear();
        $this->helper->messagesink->close();

        // Cron daily uses mtrace, turn on buffering to silence output.
        foreach ($expectedmessages as $post) {
            $this->expectOutputRegex("/{$post['count']} users were sent post {$post['id']}, '{$post['subject']}'/");
        }

        peerforum_cron();
        $messages = $this->helper->mailsink->get_messages();

        // There should be the expected number of messages.
        $this->assertEquals($expectedcount, count($messages));

        foreach ($messages as $message) {
            $this->assertRegExp('/Reply-To: moodlemoodle123\+[^@]*@example.com/', $message->header);
        }
    }

    public function test_long_subject() {
        $this->resetAfterTest(true);

        // Create a course, with a peerforum.
        $course = $this->getDataGenerator()->create_course();

        $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_FORCESUBSCRIBE);
        $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

        // Create a user enrolled in the course as student.
        list($author) = $this->helper_create_users($course, 1);

        // Post a discussion to the peerforum.
        $subject = 'This is the very long peerforum post subject that somebody was very kind of leaving, it is intended to check if long subject comes in mail correctly. Thank you.';
        $a = (object)array('courseshortname' => $course->shortname, 'peerforumname' => $peerforum->name, 'subject' => $subject);
        $expectedsubject = get_string('postmailsubject', 'peerforum', $a);
        list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $author, array('name' => $subject));

        // Run cron and check that the expected number of users received the notification.
        $messages = $this->helper_run_cron_check_count($post, 1);
        $message = reset($messages);
        $this->assertEquals($author->id, $message->useridfrom);
        $this->assertEquals($expectedsubject, $message->subject);
    }

    /**
     * dataProvider for test_peerforum_post_email_templates().
     */
    public function peerforum_post_email_templates_provider() {
        // Base information, we'll build variations based on it.
        $base = array(
            'user' => array('firstname' => 'Love', 'lastname' => 'Moodle', 'mailformat' => 0, 'maildigest' => 0),
            'course' => array('shortname' => '101', 'fullname' => 'Moodle 101'),
            'peerforums' => array(
                array(
                    'name' => 'Moodle PeerForum',
                    'peerforumposts' => array(
                        array(
                            'name' => 'Hello Moodle',
                            'message' => 'Welcome to Moodle',
                            'messageformat' => FORMAT_MOODLE,
                            'attachments' => array(
                                array(
                                    'filename' => 'example.txt',
                                    'filecontents' => 'Basic information about the course'
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'expectations' => array(
                array(
                    'subject' => '.*101.*Hello',
                    'contents' => array(
                        '~{$a',
                        '~&(amp|lt|gt|quot|\#039);(?!course)',
                        'Attachment example.txt:\n' .
                            'http://www.example.com/moodle/pluginfile.php/\d*/mod_peerforum/attachment/\d*/example.txt\n',
                        'Hello Moodle', 'Moodle PeerForum', 'Welcome.*Moodle', 'Love Moodle', '1\d1'
                    ),
                ),
            ),
        );

        // Build the text cases.
        $textcases = array('Text mail without ampersands, quotes or lt/gt' => array('data' => $base));

        // Single and double quotes everywhere.
        $newcase = $base;
        $newcase['user']['lastname'] = 'Moodle\'';
        // $newcase['user']['lastname'] = 'Moodle\'"'; // TODO: This breaks badly. See MDL-52136.
        $newcase['course']['shortname'] = '101\'';
        // $newcase['course']['shortname'] = '101\'"'; // TODO: This breaks badly. See MDL-52136.
        $newcase['peerforums'][0]['name'] = 'Moodle PeerForum\'"';
        $newcase['peerforums'][0]['peerforumposts'][0]['name'] = 'Hello Moodle\'"';
        $newcase['peerforums'][0]['peerforumposts'][0]['message'] = 'Welcome to Moodle\'"';
        $newcase['expectations'][0]['contents'] = array(
            'Attachment example.txt:', '~{\$a', '~&amp;(quot|\#039);', 'Love Moodle\'', '101\'', 'Moodle PeerForum\'"',
            'Hello Moodle\'"', 'Welcome to Moodle\'"');
        $textcases['Text mail with quotes everywhere'] = array('data' => $newcase);

        // Lt and gt everywhere. This case is completely borked because format_string()
        // strips tags with $CFG->formatstringstriptags and also escapes < and > (correct
        // for web presentation but not for text email). See MDL-19829.
        $newcase = $base;
        $newcase['user']['lastname'] = 'Moodle>';
        $newcase['course']['shortname'] = '101>';
        $newcase['peerforums'][0]['name'] = 'Moodle PeerForum>';
        $newcase['peerforums'][0]['peerforumposts'][0]['name'] = 'Hello Moodle>';
        $newcase['peerforums'][0]['peerforumposts'][0]['message'] = 'Welcome to Moodle>';
        $newcase['expectations'][0]['contents'] = array(
            'Attachment example.txt:', '~{\$a', '~&amp;gt;', 'Love Moodle>', '101&gt;', 'Moodle PeerForum&gt;',
            'Hello Moodle&gt;', 'Welcome to Moodle>');
        $textcases['Text mail with gt and lt everywhere'] = array('data' => $newcase);

        // Ampersands everywhere. This case is completely borked because format_string()
        // escapes ampersands (correct for web presentation but not for text email). See MDL-19829.
        $newcase = $base;
        $newcase['user']['lastname'] = 'Moodle&';
        $newcase['course']['shortname'] = '101&';
        $newcase['peerforums'][0]['name'] = 'Moodle PeerForum&';
        $newcase['peerforums'][0]['peerforumposts'][0]['name'] = 'Hello Moodle&';
        $newcase['peerforums'][0]['peerforumposts'][0]['message'] = 'Welcome to Moodle&';
        $newcase['expectations'][0]['contents'] = array(
            'Attachment example.txt:', '~{\$a', '~&amp;amp;', 'Love Moodle&', '101&amp;', 'Moodle PeerForum&amp;',
            'Hello Moodle&amp;', 'Welcome to Moodle&');
        $textcases['Text mail with ampersands everywhere'] = array('data' => $newcase);

        // Now the html cases.
        $htmlcases = array();

        // New base for html cases, no quotes, lts, gts or ampersands.
        $htmlbase = $base;
        $htmlbase['user']['mailformat'] = 1;
        $htmlbase['expectations'][0]['contents'] = array(
            '~{\$a',
            '~&(amp|lt|gt|quot|\#039);(?!course)',
            '<div class=3D"attachments">( *\n *)?<a href',
            '<div class=3D"subject">\n.*Hello Moodle', '>Moodle PeerForum', '>Welcome.*Moodle', '>Love Moodle', '>1\d1');
        $htmlcases['HTML mail without ampersands, quotes or lt/gt'] = array('data' => $htmlbase);

        // Single and double quotes, lt and gt, ampersands everywhere.
        $newcase = $htmlbase;
        $newcase['user']['lastname'] = 'Moodle\'>&';
        // $newcase['user']['lastname'] = 'Moodle\'">&'; // TODO: This breaks badly. See MDL-52136.
        $newcase['course']['shortname'] = '101\'>&';
        // $newcase['course']['shortname'] = '101\'">&'; // TODO: This breaks badly. See MDL-52136.
        $newcase['peerforums'][0]['name'] = 'Moodle PeerForum\'">&';
        $newcase['peerforums'][0]['peerforumposts'][0]['name'] = 'Hello Moodle\'">&';
        $newcase['peerforums'][0]['peerforumposts'][0]['message'] = 'Welcome to Moodle\'">&';
        $newcase['expectations'][0]['contents'] = array(
            '~{\$a',
            '~&amp;(amp|lt|gt|quot|\#039);',
            '<div class=3D"attachments">( *\n *)?<a href',
            '<div class=3D"subject">\n.*Hello Moodle\'"&gt;&amp;', '>Moodle PeerForum\'"&gt;&amp;',
            '>Welcome.*Moodle\'"&gt;&amp;', '>Love Moodle&\#039;&gt;&amp;', '>1\d1\'&gt;&amp');
        $htmlcases['HTML mail with quotes, gt, lt and ampersand  everywhere'] = array('data' => $newcase);

        return $textcases + $htmlcases;
    }

    /**
     * Verify peerforum emails body using templates to generate the expected results.
     *
     * @dataProvider peerforum_post_email_templates_provider
     * @param array $data provider samples.
     */
    public function test_peerforum_post_email_templates($data) {
        global $DB;

        $this->resetAfterTest();

        // Create the course, with the specified options.
        $options = array();
        foreach ($data['course'] as $option => $value) {
            $options[$option] = $value;
        }
        $course = $this->getDataGenerator()->create_course($options);

        // Create the user, with the specified options and enrol in the course.
        $options = array();
        foreach ($data['user'] as $option => $value) {
            $options[$option] = $value;
        }
        $user = $this->getDataGenerator()->create_user($options);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create peerforums, always force susbscribed (for easy), with the specified options.
        $posts = array();
        foreach ($data['peerforums'] as $datapeerforum) {
            $peerforumposts = isset($datapeerforum['peerforumposts']) ? $datapeerforum['peerforumposts'] : array();
            unset($datapeerforum['peerforumposts']);
            $options = array('course' => $course->id, 'forcesubscribe' => PEERFORUM_FORCESUBSCRIBE);
            foreach ($datapeerforum as $option => $value) {
                $options[$option] = $value;
            }
            $peerforum = $this->getDataGenerator()->create_module('peerforum', $options);

            // Create posts, always for immediate delivery (for easy), with the specified options.
            foreach ($peerforumposts as $peerforumpost) {
                $attachments = isset($peerforumpost['attachments']) ? $peerforumpost['attachments'] : array();
                unset($peerforumpost['attachments']);
                $postoptions = array('course' => $course->id, 'peerforum' => $peerforum->id, 'userid' => $user->id,
                    'mailnow' => 1, 'attachment' => !empty($attachments));
                foreach ($peerforumpost as $option => $value) {
                    $postoptions[$option] = $value;
                }
                list($discussion, $post) = $this->helper_post_to_peerforum($peerforum, $user, $postoptions);
                $posts[$post->subject] = $post; // Need this to verify cron output.

                // Add the attachments to the post.
                if ($attachments) {
                    $fs = get_file_storage();
                    foreach ($attachments as $attachment) {
                        $filerecord = array(
                            'contextid' => context_module::instance($peerforum->cmid)->id,
                            'component' => 'mod_peerforum',
                            'filearea'  => 'attachment',
                            'itemid'    => $post->id,
                            'filepath'  => '/',
                            'filename'  => $attachment['filename']
                        );
                        $fs->create_file_from_string($filerecord, $attachment['filecontents']);
                    }
                    $DB->set_field('peerforum_posts', 'attachment', '1', array('id' => $post->id));
                }
            }
        }

        // Clear the mailsink and close the messagesink.
        // (surely setup should provide us this cleared but...)
        $this->helper->mailsink->clear();
        $this->helper->messagesink->close();

        // Capture and silence cron output, verifying contents.
        foreach ($posts as $post) {
            $this->expectOutputRegex("/1 users were sent post {$post->id}, '{$post->subject}'/");
        }
        peerforum_cron(); // It's really annoying that we have to run cron to test this.

        // Get the mails.
        $mails = $this->helper->mailsink->get_messages();

        // Start testing the expectations.
        $expectations = $data['expectations'];

        // Assert the number is the expected.
        $this->assertSame(count($expectations), count($mails));

        // Start processing mails, first localizing its expectations, then checking them.
        foreach ($mails as $mail) {
            // Find the corresponding expectation.
            $foundexpectation = null;
            foreach ($expectations as $key => $expectation) {
                // All expectations must have a subject for matching.
                if (!isset($expectation['subject'])) {
                    $this->fail('Provider expectation missing mandatory subject');
                }
                if (preg_match('!' . $expectation['subject'] . '!', $mail->subject)) {
                    // If we already had found the expectation, there are non-unique subjects. Fail.
                    if (isset($foundexpectation)) {
                        $this->fail('Multiple expectations found (by subject matching). Please make them unique.');
                    }
                    $foundexpectation = $expectation;
                    unset($expectations[$key]);
                }
            }
            // Arrived here, we should have found the expectations.
            $this->assertNotEmpty($foundexpectation, 'Expectation not found for the mail');

            // If we have found the expectation and have contents to match, let's do it.
            if (isset($foundexpectation) and isset($foundexpectation['contents'])) {
                if (!is_array($foundexpectation['contents'])) { // Accept both string and array.
                    $foundexpectation['contents'] = array($foundexpectation['contents']);
                }
                foreach ($foundexpectation['contents'] as $content) {
                    if (strpos($content, '~') !== 0) {
                        $this->assertRegexp('#' . $content . '#m', $mail->body);
                    } else {
                        $this->assertNotRegexp('#' . substr($content, 1) . '#m', $mail->body);
                    }
                }
            }
        }
        // Finished, there should not be remaining expectations.
        $this->assertCount(0, $expectations);
    }
}
