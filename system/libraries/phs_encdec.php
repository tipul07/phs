<?php

namespace phs\libraries;

//! @version 1.33

class PHS_Encdec extends PHS_Language
{
    // Default crypting keys
    // this array must have max 34 elements!!! and all elements must have same length
    // ONCE YOU START ENCODING STRINGS WITH A SET OF INTERNAL KEYS DON'T CHANGE THEM
    private $internal_keys = array(
        '7105adad1f765d1066756cbb9b14664a',
        '164785354dd2a185fd5f6ba0a751c9b6',
        '7f637c2c12939c635757b78248cc1576',
        '22ac54d2a482041d898a022754bc17da',
        'd8f1567b89c0ef0a6539273477b8c869',
        '116f6e3efc9a8527898160c3bf5c6899',
        '64172ee7ee8ff1a7abfec21535fb7213',
        '7fff16511ab89b6525370965600b6b11',
        'de0aaf829049aec0c8c479c09b4589c6',
        '975c220611a4ef9e0e52223bd7448c51',
        '0965193e60c50692d3a98581cb447c05',
        '810ce8fe7fd87624915c1eadbaaa6b69',
        'b81737f17491d881f209de959dab163a',
        '72cbac90c35e0def1cb826fba64fe888',
        'db27505a42ebbaaf37926f6417c06a74',
        '465a3b8ed6ddfb973ffca2bc64c87317',
        '6b2f38aeace04507be22cf3a505ef0b9',
        '32917422d0c262b5979158a3f4008230',
        '6ccbb647466e81219967140de7c5a3e8',
        'e82f0f88d7d5ffcfc0e2e9de3356e28b',
        '29a3488a7e531092270cd13400593935',
        '618dc771d4cd21bfc285aa2f2fd024ef',
        '889ce7dfa626259f2c39baf78c35d2f9',
        '247b10c3090e6ce3043d713beb4082fb',
        'e00af92112f64e8d13941d5f31f2ec9e',
        'c0e522c5f567f9a021df0fdfe3fcc09c',
        '1c5fbd41fbbe1d7c483ee607cf729440',
        'fd345ccdd40d054999097144917c18af',
        '27b94f70725b2c50ff103a71436983c5',
        '686849ed65ba14dc70926d46b1a32c3d',
        '08c8aa10603afd9c816fdacc25df3084',
        '0297c2317a7b935a479f9843f1f4ff51',
        'd30a0fd5c5394b4621c90f98519c65d7',
        'a3f60905c42e9bebb39a671ad5cef5d0',
    );

    private $internal_keys_count, $internal_keys_len;
    private $private_key, $encoded_private_key, $encoded_private_key_len;

    /**
     *  If strings passed to this class are multi-byte strings use base64 encoding to preserve them as multi-byte strings...
     *
     * @var       $use_base64_encode
     * @since     0.3
     * @access    private
     */
    private $use_base64_encode = true;

    public function __construct( $priv_key, $use_base64 = true )
    {
        parent::__construct();

        if( !is_string( $priv_key )
         or $priv_key === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Private key is empty.' ) );
            return;
        }

        if( $this->check_internal_keys() === false )
            return;

        $this->private_key = $priv_key;
        $this->encoded_private_key = strtoupper( md5( $priv_key ) ); // upper chars have ord values lower (F = 70)
        $this->encoded_private_key_len = strlen( $this->encoded_private_key );
        $this->use_base64_encode = $use_base64;
    }

    public function set_internal_keys( $keys_array )
    {
        if( !is_array( $keys_array ) )
            return false;

        $this->internal_keys = $keys_array;
        $this->internal_keys_count = count( $this->internal_keys );

        return $this->check_internal_keys();

    }

    private function check_internal_keys()
    {
        if( empty( $this->internal_keys ) or !is_array( $this->internal_keys ) or !isset( $this->internal_keys[0] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Internal keys array is invalid!' ) );
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
            if( strlen( $key ) !== $this->internal_keys_len )
            {
                $this->set_error( self::ERR_PARAMETERS, 'Internal keys array is invalid! Internal keys array must have max 35 elements and all elements must have same length.' );
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function encrypt( $str )
    {
        if( $this->has_error() )
            return $str;

        if( $this->use_base64_encode !== false )
            $str = @base64_encode( $str );

        if( !($len = strlen( $str )) )
            return '';

        // create 'header' of encryption string
        $internal_key_index = mt_rand( 0, $this->internal_keys_count-1 );
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
                    $ch_privatei = ord( $this->encoded_private_key[$privatei] );
                $ch_internali = 0;
                if( isset( $internal_key[$internali] ) )
                    $ch_internali = ord( $internal_key[$internali] );

                //                  max 94    +  max 70      +        max 70       => max 234
                $repl_str_code = $i - $offset + $ch_privatei + $ch_internali + $unique_id;
                $repl_str_code = (string)base_convert( $repl_str_code, 10, 35 );
                if( strlen( $repl_str_code ) < 2 )
                    $repl_str_code = '0'.$repl_str_code;

                $unique_id++;
            } while( in_array( $repl_str_code, $translation_arr, true ) );

            $translation_arr[] = $repl_str_code;
            $chars_replace[] = chr( $i );

            if( $privatei === 0 )
                $privatei = $this->encoded_private_key_len;

            if( $internali === $this->internal_keys_len-1 )
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

    /**
     * @param string $decstr
     *
     * @return string
     */
    public function decrypt( $decstr )
    {
        if( $this->has_error() )
            return $decstr;

        $str = strtolower( $decstr );
        $str_len = strlen( $str );
        if( !$str_len )
            return '';

        // decode 'header' of encryption string
        $internal_key_index = (int)base_convert( $str[0], 35, 10 );
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
                    $ch_privatei = ord( $this->encoded_private_key[$privatei] );
                $ch_internali = 0;
                if( isset( $internal_key[$internali] ) )
                    $ch_internali = ord( $internal_key[$internali] );

                //                     max 94 +     max 70   +    max 70       => max 234
                $repl_str_code = $i - $offset + $ch_privatei + $ch_internali + $unique_id;
                $repl_str_code = (string)base_convert( $repl_str_code, 10, 35 );
                if( strlen( $repl_str_code ) < 2 )
                    $repl_str_code = '0'.$repl_str_code;

                $unique_id++;
            } while( in_array( $repl_str_code, $translation_arr, true ) );

            $translation_arr[] = $repl_str_code;
            $chars_replace[] = chr( $i );

            if( $privatei === 0 )
                $privatei = $this->encoded_private_key_len;

            if( $internali === $this->internal_keys_len-1 )
                $internali = -1;
        }

        $decoded_txt = '';
        for( $i = 1, $knti = 0; $i < $str_len; $i += 2, $knti++ )
        {
            $str_check = substr( $str, $i, 2 );
            //if( ($key_found_pos = array_search( $str[$i].$str[$i+1], $translation_arr )) === false )
            if( ($key_found_pos = array_search( $str_check, $translation_arr, true )) === false )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t decode the string.' ) );
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
            $decoded_txt = @base64_decode( $decoded_txt );

        return $decoded_txt;
    }
}

