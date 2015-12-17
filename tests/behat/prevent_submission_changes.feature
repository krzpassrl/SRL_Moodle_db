@mod @mod_setask
Feature: Prevent or allow setaskment submission changes
  In order to control when a student can change his/her submission
  As a teacher
  I need to prevent or allow student submission at any time

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |

  @javascript
  Scenario: Preventing changes and allowing them again
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | Test setaskment name |
      | Description | Submit your online text |
      | setasksubmission_onlinetext_enabled | 1 |
      | setasksubmission_file_enabled | 0 |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission |
    And I press "Save changes"
    And I press "Edit submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission and he/she edited me |
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    When I follow "View/grade all submissions"
    And I click on "Edit" "link" in the "Student 1" "table_row"
    And I follow "Prevent submission changes"
    Then I should see "Submission changes not allowed"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And "Edit submission" "button" should not exist
    And I should see "This setaskment is not accepting submissions"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I follow "View/grade all submissions"
    And I click on "Edit" "link" in the "Student 1" "table_row"
    And I follow "Allow submission changes"
    And I should not see "Submission changes not allowed"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I should not see "This setaskment is not accepting submissions"
    And I press "Edit submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission edited again |
    And I press "Save changes"
    And I should see "I'm the student submission edited again"

  @javascript @_alert
  Scenario: Preventing changes and allowing them again (batch action)
    Given the following "activities" exist:
      | activity | course | idnumber | name                 | intro                       | setasksubmission_onlinetext_enabled | setasksubmission_file_enabled |
      | setask   | C1     | setask1  | Test setaskment name | Test setaskment description | 1                                   | 0                             |
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission |
    And I press "Save changes"
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student2 submission |
    And I press "Save changes"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    When I follow "View/grade all submissions"
    And I set the field "selectall" to "1"
    And I click on "Go" "button" confirming the dialogue
    Then I should see "Submission changes not allowed" in the "Student 1" "table_row"
    And I should see "Submission changes not allowed" in the "Student 2" "table_row"
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I should not see "Edit submission"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I follow "View/grade all submissions"
    And I set the field "selectall" to "1"
    And I set the field "id_operation" to "Unlock submissions"
    And I click on "Go" "button" confirming the dialogue
    And I should not see "Submission changes not allowed" in the "Student 1" "table_row"
    And I should not see "Submission changes not allowed" in the "Student 2" "table_row"
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I press "Edit submission"
    And I set the following fields to these values:
      | Online text | I'm the student2 submission and he/she edited me |
    And I press "Save changes"
    And I log out
