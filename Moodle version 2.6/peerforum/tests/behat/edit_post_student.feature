@mod @mod_peerforum
Feature: Students can edit or delete their peerforum posts within a set time limit
  In order to refine peerforum posts
  As a user
  I need to edit or delete my peerforum posts within a certain period of time after posting

  Background:
    Given the following "users" exists:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@asd.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Security" node
    And I follow "Site policies"
    And I select "1 minutes" from "Maximum time to edit posts"
    And I press "Save changes"
    And I am on homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
    And I log out
    And I follow "Course 1"
    And I log in as "student1"
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | PeerForum post subject |
      | Message | This is the body |

  Scenario: Edit peerforum post
    When I follow "PeerForum post subject"
    And I follow "Edit"
    And I fill the moodle form with:
      | Subject | Edited post subject |
      | Message | Edited post body |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited post subject"
    And I should see "Edited post body"

  @javascript
  Scenario: Delete peerforum post
    When I follow "PeerForum post subject"
    And I follow "Delete"
    And I press "Continue"
    Then I should not see "PeerForum post subject"

  @javascript
  Scenario: Time limit expires
    When I wait "70" seconds
    And I follow "PeerForum post subject"
    Then I should not see "Edit" in the "region-main" "region"
    And I should not see "Delete" in the "region-main" "region"
