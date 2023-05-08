<?php
namespace phs\plugins\bbeditor\libraries;

use phs\libraries\PHS_Library;

class Bbcode extends PHS_Library
{
    public const ERR_BB_PARSE = 1;

    public const THEME_CONTROL_SEP = '{separator}';

    public const BB_CODE_REGISTRY_KEY = 'bb_code_registry';

    private $_editor_attributes = [];

    private $_theme = 'default';

    private static $BB_CODES = [];

    private static $CUSTOM_BB_CODES = [];

    private static $BB_CALLBACKS_ARR = [];

    private static $EDITOR_THEMES = [
        'default' => [
            'b', 'i', 'u', self::THEME_CONTROL_SEP,
            'p', self::THEME_CONTROL_SEP,
            'olist', 'ulist', self::THEME_CONTROL_SEP,
            'table', 'img', 'link', self::THEME_CONTROL_SEP,
            'do_preview',
        ],
    ];

    public function __construct($error_no = self::ERR_OK, $error_msg = '', $error_debug_msg = '', $static_instance = false)
    {
        parent::__construct($error_no, $error_msg, $error_debug_msg, $static_instance);

        if (empty(self::$BB_CODES)) {
            self::$BB_CODES = [
                'b' => [
                    'title'         => 'Bold',
                    'attributes'    => [],
                    'html'          => '<b>{TAG_VALUE}</b>',
                    'public'        => true,
                    'template'      => '[b]{CONTENT}[/b]',
                    'editor_button' => '<i class="fa fa-bold" aria-hidden="true"></i>',
                ],
                'i' => [
                    'title'         => 'Italic',
                    'attributes'    => [],
                    'html'          => '<i>{TAG_VALUE}</i>',
                    'public'        => true,
                    'template'      => '[i]{CONTENT}[/i]',
                    'editor_button' => '<i class="fa fa-italic" aria-hidden="true"></i>',
                ],
                'u' => [
                    'title'         => 'Underline',
                    'attributes'    => [],
                    'html'          => '<u>{TAG_VALUE}</u>',
                    'public'        => true,
                    'template'      => '[u]{CONTENT}[/u]',
                    'editor_button' => '<i class="fa fa-underline" aria-hidden="true"></i>',
                ],
                'p' => [
                    'title'         => 'Paragraph',
                    'attributes'    => ['align' => '', ],
                    'html'          => '<p{TAG_ATTRIBUTES}>{TAG_VALUE}</p>',
                    'public'        => true,
                    'template'      => '[p]{CONTENT}[/p]',
                    'editor_button' => '<i class="fa fa-paragraph" aria-hidden="true"></i>',
                ],
                'h1' => [
                    'title'         => 'Heading 1',
                    'attributes'    => [],
                    'html'          => '<h1{TAG_ATTRIBUTES}>{TAG_VALUE}</h1>',
                    'public'        => true,
                    'template'      => '[h1]{CONTENT}[/h1]',
                    'editor_button' => '<i class="fa fa-header" aria-hidden="true"></i>',
                ],
                'h2' => [
                    'title'         => 'Heading 2',
                    'attributes'    => [],
                    'html'          => '<h2{TAG_ATTRIBUTES}>{TAG_VALUE}</h2>',
                    'public'        => true,
                    'template'      => '[h2]{CONTENT}[/h2]',
                    'editor_button' => '<i class="fa fa-header" aria-hidden="true"></i>',
                ],
                'h3' => [
                    'title'         => 'Heading 3',
                    'attributes'    => [],
                    'html'          => '<h3{TAG_ATTRIBUTES}>{TAG_VALUE}</h3>',
                    'public'        => true,
                    'template'      => '[h3]{CONTENT}[/h3]',
                    'editor_button' => '<i class="fa fa-header" aria-hidden="true"></i>',
                ],
                'h4' => [
                    'title'         => 'Heading 4',
                    'attributes'    => [],
                    'html'          => '<h4{TAG_ATTRIBUTES}>{TAG_VALUE}</h4>',
                    'public'        => true,
                    'template'      => '[h4]{CONTENT}[/h4]',
                    'editor_button' => '<i class="fa fa-header" aria-hidden="true"></i>',
                ],
                'h5' => [
                    'title'         => 'Heading 5',
                    'attributes'    => [],
                    'html'          => '<h5{TAG_ATTRIBUTES}>{TAG_VALUE}</h5>',
                    'public'        => true,
                    'template'      => '[h5]{CONTENT}[/h5]',
                    'editor_button' => '<i class="fa fa-header" aria-hidden="true"></i>',
                ],
                'br' => [
                    'title'         => 'New line',
                    'attributes'    => [],
                    'html'          => '<br />',
                    'public'        => true,
                    'template'      => '[br/]',
                    'editor_button' => '<i class="fa fa-exchange" aria-hidden="true"></i>',
                ],
                'link' => [
                    'title'                => 'Link',
                    'attributes'           => ['href' => '', 'name' => '', 'title' => '', 'target' => ''],
                    'mandatory_attributes' => ['href'],
                    'html'                 => '<a{TAG_ATTRIBUTES}>{TAG_VALUE}</a>',
                    'public'               => true,
                    'template'             => '[link href="" title=""]{CONTENT}[/link]',
                    'editor_button'        => '<i class="fa fa-link" aria-hidden="true"></i>',
                ],
                'img' => [
                    'title'                => 'Image',
                    'attributes'           => ['src' => '', 'title' => '', 'width' => '', 'height' => '', ],
                    'mandatory_attributes' => ['src'],
                    'html'                 => '<img{TAG_ATTRIBUTES} />',
                    'public'               => true,
                    'template'             => '[img src="" title="" width="" height=""]{CONTENT}[/img]',
                    'editor_button'        => '<i class="fa fa-picture-o" aria-hidden="true"></i>',
                ],
                'olist' => [
                    'title'      => 'Ordered list',
                    'attributes' => [],
                    'html'       => '<ol>{TAG_VALUE}</ol>',
                    'public'     => true,
                    'template'   => '[olist]'."\n"
                        .'[li]Item 1[/li]'."\n"
                        .'[li]Item 2[/li]'."\n"
                        .'[/olist]',
                    'editor_button' => '<i class="fa fa-list-ol" aria-hidden="true"></i>',
                ],
                'ulist' => [
                    'title'      => 'Unordered list',
                    'attributes' => [],
                    'html'       => '<ul>{TAG_VALUE}</ul>',
                    'public'     => true,
                    'template'   => '[ulist]'."\n"
                        .'[li]Item 1[/li]'."\n"
                        .'[li]Item 2[/li]'."\n"
                        .'[/ulist]',
                    'editor_button' => '<i class="fa fa-list-ul" aria-hidden="true"></i>',
                ],
                'li' => [
                    'title'      => 'List item',
                    'attributes' => [],
                    'html'       => '<li>{TAG_VALUE}</li>',
                    'public'     => false,
                    'template'   => '[li]Item[/li]',
                ],
                'table' => [
                    'title'      => 'Table',
                    'attributes' => ['width' => '', 'border' => 0, 'cellspacing' => 0, 'cellpadding' => 0],
                    'html'       => '<table{TAG_ATTRIBUTES}>{TAG_VALUE}</table>',
                    'public'     => true,
                    'template'   => '[table]'."\n"
                        .'[thead][tr][th align="center"]Column 1[/th][th align="center"]Column 2[/th][/tr][/thead]'."\n"
                        .'[tbody]'."\n"
                        .'[tr][td]Item 1,1[/td][td]Item 2,1[/td][/tr]'."\n"
                        .'[tr][td]Item 1,2[/td][td]Item 2,2[/td][/tr]'."\n"
                        .'[/tbody]'."\n"
                        .'[/table]',
                    'editor_button' => '<i class="fa fa-table" aria-hidden="true"></i>',
                ],
                'thead' => [
                    'title'      => 'Table header',
                    'attributes' => [],
                    'html'       => '<thead{TAG_ATTRIBUTES}>{TAG_VALUE}</thead>',
                    'public'     => false,
                    'template'   => '[thead][/thead]',
                ],
                'tbody' => [
                    'title'      => 'Table body',
                    'attributes' => [],
                    'html'       => '<tbody{TAG_ATTRIBUTES}>{TAG_VALUE}</tbody>',
                    'public'     => false,
                    'template'   => '[tbody][/tbody]',
                ],
                'tr' => [
                    'title'      => 'Table row',
                    'attributes' => [],
                    'html'       => '<tr{TAG_ATTRIBUTES}>{TAG_VALUE}</tr>',
                    'public'     => false,
                    'template'   => '[tr][/tr]',
                ],
                'th' => [
                    'title'      => 'Header cell',
                    'attributes' => ['width' => '', 'padding' => 0, 'align' => '', ],
                    'html'       => '<th{TAG_ATTRIBUTES}>{TAG_VALUE}</th>',
                    'public'     => false,
                    'template'   => '[th align="center"]Item[/th]',
                ],
                'td' => [
                    'title'      => 'Table cell',
                    'attributes' => ['width' => '', 'padding' => 0, 'align' => '', 'bgcolor' => '', 'color' => ''],
                    'html'       => '<td{TAG_ATTRIBUTES}>{TAG_VALUE}</td>',
                    'public'     => false,
                    'template'   => '[td align="center"]Item[/td]',
                ],
                'registry' => [
                    'title'         => 'Registry value',
                    'attributes'    => ['key' => '', 'default' => '', 'prefix' => '', 'suffix' => '', 'empty' => false],
                    'callback'      => [$this, 'bb_registry_render'],
                    'public'        => true,
                    'template'      => '[registry key="" /]',
                    'editor_button' => '<i class="fa fa-bookmark" aria-hidden="true"></i>',
                ],
                'callback' => [
                    'title'         => 'Callback function',
                    'attributes'    => ['func' => '', 'lang' => ''],
                    'callback'      => [$this, 'bb_callback_render'],
                    'public'        => true,
                    'template'      => '[callback func=""][/callback]',
                    'editor_button' => '<i class="fa fa-code" aria-hidden="true"></i>',
                ],
                'do_preview' => [
                    'title'             => 'Preview document',
                    'attributes'        => [],
                    'public'            => true,
                    'functionality_tag' => true,
                    'editor_button'     => '<i class="fa fa-eye" aria-hidden="true"></i>',
                    'js_click_function' => 'phs_bb_editor.do_preview( \'{ATTRS.ID}\' )',
                ],
            ];
        }
    }

