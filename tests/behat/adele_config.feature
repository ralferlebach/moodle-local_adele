@local @local_adele @javascript
Feature: As an admin I perform several drag and drop actions.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname     | email                       |
      | user     | Username  | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher    | Test        | toolgenerator3@example.com  |
      | manager  | Manager    | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user     | C1     | student        |
      | teacher  | C1     | editingteacher |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Tags | testing |
    And I press "Save and display"
    And I wait "1" seconds
  @javascript
  Scenario: Adele usage: admin defines config
    # Navigate straight to local_adele's OWN settings page via its exact place in
    # the admin tree (Local plugins > Learning path), instead of the ambiguous
    # "I follow 'Learning path'". The latter matches any link whose text merely
    # CONTAINS "Learning path" - once enrol_adele is co-installed its
    # "Learning path enrolment management" page (under a different branch,
    # Enrolments) also matches, and the step followed that instead, landing on the
    # wrong page. Walking the tree path targets this plugin's page unambiguously
    # regardless of what other plugins are installed. The "Activate filter"
    # assertion fails fast at the navigation step if we ever land wrong again,
    # rather than only surfacing at the following form step.
    Given I navigate to "Plugins > Local plugins > Learning path" in site administration
    And I should see "Activate filter"
    And I select "All courses meeting the other criteria." from the "Activate filter" singleselect
    And I press "Save changes"
    Then I should see "Changes saved"
    And I set the field with xpath "//*[@id='id_s_local_adele_includetags']" to "test"
    And I press "Save changes"
    And I set the field with xpath "//*[@id='id_s_local_adele_includetags']" to "testing"
    And I press "Save changes"
    Then I should see "Changes saved"
