<?php

namespace phs\setup\libraries;

use phs\libraries\PHS_Registry;

class PHS_Setup_view extends PHS_Registry
{
    private $data = [];

    /** @var bool|string */
    private $template_file = false;

    /** @var bool|string */
    private static $templates_www = false;

    /** @var bool|string */
    private static $templates_dir = false;

    /**
     * @param string $template
     * @param false|array $data
     *
     * @return false|string
     */
    public function render_view($template, $data = false)
    {
        if ($data !== false
         && is_array($data)) {
            $this->set_context($data);
        }

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

        return @ob_get_clean();
    }

    public function get_resource_url($resource)
    {
        return self::get_templates_www().$resource;
    }

    public static function set_templates_www($www_path = false)
    {
        if ($www_path === false) {
            return self::$templates_www;
        }

        self::$templates_www = rtrim($www_path, '/\\');

        return self::$templates_www;
    }

    public static function get_templates_www($slash_ended = true)
    {
        if (self::$templates_www === false) {
            return 'templates'.($slash_ended ? '/' : '');
        }

        if (empty(self::$templates_www)) {
            return '';
        }

        return self::$templates_www.($slash_ended ? '/' : '');
    }

    public static function set_templates_dir($dir_path = false)
    {
        if ($dir_path === false) {
            return self::$templates_dir;
        }

        self::$templates_dir = rtrim($dir_path, '/\\');

        return self::$templates_dir;
    }

    public static function get_templates_dir($slash_ended = true)
    {
        if (self::$templates_dir === false) {
            return false;
        }

        if (empty(self::$templates_dir)) {
            return '';
        }

        return self::$templates_dir.($slash_ended ? '/' : '');
    }
}
