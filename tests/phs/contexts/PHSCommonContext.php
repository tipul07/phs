<?php

namespace phs\tests\phs\contexts;

use \phs\PHS;
use \phs\PHS_Cli;
use \phs\PHS_Scope;
use PHPUnit\Framework\Assert;
use Behat\Behat\Tester\Exception\PendingException;

class PHSCommonContext extends PHSAbstractContext
{
    /**
     * @Given /^Current PHS scope is set to "([^"]*)"$/
     *
     * @param string $arg1
     * @return bool
     */
    public function currentPHSScopeIsSetTo( $arg1 )
    {
        return (PHS_Scope::current_scope()===$arg1);
    }

    /**
     * @Given /^Script is running in CLI mode$/
     *
     * @return bool
     */
    public function scriptIsRunningInCLIMode()
    {
        return (PHS_Cli::running_in_cli()?true:false);
    }

    /**
     * @When /^I want to check framework configuration files$/
     *
     * @return bool
     */
    public function iWantToCheckFrameworkConfigurationFiles()
    {
        return true;
    }

    /**
     * @Then /^A file exists "([^"]*)" in "([^"]*)"$/
     *
     * @param string $file_name
     * @param string $dir
     */
    public function aFileExistsIn( $file_name, $dir = '' )
    {
        Assert::assertNotEmpty( $file_name, 'No file provided' );

        if( empty( $dir ) )
            $dir = '';

        $dir = rtrim( PHS_PATH.ltrim( str_replace( '..', '', $dir ), '/\\' ), '/\\' );
        $file_name = ltrim( str_replace( '..', '', $file_name ), '/\\' );

        Assert::assertFileExists( $dir.'/'.$file_name, 'File doesn\'t exist.' );
    }

    /**
     * @Given /^A symlink exists "([^"]*)" to "([^"]*)"$/
     *
     * @param string $link
     * @param string $target
     */
    public function aSymlinkExistsTo( $link, $target )
    {
        Assert::assertNotEmpty( $link, 'Please provide symlink' );
        Assert::assertNotEmpty( $target, 'Please provide symlink target' );

        $dir = rtrim( PHS_PATH, '/\\' );
        $link = ltrim( str_replace( '..', '', $link ), '/\\' );

        $link_file = $dir.'/'.$link;
        $target_file = $dir.'/'.$target;

        Assert::assertFileExists( $link_file, 'Symlink doesn\'t exist.' );
        Assert::assertFileExists( $target_file, 'Target doesn\'t exist.' );

        Assert::assertTrue( @is_link( $link_file ), 'Not a symlink' );
    }

    /**
     * @Given /^A directory "([^"]*)" exists in "([^"]*)"$/
     *
     * @param string $dir
     * @param string $location
     *
     * @return bool
     */
    public function aDirectoryExistsIn( $dir, $location )
    {
        Assert::assertNotEmpty( $dir, 'Please provide directory name' );
        Assert::assertNotEmpty( $location, 'Please provide directory location' );

        $root = rtrim( PHS_PATH, '/\\' );
        $location = $root.'/'.trim( $location, '/\\' );

        Assert::assertFileExists( $location, 'Location doesn\'t exist.' );
        Assert::assertTrue( @is_dir( $location ), 'Location is not a directory' );

        if( $dir !== '*' )
        {
            Assert::assertFileExists( $location.'/'.$dir, 'Location doesn\'t exist.' );
            Assert::assertTrue( @is_dir( $location.'/'.$dir ), 'Location is not a directory' );

            return true;
        }

        if( !($fil = @opendir( $location )) )
            \PHPUnit\Framework\throwException( new \Exception( 'Cannot open '.$location.' for reading.' ) );

        $a_directory_found = false;
        while( false !== ($entry = @readdir( $fil )) )
        {
            if( $entry === '.' or $entry === '..' )
                continue;

            if( @is_dir( $location.'/'.$entry ) )
            {
                $a_directory_found = true;
                break;
            }
        }

        @closedir( $fil );

        Assert::assertTrue( $a_directory_found, 'Directory not found.' );

        return $a_directory_found;
    }
}
