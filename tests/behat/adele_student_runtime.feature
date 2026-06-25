@local @local_adele @javascript
Feature: As a student I view my learning path at runtime and watch a timed-gated node unlock
  In order to prove the #438 per-user timed-restriction rework end to end
  As a student subscribed to a learning path
  I need a node gated by a timed restriction to appear locked, schedule a per-user
  adhoc task, and then unlock (and enrol me) once the window opens and the task runs.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | student  | Student   | Test     | toolgenerator1@example.com |
      | teacher  | Teacher   | Test     | toolgenerator3@example.com |
    And the following "courses" exist:
      | fullname | shortname | summary     | enablecompletion |
      | Course 1 | C1        | LP Course 1 | 1                |
      | Course 2 | C2        | LP Course 2 | 1                |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | student | C1     | student        |
      | teacher | C1     | editingteacher |
    And the following config values are set as admin:
      | config            | value                                                      | plugin      |
      | restrictionfilter | timed,timed_duration,specific_course,parent_courses,manual | local_adele |
    And the following "local_adele > learningpaths" exist:
      | name            | description        | filepath                                          | courses | image |
      | Timed runtime LP | Timed runtime desc | local/adele/tests/fixtures/timed_runtime_lp.json | C1,C2   |       |
    And a learning path activity "Adele Runtime" for "Timed runtime LP" exists in course "C1"
    And I change viewport size to "1366x3000"

  @javascript
  Scenario: A timed-gated node renders locked, schedules a task, then unlocks when the task runs
    # Subscribing to a future-windowed timed node must queue a per-user boundary task (#438).
    Given a learning path update task should be scheduled
    # The student opens the activity and sees their runtime path.
    And I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Adele Runtime"
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    And "[data-id='dndnode_1']" "css_element" should exist
    # The timed-gated node (dndnode_2) is LOCKED: its play button shows the lock icon
    # and carries the timed "ring" affordance (only timed restrictions show the ring, #463).
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-with-ring" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should not exist
    # The boundary is reached: the timed window opens and the scheduled task fires,
    # recomputing the path so the node unlocks and the learner is enrolled into its course.
    When the timed restriction window for node "dndnode_2" is now open
    And I run the scheduled learning path update tasks
    And I reload the page
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    # The node is now ACCESSIBLE: the lock icon is gone and the play button is shown.
    Then "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should not exist
