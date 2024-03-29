@mod @mod_peerforum
Feature: Set a certain number of discussions as a completion condition for a peerforum
  In order to ensure students are participating on peerforums
  As a teacher
  I need to set a minimum number of discussions to mark the peerforum activity as completed

  @javascript
  Scenario: Set X number of discussions as a condition
    Given the following "users" exists:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@asd.com |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "admin"
    And I set the following administration settings values:
      | Enable completion tracking | 1 |
      | Enable conditional access | 1 |
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I click on "Edit settings" "link" in the "Administration" "block"
    And I fill the moodle form with:
      | Enable completion tracking | Yes |
    And I press "Save changes"
    When I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | Description | Test peerforum description |
      | Completion tracking | Show activity as complete when conditions are met |
      | completiondiscussionsenabled | 1 |
      | completiondiscussions | 2 |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    Then I hover "//li[contains(concat(' ', normalize-space(@class), ' '), ' modtype_peerforum ')]/descendant::img[@alt='Not completed: Test peerforum name']" "xpath_element"
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Post 1 subject |
      | Message | Body 1 content |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Post 2 subject |
      | Message | Body 2 content |
    And I follow "Course 1"
    And I hover "//li[contains(concat(' ', normalize-space(@class), ' '), ' modtype_peerforum ')]/descendant::img[contains(@alt, 'Completed: Test peerforum name')]" "xpath_element"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And "Student 1" user has completed "Test peerforum name" activity
