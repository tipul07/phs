### PHS testing environment

**Note**: PHPUnit 9 requires PHP 7.3+. It is recommended you use PHP 7.3+ in tests.
If this is not posible, you will have to use older version of PHPUnit that is compatible with your PHP version.
Also, take in cosideration PHPUnit uses [generators](https://www.php.net/manual/ro/language.generators.overview.php), meaning minimum PHP version required is 5.5.0.

All context classes MUST follow [PSR-4 specifications](https://www.php-fig.org/psr/psr-4/).

*Summary*: Class names should follow file names and namespaces should mirror directory structure for respective class.

There is a CLI script in ``bin`` directory named ``tests``. You should use this script in order to enable or disable plugins for tests.
As a requirement, a plugin should have a ``tests`` directory in its directory structure (``PROJECT_DIR/plugins/PLUGIN_NAME/tests/``).

``tests`` script will create symlinks as follows:

 - Directory symlink ``PROJECT_DIR/tests/behat/contexts/PLUGIN_NAME`` will point to ``PROJECT_DIR/plugins/PLUGIN_NAME/tests/behat/contexts`` 
 - Directory symlink ``PROJECT_DIR/tests/behat/features/PLUGIN_NAME`` will point to ``PROJECT_DIR/plugins/PLUGIN_NAME/tests/behat/features`` 
 - File symlink ``PROJECT_DIR/tests/behat/config/PLUGIN_NAME.yml`` will point to ``PROJECT_DIR/plugins/PLUGIN_NAME/tests/behat/behat.yml`` 

Namespaces for plugin contexts must be like ``phs\tests\behat\contexts\PLUGIN_NAME``

#### Enabling a plugin for Behat tests
  
``tests`` script will look for a ``PROJECT_DIR/plugins/PLUGIN_NAME/tests/behat/behat.yml`` in plugin's directory. If this file is not present, script will consider plugin is not Behat ready. If you don't want to change any configuration in Behat, just create an empty ``PROJECT_DIR/plugins/PLUGIN_NAME/tests/behat/behat.yml`` file in your plugin.

Learning by example... Check ``admin`` plugin for ``tests`` directory to have a better understanding on how to integrate Behat. More details will follow here in a future release.

#### Installation notes

PHS comes with a ``composer.json`` file which includes latest versions of Behat and PHPUnit known to work well with PHS tests structure. Also, in ``PROJECT_DIR/tests`` there is a composer.phar known to work with PHS tests integration.

If you want to change any version (composer, Behat or PHPUnit) you are free to do it, but be sure that new versions still work with PHS integration for tests suite.

When installing Behat and PHPUnit it is recommended you do it using composer:

``php composer.phar require --dev behat/behat``

and

``php composer.phar require --dev phpunit/phpunit ^9``

