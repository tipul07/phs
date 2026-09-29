<?php
namespace phs\setup\libraries;

use phs\libraries\PHS_Registry;

class PHS_Setup_view extends PHS_Registry
{
    private string $template_file = '';

    private static ?string $templates_www = null;

    private static string $templates_dir = '';

    public function render_view(string $template, array $data = []) : string
    {
        $this->set_context($data);

        // Quick fallback...
        if (!($templates_path = self::get_templates_dir())) {
            $templates_path = PHS_SETUP_TEMPLATES_DIR;
        }

        if (!PHS_Setup_utils::safe_escape_script($template)) {
            return '[RENDER ERROR: Invalid template file provided.]';
        }

        if (!@file_exists($templates_path.$template.'.php')) {
            return '[RENDER ERROR: Template file ('.$template.') not found.]';
        }

        $this->template_file = $template;

        @ob_start();
        include $templates_path.$template.'.php';

        return @ob_get_clean() ?: '';
    }

    public function get_resource_url($resource) : string
    {
        return self::get_templates_www().$resource;
    }

    public static function set_templates_www(?string $www_path = null) : ?string
    {
        if ($www_path === null) {
            return self::$templates_www;
        }

        self::$templates_www = rtrim($www_path, '/\\');

        return self::$templates_www;
    }

    public static function get_templates_www(bool $slash_ended = true) : string
    {
        if (self::$templates_www === null) {
            return 'templates'.($slash_ended ? '/' : '');
        }

        if (!self::$templates_www) {
            return '';
        }

        return self::$templates_www.($slash_ended ? '/' : '');
    }

    public static function set_templates_dir(?string $dir_path = null) : string
    {
        if ($dir_path === null) {
            return self::$templates_dir;
        }

        self::$templates_dir = rtrim($dir_path, '/\\');

        return self::$templates_dir;
    }

    public static function get_templates_dir(bool $slash_ended = true) : string
    {
        if (!self::$templates_dir) {
            return '';
        }

        return self::$templates_dir.($slash_ended ? '/' : '');
    }
}