    public function bb_registry_render($params_arr)
    {
        // Not a valid node... let bb parser decide what to do here...
        if (empty($params_arr) || !is_array($params_arr)
         || empty($params_arr['node_arr'])
         || !($node_arr = $params_arr['node_arr']) || !is_array($node_arr)) {
            return '';
        }

        $registry_key = false;
        $default_value = false;
        $prefix_value = '';
        $suffix_value = '';
        $empty_value = false;
        if (!empty($node_arr['shortcode']) && is_array($node_arr['shortcode'])
         && !empty($node_arr['shortcode']['validated_attributes']) && is_array($node_arr['shortcode']['validated_attributes'])) {
            if (!empty($node_arr['shortcode']['validated_attributes']['key'])) {
                $registry_key = $node_arr['shortcode']['validated_attributes']['key'];
            }

            if (isset($node_arr['shortcode']['validated_attributes']['default'])) {
                $default_value = $node_arr['shortcode']['validated_attributes']['default'];
            }

            if (isset($node_arr['shortcode']['validated_attributes']['prefix'])) {
                $prefix_value = $node_arr['shortcode']['validated_attributes']['prefix'];
            }

            if (isset($node_arr['shortcode']['validated_attributes']['suffix'])) {
                $suffix_value = $node_arr['shortcode']['validated_attributes']['suffix'];
            }

            if (isset($node_arr['shortcode']['validated_attributes']['empty'])
             && is_string($node_arr['shortcode']['validated_attributes']['empty'])) {
                $empty_value = $node_arr['shortcode']['validated_attributes']['empty'];
            }
        }

        if (empty($registry_key)) {
            return '[Invalid registry key]';
        }

        if (($registry_val = self::get_bb_code_registry($registry_key)) === null
            || !is_string($registry_val)) {
            if ($default_value === false) {
                return '[Registry Key "'.$registry_key.'" not defined or is not string.]';
            }

            return $default_value;
        }

        if ($registry_val === ''
         && $empty_value !== false) {
            return $empty_value;
        }

        return $prefix_value.$registry_val.$suffix_value;
    }

