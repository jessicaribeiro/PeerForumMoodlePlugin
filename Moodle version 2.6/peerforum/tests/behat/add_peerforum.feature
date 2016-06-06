@mod @mod_peerforum @_file_upload
Feature: Add peerforum activities and discussions
  In order to discuss topics with other users
  As a teacher
  I need to add peerforum activities to moodle courses

  @javascript
  Scenario: Add a peerforum and a discussion attaching files
    Given the following "users" exists:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | student1 | Student | 1 | student1@asd.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | PeerForum post 1 |
      | Message | This is the body |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Post with attachment |
      | Message | This is the body |
      | Attachment | lib/tests/fixtures/empty.txt |
    And I reply "PeerForum post 1" post from "Test peerforum name" peerforum with:
      | Subject | Reply with attachment |
      | Message | This is the body |
      | Attachment | lib/tests/fixtures/upload_users.csv |
    Then I should see "Reply with attachment"
    And I should see "upload_users.csv"
    And I follow "Test peerforum name"
    And I follow "Post with attachment"
    And I should see "empty.txt"
    And I follow "Edit"
    And the field "Attachment" matches value "empty.txt"
