<?php

//! \version 1.32

class PHS_encdec extends PHS_Language
{
    //! Functionality error
    const ERR_FUNCTIONALITY = 2;

    // this array must have max 34 elements!!! and all elements must have same length
    // ONCE YOU START ENCODING STRINGS WITH A SET OF INTERNAL KEYS DON'T CHANGE THEM
    var $internal_keys = array(
        'bcbb6ba1334720bf035e1527f315507b',
        '65f58c1f74b28613a06b19ebe107dad6',
        '10f5ca50cfe342a724d1f62d8089864c',
        'ffff0fb6313e37e8994ff92a19387fcd',
        'b5e042703bd952cac114ee97efed6292',
        '853594ab8f44ba8be925927c9f66804c',
        '66c7ec1e693e0c25276df73650047ef4',
        '75f19f81997a921b5aa503c9319d415b',
        'e3b1eb8c8f849ce0612ea5a0b0c043f1',
        'c91e9d7f076c4c6ca82e8a52e406fc1a',
        'c8be4bc90ed25dfe89192831519faf22',
        'd8b4b66fe29a45c5a93a9ea0e3924e21',
        '72c4de79c01f6b6dd80bf94e3f1e5467',
        '5e3523bef7ac2d670943b53bfae207b0',
        'e3f22f34bbad86b27217b5ce141ce69e',
        '812767e60cd7a28673036de2192ca866',
        '680bc74d2e177c21808fc9babff3e9b8',
        '275e633c19427f4eada7a97f853490b3',
        'f03d7df604db763a1746ebb26633e3f3',
        '7954ef52a44b392a50cffa130de9dd34',
        '87712eed730d32e42a4a494677b84f7a',
        'a870a966f0d6a9b53ac97d241af552aa',
        'd352323e852d8fcbefdbb0a18746ddce',
        'c8234aaaf88f94671cf62e36649b6868',
        '182927ae71257091c0f4afac13fe20d3',
        '62537d3e59720dd701f5e781a80e0cc5',
        '4169e71e8d4dcb8545c21b41ecfe182c',
        '4425aa956e2bae0c48c95b3c3434fc5c',
        '45b77e1cb98c6d5717e9f16768fee2cb',
        'faa03a8fbc1d8de6e1eb5d23f0430765',
        '4e01249c84e7445d4a26c32cc0ed755d',
        'c07c5e4f358bd7784bb072e7134468d7',
        'b8cbb6eefaf86f4c3e838f50fbe85bd4',
        '3a9babe18c91a9ffaddcce4f0171b089'
        );

    var $internal_keys_count, $internal_keys_len;
    var $private_key, $encoded_private_key, $encoded_private_key_len;

    /**
     *  If strings passed to this class are multi-byte strings use base64 encoding to preserve them as multi-byte strings...
     *
     * @var       $use_base64_encode
     * @since     0.3
     * @access    private
     */
    var $use_base64_encode = false;

