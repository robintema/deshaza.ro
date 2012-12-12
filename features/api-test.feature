@api
Feature: api-get-building-details
  In order to get the details of a building
  As a mobile user
  I need to pass in the id of the building
  And the api should return a result with the details of the building

  Scenario: Home page loaded
    Given I am on home page
    Then I should see "Home" text on page