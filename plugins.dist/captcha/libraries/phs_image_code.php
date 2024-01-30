<?php
namespace phs\plugins\captcha\libraries;

use phs\PHS_Session;
use phs\libraries\PHS_Library;
use phs\plugins\captcha\PHS_Plugin_Captcha;

// ! /version 1.53

class PHS_Image_code extends PHS_Library
{
    public const ERR_NOGD = 1, ERR_NOIMG = 2;

    public const OUTPUT_JPG = 1, OUTPUT_GIF = 2, OUTPUT_PNG = 3;

    // reference code used to compare input codes
    private string $reference_code;

    // this code will be passed as parameter to the class to check if the input is correct
    private string $public_code = '';

    // number of chars that will be displayed
    private int $character_number = 5;

    // type of output image (gif, jpeg or png)
    private int $output_type = self::OUTPUT_PNG;

    // Code timeout
    private int $code_timeout = 1800;

    private int $image_quality = 95;

    /**
     * @param false|array $params
     */
    public function __construct($params = null)
    {
        parent::__construct();

        if (!@function_exists('imagecreatetruecolor')) {
            $this->set_error(self::ERR_NOGD, $this->_pt('Function imagecreatetruecolor doesn\'t exist. Maybe gd library is not installed or doesn\'t support this function.'));

            return;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        $params['public_code'] ??= '';
        $params['cnumbers'] = (int)($params['cnumbers'] ?? 5);
        $params['img_quality'] = (int)($params['img_quality'] ?? 95);
        $params['code_timeout'] = (int)($params['code_timeout'] ?? 1800);

        if (empty($params['img_type'])
            || !in_array($params['img_type'], [self::OUTPUT_JPG, self::OUTPUT_GIF, self::OUTPUT_PNG], true)) {
            $params['img_type'] = self::OUTPUT_JPG;
        }

        if (empty($params['reference_code'])) {
            $params['reference_code'] = md5('!!!Some static text that must be changed if u use this class... (don\'t use random functions here)!!!');
        } else {
            $params['reference_code'] = trim($params['reference_code']);
        }

        if ($params['cnumbers'] < 3) {
            $params['cnumbers'] = 3;
        }

        $this->image_quality = $params['img_quality'];

        $this->char_numbers($params['cnumbers']);
        $this->output_format($params['img_type']);
        $this->set_code_timeout($params['code_timeout']);

        // this code should be same for all instances of the class (u can read a file content)
        // this is like a private key for the class
        $this->reference_code = $params['reference_code'];

        if (empty($params['public_code']) || !is_string($params['public_code'])) {
            $this->regenerate_public_code();
        } else {
            $this->set_public_code($params['public_code']);
            // $this->refresh_public_code();
        }
    }

    public function set_code_timeout(?int $seconds = null) : int
    {
        if ($seconds === null) {
            return $this->code_timeout;
        }

        $this->code_timeout = $seconds;

        return $seconds;
    }

    public function regenerate_public_code() : void
    {
        $this->public_code = $this->_generate_public_code();
    }

    public function refresh_public_code() : bool
    {
        if (!($my_public_code = $this->_decode_public_code())) {
            return false;
        }

        $this->public_code = base_convert(time(), 10, 35).':'.$my_public_code['code'];

        return true;
    }

    public function valid_public_code() : bool
    {
        return ($my_public_code = $this->_decode_public_code())
               && time() - $this->code_timeout < $my_public_code['time'];
    }

    public function get_public_code() : string
    {
        return $this->public_code;
    }

    public function set_public_code(string $code) : bool
    {
        $old_code = $this->public_code;
        $this->public_code = $code;

        if (!$this->_decode_public_code()) {
            $this->public_code = $old_code;

            return false;
        }

        return true;
    }

    public function output_format(?int $format = null) : int
    {
        if ($format === null) {
            return $this->output_type;
        }

        $this->output_type = $format;

        return $format;
    }

    public function char_numbers(?int $char_number = null) : int
    {
        if ($char_number === null) {
            return $this->character_number;
        }

        $this->character_number = $char_number;

        return $char_number;
    }

    public function generate_image_code() : string
    {
        if ($this->has_error()) {
            return '';
        }

        if (!($decoded_public_code = $this->_decode_public_code())) {
            $used_public_code = $this->public_code;
        } else {
            $used_public_code = $decoded_public_code['code'];
        }

        $return_code = md5($this->reference_code.$used_public_code);
        $ret = base_convert($return_code, 16, 35);

        while (strlen($ret) < $this->character_number) {
            $return_code = strrev($return_code);
            $ret .= base_convert($return_code, 16, 35);
        }

        if (strlen($ret) > $this->character_number) {
            return substr($ret, 0, $this->character_number);
        }

        return $ret;
    }

    public function check_input(string $input) : bool
    {
        if ($this->has_error()
         || !$this->valid_public_code()) {
            return false;
        }

        return strtolower($input) === strtolower($this->generate_image_code());
    }

    public function generate_image(int $imgw, int $imgh, string $font_file = '', int $font_size = 0) : void
    {
        if ($this->has_error()) {
            return;
        }

        if (!($im = @imagecreatetruecolor($imgw, $imgh))) {
            $this->set_error(self::ERR_NOIMG, 'Error creating image object using imagecreatetruecolor function. (image size: '.$imgw.'x'.$imgh.'px)');

            return;
        }

        $bg_colorr = round(mt_rand(230, 240));
        $bg_colorg = round(mt_rand(230, 240));
        $bg_colorb = round(mt_rand(230, 240));

        $bg_color = @imagecolorallocate($im, $bg_colorr, $bg_colorg, $bg_colorb);
        @imagefilledrectangle($im, 0, 0, $imgw, $imgh, $bg_color);

        $str = $this->generate_image_code();
        $len = strlen($str);

        $size = $font_size;
        $ttf_usage = false;
        if (!empty($font_file) && @file_exists($font_file)
         && @function_exists('imageftbbox') && @function_exists('imagettftext')) {
            $ttf_usage = true;
            if (empty($font_size)) {
                $size = round(mt_rand(18, 22));
            } else {
                $size = $font_size;
            }

            $bbox = imageftbbox($size, 0, $font_file, '0', ['linespacing' => 0]);
            $bbwidth = abs($bbox[0]) + abs($bbox[2]); // distance from left to right
            $bbheight = abs($bbox[1]) + abs($bbox[5]); // distance from top to bottom
            $startx = round($imgw / 2) - round((($bbwidth + 10) * $len) / 2);
        } else {
            $bbwidth = 15; // letter width
            $bbheight = 20; // letter height
            $startx = round($imgw / 2) - round((($bbwidth + 15) * $len) / 2); // where do we start to put letters on the picture
        }

        $x = $startx;
        $y = round($imgh / 2);
        for ($i = 0; $i < $len; $i++) {
            $r1 = round(mt_rand(140, 150));
            $g1 = round(mt_rand(140, 150));
            $b1 = round(mt_rand(140, 150));

            $r2 = round(mt_rand(190, 200));
            $g2 = round(mt_rand(190, 200));
            $b2 = round(mt_rand(190, 200));

            if ($ttf_usage) {
                $angle = mt_rand(-20, 20);

                $c1 = @imagecolorallocate($im, $r1, $g1, $b1);
                $c2 = @imagecolorallocate($im, $r2, $g2, $b2);

                @imagettftext($im, $size, $angle, $x + 1, $y + round($bbheight / 2) + 1, $c1, $font_file, $str[$i]);
                @imagettftext($im, $size, $angle, $x - 1, $y + round($bbheight / 2) + 1, $c1, $font_file, $str[$i]);
                @imagettftext($im, $size, $angle, $x + 1, $y + round($bbheight / 2) - 1, $c1, $font_file, $str[$i]);
                @imagettftext($im, $size, $angle, $x - 1, $y + round($bbheight / 2) - 1, $c1, $font_file, $str[$i]);
                @imagettftext($im, $size, $angle, $x, $y + round($bbheight / 2), $c2, $font_file, $str[$i]);

                $x += $bbwidth + 10;
            } else {
                if (!isset($colors)) {
                    $colors = [];
                }

                $colors[0][0] = $r1;
                $colors[0][1] = $g1;
                $colors[0][2] = $b1;

                $colors[1][0] = $r2;
                $colors[1][1] = $g2;
                $colors[1][2] = $b2;

                $colors[2][0] = $bg_colorr;
                $colors[2][1] = $bg_colorg;
                $colors[2][2] = $bg_colorb;

                $angle = mt_rand(-10, 10);

                if (!$this->create_nottf_charimg($im, $angle, $x, $y - round($bbheight / 2) - 7, $str[$i], $colors, $bbwidth, $bbheight)) {
                    $this->set_error(self::ERR_NOIMG, 'Error while creating image.');

                    return;
                }
                $x += $bbwidth + 15;
            }
        }

        for ($i = 0; $i < $imgw; $i += 20) {
            $c1 = @imagecolorallocate($im, round(mt_rand(150, 225)), round(mt_rand(150, 225)), round(mt_rand(150, 225)));
            $c2 = @imagecolorallocate($im, round(mt_rand(150, 225)), round(mt_rand(150, 225)), round(mt_rand(150, 225)));

            $style = [$c1, $c1, $c1, $c1, $c1, $c2, $c2, $c2, $c2, $c2];
            @imagesetstyle($im, $style);
            @imageline($im, $i, 0, $i + round(rand(-3, 8)), $imgh, IMG_COLOR_STYLED);
        }

        for ($i = 0; $i < $imgh; $i += 10) {
            $c1 = @imagecolorallocate($im, round(mt_rand(150, 225)), round(mt_rand(150, 225)), round(mt_rand(150, 225)));
            $c2 = @imagecolorallocate($im, round(mt_rand(150, 225)), round(mt_rand(150, 225)), round(mt_rand(150, 225)));

            $style = [$c1, $c1, $c1, $c1, $c1, $c2, $c2, $c2, $c2, $c2];
            @imagesetstyle($im, $style);
            @imageline($im, 0, $i, $imgw, $i + round(mt_rand(-3, 8)), IMG_COLOR_STYLED);
        }

        switch ($this->output_type) {
            default:
            case self::OUTPUT_JPG:
                if (!headers_sent()) {
                    header('Content-Type: image/jpeg');
                }

                imagejpeg($im, null, $this->image_quality);
                break;
            case self::OUTPUT_GIF:
                if (!headers_sent()) {
                    header('Content-Type: image/gif');
                }

                imagegif($im);
                break;
            case self::OUTPUT_PNG:
                if (!headers_sent()) {
                    header('Content-Type: image/png');
                }

                imagepng($im);
                break;
        }

        imagedestroy($im);
    }

    public function create_nottf_charimg($im, $angle, $x, $y, $char, $colors, $letter_w, $letter_h) : bool
    {
        $percent = 2; // how much to 'zoom' the letter (2 = 200%)
        if (!($chimg = @imagecreatetruecolor($letter_w, $letter_h))) {
            return false;
        }

        $bgcolor = imagecolorallocate($chimg, $colors[2][0], $colors[2][1], $colors[2][2]);
        imagefilledrectangle($chimg, 0, 0, $letter_w, $letter_h, $bgcolor);
        if (@function_exists('imagealphablending')) {
            @imagealphablending($chimg, true);
        }

        $c1 = imagecolorallocate($chimg, $colors[0][0], $colors[0][1], $colors[0][2]);
        $c2 = imagecolorallocate($chimg, $colors[1][0], $colors[1][1], $colors[1][2]);

        imagestring($chimg, 5, round($letter_w / 2) - 5, round($letter_h / 2) - 10, $char, $c1);
        imagestring($chimg, 5, round($letter_w / 2) - 3, round($letter_h / 2) - 8, $char, $c1);
        imagestring($chimg, 5, round($letter_w / 2) - 5, round($letter_h / 2) - 8, $char, $c1);
        imagestring($chimg, 5, round($letter_w / 2) - 3, round($letter_h / 2) - 10, $char, $c1);
        imagestring($chimg, 5, round($letter_w / 2) - 4, round($letter_h / 2) - 9, $char, $c2);

        $chimgrot = imagerotate($chimg, $angle, $bgcolor);

        $letter_nw = imagesx($chimgrot) * $percent;
        $letter_nh = imagesy($chimgrot) * $percent;

        @imagecopyresampled($im, $chimgrot, $x, $y, 0, 0, $letter_nw, $letter_nh, imagesx($chimgrot), imagesy($chimgrot));

        imagedestroy($chimg);
        imagedestroy($chimgrot);

        return true;
    }

    private function _generate_public_code() : string
    {
        return base_convert(time(), 10, 35).':'.md5(uniqid(mt_rand(), true));
    }

    private function _decode_public_code() : ?array
    {
        if (empty($this->public_code) || !str_contains($this->public_code, ':')
         || !($result_arr = explode(':', $this->public_code, 2))
         || count($result_arr) !== 2 || empty($result_arr[0]) || empty($result_arr[1])
         || !is_numeric(($time_var = base_convert($result_arr[0], 35, 10)))) {
            return null;
        }

        return ['time' => (int)$time_var, 'code' => $result_arr[1]];
    }
}