    public function bb_callback_render($params_arr)
    {
        // Not a valid node... let bb parser decide what to do here...
        if (empty($params_arr) || !is_array($params_arr)
         || empty($params_arr['node_arr'])
         || !($node_arr = $params_arr['node_arr']) || !is_array($node_arr)) {
            return '';
        }

        $rendered_buf = '';
        if (!empty($node_arr['shortcode']) && is_array($node_arr['shortcode'])
         && !empty($node_arr['shortcode']['validated_attributes']) && is_array($node_arr['shortcode']['validated_attributes'])
         && !empty($node_arr['shortcode']['validated_attributes']['func'])) {
            // make sure we have a language defined as attribute...
            if (empty($node_arr['shortcode']['validated_attributes']['lang'])
             || !self::valid_language($node_arr['shortcode']['validated_attributes']['lang'])) {
                $node_arr['shortcode']['validated_attributes']['lang'] = self::get_default_language();
            }

            if (!($func_name = $this->function_name_check($node_arr['shortcode']['validated_attributes']['func']))
             || !($func_definition = $this->get_callback_function($func_name))
             || empty($func_definition['callable'])) {
                return '[UNKNOWN FUNCTION '.$func_name.']';
            }

            if (empty($func_definition['default_parameters'])) {
                $func_definition['default_parameters'] = [];
            }

            // Callable will set the error in bbeditor library...
            $this->reset_error();
            $callable = $func_definition['callable'];
            if (!($rendered_buf = $callable($func_definition['default_parameters'], $this))) {
                if (!$this->has_error()) {
                    $rendered_buf = '';
                } else {
                    $rendered_buf = '[ERROR running function '.$func_name.': '.$this->get_error_message().']';
                }
            }
        }

        return $rendered_buf;
    }

    /**
     * @param false|array $attr
     *
     * @return array|false
     */
    public function bb_editor_attributes($attr = false)
    {
        if ($attr === false) {
            return $this->_editor_attributes;
        }

        if (!is_array($attr)) {
            return false;
        }

        if (!($this->_editor_attributes = self::validate_array($attr, $this->default_bb_editor_input_attributes()))) {
            $this->_editor_attributes = $this->default_bb_editor_input_attributes();
        }

        return $this->_editor_attributes;
    }

