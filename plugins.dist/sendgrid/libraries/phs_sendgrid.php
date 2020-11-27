<?php

namespace phs\plugins\sendgrid\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Library;

class PHS_Sendgrid extends PHS_Library
{
    const ERR_DEPENDENCIES = 1, ERR_SETTINGS = 2;

    const SENDGRID_DIR = 'sendgrid';

    /** @var bool|\phs\plugins\sendgrid\PHS_Plugin_Sendgrid $_sendgrid_plugin */
    private $_sendgrid_plugin = false;

    private function _load_dependencies()
    {
        $this->reset_error();

        if( empty( $this->_sendgrid_plugin )
         && !($this->_sendgrid_plugin = PHS::load_plugin( 'sendgrid' )) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t load SendGrid plugin instance.' ) );
            return false;
        }

        if( !($sendgrid_paths = $this->get_sendgrid_dir_paths()) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Error obtaining SendGrid library directory paths.' ) );

            return false;
        }

        $autoload_file = $sendgrid_paths['path'].'vendor/autoload.php';

        if( !@file_exists( $autoload_file ) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Autoload for SendGrid functionality not found.' ) );
            return false;
        }

        ob_start();
        include_once( $autoload_file );
        @ob_get_clean();

        if( !@class_exists( '\\SendGrid', true )
         || !@class_exists( '\\SendGrid\\Mail\\Mail', true ) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'SendGrid required classes not found.' ) );
            return false;
        }

        return true;
    }

    public function get_sendgrid_dir_paths()
    {
        $this->reset_error();

        if( !($library_paths = $this->get_library_location_paths()) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Error obtaining SendGrid library directory paths.' ) );
            return false;
        }

        $return_arr = [];
        $return_arr['www'] = $library_paths['library_www'].self::SENDGRID_DIR.'/';
        $return_arr['path'] = $library_paths['library_path'].self::SENDGRID_DIR.'/';

        return $return_arr;
    }

    /**
     * @param bool $as_static Should we return a static instance
     *
     * @return bool|\SendGrid\Mail\Mail
     */
    public function get_sendgrid_instance( $as_static = false )
    {
        static $sendgrid_obj = null;

        $this->reset_error();

        if( !$this->_load_dependencies() )
            return false;

        if( !$as_static )
            return new \SendGrid\Mail\Mail();

        if( $sendgrid_obj !== null )
            return $sendgrid_obj;

        $sendgrid_obj = new \SendGrid\Mail\Mail();

        return $sendgrid_obj;
    }

}
