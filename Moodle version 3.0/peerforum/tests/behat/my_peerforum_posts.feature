@mod @mod_peerforum
Feature: A user can view their posts and discussions
  In order to ensure a user can view their posts and discussions
  As a student
  I need to view my post and discussions

  Scenario: View the student's posts and discussions
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity   | name                   | intro       | course | idnumber     | groupmode |
      | peerforum      | Test peerforum name        | Test peerforum  | C1     | peerforum        | 0         |
    And I log in as "student1"
    And I follow "Course 1"
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | PeerForum discussion 1 |
      | Message | How awesome is this peerforum discussion? |
    And I reply "PeerForum discussion 1" post from "Test peerforum name" peerforum with:
      | Message | Actually, I've seen better. |
    When I follow "Profile" in the user menu
    And I follow "PeerForum posts"
    Then I should see "How awesome is this peerforum discussion?"
    And I should see "Actually, I've seen better."
    And I follow "Profile" in the user menu
    And I follow "PeerForum discussions"
    And I should see "How awesome is this peerforum discussion?"
    And I should not see "Actually, I've seen better."