    public function bb_editor_replace_vars($str)
    {
        // replace attributes...
        if (($attrs = $this->bb_editor_attributes())
        && is_array($attrs)) {
            foreach ($attrs as $key => $val) {
                if (!is_scalar($val)) {
                    continue;
                }

                $str = str_replace('{ATTRS.'.strtoupper($key).'}', $val, $str);
            }
        }

        return $str;
    }

    public function get_shortcodes_definition()
    {
        // We don't cache results as custom shortcodes might be added
        return self::merge_array_assoc(self::$BB_CODES, self::$CUSTOM_BB_CODES);
    }

    public function get_bb_shortcodes_definition()
    {
        if (!($shortcodes_definition = $this->get_shortcodes_definition())) {
            return [];
        }

        // We don't cache results as custom shortcodes might be added
        $return_arr = [];
        foreach ($shortcodes_definition as $shortcode => $shortcode_arr) {
            if (!empty($shortcode_arr['functionality_tag'])) {
                continue;
            }

            $return_arr[$shortcode] = $shortcode_arr;
        }

        return $return_arr;
    }

    public function default_function_definition_params()
    {
        return [
            'callable'           => false,
            'default_parameters' => [],
        ];
    }

    public function function_name_check($func_name)
    {
        return strtolower(trim($func_name));
    }

    public function get_callback_function($func_name)
    {
        $func_name = $this->function_name_check($func_name);
        if (!empty(self::$BB_CALLBACKS_ARR[$func_name])) {
            return self::$BB_CALLBACKS_ARR[$func_name];
        }

        return false;
    }

