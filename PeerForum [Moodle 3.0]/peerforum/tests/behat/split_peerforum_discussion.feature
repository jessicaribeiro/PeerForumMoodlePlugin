@mod @mod_peerforum
Feature: PeerForum discussions can be split
  In order to manage peerforum discussions in my course
  As a Teacher
  I need to be able to split threads to keep them on topic.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Science 101 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Science 101"
    And I turn editing mode on
    And I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Study discussions |
      | PeerForum type | Standard peerforum for general use |
      | Description | PeerForum to discuss your coursework. |
    And I add a new discussion to "Study discussions" peerforum with:
      | Subject | Photosynethis discussion |
      | Message | Lets discuss our learning about Photosynethis this week in this thread. |
    And I log out
    And I log in as "student1"
    And I follow "Science 101"
    And I reply "Photosynethis discussion" post from "Study discussions" peerforum with:
      | Message | Can anyone tell me which number is the mass number in the periodic table? |
    And I log out

  Scenario: Split a peerforum discussion
    Given I log in as "teacher1"
    And I follow "Science 101"
    And I follow "Study discussions"
    And I follow "Photosynethis discussion"
    When I follow "Split"
    And  I set the following fields to these values:
        | Discussion name | Mass number in periodic table |
    And I press "Split"
    Then I should see "Mass number in periodic table"
    And I follow "Study discussions"
    And I should see "Teacher 1" in the "Photosynethis" "table_row"
    And I should see "Student 1" in the "Mass number in periodic table" "table_row"
