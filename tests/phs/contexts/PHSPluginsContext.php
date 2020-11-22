<?php

namespace phs\tests\phs\contexts;

use phs\PHS;
use \phs\PHS_Cli;
use PHPUnit\Framework\Assert;
use Behat\Behat\Tester\Exception\PendingException;

class PHSPluginsContext extends PHSAbstractContext
{
    /** @var \phs\system\core\models\PHS_Model_Plugins $_plugins_model */
    protected static $_plugins_model = false;

    /**
     * @BeforeSuite
     * @throws \Exception
     */
    public static function prepare( $scope )
    {
        if( !(self::$_plugins_model = PHS::load_model( 'plugins' )) )
        {
            throw new \RuntimeException( 'Couldn\'t load plugins model.' );
        }
    }

    /**
     * @return array|bool
     * @throws \RuntimeException
     */
    public function get_plugins_as_dirs()
    {
        if( empty( self::$_plugins_model ) )
        {
            throw new \RuntimeException( '@BeforeSuite not triggered for '.__CLASS__.' class.' );
        }

        $plugins_model = self::$_plugins_model;

        if( ($plugins_arr = $plugins_model->get_all_plugin_names_from_dir()) === false
         or !is_array( $plugins_arr ) )
        {
            if( !$plugins_model->has_error() )
                $error_msg = 'Error obtaining plugins list.';
            else
                $error_msg = $plugins_model->get_simple_error_message();

            throw new \RuntimeException( $error_msg );
        }

        return $plugins_arr;
    }

    /**
     * @Given /^Plugin "([^"]*)" is in status "([^"]*)"$/
     *
     * @param string $plugin
     * @param string $status installed|active if we have a not in front status is negated (eg. "not installed", "not active")
     */
    public function pluginIsInStatus( $plugin, $status )
    {
        Assert::assertNotEmpty( $plugin, self::_t( 'Please provide plugin name.' ) );
        Assert::assertNotEmpty( $status, self::_t( 'Please provide plugin status.' ) );

        Assert::assertNotEmpty( ($plugins_from_dir = $this->get_plugins_as_dirs()), self::_t( 'No plugins installed yet.' ) );

        $negate_status = false;
        if( false !== strpos( ($status = trim( $status )), ' ' ) )
        {
            $status_arr = explode( ' ', $status );
            if( !empty( $status_arr[0] )
            and strtolower( $status_arr[0] ) === 'not' )
                $negate_status = true;

            if( !empty( $status_arr[1] ) )
                $status = trim( $status_arr[1] );
        }

        $valid_statuses = [ 'installed', 'active' ];

        $status = strtolower( $status );
        Assert::assertTrue( @in_array( $status, $valid_statuses ), self::_t( 'Status should be %s', @implode( ', ', $valid_statuses ) ) );

        if( $plugin === '{main_plugins}' )
            $plugin_names_list = PHS::get_always_active_plugins();
        else
            $plugin_names_list = [ $plugin ];

        foreach( $plugin_names_list as $plugin_name )
        {
            /** @var \phs\libraries\PHS_Plugin $plugin_obj */
            if( !($plugin_obj = PHS::load_plugin( $plugin_name ))
             or !($plugin_info = $plugin_obj->get_plugin_info()) )
            {
                if( PHS::st_has_error() )
                    $error_msg = PHS::st_get_simple_error_message();
                else
                    $error_msg = self::_t( 'Couldn\'t load plugin %s', $plugin_name );

                throw new \RuntimeException( $error_msg );
            }

            $bool_status = false;
            if( $status === 'installed' )
            {
                $bool_status = (!empty( $plugin_info['is_installed'] ));
            } elseif( $status === 'active' )
            {
                $bool_status = (!empty( $plugin_info['is_active'] ));
            }

            if( !empty( $negate_status ) )
                $bool_status = !$bool_status;

            Assert::assertTrue( $bool_status, self::_t( 'Plugin %s is %s%s.',
                                                        $plugin_name, ($negate_status?'':self::_t( 'not' ).' '), $status ) );
        }
    }
}