    /**
     * @param string $func_name
     * @param false|array $func_definition
     *
     * @return bool
     */
    public function define_callback_function($func_name, $func_definition = false)
    {
        $this->reset_error();

        if (empty($func_name)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide function name.'));

            return false;
        }

        if (!empty(self::$BB_CALLBACKS_ARR[$func_name])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Function %s already defined.', $func_name));

            return false;
        }

        $default_function_params = $this->default_function_definition_params();
        if (empty($func_definition) || !is_array($func_definition)) {
            $func_definition = $default_function_params;
        } else {
            $func_definition = self::validate_array($func_definition, $default_function_params);
        }

        if (empty($func_definition['callable']) || !@is_callable($func_definition['callable'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a callable for function %s.', $func_name));

            return false;
        }

        $func_name = $this->function_name_check($func_name);
        self::$BB_CALLBACKS_ARR[$func_name] = $func_definition;

        return true;
    }

    /**
     * @param false|string $theme
     *
     * @return false|string|array
     */
    public function editor_theme($theme = false)
    {
        if ($theme === false) {
            return $this->_theme;
        }

        $theme = strtolower(trim($theme));
        if (empty(self::$EDITOR_THEMES[$theme])) {
            return false;
        }

        $this->_theme = $theme;

        return self::$EDITOR_THEMES[$theme];
    }

    public function get_current_theme()
    {
        if (empty(self::$EDITOR_THEMES[$this->_theme])) {
            return false;
        }

        return self::$EDITOR_THEMES[$this->_theme];
    }

    public function get_shortcodes()
    {
        if (!($bb_codes_arr = $this->get_shortcodes_definition())
         || !is_array($bb_codes_arr)) {
            return [];
        }

        return array_keys($bb_codes_arr);
    }

    public function valid_shortcode($code)
    {
        $all_codes = $this->get_shortcodes_definition();
        $code = strtolower(trim($code));
        if (empty($all_codes[$code])) {
            return false;
        }

        return $all_codes[$code];
    }

    public function get_bb_shortcodes()
    {
        if (!($bb_codes_arr = $this->get_bb_shortcodes_definition())
         || !is_array($bb_codes_arr)) {
            return [];
        }

        return array_keys($bb_codes_arr);
    }

    public function valid_bb_shortcode($code)
    {
        $all_codes = $this->get_bb_shortcodes_definition();
        $code = strtolower(trim($code));
        if (empty($all_codes[$code])) {
            return false;
        }

        return $all_codes[$code];
    }

    public function validate_shortcode_attributes($shortcode, $attr_arr, $params = false)
    {
        if (empty($attr_arr) || !is_array($attr_arr)
         || !($shortcode_arr = $this->valid_shortcode($shortcode))
         || empty($shortcode_arr['attributes']) || !is_array($shortcode_arr['attributes'])) {
            return [];
        }

        $attributes_arr = [];
        foreach ($shortcode_arr['attributes'] as $attr_key => $attr_def_val) {
            if (array_key_exists($attr_key, $attr_arr)) {
                $attributes_arr[$attr_key] = $attr_arr[$attr_key];
            }
        }

        return $attributes_arr;
    }

    /**
     * @param false|array $tagnames
     *
     * @return string
     */
    public function get_shortcode_regex($tagnames = false)
    {
        if (empty($tagnames) || !is_array($tagnames)) {
            $tagnames = $this->get_bb_shortcodes();
        }

        $tagregexp = implode('|', array_map('preg_quote', $tagnames));

        return '/\[(|\/)('.$tagregexp.')(|\s+[^\[\]]*)(|\/)\]/miU';
    }

    public function parse_attributes($text)
    {
        if (empty($text)) {
            return [];
        }

        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $atts = [];
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);

        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && $m[7] !== '') {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                }
            }
        }

        return $atts;
    }

    /**
     * @param string $str
     * @param false|array $params
     *
     * @return array
     */
    public function bb_to_array($str, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['allow_html_code'])) {
            $params['allow_html_code'] = false;
        }

        $pattern = $this->get_shortcode_regex();
        if (empty($params['allow_html_code'])) {
            $str = strip_tags($str);
        }

        $result_arr = [];
        $errors_arr = [];
        $str_len = strlen($str);
        if (preg_match_all($pattern, $str, $matches_arr, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)
        && !empty($matches_arr) && is_array($matches_arr)
        && ($matches_count = count($matches_arr))) {
            $parse_params = [];
            $parse_params['matches_index'] = 0;
            $old_offset = 0;
            while ($parse_params['matches_index'] < $matches_count) {
                if (!($parse_params = $this->parser_extract_node($str, $matches_arr, $parse_params))
                 || !is_array($parse_params)) {
                    break;
                }

                $old_offset = $parse_params['str_old_offset'];

                if (!empty($parse_params['errors'])) {
                    $errors_arr = array_merge($errors_arr, $parse_params['errors']);
                }
                if (!empty($parse_params['nodes'])) {
                    $result_arr = array_merge($result_arr, $parse_params['nodes']);
                }
            }

            if ($old_offset < $str_len) {
                $node_arr = $this->default_parser_node_definition();
                $node_arr['text'] = substr($str, $old_offset);

                $result_arr[] = $node_arr;
            }
        }

        return [
            'errors' => $errors_arr,
            'result' => $result_arr,
        ];
    }

    /**
     * @param string|array $str
     * @param false|array $params
     *
     * @return string
     */
    public function remove_bb_code($str, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['allow_html_code'])) {
            $params['allow_html_code'] = false;
        }
        // Internally set...
        if (empty($params['recurring_level'])) {
            $params['recurring_level'] = 0;
        }

        if (empty($params['recurring_level'])
        && is_string($str)) {
            if (strpos($str, '[') === false
               || !($array_result = $this->bb_to_array($str, ['allow_html_code' => (!empty($params['allow_html_code']))]))
               || !is_array($array_result['result'])) {
                return $str;
            }

            $parts_arr = $array_result['result'];
        }

        if (!empty($params['recurring_level'])
        && is_array($str)) {
            $parts_arr = $str;
        }

        if (empty($parts_arr) || !is_array($parts_arr)) {
            if (is_string($str)) {
                return $str;
            }

            return '';
        }

        $clean_result = '';
        foreach ($parts_arr as $node_arr) {
            $node_str = '';

            if (!empty($node_arr['text'])) {
                $node_str .= $node_arr['text'];
            }

            if (!empty($node_arr['content']) && is_array($node_arr['content'])) {
                $recurring_params = $params;
                $recurring_params['recurring_level']++;

                $node_str .= $this->remove_bb_code($node_arr['content'], $recurring_params);
            }

            $clean_result .= $node_str;
        }

        return $clean_result;
    }

    /**
     * @param string|array $str
     * @param false|array $params
     *
     * @return string
     */
    public function bb_to_html($str, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['allow_html_code'])) {
            $params['allow_html_code'] = false;
        }

        // Internally set...
        if (empty($params['recurring_level'])) {
            $params['recurring_level'] = 0;
        }

        if (empty($params['recurring_level'])
        && is_string($str)) {
            if (strpos($str, '[') === false
            || !($array_result = $this->bb_to_array($str, ['allow_html_code' => (!empty($params['allow_html_code']))]))
            || !is_array($array_result['result'])) {
                return $str;
            }

            $parts_arr = $array_result['result'];
        }

        if (!empty($params['recurring_level'])
        && is_array($str)) {
            $parts_arr = $str;
        }

        if (empty($parts_arr) || !is_array($parts_arr)) {
            if (is_string($str)) {
                return $str;
            }

            return '';
        }

        $html_result = '';
        foreach ($parts_arr as $node_arr) {
            if (empty($node_arr) || !is_array($node_arr)) {
                continue;
            }

            $node_str = '';

            if (!empty($node_arr['shortcode'])) {
                if (!$this->init_parsed_shortcode_for_html($node_arr)) {
                    continue;
                }
            }

            if (!empty($node_arr['text'])) {
                $node_str .= $node_arr['text'];
            }

            if (!empty($node_arr['content']) && is_array($node_arr['content'])) {
                $recurring_params = $params;
                $recurring_params['recurring_level']++;

                $node_str .= $this->bb_to_html($node_arr['content'], $recurring_params);
            }

            if (!empty($node_arr['shortcode'])
             && ($new_node_str = $this->render_parsed_shortcode_to_html($node_str, $node_arr))) {
                $node_str = $new_node_str;
            }

            $html_result .= $node_str;
        }

        return $html_result;
    }

    /**
     * @param string $content_text
     * @param array $node_arr
     *
     * @return string
     */
    public function render_parsed_shortcode_to_html($content_text, $node_arr)
    {
        if (empty($node_arr) || !is_array($node_arr)
         || empty($node_arr['shortcode']) || !($shortcode_arr = $node_arr['shortcode'])
         || empty($shortcode_arr) || !is_array($shortcode_arr)
         || empty($shortcode_arr['shortcode'])
         || !($definition_arr = $this->valid_bb_shortcode($shortcode_arr['shortcode']))) {
            return '';
        }

        if (!empty($definition_arr['callback'])) {
            if (!is_callable($definition_arr['callback'])) {
                return 'ERROR! [Shortcode:'.$shortcode_arr['shortcode'].' invalid callback.]';
            }

            if (empty($definition_arr['callback_params'])) {
                $definition_arr['callback_params'] = [];
            }

            if (empty($shortcode_arr['validated_attributes']) || !is_array($shortcode_arr['validated_attributes'])) {
                $shortcode_arr['validated_attributes'] = [];
            }

            $definition_arr['callback_params']['node_arr'] = $node_arr;
            $definition_arr['callback_params']['content_text'] = $content_text;

            if (($html_code = @call_user_func($definition_arr['callback'], $definition_arr['callback_params'])) === null
             || !is_string($html_code)) {
                $html_code = '';
            }

            return $html_code;
        }

        if (empty($definition_arr['html'])) {
            return '';
        }

        // render attibutes...
        $mandatory_fields = [];
        if (!empty($definition_arr['mandatory_attributes']) && is_array($definition_arr['mandatory_attributes'])) {
            foreach ($definition_arr['mandatory_attributes'] as $field) {
                $mandatory_fields[$field] = true;
            }
        }

        $attributes_str = '';
        if (!empty($shortcode_arr['validated_attributes']) && is_array($shortcode_arr['validated_attributes'])) {
            foreach ($shortcode_arr['validated_attributes'] as $attr_key => $attr_val) {
                if (!empty($mandatory_fields[$attr_key])) {
                    unset($mandatory_fields[$attr_key]);
                }

                $attributes_str .= ' '.$attr_key.'="'.form_str($attr_val).'"';
            }
        }

        if (!empty($mandatory_fields) && is_array($mandatory_fields)) {
            foreach ($mandatory_fields as $attr_field => $junk) {
                if (!isset($definition_arr['attributes'][$attr_field])) {
                    continue;
                }

                $attributes_str .= ' '.$attr_field.'="MANDATORY_N/A"';
            }
        }

        return str_replace(['{TAG_ATTRIBUTES}', '{TAG_VALUE}'], [$attributes_str, $content_text], $definition_arr['html']);
    }

    public function init_parsed_shortcode_for_html($node_arr)
    {
        // For nodes that are not shortcodes, just say anything is ok...
        if (empty($node_arr) || !is_array($node_arr)
         || empty($node_arr['shortcode']) || !($shortcode_arr = $node_arr['shortcode'])
         || empty($shortcode_arr) || !is_array($shortcode_arr)
         || empty($shortcode_arr['shortcode'])) {
            return true;
        }

        // Shortcode is not valid... ignore it...
        if (!($definition_arr = $this->valid_bb_shortcode($shortcode_arr['shortcode']))) {
            return false;
        }

        // No init required... everything is fine...
        if (empty($definition_arr['init_callback'])) {
            return true;
        }

        // Init function is not callable... ignore shortcode...
        if (!is_callable($definition_arr['init_callback'])) {
            return false;
        }

        if (empty($definition_arr['init_callback_params'])) {
            $definition_arr['init_callback_params'] = [];
        }

        if (empty($shortcode_arr['validated_attributes']) || !is_array($shortcode_arr['validated_attributes'])) {
            $shortcode_arr['validated_attributes'] = [];
        }

        $definition_arr['init_callback_params']['node_arr'] = $node_arr;

        return @call_user_func($definition_arr['init_callback'], $definition_arr['init_callback_params']);
    }

    public function default_bb_editor_input_attributes()
    {
        return [
            'unique_input_id' => '',
            'id'              => 'bb_editor',
            'name'            => 'bb_editor',
            'class'           => '',
            'placeholder'     => '',
            'style'           => '',
        ];
    }

    /**
     * @param string $text
     * @param false|array $params
     *
     * @return bool|string
     */
    public function bb_editor($text, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['bb_editor_attributes']) || !is_array($params['bb_editor_attributes'])) {
            $params['bb_editor_attributes'] = [];
        }

        if (empty($text)) {
            $text = '';
        }

        /** @var \phs\plugins\bbeditor\PHS_Plugin_Bbeditor $plugin_obj */
        if (!($plugin_obj = $this->get_plugin_instance())) {
            return '<div style="color:red"><small>Couldn\'t initialize BB code editor...</small></div><br/>'.$text;
        }

        if (!($bb_editor_attributes = self::validate_array($params['bb_editor_attributes'], $this->default_bb_editor_input_attributes()))) {
            $bb_editor_attributes = $this->default_bb_editor_input_attributes();
        }

        if (empty($bb_editor_attributes['unique_input_id'])) {
            $bb_editor_attributes['unique_input_id'] = str_replace('.', '', microtime(true));
            $bb_editor_attributes['id'] .= $bb_editor_attributes['unique_input_id'];
        }

        $this->bb_editor_attributes($bb_editor_attributes);

        $data = [
            'bb_editor_attributes' => $bb_editor_attributes,
            'bb_text'              => $text,
            'bb_code_obj'          => $this,
        ];

        return $plugin_obj->quick_render_template_for_buffer('bb_editor', $data);
    }

    private function default_parser_node_definition(): array
    {
        return [
            'text'      => '',
            'path'      => '',
            'shortcode' => false, // false or array with shortcode details
            'content'   => [],
        ];
    }

    /**
     * @param string $str
     * @param array $matches_arr
     * @param false|array $params
     *
     * @return array|false|mixed
     */
    private function parser_extract_node($str, $matches_arr, $params = false)
    {
        if (empty($str)
         || empty($matches_arr) || !is_array($matches_arr)) {
            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['shortcodes_stack']) || !is_array($params['shortcodes_stack'])) {
            $params['shortcodes_stack'] = [];
        }

        $stack_index = count($params['shortcodes_stack']);

        if (empty($params['matches_index'])) {
            $params['matches_index'] = 0;
        }
        if (empty($params['str_old_offset'])) {
            $params['str_old_offset'] = 0;
        }

        if (empty($matches_arr[$params['matches_index']])) {
            return false;
        }

        $return_arr = $params;
        $return_arr['matches_index']++;
        $return_arr['errors'] = [];
        $return_arr['nodes'] = [];
        $return_arr['closing_tag'] = false;

        $match_arr = $matches_arr[$params['matches_index']];

        if ($match_arr[0][1] > $return_arr['str_old_offset']) {
            $node_arr = $this->default_parser_node_definition();
            $node_arr['text'] = substr($str, $return_arr['str_old_offset'], $match_arr[0][1] - $return_arr['str_old_offset']);

            $return_arr['nodes'][] = $node_arr;
        }

        $return_arr['str_old_offset'] = $match_arr[0][1] + strlen($match_arr[0][0]);

        // invalid shortcode...
        if (!($shortcode_definition = $this->valid_bb_shortcode($match_arr[2][0]))) {
            $node_arr = $this->default_parser_node_definition();
            $node_arr['text'] = $match_arr[0][0];

            $return_arr['nodes'][] = $node_arr;

            return $return_arr;
        }

        $shortcode_arr = [];
        $shortcode_arr['shortcode'] = strtolower($match_arr[2][0]);
        if (!empty($match_arr[3][0])) {
            $shortcode_arr['shortcode_attributes'] = $this->parse_attributes(trim($match_arr[3][0]));
            $shortcode_arr['validated_attributes'] = $this->validate_shortcode_attributes($shortcode_arr['shortcode'], $shortcode_arr['shortcode_attributes']);
        } else {
            $shortcode_arr['shortcode_attributes'] = [];
            $shortcode_arr['validated_attributes'] = [];
        }

        $full_shortcode = $match_arr[0][0];
        $shortcode_offset = $match_arr[0][1];
        $closing_shortcode = ($match_arr[1][0] === '/');
        $self_closing_shortcode = ($match_arr[4][0] === '/');

        if (!empty($self_closing_shortcode)) {
            // simple shortcode
            $node_arr = $this->default_parser_node_definition();
            $node_arr['shortcode'] = $shortcode_arr;

            $return_arr['nodes'][] = $node_arr;

            return $return_arr;
        }

        if (empty($closing_shortcode)) {
            $recurring_params = $return_arr;
            $recurring_params['shortcodes_stack'][] = $shortcode_arr['shortcode'];

            $node_arr = $this->default_parser_node_definition();
            $node_arr['shortcode'] = $shortcode_arr;
            $node_arr['path'] = implode('.', $recurring_params['shortcodes_stack']);

            while (($recurring_result = $this->parser_extract_node($str, $matches_arr, $recurring_params))) {
                $node_arr['content'] = array_merge($node_arr['content'], $recurring_result['nodes']);

                $return_arr['str_old_offset'] = $recurring_result['str_old_offset'];
                $return_arr['matches_index'] = $recurring_result['matches_index'];

                if (!empty($recurring_result['closing_tag']) && $recurring_result['closing_tag'] === $shortcode_arr['shortcode']) {
                    break;
                }

                $recurring_params['shortcodes_stack'] = $recurring_result['shortcodes_stack'];
                $recurring_params['str_old_offset'] = $recurring_result['str_old_offset'];
                $recurring_params['matches_index'] = $recurring_result['matches_index'];

                if (!empty($recurring_result['errors'])) {
                    $return_arr['errors'] = array_merge($return_arr['errors'], $recurring_result['errors']);
                }

                if (empty($matches_arr[$recurring_result['matches_index']])
                 || empty($recurring_result['shortcodes_stack'])) {
                    break;
                }
            }

            $return_arr['nodes'][] = $node_arr;
        } else {
            if (!$stack_index
             || $return_arr['shortcodes_stack'][$stack_index - 1] !== $shortcode_arr['shortcode']) {
                // invalid closing tag...
                $return_arr['errors'][] = $this->_pt('Invalid closing tag [%s] at offset %s.', $shortcode_arr['shortcode'], $shortcode_offset);

                $node_arr = $this->default_parser_node_definition();
                $node_arr['text'] = $full_shortcode;

                $return_arr['nodes'][] = $node_arr;
            } else {
                $return_arr['closing_tag'] = array_pop($return_arr['shortcodes_stack']);
            }
        }

        return $return_arr;
    }

    public static function set_bb_code_registry($key, $val = null)
    {
        if (!($bb_registry_arr = self::get_data(self::BB_CODE_REGISTRY_KEY))
         || !is_array($bb_registry_arr)) {
            $bb_registry_arr = [];
        }

        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                $bb_registry_arr[$kkey] = $kval;
            }
        } else {
            if (!is_scalar($key)) {
                return false;
            }

            $bb_registry_arr[$key] = $val;
        }

        return self::set_data(self::BB_CODE_REGISTRY_KEY, $bb_registry_arr);
    }

    /**
     * @param false|array|string $key
     *
     * @return null|array|mixed
     */
    public static function get_bb_code_registry($key = false)
    {
        if (!($bb_registry_arr = self::get_data(self::BB_CODE_REGISTRY_KEY))
         || !is_array($bb_registry_arr)) {
            $bb_registry_arr = [];
        }

        if ($key === false) {
            return $bb_registry_arr;
        }

        if (is_string($key)) {
            $key_arr = explode('.', $key);
        } elseif (is_array($key)) {
            $key_arr = $key;
        } else {
            return null;
        }

        $pool_arr = $bb_registry_arr;
        $result_val = null;
        foreach ($key_arr as $key_part) {
            if (empty($key_part)
             || !is_string($key_part)) {
                continue;
            }

            if (!is_array($pool_arr)
             || !array_key_exists($key_part, $pool_arr)) {
                return null;
            }

            $result_val = $pool_arr[$key_part];
            $pool_arr = $pool_arr[$key_part];
        }

        return $result_val;
    }

    public static function get_editor_themes()
    {
        return array_keys(self::$EDITOR_THEMES);
    }

    public static function add_editor_theme($theme_name, $theme_arr)
    {
        self::st_reset_error();

        $theme_name = strtolower(trim($theme_name));
        if (empty($theme_name)
         || empty($theme_arr) || !is_array($theme_arr)) {
            self::st_set_error(self::ERR_PARAMETERS, self::st_pt('Invalid theme name or theme definition.'));

            return false;
        }

        self::$EDITOR_THEMES[$theme_name] = $theme_arr;

        return $theme_arr;
    }

    public static function default_shortcode_definition_fields()
    {
        return [
            'title'                => '',
            'attributes'           => [],
            'mandatory_attributes' => [],
            'html'                 => '', // HTML template to transform BB code to
            'template'             => '', // default text to be inserted
            'editor_button'        => '', // Custom content of button to be presented in editor for this shortcode

            'functionality_tag' => false, // This is not actually a BB code tag, but an editor functionality
            'public'            => false, // can this be presented as button in editor?
            'js_click_function' => false, // javascript function to be called when user clicks shortcode button in editor

            // Init function should return true if shortcode should be parsed... if returning empty result (false or null) shortcode will be ignored
            'init_callback'        => false, // function which should initialize the tag before rendering (if required)
            'init_callback_params' => false, // custom parameters for init_callback function - array (if required)

            'callback'        => false, // function which should "render" the tag
            'callback_params' => false, // custom parameters for callback function - array (if required)

            'editor_button_callback'        => false, // function which should "render" button in editor interface
            'editor_button_callback_params' => false, // custom parameters for editor_button_callback function - array (if required)
        ];
    }

    public static function reset_custom_shortcodes()
    {
        self::$CUSTOM_BB_CODES = [];
    }

    public static function add_shortcode($shortcode, $shortcode_arr)
    {
        $shortcode = strtolower(trim($shortcode));
        if (empty($shortcode)
         || empty($shortcode_arr) || !is_array($shortcode_arr)) {
            return false;
        }

        $shortcode_arr = self::validate_array($shortcode_arr, self::default_shortcode_definition_fields());

        if (empty($shortcode_arr['title'])) {
            $shortcode_arr['title'] = 'Custom #'.count(self::$CUSTOM_BB_CODES);
        }

        if (!is_array($shortcode_arr['attributes'])) {
            $shortcode_arr['attributes'] = [];
        }

        self::$CUSTOM_BB_CODES[$shortcode] = $shortcode_arr;

        return $shortcode_arr;
    }
}