    function __construct( $priv_key, $use_base64 = false )
    {
        parent::__construct();

        if( !strlen( $priv_key ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Private key is empty.' );
            return;
        }

        if( $this->check_internal_keys() === false )
            return;

        $this->private_key = $priv_key;
        $this->encoded_private_key = strtoupper( md5( $priv_key ) ); // upper chars have ord values lower (F = 70)
        $this->encoded_private_key_len = strlen( $this->encoded_private_key );
        $this->use_base64_encode = $use_base64;
    }

    function set_internal_keys( $keys_array )
    {
        if( !is_array( $keys_array ) )
            return false;

        $this->internal_keys = $keys_array;
        $this->internal_keys_count = count( $this->internal_keys );

        return $this->check_internal_keys();

    }

    function check_internal_keys()
    {
        if( empty( $this->internal_keys ) or !is_array( $this->internal_keys ) or !isset( $this->internal_keys[0] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Internal keys array is invalid!' );
            return false;
        }

        $this->internal_keys_count = count( $this->internal_keys );
        $this->internal_keys_len = strlen( $this->internal_keys[0] );

        if( !$this->internal_keys_count or !$this->internal_keys_len
         or $this->internal_keys_count > 35 )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Internal keys array is invalid! Internal keys array must have max 35 elements and all elements must have same length.' );
            return false;
        }

        foreach( $this->internal_keys as $key )
        {
            if( strlen( $key ) != $this->internal_keys_len )
            {
                $this->set_error( self::ERR_PARAMETERS, 'Internal keys array is invalid! Internal keys array must have max 35 elements and all elements must have same length.' );
                return false;
            }
        }

        return true;
    }

    function encrypt( $str )
    {
        if( $this->get_error_code() != self::ERR_OK )
            return $str;

        if( $this->use_base64_encode !== false )
            $str = base64_encode( $str );

        if( !($len = strlen( $str )) )
            return '';

        // create 'header' of encryption string
        $internal_key_index = rand( 0, $this->internal_keys_count-1 );
        $internal_key = strtoupper( $this->internal_keys[$internal_key_index] );
        $encrypted_str = base_convert( $internal_key_index, 10, 35 );

        $offset = 32;
        $chars_replace = array();
        $translation_arr = array();
        for( $i = $offset, $privatei = $this->encoded_private_key_len - 1, $internali = 0; $i <= 126; $i++, $privatei--, $internali++ )
        {
            // make sure we generate unique pairs...
            $unique_id = 0;
            do
            {
                $ch_privatei = 0;
                if( isset( $this->encoded_private_key[$privatei] ) )
                    $ch_privatei = $this->encoded_private_key[$privatei];
                $ch_internali = 0;
                if( isset( $internal_key[$internali] ) )
                    $ch_internali = $internal_key[$internali];

                //                  max 94    +  max 70      +        max 70       => max 234
                $repl_str_code = $i - $offset + $ch_privatei + $ch_internali + $unique_id;
                $repl_str_code = base_convert( $repl_str_code, 10, 35 );
                if( strlen( $repl_str_code ) < 2 )
                    $repl_str_code = '0'.$repl_str_code;

                $unique_id++;
            } while( in_array( $repl_str_code, $translation_arr ) );

            $translation_arr[] = $repl_str_code;
            $chars_replace[] = chr( $i );

            if( $privatei == 0 )
                $privatei = $this->encoded_private_key_len;

            if( $internali == $this->internal_keys_len-1 )
                $internali = -1;
        }

        for( $i = 0; $i < $len; $i++ )
        {
            $pos_ch = (ord( $str[$i] ) - 32) + $i;
            if( $pos_ch > 94 )
                $pos_ch = $pos_ch % 95;

            $encrypted_str .= $translation_arr[$pos_ch];
        }

        return strtoupper( $encrypted_str );
    }

    function decrypt( $decstr )
    {
        if( $this->get_error_code() != self::ERR_OK )
            return $decstr;

        $str = strtolower( $decstr );
        $str_len = strlen( $str );
        if( !$str_len )
            return '';

        // decode 'header' of encryption string
        $internal_key_index = base_convert( $str[0], 35, 10 );
        $internal_key = strtoupper( $this->internal_keys[$internal_key_index] );

        $offset = 32;
        $chars_replace = array();
        $translation_arr = array();
        for( $i = $offset, $privatei = $this->encoded_private_key_len - 1, $internali = 0; $i <= 126; $i++, $privatei--, $internali++ )
        {
            // make sure we generate unique pairs...
            $unique_id = 0;
            do
            {
                $ch_privatei = 0;
                if( isset( $this->encoded_private_key[$privatei] ) )
                    $ch_privatei = $this->encoded_private_key[$privatei];
                $ch_internali = 0;
                if( isset( $internal_key[$internali] ) )
                    $ch_internali = $internal_key[$internali];

                //                     max 94 +     max 70   +    max 70       => max 234
                $repl_str_code = $i - $offset + $ch_privatei + $ch_internali + $unique_id;
                $repl_str_code = base_convert( $repl_str_code, 10, 35 );
                if( strlen( $repl_str_code ) < 2 )
                    $repl_str_code = '0'.$repl_str_code;

                $unique_id++;
            } while( in_array( $repl_str_code, $translation_arr ) );

            $translation_arr[] = $repl_str_code;
            $chars_replace[] = chr( $i );

            if( $privatei == 0 )
                $privatei = $this->encoded_private_key_len;

            if( $internali == $this->internal_keys_len-1 )
                $internali = -1;
        }

        $decoded_txt = '';
        for( $i = 1, $knti = 0; $i < $str_len; $i += 2, $knti++ )
        {
            $str_check = substr( $str, $i, 2 );
            //if( ($key_found_pos = array_search( $str[$i].$str[$i+1], $translation_arr )) === false )
            if( ($key_found_pos = array_search( $str_check, $translation_arr )) === false )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, 'Couldn\'t decode the string.' );
                return $decstr;
            }

            if( ($neg_val = ($key_found_pos - $knti)) < 0 )
            {
                $key_pos = 95 - (abs( $neg_val ) % 95);
            } else
            {
                $key_pos = $key_found_pos - $knti;
            }

            $decoded_txt .= $chars_replace[$key_pos];
        }

        if( $this->use_base64_encode !== false )
            $decoded_txt = base64_decode( $decoded_txt );

        return $decoded_txt;
    }
}

