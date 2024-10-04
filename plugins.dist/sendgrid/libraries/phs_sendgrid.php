<?php

namespace phs\plugins\sendgrid\libraries;

use SendGrid;
use SendGrid\Mail\Mail;
use phs\libraries\PHS_Library;
use phs\plugins\sendgrid\PHS_Plugin_Sendgrid;

class PHS_Sendgrid extends PHS_Library
{
    public const SENDGRID_DIR = 'sendgrid';

    private ?PHS_Plugin_Sendgrid $_sendgrid_plugin = null;

    public function get_sendgrid_dir_paths() : ?array
    {
        $this->reset_error();

        if (!($library_paths = $this->get_library_location_paths())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error obtaining SendGrid library directory paths.'));

            return null;
        }

        $return_arr = [];
        $return_arr['www'] = $library_paths['library_www'].self::SENDGRID_DIR.'/';
        $return_arr['path'] = $library_paths['library_path'].self::SENDGRID_DIR.'/';

        return $return_arr;
    }

    public function get_sendgrid_instance(bool $as_static = false) : ?Mail
    {
        static $sendgrid_obj = null;

        $this->reset_error();

        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!$as_static) {
            return new Mail();
        }

        if ($sendgrid_obj !== null) {
            return $sendgrid_obj;
        }

        $sendgrid_obj = new Mail();

        return $sendgrid_obj;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (empty($this->_sendgrid_plugin)
            && !($this->_sendgrid_plugin = PHS_Plugin_Sendgrid::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Couldn\'t load SendGrid plugin instance.'));

            return false;
        }

        if (!($sendgrid_paths = $this->get_sendgrid_dir_paths())) {
            $this->set_error_if_not_set(self::ERR_DEPENDENCIES, $this->_pt('Error obtaining SendGrid library directory paths.'));

            return false;
        }

        $autoload_file = $sendgrid_paths['path'].'vendor/autoload.php';

        if (!@file_exists($autoload_file)) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Autoload for SendGrid functionality not found.'));

            return false;
        }

        ob_start();
        include_once $autoload_file;
        @ob_get_clean();

        if (!@class_exists(SendGrid::class, true)
            || !@class_exists(Mail::class, true)) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('SendGrid required classes not found.'));

            return false;
        }

        return true;
    }
}
