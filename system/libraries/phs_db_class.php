<?php

namespace phs\libraries;

use \phs\PHS_db;

/**
 *  MySQL class parser for PHS suite...
 */
abstract class PHS_db_class extends PHS_Registry implements PHS_db_interface
{
    //! Cannot connect to server.
    const ERR_CONNECT = 1;
    //! Cannot query server.
    const ERR_QUERY = 2;

    public static function default_dump_parameters()
    {
        return array(
            // input parameters
            'connection_name' => false,
            'output_dir' => '',
            'log_file' => '',
            'zip_dump' => true,

            // Binary files used in dump (for all known drivers)
            'binaries' => array(
                'zip_bin' => 'zip',
                'mysqldump_bin' => 'mysqldump',
                'mongodump_bin' => 'mongodump',
            ),

            // output parameters
            'connection_identifier' => array(),
            'dump_commands_for_shell' => array(),
            'delete_files_after_export' => array(),
            // Files to be deleted in case we get an error in dump process
            'generated_files' => array(),
            'resulting_files' => array(
                'dump_files' => array(),
                'log_files' => array(),
            ),
        );
    }

    //! This method does only common part on checking and validating $dump_params
    /**
     * @inheritdoc
     */
    public function dump_database( $dump_params = false )
    {
        if( !($dump_params = self::validate_array_recursive( $dump_params, self::default_dump_parameters() )) )
            $dump_params = array();

        if( empty( $dump_params['output_dir'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Please provide output directory for database dump.' ) );
            return false;
        }

        $dump_params['output_dir'] = rtrim( $dump_params['output_dir'], '/\\' );

        if( !@is_dir( $dump_params['output_dir'] )
         or !@is_writable( $dump_params['output_dir'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Output directory does not exist or is not writable.' ) );
            return false;
        }

        if( !($connection_identifier = PHS_db::get_connection_identifier( $dump_params['connection_name'] ))
         or empty( $connection_identifier['identifier'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t get connection identifier for database connection.' ) );
            return false;
        }

        if( empty( $connection_identifier['type'] ) )
            $connection_identifier['type'] = 'dump';

        $dump_params['connection_identifier'] = $connection_identifier;

        if( empty( $dump_params['log_file'] ) )
            $dump_params['log_file'] = $dump_params['output_dir'].'/'.$connection_identifier['identifier'].'_'.$connection_identifier['type'].'.log';

        if( ($dirname = @dirname( $dump_params['log_file'] )) )
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
