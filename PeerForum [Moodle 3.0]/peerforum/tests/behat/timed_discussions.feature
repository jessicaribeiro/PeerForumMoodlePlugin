@mod @mod_peerforum
Feature: Users can choose to set start and end time for display of their discussions
  In order to temporarly hide discussions to students
  As a teacher
  I need to set a discussion time start and time end

  Scenario: Student should not see the tooltip or the discussion
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And the following config values are set as admin:
      | peerforum_enabletimedposts | 1 |
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | Description | Test peerforum description |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Discussion 1 |
      | Message | Discussion contents 1, first message |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject          | Discussion 2 timed not visible       |
      | Message          | Discussion contents 2, first message |
      | timeend[enabled] | 1 |
      | timeend[year]    | 2014 |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject            | Discussion 3 timed visible now       |
      | Message            | Discussion contents 3, first message |
      | timestart[enabled] | 1 |
    And I am on site homepage
    And I follow "Course 1"
    And I follow "Test peerforum name"
    And I should see "Discussion 2 timed"
    And I should see "Discussion 3 timed"
    And ".timedpost" "css_element" should exist
    And I log out
    And I log in as "student1"
    When I follow "Course 1"
    And I follow "Test peerforum name"
    Then I should see "Discussion 1"
    And I should not see "Discussion 2 timed"
    And ".timedpost" "css_element" should not exist
    And I should see "Discussion 3 timed"
