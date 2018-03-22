@insulated @disablePreviews
Feature: login users
As a user
I want to be able to log into my account
So that I have access to my files

As an admin
I want only authorised users to log in
So that unauthorised access is impossible

	@TestAlsoOnExternalUserBackend
	Scenario: simple user login
		Given a regular user exists but is not initialized
		And I am on the login page
		When I login as a regular user with a correct password
		Then I should be redirected to a page with the title "Files - ownCloud"

	Scenario: admin login
		Given I am on the login page
		When I login with username "admin" and password "admin"
		Then I should be redirected to a page with the title "Files - ownCloud"

	Scenario: admin login with invalid password
		Given I am on the login page
		When I login with username "admin" and invalid password "invalidpassword"
		Then I should be redirected to a page with the title "ownCloud"

	Scenario: access the personal general settings page when not logged in
		Given I attempt to go to the personal general settings page
		Then I should be redirected to a page with the title "ownCloud"
		When I login with username "admin" and password "admin" after a redirect from the "personal general settings" page
		Then I should be redirected to a page with the title "Settings - ownCloud"

	Scenario: access the personal general settings page when not logged in using incorrect then correct password
		Given I attempt to go to the personal general settings page
		Then I should be redirected to a page with the title "ownCloud"
		When I login with username "admin" and invalid password "qwerty"
		Then I should be redirected to a page with the title "ownCloud"
		When I login with username "admin" and password "admin" after a redirect from the "personal general settings" page
		Then I should be redirected to a page with the title "Settings - ownCloud"
