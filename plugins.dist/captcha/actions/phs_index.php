<?php
namespace phs\plugins\captcha\actions;

use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use JetBrains\PhpStorm\NoReturn;
use phs\plugins\captcha\PHS_Plugin_Captcha;

class PHS_Action_Index extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    #[NoReturn]
    public function execute() : void
    {
        if (!@function_exists('imagecreatetruecolor')) {
            echo $this->_pt('Function imagecreatetruecolor doesn\'t exist. Maybe gd library is not installed or doesn\'t support this function.');
            exit;
        }

        /** @var PHS_Plugin_Captcha $captcha_plugin */
        if (!($captcha_plugin = PHS_Plugin_Captcha::get_instance())
            || !$captcha_plugin->plugin_active()
            || !($plugin_settings = $captcha_plugin->get_plugin_settings())
            || !($img_library = $captcha_plugin->load_image_library())) {
            echo $this->_pt('Error loading required resources.');
            exit;
        }

        if (empty($plugin_settings['font'])
            || !($font_file = $captcha_plugin->get_font_full_path($plugin_settings['font']))) {
            echo $this->_pt('Font file not found.');
            exit;
        }

        if (!$captcha_plugin->generate_or_refresh_public_code()) {
            echo $this->_pt('Error refreshing public code. Please try again.');
            exit;
        }

        $img_width = PHS_Params::_g('w', PHS_Params::T_INT) ?: $plugin_settings['default_width'] ?? 200;
        $img_height = PHS_Params::_g('h', PHS_Params::T_INT) ?: $plugin_settings['default_height'] ?? 50;

        $img_library->generate_image($img_width, $img_height, $font_file);
        exit;
    }
}
