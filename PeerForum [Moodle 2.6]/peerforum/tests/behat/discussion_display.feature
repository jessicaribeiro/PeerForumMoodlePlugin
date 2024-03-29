@mod @mod_peerforum
Feature: Students can choose from 4 discussion display options and their choice is remembered
  In order to read peerforum posts in a suitable view
  As a user
  I need to select which display method I want to use

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
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "PeerForum" to section "1" and I fill the form with:
      | PeerForum name | Test peerforum name |
      | Description | Test peerforum description |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Discussion 1 |
      | Message | Discussion contents 1, first message |
    And I reply "Discussion 1" post from "Test peerforum name" peerforum with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
    And I add a new discussion to "Test peerforum name" peerforum with:
      | Subject | Discussion 2 |
      | Message | Discussion contents 2, first message |
    And I reply "Discussion 2" post from "Test peerforum name" peerforum with:
      | Subject | Reply 1 to discussion 2 |
      | Message | Discussion contents 2, second message |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"

  @javascript
  Scenario: Display replies flat, with oldest first
    Given I reply "Discussion 1" post from "Test peerforum name" peerforum with:
      | Subject | Reply 2 to discussion 1 |
      | Message | Discussion contents 1, third message |
    When I select "Display replies flat, with oldest first" from "mode"
    Then I should see "Discussion contents 1, first message" in the "div.firstpost.starter" "css_element"
    And I should see "Discussion contents 1, second message" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' peerforumpost ') and not(contains(@class, 'starter'))]" "xpath_element"
    And I reply "Discussion 2" post from "Test peerforum name" peerforum with:
      | Subject | Reply 2 to discussion 2 |
      | Message | Discussion contents 2, third message |
    And the "Display mode" field should match "Display replies flat, with oldest first" value
    And I should see "Discussion contents 2, first message" in the "div.firstpost.starter" "css_element"
    And I should see "Discussion contents 2, second message" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' peerforumpost ') and not(contains(@class, 'starter'))]" "xpath_element"

  @javascript
  Scenario: Display replies flat, with newest first
    Given I reply "Discussion 1" post from "Test peerforum name" peerforum with:
      | Subject | Reply 2 to discussion 1 |
      | Message | Discussion contents 1, third message |
    When I select "Display replies flat, with newest first" from "mode"
    Then I should see "Discussion contents 1, first message" in the "div.firstpost.starter" "css_element"
    And I should see "Discussion contents 1, third message" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' peerforumpost ') and not(contains(@class, 'starter'))]" "xpath_element"
    And I reply "Discussion 2" post from "Test peerforum name" peerforum with:
      | Subject | Reply 2 to discussion 2 |
      | Message | Discussion contents 2, third message |
    And the "Display mode" field should match "Display replies flat, with newest first" value
    And I should see "Discussion contents 2, first message" in the "div.firstpost.starter" "css_element"
    And I should see "Discussion contents 2, third message" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' peerforumpost ') and not(contains(@class, 'starter'))]" "xpath_element"

  @javascript
  Scenario: Display replies in threaded form
    Given I follow "Test peerforum name"
    And I follow "Discussion 1"
    When I select "Display replies in threaded form" from "mode"
    Then I should see "Discussion contents 1, first message"
    And I should see "Reply 1 to discussion 1" in the "span.peerforumthread" "css_element"
    And I follow "Test peerforum name"
    And I follow "Discussion 2"
    And the "Display mode" field should match "Display replies in threaded form" value
    And I should see "Discussion contents 2, first message"
    And I should see "Reply 1 to discussion 2" in the "span.peerforumthread" "css_element"

  @javascript
  Scenario: Display replies in nested form
    Given I follow "Test peerforum name"
    And I follow "Discussion 1"
    When I select "Display replies in nested form" from "mode"
    Then I should see "Discussion contents 1, first message" in the "div.firstpost.starter" "css_element"
    And I should see "Discussion contents 1, second message" in the "div.indent div.peerforumpost" "css_element"
    And I follow "Test peerforum name"
    And I follow "Discussion 2"
    And the "Display mode" field should match "Display replies in nested form" value
    And I should see "Discussion contents 2, first message" in the "div.firstpost.starter" "css_element"
    And I should see "Discussion contents 2, second message" in the "div.indent div.peerforumpost" "css_element"
