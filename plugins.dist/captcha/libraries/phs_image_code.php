<?php

namespace phs\plugins\captcha\libraries;

use \phs\libraries\PHS_Library;

//! /version 1.53

class PHS_Image_code extends PHS_Library
{
    const ERR_NOGD = 1, ERR_NOIMG = 2;

    const OUTPUT_JPG = 1, OUTPUT_GIF = 2, OUTPUT_PNG = 3;

    // reference code used to compare input codes
    var $reference_code;

    // this code will be passed as parameter to the class to check if the input is correct
    var $public_code;

    // number of chars that will be displayed
    var $character_number;

    // type of output image (gif, jpeg or png)
    var $output_type;

    // Code timeout
    var $code_timeout;

    var $image_quality = 95;

    function __construct( $params = false )
    {
        parent::__construct();

        if( !@function_exists( 'imagecreatetruecolor' ) )
        {
            $this->set_error( self::ERR_NOGD, $this->_pt( 'Function imagecreatetruecolor doesn\'t exist. Maybe gd library is not installed or doesn\'t support this function.' ) );
            return;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['cnumbers'] ) )
            $params['cnumbers'] = 5;
        if( empty( $params['param_code'] ) )
            $params['param_code'] = '';
        if( empty( $params['img_quality'] ) )
            $params['img_quality'] = 95;
        else
            $params['img_quality'] = intval( $params['img_quality'] );
        if( empty( $params['img_type'] ) )
            $params['img_type'] = self::OUTPUT_JPG;

        if( empty( $params['code_timeout'] ) )
            $params['code_timeout'] = 1800; // expire the code after 30 mins
        else
            $params['code_timeout'] = intval( $params['code_timeout'] );

        if( empty( $params['reference_code'] ) )
            $params['reference_code'] = md5( '!!!Some static text that must be changed if u use this class... (don\'t use random functions here)!!!' );
        else
            $params['reference_code'] = trim( $params['reference_code'] );

        $this->image_quality = $params['img_quality'];

        if( $params['cnumbers'] < 3 )
            $params['cnumbers'] = 3;

        $this->char_numbers( $params['cnumbers'] );

        if( empty( $params['img_type'] )
         or !in_array( $params['img_type'], array( self::OUTPUT_JPG, self::OUTPUT_GIF, self::OUTPUT_PNG ) ) )
            $this->output_format( self::OUTPUT_JPG );
        else
            $this->output_format( $params['img_type'] );

        $this->set_code_timeout( $params['code_timeout'] );

        // this code should be same for all instances of the class (u can read a file content)
        // this is like a private key for the class
        $this->reference_code = $params['reference_code'];

        if( empty( $params['param_code'] ) )
            $this->regenerate_public_code();

