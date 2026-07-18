@block @block_oerclient @javascript
Feature: Add the OER Exchange block to the Dashboard
  In order to see my shares and recent OER at a glance
  As a user
  I need to be able to add the combined block to my Dashboard

  Scenario: An admin adds the block to their Dashboard and both panels render
    Given I log in as "admin"
    And I visit "/my/"
    And I turn editing mode on
    When I add the "OER Exchange" block
    Then I should see "What I've shared" in the "OER Exchange" "block"
    And I should see "Nothing has been shared to the Exchange from this site yet." in the "OER Exchange" "block"
    And I should see "Recent OER available" in the "OER Exchange" "block"
