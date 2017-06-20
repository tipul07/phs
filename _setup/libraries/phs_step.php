<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_Registry;

abstract class PHS_Step extends PHS_Registry
{
    /** @var bool|\phs\setup\libraries\PHS_Setup $setup_obj */
    private $setup_obj = false;

    private $config_file_loaded = false;

    abstract public function step_details();
    abstract public function get_config_file();
    abstract public function step_config_passed();
    abstract public function load_current_configuration();

    abstract protected function render_step_interface( $data = false );

    function __construct( $setup_inst = false )
    {
        parent::__construct();

        $this->setup_instance( $setup_inst );
    }

    protected function save_step_config_file( $params )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Invalid parameters sent to save config file method.' );
            return false;
        }

        if( empty( $params['defines'] ) or !is_array( $params['defines'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Nothing to save in the file.' );
            return false;
        }

        if( !($config_file = $this->get_config_file()) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Couldn\'t obtain config file name for current step.' );
            return false;
        }

        if( !($fil = @fopen( PHS_SETUP_CONFIG_DIR.$config_file, 'w' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Couldn\'t create config file with write rights ('.PHS_SETUP_CONFIG_DIR.$config_file.'). Please make sure PHP has rights to write in that file.' );
            return false;
        }

        @fputs( $fil, '<?php'."\n\n" );

        if( ($step_details = $this->step_details()) )
        {
            @fputs( $fil, '//'."\n".
                          '// '.$step_details['title']."\n".
                          '// '.str_replace( "\n", ' ', $step_details['description'] )."\n".
                          '//'."\n\n" );
        }

        foreach( $params['defines'] as $define_key => $definition_info )
        {
            if( !is_array( $definition_info ) )
                $definition_info = array( 'value' => $definition_info );

            if( !isset( $definition_info['value'] )
            and !isset( $definition_info['raw'] )
            and !isset( $definition_info['line_comment'] )
            and !isset( $definition_info['block_comment'] ) )
                continue;

            if( isset( $definition_info['line_comment'] )
             or isset( $definition_info['block_comment'] ) )
            {
                if( isset( $definition_info['line_comment'] ) )
                    @fputs( $fil, '// '.str_replace( "\n", '', $definition_info['line_comment'] ).' '."\n" );

                else
                {
                    @fputs( $fil, "\n\n".
                                '//'."\n".
                                '// '.trim( str_replace( "\n", "\n// ", $definition_info['block_comment'] ) )."\n".
                                '//'."\n".
                                "\n" );
                    continue;
                }
            }

            if( isset( $definition_info['value'] ) )
                $define_val = '\''.str_replace( '\'', '\\\'', $definition_info['value'] ).'\'';
            else
                $define_val = $definition_info['raw'];

            @fputs( $fil, 'define( \''.$define_key.'\', '.$define_val.' );'.
                          (isset( $definition_info['quick_comment'] )?' // '.str_replace( "\n", '', $definition_info['quick_comment'] ):'').
                          "\n" );
        }

        @fputs( $fil, "\n\n" );

        @fclose( $fil );
        @fflush( $fil );

        return true;
    }

    public function config_file_loaded( $loaded = null )
    {
        if( $loaded === null )
            return $this->config_file_loaded;

        $this->config_file_loaded = (!empty( $loaded ));

        return $this->config_file_loaded;
    }

    public function setup_instance( $setup_inst = false )
    {
        if( $setup_inst === false )
            return $this->setup_obj;

        $this->setup_obj = $setup_inst;

        return $this->setup_obj;
    }

    public function render( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        if( !($step_interface_buf = $this->render_step_interface( $data )) )
            $step_interface_buf = '';

        $data['step_interface_buf'] = $step_interface_buf;
        $data['step_instance'] = $this;

        return PHS_Setup_layout::get_instance()->render( 'template_steps', $data, true );
    }

    public function has_success_msgs()
    {
        return PHS_Setup_layout::get_instance()->has_success_msgs();
    }

    public function has_error_msgs()
    {
        return PHS_Setup_layout::get_instance()->has_error_msgs();
    }

    public function has_notice_msgs()
    {
        return PHS_Setup_layout::get_instance()->has_notices_msgs();
    }

    public function reset_success_msgs()
    {
        return PHS_Setup_layout::get_instance()->reset_success_msgs();
    }

    public function reset_error_msgs()
    {
        return PHS_Setup_layout::get_instance()->reset_error_msgs();
    }

    public function reset_notice_msgs()
    {
        return PHS_Setup_layout::get_instance()->reset_notice_msgs();
    }

    public function add_success_msg( $msg )
    {
        return PHS_Setup_layout::get_instance()->add_success_msg( $msg );
    }

    public function add_error_msg( $msg )
    {
        return PHS_Setup_layout::get_instance()->add_error_msg( $msg );
    }

    public function add_notice_msg( $msg )
    {
        return PHS_Setup_layout::get_instance()->add_notice_msg( $msg );
    }
}