        else
        {
            $this->public_code = $params['param_code'];
            $this->refresh_public_code();
        }
    }

    function set_code_timeout( $seconds = false )
    {
        if( $seconds === false )
            return $this->code_timeout;

        $this->code_timeout = $seconds;
        return $seconds;
    }

    function regenerate_public_code()
    {
        $this->public_code = $this->generate_public_code();
    }

    function refresh_public_code()
    {
        if( ($my_public_code = $this->decode_public_code()) === false )
            return false;

        $this->public_code = base_convert( time(), 10, 35 ).':'.$my_public_code['code'];

        return true;
    }

    function check_public_code()
    {
        if( ($my_public_code = $this->decode_public_code()) === false
         or time() - $this->code_timeout > $my_public_code['time'] )
            return false;

        return true;
    }

    function generate_public_code()
    {
        return base_convert( time(), 10, 35 ).':'.md5( uniqid( rand(), true ) );
    }

    function decode_public_code()
    {
        if( empty( $this->public_code ) or strstr( $this->public_code, ':' ) === false
         or ($result_arr = explode( ':', $this->public_code )) === false
         or count( $result_arr ) != 2 or empty( $result_arr[0] ) or empty( $result_arr[1] )
         or !is_numeric( ($time_var = base_convert( $result_arr[0], 35, 10 )) ) )
            return false;

        return array( 'time' => $time_var, 'code' => $result_arr[1] );
    }

    function get_public_code()
    {
        return $this->public_code;
    }

    function output_format( $format = false )
    {
        if( $format === false )
            return $this->output_type;

        $this->output_type = $format;
        return $format;
    }

    function char_numbers( $char_number = false )
    {
        if( $char_number === false )
            return $this->character_number;

        $this->character_number = $char_number;
        return $char_number;
    }

    function generate_image_code()
    {
        if( $this->has_error() )
            return '';

        if( ($decoded_public_code = $this->decode_public_code()) === false )
            $used_public_code = $this->public_code;
        else
            $used_public_code = $decoded_public_code['code'];

        $return_code = md5( $this->reference_code.$used_public_code );
        $ret = base_convert( $return_code, 16, 35 );

        while( strlen( $ret ) < $this->character_number )
        {
            $return_code = strrev( $return_code );
            $ret .= base_convert( $return_code, 16, 35 );
        }

        if( strlen( $ret ) > $this->character_number )
          return substr( $ret, 0, $this->character_number );

        return $ret;
    }

    function check_input( $input )
    {
        if( $this->has_error()
         or !$this->check_public_code() )
            return false;

        return (strtolower( $input ) == strtolower($this->generate_image_code()));
    }

    function generate_image( $imgw, $imgh, $font_file = '', $font_size = 0 )
    {
        if( $this->has_error() )
            return;

        if( !($im = @imagecreatetruecolor( $imgw, $imgh )) )
        {
            $this->set_error( self::ERR_NOIMG, 'Error creating image object using imagecreatetruecolor function. (image size: '.$imgw.'x'.$imgh.'px)' );
            return;
        }

        $bg_colorr = round( rand( 230, 240 ) );
        $bg_colorg = round( rand( 230, 240 ) );
        $bg_colorb = round( rand( 230, 240 ) );

        $bg_color = @imagecolorallocate( $im, $bg_colorr, $bg_colorg, $bg_colorb );
        @imagefilledrectangle( $im, 0, 0, $imgw, $imgh, $bg_color );

        $str = $this->generate_image_code();
        $len = strlen( $str );

        $size = $font_size;
        $ttf_usage = false;
        if( !empty( $font_file ) and @file_exists( $font_file )
        and @function_exists( 'imageftbbox' ) and @function_exists( 'imagettftext' ) )
        {
            $ttf_usage = true;
            if( empty( $font_size ) )
                $size = round( rand( 18, 22 ) );
            else
                $size = $font_size;

            $bbox = imageftbbox( $size, 0, $font_file, '0', array( 'linespacing' => 0 ) );
            $bbwidth = abs($bbox[0]) + abs($bbox[2]); // distance from left to right
            $bbheight = abs($bbox[1]) + abs($bbox[5]); // distance from top to bottom
            $startx = round($imgw/2) - round((($bbwidth+10)*$len)/2);
        } else
        {
            $bbwidth = 15; // letter width
            $bbheight = 20; // letter height
            $startx = round($imgw/2) - round((($bbwidth+15)*$len)/2); // where do we start to put letters on the picture
        }

        $x = $startx;
        $y = round($imgh/2);
        for( $i = 0; $i < $len; $i++ )
        {
            $r1 = round( rand( 140, 150 ) );
            $g1 = round( rand( 140, 150 ) );
            $b1 = round( rand( 140, 150 ) );

            $r2 = round( rand( 190, 200 ) );
            $g2 = round( rand( 190, 200 ) );
            $b2 = round( rand( 190, 200 ) );

            if( $ttf_usage )
            {
               $angle = intval( rand( -20, 20 ) );

               $c1 = @imagecolorallocate( $im, $r1, $g1, $b1 );
               $c2 = @imagecolorallocate( $im, $r2, $g2, $b2 );

               @imagettftext( $im, $size, $angle, $x+1, $y+round($bbheight/2)+1, $c1, $font_file, $str[$i] );
               @imagettftext( $im, $size, $angle, $x-1, $y+round($bbheight/2)+1, $c1, $font_file, $str[$i] );
               @imagettftext( $im, $size, $angle, $x+1, $y+round($bbheight/2)-1, $c1, $font_file, $str[$i] );
               @imagettftext( $im, $size, $angle, $x-1, $y+round($bbheight/2)-1, $c1, $font_file, $str[$i] );
               @imagettftext( $im, $size, $angle, $x, $y+round($bbheight/2), $c2, $font_file, $str[$i] );

               $x += $bbwidth + 10;
            } else
            {
               //imagettftext( $im, round(rand(17, 20)), $angle, $x,24,$color,$this->font_file,$text);
               if( !isset( $colors ) )
                  $colors = array();

               $colors[0][0] = $r1;
               $colors[0][1] = $g1;
               $colors[0][2] = $b1;

               $colors[1][0] = $r2;
               $colors[1][1] = $g2;
               $colors[1][2] = $b2;

               $colors[2][0] = $bg_colorr;
               $colors[2][1] = $bg_colorg;
               $colors[2][2] = $bg_colorb;

               $angle = intval( rand( -10, 10 ) );

               if( !$this->create_nottf_charimg( $im, $angle, $x, $y-round($bbheight/2)-7, $str[$i], $colors, $bbwidth, $bbheight ) )
               {
                    $this->set_error( self::ERR_NOIMG, 'Error while creating image.' );
                    return;
               }
               $x += $bbwidth + 15;
            }
        }

        for( $i = 0; $i < $imgw; $i += 20 )
        {
            $c1 = @imagecolorallocate( $im, round( rand( 150, 225 ) ), round( rand( 150, 225 ) ), round( rand( 150, 225 ) ) );
            $c2 = @imagecolorallocate( $im, round( rand( 150, 225 ) ), round( rand( 150, 225 ) ), round( rand( 150, 225 ) ) );

            $style = array( $c1, $c1, $c1, $c1, $c1, $c2, $c2, $c2, $c2, $c2 );
            @imagesetstyle( $im, $style );
            @imageline( $im, $i, 0, $i+round(rand(-3, 8)), $imgh, IMG_COLOR_STYLED );
        }

        for( $i = 0; $i < $imgh; $i += 10 )
        {
            $c1 = @imagecolorallocate( $im, round( rand( 150, 225 ) ), round( rand( 150, 225 ) ), round( rand( 150, 225 ) ) );
            $c2 = @imagecolorallocate( $im, round( rand( 150, 225 ) ), round( rand( 150, 225 ) ), round( rand( 150, 225 ) ) );

            $style = array( $c1, $c1, $c1, $c1, $c1, $c2, $c2, $c2, $c2, $c2 );
            @imagesetstyle( $im, $style );
            @imageline( $im, 0, $i, $imgw, $i+round(rand(-3, 8)), IMG_COLOR_STYLED );
        }

        switch( $this->output_type )
        {
            default:
            case self::OUTPUT_JPG:
                if( !headers_sent() )
                    header( 'Content-Type: image/jpeg' );

                imagejpeg( $im, null, $this->image_quality );
            break;
            case self::OUTPUT_GIF:
                if( !headers_sent() )
                    header( 'Content-Type: image/gif' );

                imagegif( $im );
            break;
            case self::OUTPUT_PNG:
                if( !headers_sent() )
                    header( 'Content-Type: image/png' );

                imagepng( $im );
            break;
        }

        imagedestroy( $im );
    }

    function create_nottf_charimg( $im, $angle, $x, $y, $char, $colors, $letter_w, $letter_h )
    {
        $percent = 2; // how much to 'zoom' the letter (2 = 200%)
        if( !($chimg = @imagecreatetruecolor( $letter_w, $letter_h )) )
            return false;

        $bgcolor = imagecolorallocate( $chimg, $colors[2][0], $colors[2][1], $colors[2][2] );
        imagefilledrectangle( $chimg, 0, 0, $letter_w, $letter_h, $bgcolor );
        if( @function_exists( 'imagealphablending' ) )
            @imagealphablending( $chimg, true );

        $c1 = imagecolorallocate( $chimg, $colors[0][0], $colors[0][1], $colors[0][2] );
        $c2 = imagecolorallocate( $chimg, $colors[1][0], $colors[1][1], $colors[1][2] );

        imagestring( $chimg, 5, round($letter_w/2)-5, round($letter_h/2)-10, $char, $c1 );
        imagestring( $chimg, 5, round($letter_w/2)-3, round($letter_h/2)-8, $char, $c1 );
        imagestring( $chimg, 5, round($letter_w/2)-5, round($letter_h/2)-8, $char, $c1 );
        imagestring( $chimg, 5, round($letter_w/2)-3, round($letter_h/2)-10, $char, $c1 );
        imagestring( $chimg, 5, round($letter_w/2)-4, round($letter_h/2)-9, $char, $c2 );

        $chimgrot = imagerotate( $chimg, $angle, $bgcolor );

        $letter_nw = imagesx($chimgrot) * $percent;
        $letter_nh = imagesy($chimgrot) * $percent;

        @imagecopyresampled( $im, $chimgrot, $x, $y, 0, 0, $letter_nw, $letter_nh, imagesx($chimgrot), imagesy($chimgrot) );

        imagedestroy( $chimg );
        imagedestroy( $chimgrot );
        return true;
    }
}
