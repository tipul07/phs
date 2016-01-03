<?php
namespace phs\system\core\views;

use \phs\PHS;
use \phs\libraries\PHS_Signal_and_slot;
use \phs\libraries\PHS_Controller;

class PHS_View extends PHS_Signal_and_slot
{
    const ERR_BAD_CONTROLLER = 30000, ERR_BAD_TEMPLATE = 30001, ERR_BAD_THEME = 30002, ERR_TEMPLATE_DIRS = 30003;

    protected $_template = '';
    protected $_theme = '';
    // Array of directories where we check if template exists
    protected $_template_dirs = array();

    // Resulting template file
    protected $_template_file = '';

    /** @var PHS_Controller|bool $_controller */
    protected $_controller = false;

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_VIEW;
    }

    protected function reset_view()
    {
        $this->_template = '';
        $this->_theme = '';
        $this->_template_dirs = array();
        $this->_template_file = '';
    }

    function __clone()
    {
        $this->reset_view();
    }

    public function set_controller( $controller_obj )
    {
        if( $controller_obj !== false
        and !($controller_obj instanceof PHS_Controller) )
        {
            $this->set_error( self::ERR_BAD_CONTROLLER, self::_t( 'Not a controller instance.' ) );
            return false;
        }

        $this->_controller = $controller_obj;
        return true;
    }

    /**
     * @return bool|\phs\libraries\PHS_Controller Controller that "owns" this view or false if no controller
     */
    public function get_controller()
    {
        return $this->_controller;
    }

    public function get_template()
    {
        return $this->_template;
    }

    public function get_template_dirs()
    {
        return $this->_template_dirs;
    }

    public function get_template_file()
    {
        return $this->_template_file;
    }

    protected function _get_template_directories()
    {
        $this->_template_dirs = array();

        if( !empty( $this->_controller ) and !$this->_controller->instance_is_core() )
            $this->_template_dirs[$this->_controller->instance_plugin_path() . 'templates/'] = $this->_controller->instance_plugin_www() . 'templates/';

        if( defined( 'PHS_THEMES_WWW' ) and defined( 'PHS_THEMES_DIR' ) )
        {
            if( !empty( $this->_theme ) )
                $this->_template_dirs[PHS_THEMES_DIR . $this->_theme . '/'] = PHS_THEMES_WWW . $this->_theme . '/';
            if( ($default_theme = PHS::get_default_theme())
            and $default_theme != $this->_theme )
                $this->_template_dirs[PHS_THEMES_DIR . $default_theme . '/'] = PHS_THEMES_WWW . $default_theme . '/';
        }

        return $this->_template_dirs;
    }

    protected function _get_template_path()
    {
        $this->reset_error();

        if( !empty( $this->_template_file ) )
            return $this->_template_file;

        if( empty( $this->_template ) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Please provide a template first.' ) );
            return false;
        }

        if( !($dirs_list = $this->_get_template_directories())
         or !is_array( $dirs_list ) )
        {
            $this->set_error( self::ERR_TEMPLATE_DIRS, self::_t( 'Couldn\'t get template directories.' ) );
            return false;
        }

        @clearstatcache();
        foreach( $dirs_list as $dir_path => $dir_www )
        {
            if( @file_exists( $dir_path.$this->_template.'.php' ) )
            {
                $this->_template_file = $dir_path.$this->_template.'.php';
                return true;
            }
        }

        $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Template [%s] not found.', $this->_template ) );
        return false;
    }

    public static function safe_escape_template( $template )
    {
        if( empty( $template ) or !is_string( $template )
            or preg_match( '@[^a-zA-Z0-9_-]@', $template ) )
            return false;

        return $template;
    }

    public static function safe_escape_resource( $resource )
    {
        if( empty( $resource ) or !is_string( $resource )
         or preg_match( '@[^a-zA-Z0-9_-\./]@', $resource ) )
            return false;

        $resource = str_replace( '..', '', trim( $resource, '/' ) );

        return $resource;
    }

    public function set_template( $template )
    {
        $this->reset_error();

        if( !self::safe_escape_template( $template ) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Invalid template name.' ) );
            return false;
        }

        $this->_template = $template;
        return true;
    }

    public function set_theme( $theme )
    {
        $this->reset_error();

        if( empty( $theme ) )
            $theme = PHS::get_theme();

        if( !PHS::valid_theme( $theme ) )
        {
            $this->set_error( self::ERR_BAD_THEME, self::_t( 'Invalid theme.' ) );
            return false;
        }

        $this->_theme = $theme;
        return true;
    }

    public function sub_view( $template, $force_theme = false )
    {
        $subview_obj = clone $this;

        $subview_obj->set_theme( $force_theme );
        $subview_obj->set_template( $template );

        return $subview_obj->render( $template, $force_theme );
    }

    public function render( $template = false, $force_theme = false )
    {
        if( $template !== false )
        {
            if( !$this->set_template( $template ) )
                return false;
        }

        if( $force_theme !== false )
        {
            if( !$this->set_theme( $force_theme ) )
                return false;
        }

        if( !$this->_get_template_path() )
            return false;

        $resulting_buf = '';

        // sanity check...
        if( !empty( $this->_template_file )
        and @file_exists( $this->_template_file ) )
        {
            ob_start();
            include( $this->_template_file );
            $resulting_buf .= ob_get_clean();
        }

        return $resulting_buf;
    }
}
