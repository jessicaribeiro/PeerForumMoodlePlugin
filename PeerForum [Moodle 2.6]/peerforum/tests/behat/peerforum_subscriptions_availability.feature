@mod @mod_peerforum
Feature: As a teacher I need to see an accurate list of subscribed users
  In order to see who is subscribed to a peerforum
  As a teacher
  I need to view the list of subscribed users

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher  | Teacher   | Teacher  | teacher@example.com |
      | student1 | Student   | 1        | student.1@example.com |
      | student2 | Student   | 2        | student.2@example.com |
      | student3 | Student   | 3        | student.3@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher  | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
    And the following "groups" exist:
      | name | course | idnumber |
      | Group 1 | C1 | G1 |
      | Group 2 | C1 | G2 |
    And the following "group members" exist:
      | user        | group |
      | student1    | G1    |
      | student2    | G2    |
    And the following "groupings" exist:
      | name        | course | idnumber |
      | Grouping 1  | C1     | GG1      |
    And the following "grouping groups" exist:
      | grouping | group |
      | GG1      | G1    |
    And I log in as "admin"
    And I set the following administration settings values:
      | Enable group members only | 1 |
    And I log out
    And I log in as "teacher"
    And I follow "Course 1"
    And I turn editing mode on

  @javascript
  Scenario: A forced peerforum lists all subscribers
    When I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name        | Forced PeerForum 1 |
      | PeerForum type        | Standard peerforum for general use |
      | Description       | Test peerforum description |
      | Subscription mode | Forced subscription |
    And I follow "Forced PeerForum 1"
    And I follow "Show/edit current subscribers"
    Then I should see "Student 1"
    And I should see "Teacher Teacher"
    And I should see "Student 2"
    And I should see "Student 3"
    And I click on "Edit settings" "link" in the "Administration" "block"
    And I set the following fields to these values:
      | Grouping                          | Grouping 1 |
      | Available for group members only  | 1          |
    And I press "Save and display"
    And I follow "Show/edit current subscribers"
    And I should see "Student 1"
    And I should see "Teacher Teacher"
    And I should not see "Student 2"
    And I should not see "Student 3"

  @javascript
  Scenario: An automatic peerforum lists all subscribers
    When I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name        | Forced PeerForum 1 |
      | PeerForum type        | Standard peerforum for general use |
      | Description       | Test peerforum description |
      | Subscription mode | Auto subscription |
    And I follow "Forced PeerForum 1"
    And I follow "Show/edit current subscribers"
    Then I should see "Student 1"
    And I should see "Teacher Teacher"
    And I should see "Student 2"
    And I should see "Student 3"
    And I click on "Edit settings" "link" in the "Administration" "block"
    And I set the following fields to these values:
      | Grouping                          | Grouping 1 |
      | Available for group members only  | 1          |
    And I press "Save and display"
    And I follow "Show/edit current subscribers"
    And I should see "Student 1"
    And I should see "Teacher Teacher"
    And I should not see "Student 2"
    And I should not see "Student 3"
