@webUI
Feature: add users
  As an admin
  I want to be able to change different settings
  So that I can see various details about users

  Background:
    Given these users have been created with default attributes but not initialized:
      | username |
      | user1    |
      | user2    |
    And user admin has logged in using the webUI
    And the administrator has browsed to the users page

  Scenario: administrator should be able to see email of a user
    When the administrator enables the setting "Show email address" in the User Management page using the webUI
    Then the administrator should be able to see the email of these users in the User Management page:
      | username | email        |
      | user1    | user1@example.org |
      | user2    | user2@example.org |

  Scenario: administrator should be able to see storage location of a user
    When the administrator enables the setting "Show storage location" in the User Management page using the webUI
    Then the administrator should be able to see the storage location of these users in the User Management page:
      | username | storage location |
      | user1    | /data/user1      |
      | user2    | /data/user2      |

  Scenario: administrator should be able to see last login of a user when the user is not initialized
    When the administrator enables the setting "Show last log in" in the User Management page using the webUI
    Then the administrator should be able to see the last login of these users in the User Management page:
      | username | last login |
      | user1    | never      |
      | user2    | never      |

  Scenario: administrator should be able to see last login of a user when the user is initialized
    When the administrator enables the setting "Show last log in" in the User Management page using the webUI
    And the administrator logs out of the webUI
    And user "user1" logs in using the webUI
    And the user logs out of the webUI
    And user admin logs in using the webUI
    And the administrator browses to the users page
    Then the administrator should be able to see the last login of these users in the User Management page:
      | username | last login  |
      | user1    | seconds ago |
      | user2    | never       |