@mod @mod_peerforum
Feature: A teacher can set one of 3 possible options for tracking read peerforum posts
  In order to ease the peerforum posts follow up
  As a user
  I need to distinct the unread posts from the read ones

  Background:
    Given the following "users" exists:
      | username | firstname | lastname | email | trackforums |
      | student1 | Student | 1 | student1@asd.com | 1 |
      | student2 | Student | 2 | student2@asd.com | 0 |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "admin"
    And I follow "Course 1"
    And I turn editing mode on

  @javascript
  Scenario: Tracking peerforum posts off
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Off |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test peerforum name"
    And I should not see "Track unread posts"

  @javascript
  Scenario: Tracking peerforum posts optional with user tracking on
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Optional |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test peerforum name"
    And I follow "Don't track unread posts"
    And I wait to be redirected
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test peerforum name"
    And I follow "Track unread posts"
    And I wait to be redirected
    And I click on "1" "link" in the "Admin User" "table_row"
    And I follow "Course 1"
    And I should not see "1 unread post"

  @javascript
  Scenario: Tracking peerforum posts optional with user tracking off
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Optional |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test peerforum name"
    And I should not see "Track unread posts"

  @javascript
  Scenario: Tracking peerforum posts forced with user tracking on
    Given I set the following administration settings values:
      | Allow forced read tracking | 1 |
    And I follow "Home"
    And I follow "Course 1"
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Force |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  @javascript
  Scenario: Tracking peerforum posts forced with user tracking off
    Given I set the following administration settings values:
      | Allow forced read tracking | 1 |
    And I follow "Home"
    And I follow "Course 1"
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Force |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  @javascript
  Scenario: Tracking peerforum posts forced (with force disabled) with user tracking on
    Given I set the following administration settings values:
      | Allow forced read tracking | 1 |
    And I follow "Home"
    And I follow "Course 1"
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Force |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I set the following administration settings values:
      | Allow forced read tracking | 0 |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test peerforum name"
    And I follow "Don't track unread posts"
    And I wait to be redirected
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test peerforum name"
    And I follow "Track unread posts"
    And I wait to be redirected
    And I click on "1" "link" in the "Admin User" "table_row"
    And I follow "Course 1"
    And I should not see "1 unread post"

  @javascript
  Scenario: Tracking peerforum posts forced (with force disabled) with user tracking off
    Given I set the following administration settings values:
      | Allow forced read tracking | 1 |
    And I follow "Home"
    And I follow "Course 1"
    Given I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | PeerForum type | Standard peerforum for general use |
      | Description | Test peerforum description |
      | Read tracking | Force |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I set the following administration settings values:
      | Allow forced read tracking | 0 |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test peerforum name"
    And I should not see "Track unread posts"
