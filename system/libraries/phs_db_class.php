<?php

namespace phs\libraries;

/**
 *  MySQL class parser for PHS suite...
 */
abstract class PHS_db_class extends PHS_Registry implements PHS_db_interface
{
    //! Cannot connect to server.
    const ERR_CONNECT = 1;
    //! Cannot query server.
    const ERR_QUERY = 2;

    public function default_dump_parameters()
    {
        return array(
            // input parameters
            'output_dir' => '',
            'log_file' => '',

            // output parameters
            'dump_command_for_shell' => '',
        );
    }

    //! This method does only common part on checking and validating $dump_params
    /**
     * @inheritdoc
     */
    public function dump_database( $dump_params = false )
    {
        if( !($dump_params = self::validate_array( $dump_params, $this->default_dump_parameters() )) )
            $dump_params = array();

        if( !empty( $dump_params['output_dir'] ) )
        {
            if( !@is_dir( $dump_params['output_dir'] )
             or !@is_writable( $dump_params['output_dir'] ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Output directory does not exist or is not writable.' ) );
                return false;
            }
        }

        if( !empty( $dump_params['log_file'] )
        and ($dirname = @dirname( $dump_params['log_file'] )) )
        {
            if( !@is_dir( $dirname )
             or !@is_writable( $dirname ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Directory of log file does not exist or is not writable.' ) );
                return false;
            }
        }

        return $dump_params;
    }
}
