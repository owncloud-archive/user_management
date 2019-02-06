@webUI @insulated @disablePreviews
Feature: manage user quota
  As an admin
  I want to manage user quota
  So that users can only take up a certain amount of storage space

  Background:
    Given these users have been created with default attributes but not initialized:
      | username |
      | user1    |
    And user admin has logged in using the webUI
    And the administrator has browsed to the users page

  Scenario Outline: change quota to a valid value
    Given the administrator has set the quota of user "user1" to "<start_quota>" using the webUI
    When the administrator changes the quota of user "user1" to "<wished_quota>" using the webUI
    And the administrator reloads the users page
    Then the quota of user "user1" should be set to "<expected_quota>" on the webUI
    Examples:
      | start_quota | wished_quota | expected_quota |
      | Unlimited   | 5 GB         | 5 GB           |
      | 1 GB        | 5 GB         | 5 GB           |

  @skipOnOcV10.0.3
  Scenario: change quota to a valid value that do not work on 10.0.3
    Given the administrator has set the quota of user "user1" to "Unlimited" using the webUI
    When the administrator changes the quota of user "user1" to "0 Kb" using the webUI
    And the administrator reloads the users page
    Then the quota of user "user1" should be set to "0 B" on the webUI

  @issue-100
  Scenario: change quota to an invalid value
    When the administrator changes the quota of user "user1" to "stupidtext" using the webUI
    Then a notification should be displayed on the webUI with the text 'Invalid quota value "stupidtext"'
    And the quota of user "user1" should be set to "stupidtext" on the webUI
    #And the quota of user "user1" should be set to "Default" on the webUI

  Scenario: change quota to an invalid value
    When the administrator changes the quota of user "user1" to "34,54GB" using the webUI
    Then a notification should be displayed on the webUI with the text 'Invalid quota value "34,54GB"'
    And the quota of user "user1" should be set to "34,54GB" on the webUI
    #And the quota of user "user1" should be set to "Default" on the webUI

  Scenario: change quota to an invalid value
    When the administrator changes the quota of user "user1" to "30/40GB" using the webUI
    Then a notification should be displayed on the webUI with the text 'Invalid quota value "30/40GB"'
    And the quota of user "user1" should be set to "30/40GB" on the webUI
    #And the quota of user "user1" should be set to "Default" on the webUI

  Scenario: change quota to an invalid value
    When the administrator changes the quota of user "user1" to "30/40" using the webUI
    Then a notification should be displayed on the webUI with the text 'Invalid quota value "30/40"'
    And the quota of user "user1" should be set to "30/40" on the webUI
    #And the quota of user "user1" should be set to "Default" on the webUI

  Scenario: change quota to an invalid value
    When the administrator changes the quota of user "user1" to "3+56 B" using the webUI
    Then a notification should be displayed on the webUI with the text 'Invalid quota value "3+56 B"'
    And the quota of user "user1" should be set to "3+56 B" on the webUI
    #And the quota of user "user1" should be set to "Default" on the webUI

  Scenario: change quota to an invalid value
    When the administrator changes the quota of user "user1" to "-1 B" using the webUI
    Then a notification should be displayed on the webUI with the text 'Invalid quota value "-1 B"'
    And the quota of user "user1" should be set to "-1 B" on the webUI
    #And the quota of user "user1" should be set to "Default" on the webUI
