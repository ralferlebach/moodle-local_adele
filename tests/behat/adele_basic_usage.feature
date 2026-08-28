@local @local_adele @javascript

Feature: As an admin I perform basic adele actions - create, update, duplicate, delete.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | user     | Username  | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname | summary     |
      | Course 1 | C1        | LP Course 1 |
      | Course 2 | C2        | LP Course 2 |
      | Course 3 | C3        | LP Course 3 |
      | Course 4 | C4        | LP Course 4 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user     | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following config values are set as admin:
      | config            | value                                                      | plugin      |
      | restrictionfilter | timed,timed_duration,specific_course,parent_courses,manual | local_adele |
    And I change viewport size to "1366x3000"

  @javascript
  Scenario: Adele usage: admin create a new learning path
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "Test Learning Path"
    And I set the field "goalsubjectplaceholder" to "Test Learning Path Description"
    And I click on "Select learning path image" "button"
    And I click on ".image-selection-container .image-option-img" "css_element"
    ## Create learning part in visual way.
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='starting_node']"
    And I click vue flow node "[data-id='starting_node']"
    And I wait "1" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(2)" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='dndnode_1']"
    And I zoom vue flow to "40" percent
    And I connect vue flow node "[data-id='dndnode_1']" to "[data-id='dndnode_2']"
    And I pan vue flow to "[data-id='starting_node']"
    And I click vue flow node "[data-id='starting_node']"
    And I wait "1" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(3)" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='dndnode_1']"
    And I zoom vue flow to "40" percent
    And I connect vue flow node "[data-id='dndnode_2']" to "[data-id='dndnode_3']"
    ## Manually add restriction to the parent nodes.
    And I click on "[data-id='dndnode_2'] .icon-link .fa-lock" "css_element"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(4)" to ".vue-flow__pane.vue-flow__container.draggable"
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "[data-id='dndnode_3'] .icon-link .fa-lock" "css_element"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(4)" to ".vue-flow__pane.vue-flow__container.draggable"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    ## Save entire learning path and validate its root elements - nodes and connections.
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I should see "Test Learning Path" in the ".learningcardcont .learningcard" "css_element"
    And I click on ".learningcardcont .learningcard " "css_element"
    And I should see "Course 1" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 2" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 3" in the ".vue-flow.learning-path-flow" "css_element"
    And "[data-id=\"dndnode_2dndnode_1\"]" "css_element" should exist
    And "[data-id=\"dndnode_3dndnode_2\"]" "css_element" should exist

  @javascript
  Scenario: Abandoning a criterion via Close must not persist it (#476 #494 retests)
    # Azadeh's retest: adding an access criterion and leaving the criteria editor
    # via "Close" (instead of Save) kept the criterion; the path then saved with
    # no error and the student view broke again. Closing must DISCARD the
    # abandoned criterion.
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "Close Leak LP"
    And I set the field "goalsubjectplaceholder" to "Close leak description"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='dndnode_1']"
    And I wait "1" seconds
    # Open the FIRST node's criteria editor and drop a "parent nodes" condition
    # (the #476 case - it may never be saved on a first node).
    And I click on "[data-id='dndnode_1'] .icon-link .fa-lock" "css_element"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(4)" to ".vue-flow__pane.vue-flow__container.draggable"
    And I wait "1" seconds
    # Abandon via Close (not Save); confirm the discard prompt if it appears.
    And I click on "Close" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    # The discard confirmation may or may not appear (that inconsistency is part
    # of the bug); accept it when shown.
    And I click on "Close anyway" "button" if it exists
    And I wait "1" seconds
    # Save the whole learning path.
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    Then I should see "Close Leak LP" in the ".learningcardcont .learningcard" "css_element"
    And learning path "Close Leak LP" node "dndnode_1" should have no restriction conditions
    # Reopen the criteria editor: the canvas must show the STORED (empty) state,
    # not resurrect the abandoned condition (stale-canvas half of the retest).
    And I click on ".learningcardcont .learningcard " "css_element"
    And I wait "1" seconds
    And I pan vue flow to "[data-id='dndnode_1']"
    And I click on "[data-id='dndnode_1'] .icon-link .fa-lock" "css_element"
    And I wait "1" seconds
    And "[data-id^='condition_']" "css_element" should not exist
    # The user's exact sequence: add an INVALID timed criterion, try to save the
    # criteria (blocked with the validation alert), close, reopen - the rejected
    # criterion must NOT linger in the editor session.
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to ".vue-flow__pane.vue-flow__container.draggable"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on ".modal.show .modal-footer button" "css_element" if it exists
    And I wait "1" seconds
    And I click on "Close" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "Close anyway" "button" if it exists
    And I wait "1" seconds
    And I click on "[data-id='dndnode_1'] .icon-link .fa-lock" "css_element"
    And I wait "1" seconds
    And "[data-id^='condition_']" "css_element" should not exist
    And learning path "Close Leak LP" node "dndnode_1" should have no restriction conditions

  @javascript
  Scenario: Editing a saved criterion - Save persists, Close discards (#494 #566 retests)
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "Roundtrip LP"
    And I set the field "goalsubjectplaceholder" to "Roundtrip description"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='dndnode_1']"
    And I wait "1" seconds
    # Create a COMPLETE "2 days from node opening" duration criterion (#566: this
    # exact combination was falsely rejected before).
    And I click on "[data-id='dndnode_1'] .icon-link .fa-lock" "css_element"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(2)" to ".vue-flow__pane.vue-flow__container.draggable"
    And I wait "1" seconds
    And I set the field "restriction-condition_1-option" to "1"
    And I set the field "restriction-condition_1-duration" to "2"
    And I set the field "restriction-condition_1-format" to "0"
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    Then learning path "Roundtrip LP" node "dndnode_1" restriction "timed_duration" value "selectedDuration" should be "2"
    # Abandon an edit via Close: the stored value must survive.
    And I click on ".learningcardcont .learningcard " "css_element"
    And I wait "1" seconds
    And I pan vue flow to "[data-id='dndnode_1']"
    And I click on "[data-id='dndnode_1'] .icon-link .fa-lock" "css_element"
    And I wait "1" seconds
    And I set the field "restriction-condition_1-duration" to "9"
    And I click on "Close" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "Close anyway" "button" if it exists
    And I wait "1" seconds
    Then learning path "Roundtrip LP" node "dndnode_1" restriction "timed_duration" value "selectedDuration" should be "2"
    # Reopen: the field must show the STORED 2, and a saved edit must persist.
    And I pan vue flow to "[data-id='dndnode_1']"
    And I click on "[data-id='dndnode_1'] .icon-link .fa-lock" "css_element"
    And I wait "3" seconds
    And "[data-id='condition_1']" "css_element" should exist
    And exactly one "[data-id='condition_1']" element should exist
    And exactly one "#restriction-condition_1-duration" element should exist
    And the field "restriction-condition_1-format" matches value "0"
    And the field "restriction-condition_1-option" matches value "1"
    And the field "restriction-condition_1-duration" matches value "2"
    And I set the field "restriction-condition_1-duration" to "9"
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    Then learning path "Roundtrip LP" node "dndnode_1" restriction "timed_duration" value "selectedDuration" should be "9"
