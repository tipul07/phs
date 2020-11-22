<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_Params;

class PHS_Step_4 extends PHS_Step
{
    public function step_details()
    {
        return array(
            'title' => $this->_pt( 'Site Security' ),
            'description' => $this->_pt( 'Setting up crypto configuration...' ),
        );
    }

    public function get_config_file()
    {
        return 'site_security.php';
    }

    public function step_config_passed()
    {
        if( !$this->load_current_configuration() )
            return false;

        if( !defined( 'PHS_DEFAULT_CRYPT_KEY' )
         or !constant( 'PHS_DEFAULT_CRYPT_KEY' ) )
        {
            $this->add_error_msg( $this->_pt( 'Crypt Key not provided.' ) );
            return false;
        }

        global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;

        if( empty( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR )
         or !$this->_validate_crypto_internal_keys_array( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) )
        {
            if( $this->has_error() )
                $this->add_error_msg( $this->get_error_message() );
            else
                $this->add_error_msg( $this->_pt( 'Error validating crypto internal keys array.' ) );

            return false;
        }

        return true;
    }

    public function load_current_configuration()
    {
        if( $this->config_file_loaded() )
            return true;

        $config_file = PHS_SETUP_CONFIG_DIR.$this->get_config_file();
        if( !@file_exists( $config_file ) )
            return false;

        ob_start();
        include( $config_file );
        ob_end_clean();

        $this->config_file_loaded( true );

        return true;
    }

    protected function render_step_interface( $data = false )
    {
        $this->reset_error();

        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $do_generate_keys = PHS_Params::_p( 'do_generate_keys', PHS_Params::T_INT );
        $phs_crypt_key = PHS_Params::_p( 'phs_crypt_key', PHS_Params::T_ASIS );
        $phs_crypt_internal_keys_arr = PHS_Params::_p( 'phs_crypt_internal_keys_arr', PHS_Params::T_ARRAY, array( 'type' => PHS_Params::T_NOHTML ) );

        $do_submit = PHS_Params::_p( 'do_submit', PHS_Params::T_NOHTML );

        if( empty( $phs_crypt_internal_keys_arr )
         or !is_array( $phs_crypt_internal_keys_arr )
         or count( $phs_crypt_internal_keys_arr ) != 34 )
            $phs_crypt_internal_keys_arr = array();

        if( !empty( $do_generate_keys ) )
            $phs_crypt_internal_keys_arr = $this->_generate_crypto_internal_keys_array();

        if( !empty( $do_submit ) )
        {
            if( empty( $phs_crypt_internal_keys_arr )
             or !is_array( $phs_crypt_internal_keys_arr ) )
                $this->add_error_msg( $this->_pt( 'Please provide crypto internal keys array.' ) );

            elseif( !($cleaned_keys = $this->_validate_crypto_internal_keys_array( $phs_crypt_internal_keys_arr )) )
            {
                if( $this->has_error() )
                    $this->add_error_msg( $this->get_error_message() );
                else
                    $this->add_error_msg( $this->_pt( 'Error validating crypto internal keys array.' ) );
            } else
                $phs_crypt_internal_keys_arr = $cleaned_keys;

            if( empty( $phs_crypt_key ) )
                $this->add_error_msg( 'Please provide Crypting Key.' );

            if( !$this->has_error_msgs() )
            {
                $defines_arr = array(
                    'PHS_DEFAULT_CRYPT_KEY' => array(
                        'value' => $phs_crypt_key,
                        'line_comment' => 'Default crypting keys...',
                    ),
                );

                $crypt_internal_keys_raw_str =
                    "\n".
                    '// !!! DO NOT CHANGE THESE UNLESS YOU KNOW WHAT YOU\'R DOING !!!'."\n".
                    'global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;'."\n".
                    '$PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR = array('."\n";

                foreach( $phs_crypt_internal_keys_arr as $internal_key_str )
                {
                    $crypt_internal_keys_raw_str .= '    \''.$internal_key_str.'\','."\n";
                }

                $crypt_internal_keys_raw_str .=
                    ');'."\n\n";

                $config_params = array(
                    array(
                        'defines' => $defines_arr,
                    ),
                    array(
                        'line_comment' => 'Crypting internal keys. If you change this everything crypted will be lost!!!',
                        'raw' => $crypt_internal_keys_raw_str,
                    ),
                );

                if( $this->save_step_config_file( $config_params ) )
                {
                    $this->add_success_msg( $this->_pt( 'Config file saved with success. Redirecting to next step...' ) );

                    if( ($setup_instance = $this->setup_instance()) )
                        $setup_instance->goto_next_step();
                }

                else
                {
                    if( $this->has_error() )
                        $this->add_error_msg( $this->get_error_message() );
                    else
                        $this->add_error_msg( $this->_pt( 'Error saving config file for current step.' ) );
                }
            }
        }

        if( empty( $foobar ) )
        {
            global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;

            if( $this->config_file_loaded() )
            {
                $this->add_notice_msg( 'Existing config file loaded...' );

                if( empty( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR )
                 or !is_array( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) )
                    $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR = array();

                $phs_crypt_key = PHS_DEFAULT_CRYPT_KEY;
                $phs_crypt_internal_keys_arr = $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;
            } else
            {
                $phs_crypt_key = '';
                $phs_crypt_internal_keys_arr = $this->_generate_crypto_internal_keys_array();
            }
        }

        $data['phs_crypt_key'] = $phs_crypt_key;
        $data['phs_crypt_internal_keys_arr'] = $phs_crypt_internal_keys_arr;

        return PHS_Setup_layout::get_instance()->render( 'step4', $data );
    }

    private function _generate_crypto_internal_keys_array()
    {
        $internal_keys_arr = array();
        for( $i = 0; $i < 34; $i++ )
        {
            $internal_keys_arr[] = md5( rand( 0, PHP_INT_MAX ).microtime().rand( 0, PHP_INT_MAX ) );
        }

        return $internal_keys_arr;
    }

    private function _validate_crypto_internal_keys_array( $arr )
    {
        $this->reset_error();

        if( empty( $arr ) or !is_array( $arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Crypto internal keys parameter is not an array.' ) );
            return false;
        }

        if( count( $arr ) != 34 )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Crypto internal keys array should have exactly 34 elements.' ) );
            return false;
        }

        $new_crypto_arr = array();
        $knti = -1;
        foreach( $arr as $key_i => $key_str )
        {
            $knti++;

            if( !empty( $key_str ) and is_string( $key_str ) )
                $key_str = trim( $key_str );

            if( empty( $key_str )
             or !is_string( $key_str )
             or !@preg_match( '/[0-9a-f]{32}/i', $key_str ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Index %s of Crypto internal keys array should be a string with hexa values, 32 chars length.', $knti ) );
                return false;
            }

            $new_crypto_arr[] = strtolower( $key_str );
        }

        return $new_crypto_arr;
    }
}
