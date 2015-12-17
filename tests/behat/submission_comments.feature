@mod @mod_setask
Feature: In an setaskment, students can comment in their submissions
  In order to refine setaskment submissions
  As a student
  I need to add comments about submissions

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Student comments an setaskment submission
    Given the following "activities" exist:
      | activity | course | idnumber | name                 | intro                       | setasksubmission_onlinetext_enabled |
      | setask   | C1     | setask1  | Test setaskment name | Test setaskment description | 1 |
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    When I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission |
    And I press "Save changes"
    And I click on ".comment-link" "css_element"
    And I set the field "content" to "First student comment"
    And I follow "Save comment"
    Then I should see "First student comment"
    And the field "content" matches value "Add a comment..."
    And I follow "Delete this comment"
    # Wait for the animation to finish.
    And I wait "2" seconds
    And I set the field "content" to "Second student comment"
    And I follow "Save comment"
    And I should see "Second student comment"
    And I should not see "First student comment"
    And I follow "Test setaskment name"
    And I click on ".comment-link" "css_element"
    And I should see "Second student comment"
    And I should not see "First student comment"

  @javascript
  Scenario: Teacher can comment on an offline setaskment
    Given the following "activities" exist:
      | activity | course | idnumber | name                 | intro                       | setasksubmission_onlinetext_enabled | setaskmentsubmission_file_enabled | setaskfeedback_comments_enabled |
      | setask   | C1     | setask1  | Test setaskment name | Test setaskment description | 0 | 0 | 1 |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test setaskment name"
    And I follow "View/grade all submissions"
    And I click on "Grade Student 1" "link" in the "Student 1" "table_row"
    When I set the following fields to these values:
      | Grade out of 100 | 50 |
      | Feedback comments | I'm the teacher feedback |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "50.00" in the "Student 1" "table_row"
    And I should see "I'm the teacher feedback" in the "Student 1" "table_row"
