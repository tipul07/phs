Feature: Check PHS framework installation
  In order to start working with the framework
  Basic installation of the framework should be completed

  Scenario: Check framework configuration files
    Given Current PHS scope is set to "test"
    And Script is running in CLI mode
    When I want to check framework configuration files
    Then A file exists "main.php" in "/"
    And A symlink exists "languages/en" to "languages/en.dist"
#    And A directory "*" exists in "/plugins"
    And Plugin "{main_plugins}" is in status "active"
