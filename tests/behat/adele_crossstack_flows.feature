@local @local_adele @javascript
Feature: High-value cross-stack Adele flows that unit tests cannot reach
  In order to protect the flows that span PHP web services, the recompute engine
  and the Vue3 SPA, I exercise the editor/course-picker load, a completion-based
  unlock, the teacher progress view and stored-name safety end to end.

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
    And I change viewport size to "1366x3000"

  @javascript
  Scenario: Editor and course picker load with no coding error and list courses
    # The single most valuable scenario: guards the get_availablecourses WS which
    # recently threw a $PAGE codingerror (#464 M5) and returned an empty course list.
    # A populated LP is created and an activity is added, then the admin opens the
    # editor: the flow must render AND the "Kurse" sidebar must list courses (proving
    # the WS returned rows rather than erroring out).
    # NB: the learningpaths generator names the LP from the fixture's own "name"
    # field, so the DB name is "Completion gated LP" regardless of a row alias.
    Given the following "local_adele > learningpaths" exist:
      | name                | description | filepath                                            | courses | image |
      | Completion gated LP | Editor desc | local/adele/tests/fixtures/completion_gated_lp.json | C1,C2   |       |
    And a learning path activity "Adele Editor" for "Completion gated LP" exists in course "C1"
    And I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I wait until the page is ready
    And I should see "Completion gated LP" in the ".learningcardcont .learningcard" "css_element"
    And I click on ".learningcardcont .learningcard" "css_element"
    And I wait until ".vue-flow.learning-path-flow" "css_element" exists
    # No exception / coding-error notice was rendered anywhere on the page.
    Then I should not see a coding error notice
    # The flow rendered the populated learning path (its course nodes).
    And I should see "Course 1" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 2" in the ".vue-flow.learning-path-flow" "css_element"
    # The "Kurse" sidebar course picker is populated by get_availablecourses: at least
    # one draggable course entry proves the WS returned courses (not an empty list).
    And ".learning-path-nodes-container .nodes [data-draggable]" "css_element" should exist
    And I should see "Course 1" in the ".learning-path-nodes-container .nodes" "css_element"

  @javascript
  Scenario: A completion-gated node unlocks once its parent course is completed
    # dndnode_2 carries a parent_courses restriction on dndnode_1; dndnode_1 carries a
    # course_completed completion. The node stays locked until the parent course is
    # completed, at which point the recompute unlocks it (and enrols the learner).
    Given the following "local_adele > learningpaths" exist:
      | name                | description     | filepath                                            | courses | image |
      | Completion gated LP | Completion desc | local/adele/tests/fixtures/completion_gated_lp.json | C1,C2   |       |
    And a learning path activity "Adele Completion" for "Completion gated LP" exists in course "C1"
    And I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Adele Completion"
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    And "[data-id='dndnode_1']" "css_element" should exist
    # The gated node is LOCKED (parent not yet complete). It carries no timed ring.
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-with-ring" "css_element" should not exist
    And "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should not exist
    # Complete the parent node's course; the event-driven recompute unlocks dndnode_2.
    When the course "C1" is completed for every subscribed learner
    And I reload the page
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    # The node is now ACCESSIBLE: the lock is gone and the play button is shown.
    Then "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should not exist

  @javascript
  Scenario: A course_completed event aggregated by the system unlocks the gated node
    # Regression guard: when the course completion is aggregated by the system
    # (cron/admin) rather than the learner, \core\event\course_completed carries
    # userid = admin and relateduserid = learner. The observer must resolve the
    # learner via relateduserid; otherwise the gated node would never unlock.
    Given the following "local_adele > learningpaths" exist:
      | name                | description     | filepath                                            | courses | image |
      | Completion gated LP | Completion desc | local/adele/tests/fixtures/completion_gated_lp.json | C1,C2   |       |
    And a learning path activity "Adele Completion" for "Completion gated LP" exists in course "C1"
    And I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Adele Completion"
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    And "[data-id='dndnode_1']" "css_element" should exist
    # The gated node is LOCKED (parent not yet complete).
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should not exist
    # The system aggregates the course completion; the REAL course_completed
    # observer chain (userid = admin, relateduserid = learner) recomputes the path.
    When the course "C1" is completed and aggregated by the system
    And I reload the page
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    # The node is now ACCESSIBLE: the lock is gone and the play button is shown.
    Then "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should not exist

  @javascript
  Scenario: A quiz submission recomputes the learner path and unlocks the gated node
    # Regression guard for #496: dndnode_1 completes on a mod_quiz submission and
    # dndnode_2 is gated behind it. The submission event carries userid = admin
    # (acting/system) and relateduserid = learner. The observer must resolve the
    # learner via relateduserid; otherwise dndnode_1 would never complete for the
    # learner and dndnode_2 would stay locked.
    Given the following "local_adele > learningpaths" exist:
      | name          | description | filepath                                        | courses | image |
      | Quiz gated LP | Quiz desc   | local/adele/tests/fixtures/quiz_gated_lp.json   | C1,C2   |       |
    And a quiz "AdeleQuiz" completing node "dndnode_1" exists in course "C1"
    And a learning path activity "Adele Quiz" for "Quiz gated LP" exists in course "C1"
    And I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Adele Quiz"
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    And "[data-id='dndnode_1']" "css_element" should exist
    # The gated node is LOCKED (the quiz node is not completed yet).
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should not exist
    # The quiz is submitted by the system (userid = admin, relateduserid = learner).
    When the quiz "AdeleQuiz" in course "C1" is submitted and aggregated by the system
    And I reload the page
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    # The node is now ACCESSIBLE: the lock is gone and the play button is shown.
    Then "[data-id='dndnode_2'] .icon-link i.fa-play" "css_element" should exist
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should not exist

  @javascript
  Scenario: Teacher opens the activity and sees the participant progress list
    # The teacher needs Adele manager access at system context to see the progress
    # list (check_access() checks local/adele:canmanage at the system level; a plain
    # course editingteacher does not have it - the known role/capability gap).
    Given the following "role assigns" exist:
      | user    | role    | contextlevel | reference |
      | teacher | manager | System       |           |
    And the following "local_adele > learningpaths" exist:
      | name                | description  | filepath                                            | courses | image |
      | Completion gated LP | Teacher desc | local/adele/tests/fixtures/completion_gated_lp.json | C1,C2   |       |
    And a learning path activity "Adele Teacher" for "Completion gated LP" exists in course "C1"
    And I log in as "teacher"
    And I am on "Course 1" course homepage
    And I follow "Adele Teacher"
    And I wait until the page is ready
    And I wait until "#adele-userlist-toggle" "css_element" exists
    # The participant list renders with the subscribed student's row and the
    # progress / ranking columns.
    Then "#adele-userlist-table" "css_element" should exist
    And "#adele-userlist-row-r1" "css_element" should exist
    And I should see "Student" in the "#adele-userlist-table" "css_element"

  @javascript
  Scenario: A stored XSS node name renders inert in the runtime node feedback
    # The parent node's name is an <img onerror> payload. The child node is locked by a
    # parent_courses restriction whose feedback string embeds the parent node name via
    # v-html (#464 H4). Opening the locked child's feedback panel must render that name
    # as inert text: the marker "PWN" shows, but no live <img onerror> element (which
    # would execute) ever materialises in the DOM.
    Given the following "local_adele > learningpaths" exist:
      | name             | description | filepath                                         | courses | image |
      | XSS node name LP | XSS desc    | local/adele/tests/fixtures/xss_node_name_lp.json | C1,C2   |       |
    And a learning path activity "Adele XSS" for "XSS node name LP" exists in course "C1"
    And I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Adele XSS"
    And I wait until the page is ready
    And I wait until "[data-id='dndnode_2']" "css_element" exists
    # The child node is locked by the parent-courses restriction referencing the
    # payload-named parent node.
    And "[data-id='dndnode_2'] .icon-link i.fa-lock" "css_element" should exist
    # Open the locked node's feedback panel, which renders the restriction feedback
    # string (embedding the parent node name) via v-html.
    And I pan vue flow to "[data-id='dndnode_2']"
    And I open the runtime feedback panel for node "dndnode_2"
    And I wait until ".dndnode_2_user_info_listener .selectable" "css_element" exists
    # The parent name text is shown (escaped) - proving the v-html sink is exercised...
    Then I should see "PWN" in the ".dndnode_2_user_info_listener" "css_element"
    # ...but no live <img onerror> element ever executes.
    And no live "img" element with an "onerror" attribute should exist
