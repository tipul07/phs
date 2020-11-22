<?php

namespace phs\system\core\libraries;

use \phs\libraries\PHS_Paginator_exporter_library;
use \phs\libraries\PHS_Utils;

class PHS_Paginator_exporter_csv extends PHS_Paginator_exporter_library
{
    public function default_csv_params()
    {
        return array(
            'line_delimiter' => "\n",
            'column_delimiter' => ',',
            'field_enclosure' => '"',
            'enclosure_escape' => '"',
        );
    }

    /**
     * @inheritdoc
     */
    public function record_to_buffer( $record_data, $params = false )
    {
        if( empty( $record_data ) or !is_array( $record_data )
         or empty( $record_data['record_arr'] ) or !is_array( $record_data['record_arr'] ) )
            return '';

        if( !($csv_format = $this->export_registry( 'csv_format' )) )
        {
            if( empty( $params ) or !is_array( $params ) )
                $csv_format = $this->default_csv_params();
            else
                $csv_format = self::validate_array( $params, $this->default_csv_params() );

            $this->export_registry( 'csv_format', $csv_format );
        }

        if( ($csv_line = PHS_Utils::csv_line( $record_data['record_arr'],
                                    $csv_format['line_delimiter'], $csv_format['column_delimiter'],
                                    $csv_format['field_enclosure'], $csv_format['enclosure_escape'] )
        ) )
        {
            if( ($export_encoding = $this->export_registry( 'export_encoding' ))
            and @function_exists( 'mb_internal_encoding' )
            and @function_exists( 'mb_convert_encoding' ) )
            {
                if( strtolower( @mb_internal_encoding() ) != strtolower( $export_encoding ) )
                    $csv_line = @mb_convert_encoding( $csv_line, $export_encoding );
            }
        }

        return $csv_line;
    }
}
