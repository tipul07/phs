<?php
namespace phs\libraries;

class PHS_Ldap extends PHS_Registry
{
    public const ERR_LDAP = 2, ERR_SOURCE = 3, ERR_DESTINATION = 4, ERR_LDAP_DIR = 5, ERR_LDAP_META = 6, ERR_LDAP_CONFIG = 7;

    private array $server_config;

    // ! true if class was provided a valid directory (not necessary writeable)
    private bool $server_ready;

    // ! (bool) true if there are no rights to write files in the directory, false if class can write files in directory
    private bool $server_readonly;

    public function __construct($params = false)
    {
        parent::__construct();

        $this->_reset_server_settings();

        if (!empty($params)) {
            if (empty($params['server']) || !is_array($params['server'])) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Unkown parameters for LDAP class.'));

                return;
            }

            if (!$this->server_settings($params['server'])) {
                return;
            }
        }

        $this->reset_error();
    }

    public function server_settings(?array $settings = null) : bool | array
    {
        if ($settings === null) {
            return $this->server_config;
        }

        $this->reset_error();

        $settings['ignore_config_file'] = !empty($settings['ignore_config_file']);

        if (!($settings = self::settings_valid($settings))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid LDAP server settings.'));

            return false;
        }

        if (!str_ends_with($settings['root'], '/')) {
            $settings['root'] .= '/';
        }

        // Read server settings from file if available
        if (empty($settings['ignore_config_file'])
            && @file_exists($settings['root'].self::_ldap_config_file())
            && ($file_settings_arr = self::load_ldap_settings($settings['root']))
            && ($file_settings_arr = self::settings_valid($file_settings_arr))) {
            $settings = $file_settings_arr;
        }

        if (!($server_settings = self::validate_settings($settings))) {
            $this->set_error(self::ERR_LDAP_CONFIG, self::_t('Failed validating LDAP config.'));

            return false;
        }

        $this->_reset_server_settings();

        $this->server_config = $server_settings;
        $this->server_readonly = !@is_writable($this->server_config['root']);
        $this->server_ready = true;

        if (!$this->server_readonly) {
            $this->_save_ldap_settings();
        }

        return true;
    }

    public function is_ready() : bool
    {
        return !empty($this->server_ready);
    }

    public function unlink_all() : bool
    {
        $this->reset_error();

        if (!$this->is_ready()
         || !($settings = $this->server_settings()) || !isset($settings['root'])
         || !@file_exists($settings['root']) || !@is_dir($settings['root'])) {
            $this->set_error(self::ERR_LDAP, self::_t('LDAP repository not setup.'));

            return false;
        }

        return $this->_unlink_ldap_dir($settings['root']);
    }

    public function rename(array $params) : ?array
    {
        $this->reset_error();

        if (empty($params['ldap_from']) || empty($params['ldap_to'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unkown parameters sent to LDAP rename.'));

            return null;
        }

        if (is_string($params['ldap_from'])) {
            $ldap_from = $this->identifier2ldap($params['ldap_from']);
        } else {
            $ldap_from = self::validate_array($params['ldap_from'], self::default_ldap_data());
        }

        if (empty($ldap_from['ldap_full_file_path'])) {
            $this->set_error_if_not_set(self::ERR_SOURCE, self::_t('Cannot get source LDAP info.'));

            return null;
        }

        if (!@file_exists($ldap_from['ldap_full_file_path'])) {
            $this->set_error(self::ERR_SOURCE, self::_t('LDAP resource not found.'));

            return null;
        }

        if (is_string($params['ldap_to'])) {
            $ldap_to_meta = $ldap_from['ldap_meta_arr'];
            $ldap_to_meta['ldap_id'] = $params['ldap_to'];

            $ldap_to = $this->identifier2ldap($params['ldap_to'], null, $ldap_to_meta);
        } else {
            $ldap_to = self::validate_array($params['ldap_to'], self::default_ldap_data());

            $ldap_to['ldap_meta_arr'] = $ldap_from['ldap_meta_arr'];
            $ldap_to['ldap_meta_arr']['ldap_id'] = $ldap_to['ldap_id'];
        }

        if (!is_array($ldap_to)) {
            $this->set_error(self::ERR_DESTINATION, self::_t('Cannot get destination LDAP info.'));

            return null;
        }

        $ldap_to['ldap_meta_arr']['renamed'] = date('d-m-Y H:i:s');

        if (!$this->_mkdir_tree($ldap_to['ldap_path_segments'])) {
            return null;
        }

        // make sure we can save ldap_to meta file
        if (!$this->update_meta($ldap_to, ['ignore_read_errors' => true])) {
            $this->set_error_if_not_set(self::ERR_LDAP_META, self::_t('Failed updating destination meta data.'));

            return null;
        }

        if (!@rename($ldap_from['ldap_full_file_path'], $ldap_to['ldap_full_file_path'])) {
            // Delete destination meta file
            @unlink($ldap_to['ldap_full_meta_file_path']);

            $this->set_error(self::ERR_DESTINATION, self::_t('Failed renaming LDAP resource.'));

            return null;
        }

        // Delete old meta data
        if (@file_exists($ldap_from['ldap_full_meta_file_path'])) {
            @unlink($ldap_from['ldap_full_meta_file_path']);
        }

        return [
            'ldap_from' => $ldap_from,
            'ldap_to'   => $ldap_to,
        ];
    }

    public function identifier_details(string | array $ldap_id) : ?array
    {
        $this->reset_error();

        if (empty($ldap_id)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unkown parameters sent to LDAP file_details.'));

            return null;
        }

        if (is_string($ldap_id)) {
            $ldap_data = $this->identifier2ldap($ldap_id);
        } else {
            $ldap_data = $ldap_id;
        }

        if (!is_array($ldap_data)) {
            $this->set_error(self::ERR_LDAP, self::_t('Cannot generate LDAP info.'));

            return null;
        }

        return $ldap_data;
    }

    public function unlink(array $params) : bool
    {
        if (empty($params)
            || empty($params['ldap_data'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unkown parameters sent to LDAP unlink.'));

            return false;
        }

        if (is_string($params['ldap_data'])) {
            $ldap_data = $this->identifier2ldap($params['ldap_data']);
        } else {
            $ldap_data = $params['ldap_data'];
        }

        if (empty($ldap_data['ldap_full_file_path']) || empty($ldap_data['ldap_full_meta_file_path'])) {
            $this->set_error(self::ERR_DESTINATION, self::_t('Cannot generate LDAP info.'));

            return false;
        }

        // Delete meta data
        if (@file_exists($ldap_data['ldap_full_meta_file_path'])) {
            @unlink($ldap_data['ldap_full_meta_file_path']);
        }

        // We deleted meta-data to be sure no related file is left
        if (!@file_exists($ldap_data['ldap_full_file_path'])) {
            return true;
        }

        @unlink($ldap_data['ldap_full_file_path']);

        return true;
    }

    public function add(array $params) : ?array
    {
        $this->reset_error();

        if (!$this->is_ready()) {
            $this->set_error(self::ERR_LDAP, self::_t('LDAP repository not ready.'));

            return null;
        }

        if (empty($params)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Bad parameters sent to LDAP add.'));

            return null;
        }

        if (empty($params['file'])
         || !@file_exists($params['file']) || !@is_readable($params['file'])
         || (!@is_file($params['file']) && !@is_link($params['file']))) {
            $this->set_error(self::ERR_SOURCE, self::_t('Cannot read source file [%s]', $params['file']));

            return null;
        }

        $settings = $this->server_settings();

        $params['overwrite'] = !empty($params['overwrite']);
        $params['move_source'] = !empty($params['move_source']);
        $params['skip_extension_check'] = !empty($params['skip_extension_check']);
        $params['extra_meta'] ??= [];

        if (empty($params['ldap_data'])) {
            $params['ldap_data'] = @basename($params['file']);
        }

        if (empty($params['ldap_data'])
            || !(is_string($params['ldap_data']) || !is_array($params['ldap_data']))) {
            $this->set_error(self::ERR_DESTINATION, self::_t('Cannot generate LDAP info.'));

            return null;
        }

        if (is_string($params['ldap_data'])) {
            $ldap_details = $this->identifier2ldap($params['ldap_data'], $params['file']);
        } else {
            $ldap_details = $params['ldap_data'];
        }

        if (!isset($ldap_details['ldap_meta_arr']['file_extension'])) {
            $this->set_error(self::ERR_DESTINATION, self::_t('Cannot generate LDAP info.'));

            return null;
        }

        if (!$params['skip_extension_check']
            && !empty($settings['allowed_extentions']) && is_array($settings['allowed_extentions'])
            && !in_array($ldap_details['ldap_meta_arr']['file_extension'], $settings['allowed_extentions'], true)) {
            $this->set_error(self::ERR_SOURCE,
                self::_t('Extension [%s] not allowed. [Only: %s]', $ldap_details['ldap_meta_arr']['file_extension'], implode(', ', $settings['allowed_extentions'])));

            return null;
        }

        if (!$params['skip_extension_check']
            && !empty($settings['denied_extentions']) && is_array($settings['denied_extentions'])
            && in_array($ldap_details['ldap_meta_arr']['file_extension'], $settings['denied_extentions'], true)) {
            $this->set_error(self::ERR_SOURCE,
                self::_t('Extension [%s] not allowed. [Denied: %s]', $ldap_details['ldap_meta_arr']['file_extension'], implode(', ', $settings['denied_extentions'])));

            return null;
        }

        if (!$this->_mkdir_tree($ldap_details['ldap_path_segments'])) {
            return null;
        }

        if (@file_exists($ldap_details['ldap_full_file_path'])) {
            if (empty($params['overwrite'])) {
                $this->set_error(self::ERR_DESTINATION, self::_t('LDAP file already exists. [%s]', $ldap_details['ldap_file_name']));

                return null;
            }

            @unlink($ldap_details['ldap_full_file_path']);
        }

        if (!empty($params['move_source'])) {
            $result = @rename($params['file'], $ldap_details['ldap_full_file_path']);
        } else {
            $result = @copy($params['file'], $ldap_details['ldap_full_file_path']);
        }

        if ($result === false) {
            $this->set_error(self::ERR_SOURCE, self::_t('Error copying resource file to LDAP structure.'));

            return null;
        }

        if (empty($ldap_details) || !is_array($ldap_details)) {
            $ldap_details = [];
        }

        if (empty($ldap_details['ldap_meta_arr']) || !is_array($ldap_details['ldap_meta_arr'])) {
            $ldap_details['ldap_meta_arr'] = [];
        }

        /** @var array $ldap_details['ldap_meta_arr'] */
        if (!empty($params['extra_meta']) && is_array($params['extra_meta'])) {
            foreach ($params['extra_meta'] as $key => $val) {
                if ($key !== ''
                    && !isset($ldap_details['ldap_meta_arr'][$key])) {
                    $ldap_details['ldap_meta_arr'][$key] = $val;
                }
            }
        }

        $ldap_details['ldap_meta_arr']['added'] = date('d-m-Y H:i:s');

        if (!($ldap_details['ldap_meta_arr'] = $this->update_meta($ldap_details))) {
            @unlink($ldap_details['ldap_full_file_path']);

            return null;
        }

        return $ldap_details;
    }

    public function get_meta(string | array $ldap_data, array $params = []) : ?array
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['ignore_read_errors'])) {
            $params['ignore_read_errors'] = false;
        }

        if (is_string($ldap_data)) {
            $ldap_data = $this->identifier2ldap($ldap_data);
        }

        if (empty($ldap_data['ldap_full_meta_file_path'])) {
            $this->set_error(self::ERR_LDAP_META, self::_t('Unkown LDAP meta file.'));

            return null;
        }

        if (!@file_exists($ldap_data['ldap_full_meta_file_path'])) {
            return self::default_meta_data();
        }

        if (null === ($return_arr = self::_read_meta_data($ldap_data['ldap_full_meta_file_path']))
            && empty($params['ignore_read_errors'])) {
            $this->set_error(self::ERR_LDAP_META, self::_t('Couldn\'t read LDAP meta file.'));

            return null;
        }

        return $return_arr ?: self::default_meta_data();
    }

    public function update_meta(string | array $ldap_data, array $params = []) : ?array
    {
        $this->reset_error();

        $params['ignore_read_errors'] = !empty($params['ignore_read_errors']);

        if (is_string($ldap_data)) {
            $ldap_data = $this->identifier2ldap($ldap_data);
        }

        if (empty($ldap_data['ldap_full_meta_file_path'])) {
            $this->set_error(self::ERR_LDAP_META, self::_t('Unkown LDAP meta file.'));

            return null;
        }

        if (null === ($existing_meta = $this->get_meta($ldap_data, $params))) {
            if (empty($params['ignore_read_errors'])) {
                $this->set_error_if_not_set(self::ERR_LDAP_META, self::_t('Error reading LDAP meta file.'));

                return null;
            }

            $existing_meta = [];
        }

        $new_meta = PHS_Line_params::update_line_params($existing_meta, $ldap_data['ldap_meta_arr']);
        $new_meta['last_save'] = date('d-m-Y H:i:s');

        $new_meta_str = PHS_Line_params::to_string($new_meta);

        $retries = 5;
        while (!($fil = @fopen($ldap_data['ldap_full_meta_file_path'], 'wb')) && $retries > 0) {
            $retries--;
        }

        if (empty($fil)) {
            $this->set_error(self::ERR_LDAP_META, self::_t('Cannot open meta file for write.'));

            return null;
        }

        @fwrite($fil, $new_meta_str);
        @fflush($fil);
        @fclose($fil);

        return $new_meta;
    }

    public function identifier2ldap(string $identifier, ?string $source_file = null, array $meta_arr = []) : ?array
    {
        if (empty($identifier)) {
            return null;
        }

        $settings = $this->server_settings();
        $settings = empty($settings)
            ? self::default_settings()
            : self::validate_array($settings, self::default_settings());

        $file_hash = md5($identifier);
        if (!($segments_arr = str_split($file_hash, $settings['dir_length']))) {
            return null;
        }

        $return_arr = self::default_ldap_data();
        $return_arr['ldap_id'] = $identifier;
        $return_arr['ldap_root'] = $settings['root'];
        $return_arr['ldap_path'] = '';
        $return_arr['ldap_path_segments'] = [];
        $return_arr['ldap_full_path'] = '';
        $return_arr['ldap_file_name'] = '';
        $return_arr['ldap_full_file_path'] = '';
        $return_arr['ldap_meta_file'] = $settings['file_prefix'].$file_hash.'_.meta'; // added _ at the end in case file we put in LDAP has 'meta' extension
        $return_arr['ldap_full_meta_file_path'] = '';
        $return_arr['ldap_meta_arr'] = self::default_meta_data();

        for ($i = 0; isset($segments_arr[$i]) && $i < $settings['depth']; $i++) {
            $return_arr['ldap_path'] .= $segments_arr[$i].'/';
            $return_arr['ldap_path_segments'][] = $segments_arr[$i];
        }

        $return_arr['ldap_full_path'] = $return_arr['ldap_root'].$return_arr['ldap_path'];
        $return_arr['ldap_full_meta_file_path'] = $return_arr['ldap_full_path'].$return_arr['ldap_meta_file'];

        // If we have a source file, populate LDAP identification with what we can extract from file...
        if (null !== $source_file) {
            // If we don't have a valid file as source, we cannot extract LDAP info correctly
            if (!@file_exists($source_file) || !@is_readable($source_file)
             || (!@is_file($source_file) && !@is_link($source_file))
             || !($return_arr['ldap_meta_arr'] = $this->_extract_meta_data($source_file, $identifier))) {
                return null;
            }
        } elseif (!empty($meta_arr)) {
            $return_arr['ldap_meta_arr'] = self::validate_array($meta_arr, self::default_meta_data());
        } elseif (!@file_exists($return_arr['ldap_full_meta_file_path'])
                  || null === ($return_arr['ldap_meta_arr'] = self::_read_meta_data($return_arr['ldap_full_meta_file_path']))) {
            return null;
        }

        $return_arr['ldap_file_name'] = $settings['file_prefix'].$file_hash.'.'.$return_arr['ldap_meta_arr']['file_extension'];
        $return_arr['ldap_full_file_path'] = $return_arr['ldap_full_path'].$return_arr['ldap_file_name'];

        return $return_arr;
    }

    private function _reset_server_settings() : void
    {
        $this->server_config = self::default_settings();

        $this->server_readonly = true;
        $this->server_ready = false;
    }

    private function _unlink_ldap_dir(string $dir, int $level = 0) : bool
    {
        if (empty($dir) || !@is_dir($dir)) {
            return false;
        }

        if (($files_arr = glob($dir.'*'))) {
            foreach ($files_arr as $file) {
                if (@is_dir($file)) {
                    $this->_unlink_ldap_dir($file.'/', $level + 1);
                } else {
                    @unlink($file);
                }
            }
        }

        if ($level > 0) {
            @rmdir($dir);
        }

        return true;
    }

    private function _extract_meta_data(string $file_name, string $ldap_id) : array
    {
        if (empty($file_name)
            || !($new_file_name = @realpath($file_name))
            || !@file_exists($new_file_name)
            || (!@is_file($new_file_name) && !@is_link($new_file_name))) {
            return [];
        }
        $file_name = $new_file_name;

        $file_ext = '';
        if (($file_dots_arr = explode('.', $file_name))
            && count($file_dots_arr) > 1) {
            $file_ext = strtolower(array_pop($file_dots_arr));
        }

        $return_arr = self::default_meta_data();
        $return_arr['ldap_id'] = $ldap_id;
        $return_arr['file_name'] = @basename($file_name);
        $return_arr['file_extension'] = $file_ext;
        $return_arr['size'] = @filesize($file_name);

        return $return_arr;
    }

    private function _mkdir_tree(array $segments_arr) : bool
    {
        if (empty($segments_arr)
            || !$this->is_ready()
            || !($settings = $this->server_settings())
            || empty($settings['root'])) {
            return false;
        }

        $this->reset_error();

        $settings['root'] = rtrim($settings['root'], '/\\');

        $segments_path = '';
        foreach ($segments_arr as $dir_segment) {
            if (empty($dir_segment)) {
                continue;
            }

            $segments_path .= '/'.$dir_segment;

            $current_path = $settings['root'].$segments_path;

            if (@file_exists($current_path)) {
                if (!@is_dir($current_path)) {
                    $this->set_error(self::ERR_LDAP_DIR, self::_t('[%s] is not a directory.', $current_path));

                    return false;
                }

                continue;
            }

            if (!@mkdir($current_path) && !@is_dir($current_path)) {
                $this->set_error(self::ERR_LDAP_DIR, 'Cannot create directory ['.$current_path.']');

                return false;
            }

            @chmod($current_path, $settings['dir_mode']);
        }

        return true;
    }

    private function get_settings_checksum(array $settings_arr) : string
    {
        $settings_arr = self::validate_array($settings_arr, self::default_settings());

        $settings_arr['checksum'] = '';
        $settings_arr['config_last_save'] = '';

        return @md5(@json_encode($settings_arr));
    }

    private function _save_ldap_settings() : bool
    {
        if (!$this->is_ready()) {
            return false;
        }

        $settings = self::validate_array($this->server_settings(), self::default_settings());

        $settings_checksum = $this->get_settings_checksum($settings);

        if (empty($settings['checksum'])) {
            $settings['checksum'] = $settings_checksum;
        } elseif ($settings['checksum'] === $settings_checksum) {
            return true;
        }

        $config_file = self::_ldap_config_file();

        $retries = 5;
        while (!($fil = @fopen($settings['root'].$config_file, 'wb')) && $retries > 0) {
            $retries--;
        }

        if (empty($fil)) {
            $this->set_error(self::ERR_LDAP_META, self::_t('Cannot open LDAP config file for write.'));

            return false;
        }

        $settings['config_last_save'] = date('d-m-Y H:i:s');

        @fwrite($fil, PHS_Line_params::to_string($settings));
        @fflush($fil);
        @fclose($fil);

        return true;
    }

    public static function settings_valid(array $settings) : ?array
    {
        if (empty($settings)
            || empty($settings['root'])
            || !($settings['root'] = @realpath($settings['root']))
            || !@is_dir($settings['root'])) {
            return null;
        }

        return $settings;
    }

    public static function default_settings() : array
    {
        $default_config = [];
        $default_config['version'] = 1;
        $default_config['name'] = 'LDAP Repository';
        $default_config['root'] = '';
        $default_config['dir_length'] = 2;
        $default_config['depth'] = 4;
        $default_config['file_prefix'] = '';
        $default_config['dir_mode'] = 0775; // 0775 is octal value, not decimal
        $default_config['file_mode'] = 0775; // 0775 is octal value, not decimal
        $default_config['allowed_extentions'] = [];
        $default_config['denied_extentions'] = [];
        $default_config['config_last_save'] = 0;
        $default_config['checksum'] = '';

        return $default_config;
    }

    public static function validate_settings(array $settings) : array
    {
        $def_settings = self::default_settings();
        if (empty($settings)) {
            return $def_settings;
        }

        $settings = self::validate_array($settings, $def_settings);

        $settings['version'] = (int)$settings['version'];
        $settings['name'] = trim($settings['name']);
        $settings['root'] = trim($settings['root']);
        $settings['dir_length'] = (int)$settings['dir_length'];
        $settings['depth'] = (int)$settings['depth'];
        $settings['file_prefix'] = trim($settings['file_prefix']);
        $settings['dir_mode'] = (int)$settings['dir_mode'];
        $settings['file_mode'] = (int)$settings['file_mode'];

        if (empty($settings['allowed_extentions']) || !is_array($settings['allowed_extentions'])) {
            $settings['allowed_extentions'] = $def_settings['allowed_extentions'];
        }

        if (empty($settings['denied_extentions']) || !is_array($settings['denied_extentions'])) {
            $settings['denied_extentions'] = $def_settings['denied_extentions'];
        }

        if (!str_ends_with($settings['root'], '/')) {
            $settings['root'] .= '/';
        }

        if (!empty($settings['name'])) {
            $settings['name'] = trim(str_replace(['.', '/', '\\', "\r", "\n"], '', $settings['name']));
        }
        if (!empty($settings['file_prefix'])) {
            $settings['file_prefix'] = trim(str_replace(
                ['.', '/', '\\', "\r", "\n", '~', '`', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '=', '{', '}', '[', ']', '|', '>', '<', ';', ':', '\'', '"', '?', ','],
                '', $settings['file_prefix']));
        }

        if (isset($settings['allowed_extentions'])) {
            $settings['allowed_extentions'] = self::_validate_extensions($settings['allowed_extentions']);
        }

        if (isset($settings['denied_extentions'])) {
            $settings['denied_extentions'] = self::_validate_extensions($settings['denied_extentions']);
        }

        return $settings;
    }

    public static function default_meta_data() : array
    {
        return [
            'version'        => 1,
            'ldap_id'        => '',
            'file_name'      => '',
            'file_extension' => '',
            'size'           => 0,
        ];
    }

    /**
     * @return array
     */
    public static function default_ldap_data() : array
    {
        return [
            'ldap_id'                  => '',
            'ldap_root'                => '',
            'ldap_path'                => '',
            'ldap_path_segments'       => [],
            'ldap_full_path'           => '',
            'ldap_file_name'           => '',
            'ldap_full_file_path'      => '',
            'ldap_meta_file'           => '',
            'ldap_full_meta_file_path' => '',
            'ldap_meta_arr'            => self::default_meta_data(),
        ];
    }

    public static function load_ldap_settings(string $root) : ?array
    {
        if (empty($root)
            || !($new_root = @realpath($root))
            || !@is_dir($new_root)) {
            return null;
        }

        $root = $new_root;
        if (!str_ends_with($root, '/')) {
            $root .= '/';
        }

        $config_file = self::_ldap_config_file();
        if (!@file_exists($root.$config_file)) {
            return [];
        }

        if (!($existing_config = @file_get_contents($root.$config_file))) {
            return null;
        }

        return self::validate_array(PHS_Line_params::parse_string($existing_config), self::default_settings());
    }

    private static function _validate_extensions(mixed $extensions_arr) : array
    {
        if (empty($extensions_arr) || !is_array($extensions_arr)) {
            return [];
        }

        $return_arr = [];
        foreach ($extensions_arr as $ext) {
            $ext = strtolower(trim(str_replace(['/', '.', '\\'], '', $ext)));
            if ($ext === '') {
                continue;
            }

            $return_arr[] = $ext;
        }

        return $return_arr;
    }

    private static function _read_meta_data(string $meta_file) : ?array
    {
        if (!@file_exists($meta_file) || !@is_readable($meta_file)
         // refuse to read files bigger than 2Mb - might be an error regarding meta file name
         || @filesize($meta_file) > 2097152
         || ($meta_buffer = @file_get_contents($meta_file)) === false) {
            return null;
        }

        $return_arr = [];
        if (!empty($meta_buffer)) {
            $return_arr = self::validate_array(PHS_Line_params::parse_string($meta_buffer), self::default_meta_data());
        }

        return $return_arr;
    }

    private static function _ldap_config_file() : string
    {
        return '__ldap.config';
    }
}
