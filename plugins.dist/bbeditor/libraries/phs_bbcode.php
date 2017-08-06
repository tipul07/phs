<?php

namespace phs\plugins\bbeditor\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Library;
use \phs\plugins\s2p_companies\models\PHS_Model_Companies;

class Bbcode extends PHS_Library
{
    const ERR_BB_PARSE = 1;

    const THEME_CONTROL_SEP = '{separator}';

    const BB_CODE_REGISTRY_KEY = 'bb_code_registry';

    private static $BB_CODES = array();

    private static $CUSTOM_BB_CODES = array();

    private static $BB_CALLBACKS_ARR = array();

    private static $EDITOR_THEMES = array(
        'default' => array(
            'b', 'i', 'u', self::THEME_CONTROL_SEP,
            'p', self::THEME_CONTROL_SEP,
            'olist', 'ulist', self::THEME_CONTROL_SEP,
            'table', 'img', 'link', self::THEME_CONTROL_SEP,
            'do_preview',
        )
    );

    private $_editor_attributes = array();

    private $_theme = 'default';

    public function __construct( $error_no = self::ERR_OK, $error_msg = '', $error_debug_msg = '', $static_instance = false )
    {
        parent::__construct( $error_no, $error_msg, $error_debug_msg, $static_instance );

        array(
            'b' => array(
                'title' => 'Bold',
                'attributes' => array(),
                'html' => '<b>{TAG_VALUE}</b>',
                'public' => true,
                'template' => '[b]{CONTENT}[/b]',
                'editor_button' => '<i class="fa fa-bold" aria-hidden="true"></i>',
            ),
            'i' => array(
                'title' => 'Italic',
                'attributes' => array(),
                'html' => '<i>{TAG_VALUE}</i>',
                'public' => true,
                'template' => '[i]{CONTENT}[/i]',
                'editor_button' => '<i class="fa fa-italic" aria-hidden="true"></i>',
            ),
            'u' => array(
                'title' => 'Underline',
                'attributes' => array(),
                'html' => '<u>{TAG_VALUE}</u>',
                'public' => true,
                'template' => '[u]{CONTENT}[/u]',
                'editor_button' => '<i class="fa fa-underline" aria-hidden="true"></i>',
            ),
            'p' => array(
                'title' => 'Paragraph',
                'attributes' => array( 'align' => '', ),
                'html' => '<p{TAG_ATTRIBUTES}>{TAG_VALUE}</p>',
                'public' => true,
                'template' => '[p]{CONTENT}[/p]',
                'editor_button' => '<i class="fa fa-paragraph" aria-hidden="true"></i>',
            ),
            'link' => array(
                'title' => 'Link',
                'attributes' => array( 'href' => '', 'name' => '', 'title' => '', 'target' => '' ),
                'mandatory_attributes' => array( 'href' ),
                'html' => '<a{TAG_ATTRIBUTES}>{TAG_VALUE}</a>',
                'public' => true,
                'template' => '[link href="" title=""]{CONTENT}[/link]',
                'editor_button' => '<i class="fa fa-link" aria-hidden="true"></i>',
            ),
            'img' => array(
                'title' => 'Image',
                'attributes' => array( 'src' => '', 'title' => '', 'width' => '', 'height' => '', ),
                'mandatory_attributes' => array( 'src' ),
                'html' => '<img{TAG_ATTRIBUTES} />',
                'public' => true,
                'template' => '[img src="" title="" width="" height=""]{CONTENT}[/img]',
                'editor_button' => '<i class="fa fa-picture-o" aria-hidden="true"></i>',
            ),
            'olist' => array(
                'title' => 'Ordered list',
                'attributes' => array(),
                'html' => '<ol>{TAG_VALUE}</ol>',
                'public' => true,
                'template' => '[olist]'."\n".
                    '[li]Item 1[/li]'."\n".
                    '[li]Item 2[/li]'."\n".
                    '[/olist]',
                'editor_button' => '<i class="fa fa-list-ol" aria-hidden="true"></i>',
            ),
            'ulist' => array(
                'title' => 'Unordered list',
                'attributes' => array(),
                'html' => '<ul>{TAG_VALUE}</ul>',
                'public' => true,
                'template' => '[ulist]'."\n".
                    '[li]Item 1[/li]'."\n".
                    '[li]Item 2[/li]'."\n".
                    '[/ulist]',
                'editor_button' => '<i class="fa fa-list-ul" aria-hidden="true"></i>',
            ),
            'li' => array(
                'title' => 'List item',
                'attributes' => array(),
                'html' => '<li>{TAG_VALUE}</li>',
                'public' => false,
                'template' => '[li]Item[/li]',
            ),
            'table' => array(
                'title' => 'Table',
                'attributes' => array( 'width' => '', 'border' => 0, 'cellspacing' => 0, 'cellpadding' => 0 ),
                'html' => '<table{TAG_ATTRIBUTES}>{TAG_VALUE}</table>',
                'public' => true,
                'template' => '[table]'."\n".
                    '[thead][tr][th align="center"]Column 1[/th][th align="center"]Column 2[/th][/tr][/thead]'."\n".
                    '[tbody]'."\n".
                    '[tr][td]Item 1,1[/td][td]Item 2,1[/td][/tr]'."\n".
                    '[tr][td]Item 1,2[/td][td]Item 2,2[/td][/tr]'."\n".
                    '[/tbody]'."\n".
                    '[/table]',
                'editor_button' => '<i class="fa fa-table" aria-hidden="true"></i>',
            ),
            'thead' => array(
                'title' => 'Table header',
                'attributes' => array(),
                'html' => '<thead{TAG_ATTRIBUTES}>{TAG_VALUE}</thead>',
                'public' => false,
                'template' => '[thead][/thead]',
            ),
            'tbody' => array(
                'title' => 'Table body',
                'attributes' => array(),
                'html' => '<tbody{TAG_ATTRIBUTES}>{TAG_VALUE}</tbody>',
                'public' => false,
                'template' => '[tbody][/tbody]',
            ),
            'tr' => array(
                'title' => 'Table row',
                'attributes' => array(),
                'html' => '<tr{TAG_ATTRIBUTES}>{TAG_VALUE}</tr>',
                'public' => false,
                'template' => '[tr][/tr]',
            ),
            'th' => array(
                'title' => 'Header cell',
                'attributes' => array( 'width' => '', 'padding' => 0, 'align' => '', ),
                'html' => '<th{TAG_ATTRIBUTES}>{TAG_VALUE}</th>',
                'public' => false,
                'template' => '[th align="center"]Item[/th]',
            ),
            'td' => array(
                'title' => 'Table cell',
                'attributes' => array( 'width' => '', 'padding' => 0, 'align' => '', 'bgcolor' => '', 'color' => '' ),
                'html' => '<td{TAG_ATTRIBUTES}>{TAG_VALUE}</td>',
                'public' => false,
                'template' => '[td align="center"]Item[/td]',
            ),
            'registry' => array(
                'title' => 'Registry value',
                'attributes' => array( 'key' => '', 'default' => '', 'prefix' => '', 'suffix' => '', 'empty' => false ),
                'callback' => array( $this, 'bb_registry_render' ),
                'public' => true,
                'template' => '[registry key="" /]',
                'editor_button' => '<i class="fa fa-bookmark" aria-hidden="true"></i>',
            ),
            'callback' => array(
                'title' => 'Callback function',
                'attributes' => array( 'func' => '', 'lang' => '' ),
                'callback' => array( $this, 'bb_callback_render' ),
                'public' => true,
                'template' => '[callback func=""][/callback]',
                'editor_button' => '<i class="fa fa-code" aria-hidden="true"></i>',
            ),
            'do_preview' => array(
                'title' => 'Preview document',
                'attributes' => array(),
                'public' => true,
                'functionality_tag' => true,
                'editor_button' => '<i class="fa fa-eye" aria-hidden="true"></i>',
                'js_click_function' => 'phs_bb_editor.do_preview( \'{ATTRS.ID}\' )',
            ),
        );
    }

    public function bb_registry_render( $params_arr )
    {
        // Not a valid node... let bb parser decide what to do here...
        if( empty( $params_arr ) or !is_array( $params_arr )
            or empty( $params_arr['node_arr'] )
            or !($node_arr = $params_arr['node_arr']) or !is_array( $node_arr ) )
            return '';

        $registry_key = false;
        $default_value = false;
        $prefix_value = '';
        $suffix_value = '';
        $empty_value = false;
        if( !empty( $node_arr['shortcode'] ) and is_array( $node_arr['shortcode'] )
            and !empty( $node_arr['shortcode']['validated_attributes'] ) and is_array( $node_arr['shortcode']['validated_attributes'] ) )
        {
            if( !empty( $node_arr['shortcode']['validated_attributes']['key'] ) )
                $registry_key = $node_arr['shortcode']['validated_attributes']['key'];

            if( isset( $node_arr['shortcode']['validated_attributes']['default'] ) )
                $default_value = $node_arr['shortcode']['validated_attributes']['default'];

            if( isset( $node_arr['shortcode']['validated_attributes']['prefix'] ) )
                $prefix_value = $node_arr['shortcode']['validated_attributes']['prefix'];

            if( isset( $node_arr['shortcode']['validated_attributes']['suffix'] ) )
                $suffix_value = $node_arr['shortcode']['validated_attributes']['suffix'];

            if( isset( $node_arr['shortcode']['validated_attributes']['empty'] )
                and is_string( $node_arr['shortcode']['validated_attributes']['empty'] ) )
                $empty_value = $node_arr['shortcode']['validated_attributes']['empty'];
        }

        if( empty( $registry_key ) )
            return '[Invalid registry key]';

        if( ($registry_val = self::get_bb_code_registry( $registry_key )) === null
            or !is_string( $registry_val ) )
        {
            if( $default_value === false )
                return '[Registry Key "' . $registry_key . '" not defined or is not string.]';

            return $default_value;
        }

        if( $registry_val === ''
            and $empty_value !== false )
            return $empty_value;

        return $prefix_value.$registry_val.$suffix_value;
    }

    public function bb_callback_render( $params_arr )
    {
        // Not a valid node... let bb parser decide what to do here...
        if( empty( $params_arr ) or !is_array( $params_arr )
         or empty( $params_arr['node_arr'] )
         or !($node_arr = $params_arr['node_arr']) or !is_array( $node_arr ) )
            return '';

        $rendered_buf = '';
        if( !empty( $node_arr['shortcode'] ) and is_array( $node_arr['shortcode'] )
        and !empty( $node_arr['shortcode']['validated_attributes'] ) and is_array( $node_arr['shortcode']['validated_attributes'] )
        and !empty( $node_arr['shortcode']['validated_attributes']['func'] ) )
        {
            // make sure we have a language defined as attribute...
            if( empty( $node_arr['shortcode']['validated_attributes']['lang'] )
             or !self::valid_language( $node_arr['shortcode']['validated_attributes']['lang'] ) )
                $node_arr['shortcode']['validated_attributes']['lang'] = self::get_default_language();

            if( !($func_name = $this->function_name_check( $node_arr['shortcode']['validated_attributes']['func'] ))
             or !($func_definition = $this->get_callback_function( $func_name ))
             or empty( $func_definition['callable'] ) )
                return '[UNKNOWN FUNCTION '.$func_name.']';

            if( empty( $func_definition['default_parameters'] ) )
                $func_definition['default_parameters'] = array();

            // Callable will set the error in bbeditor library...
            $this->reset_error();
            $callable = $func_definition['callable'];
            if( !($rendered_buf = $callable( $func_definition['default_parameters'], $this )) )
            {
                if( !$this->has_error() )
                    $rendered_buf = '';
                else
                    $rendered_buf = '[ERROR running function '.$func_name.': '.$this->get_error_message().']';
            }
        }

        return $rendered_buf;
    }

    public static function set_bb_code_registry( $key, $val = null )
    {
        if( !($bb_registry_arr = self::get_data( self::BB_CODE_REGISTRY_KEY ))
         or !is_array( $bb_registry_arr ) )
            $bb_registry_arr = array();

        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                $bb_registry_arr[$kkey] = $kval;
            }
        } else
        {
            if( !is_scalar( $key ) )
                return false;

            $bb_registry_arr[$key] = $val;
        }

        return self::set_data( self::BB_CODE_REGISTRY_KEY, $bb_registry_arr );
    }

    public static function get_bb_code_registry( $key = false )
    {
        if( !($bb_registry_arr = self::get_data( self::BB_CODE_REGISTRY_KEY ))
         or !is_array( $bb_registry_arr ) )
            $bb_registry_arr = array();

        if( $key === false )
            return $bb_registry_arr;

        if( is_string( $key ) )
            $key_arr = explode( '.', $key );
        elseif( is_array( $key ) )
            $key_arr = $key;
        else
            return null;

        $pool_arr = $bb_registry_arr;
        $result_val = null;
        foreach( $key_arr as $key_part )
        {
            if( empty( $key_part )
             or !is_string( $key_part ) )
                continue;

            if( !is_array( $pool_arr )
             or !array_key_exists( $key_part, $pool_arr ) )
                return null;

            $result_val = $pool_arr[$key_part];
            $pool_arr = $pool_arr[$key_part];
        }

        return $result_val;
    }

    public function bb_editor_attributes( $attr = false )
    {
        if( $attr === false )
            return $this->_editor_attributes;

        if( !is_array( $attr ) )
            return false;

        if( !($this->_editor_attributes = self::validate_array( $attr, $this->default_bb_editor_input_attributes() )) )
            $this->_editor_attributes = $this->default_bb_editor_input_attributes();

        return $this->_editor_attributes;
    }
    
    public function bb_editor_replace_vars( $str )
    {
        // replace attributes...
        if( ($attrs = $this->bb_editor_attributes())
        and is_array( $attrs ) )
        {
            foreach( $attrs as $key => $val )
            {
                if( !is_scalar( $val ) )
                    continue;

                $str = str_replace( '{ATTRS.'.strtoupper( $key ).'}', $val, $str );
            }
        }

        return $str;
    }

    public function get_shortcodes_definition()
    {
        // We don't cache results as custom shortcodes might be added
        return self::merge_array_assoc( self::$BB_CODES, self::$CUSTOM_BB_CODES );
    }

    public function get_bb_shortcodes_definition()
    {
        if( !($shortcodes_definition = $this->get_shortcodes_definition()) )
            return array();

        // We don't cache results as custom shortcodes might be added
        $return_arr = array();
        foreach( $shortcodes_definition as $shortcode => $shortcode_arr )
        {
            if( !empty( $shortcode_arr['functionality_tag'] ) )
                continue;

            $return_arr[$shortcode] = $shortcode_arr;
        }

        return $return_arr;
    }

    public function default_function_definition_params()
    {
        return array(
            'callable' => false,
            'default_parameters' => array(),
        );
    }

    public function function_name_check( $func_name )
    {
        return strtolower( trim( $func_name ) );
    }

    public function get_callback_function( $func_name )
    {
        $func_name = $this->function_name_check( $func_name );
        if( !empty( self::$BB_CALLBACKS_ARR[$func_name] ) )
            return self::$BB_CALLBACKS_ARR[$func_name];

        return false;
    }

    public function define_callback_function( $func_name, $func_definition = false )
    {
        $this->reset_error();

        if( empty( $func_name ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide function name.' ) );
            return false;
        }

        if( !empty( self::$BB_CALLBACKS_ARR[$func_name] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Function %s already defined.', $func_name ) );
            return false;
        }

        $default_function_params = $this->default_function_definition_params();
        if( empty( $func_definition ) or !is_array( $func_definition ) )
            $func_definition = $default_function_params;
        else
            $func_definition = self::validate_array( $func_definition, $default_function_params );

        if( empty( $func_definition['callable'] ) or !@is_callable( $func_definition['callable'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a callable for function %s.', $func_name ) );
            return false;
        }

        $func_name = $this->function_name_check( $func_name );
        self::$BB_CALLBACKS_ARR[$func_name] = $func_definition;

        return true;
    }

    public static function get_editor_themes()
    {
        return array_keys( self::$EDITOR_THEMES );
    }

    public function editor_theme( $theme = false )
    {
        if( $theme === false )
            return $this->_theme;

        $theme = strtolower( trim( $theme ) );
        if( empty( self::$EDITOR_THEMES[$theme] ) )
            return false;

        $this->_theme = $theme;

        return self::$EDITOR_THEMES[$theme];
    }

    public function get_current_theme()
    {
        if( empty( self::$EDITOR_THEMES[$this->_theme] ) )
            return false;

        return self::$EDITOR_THEMES[$this->_theme];
    }

    public static function add_editor_theme( $theme_name, $theme_arr )
    {
        self::st_reset_error();

        $theme_name = strtolower( trim( $theme_name ) );
        if( empty( $theme_name )
         or empty( $theme_arr ) or !is_array( $theme_arr ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::st_pt( 'Invalid theme name or theme definition.' ) );
            return false;
        }

        self::$EDITOR_THEMES[$theme_name] = $theme_arr;

        return $theme_arr;
    }

    public function get_shortcodes()
    {
        if( !($bb_codes_arr = $this->get_shortcodes_definition())
         or !is_array( $bb_codes_arr ) )
            return array();

        return array_keys( $bb_codes_arr );
    }

    public function valid_shortcode( $code )
    {
        $all_codes = $this->get_shortcodes_definition();
        $code = strtolower( trim( $code ) );
        if( empty( $all_codes[$code] ) )
            return false;

        return $all_codes[$code];
    }

    public function get_bb_shortcodes()
    {
        if( !($bb_codes_arr = $this->get_bb_shortcodes_definition())
         or !is_array( $bb_codes_arr ) )
            return array();

        return array_keys( $bb_codes_arr );
    }

    public function valid_bb_shortcode( $code )
    {
        $all_codes = $this->get_bb_shortcodes_definition();
        $code = strtolower( trim( $code ) );
        if( empty( $all_codes[$code] ) )
            return false;

        return $all_codes[$code];
    }

    public function validate_shortcode_attributes( $shortcode, $attr_arr, $params = false )
    {
        if( empty( $attr_arr ) or !is_array( $attr_arr )
         or !($shortcode_arr = $this->valid_shortcode( $shortcode ))
         or empty( $shortcode_arr['attributes'] ) or !is_array( $shortcode_arr['attributes'] ) )
            return array();

        $attributes_arr = array();
        foreach( $shortcode_arr['attributes'] as $attr_key => $attr_def_val )
        {
            if( array_key_exists( $attr_key, $attr_arr ) )
                $attributes_arr[$attr_key] = $attr_arr[$attr_key];
        }

        return $attributes_arr;
    }

    public static function default_shortcode_definition_fields()
    {
        return array(
            'title' => '',
            'attributes' => array(),
            'mandatory_attributes' => array(),
            'html' => '', // HTML template to transform BB code to
            'template' => '', // default text to be inserted
            'editor_button' => '', // Custom content of button to be presented in editor for this shortcode

            'functionality_tag' => false, // This is not actually a BB code tag, but an editor functionality
            'public' => false, // can this be presented as button in editor?
            'js_click_function' => false, // javascript function to be called when user clicks shortcode button in editor

            // Init function should return true if shortcode should be parsed... if returning empty result (false or null) shortcode will be ignored
            'init_callback' => false, // function which should initialize the tag before rendering (if required)
            'init_callback_params' => false, // custom parameters for init_callback function - array (if required)

            'callback' => false, // function which should "render" the tag
            'callback_params' => false, // custom parameters for callback function - array (if required)

            'editor_button_callback' => false, // function which should "render" button in editor interface
            'editor_button_callback_params' => false, // custom parameters for editor_button_callback function - array (if required)
        );
    }

    public static function reset_custom_shortcodes()
    {
        self::$CUSTOM_BB_CODES = array();
    }

    public static function add_shortcode( $shortcode, $shortcode_arr )
    {
        $shortcode = strtolower( trim( $shortcode ) );
        if( empty( $shortcode )
         or empty( $shortcode_arr ) or !is_array( $shortcode_arr ) )
            return false;

        $shortcode_arr = self::validate_array( $shortcode_arr, self::default_shortcode_definition_fields() );

        if( empty( $shortcode_arr['title'] ) )
            $shortcode_arr['title'] = 'Custom #'.count( self::$CUSTOM_BB_CODES );

        if( !is_array( $shortcode_arr['attributes'] ) )
            $shortcode_arr['attributes'] = array();

        self::$CUSTOM_BB_CODES[$shortcode] = $shortcode_arr;

        return $shortcode_arr;
    }

    public function get_shortcode_regex( $tagnames = false )
    {
        if( empty( $tagnames ) or !is_array( $tagnames ) )
            $tagnames = $this->get_bb_shortcodes();

        $tagregexp = join( '|', array_map( 'preg_quote', $tagnames ) );

        return '/\[(|\/)('.$tagregexp.')(|\s+[^\[\]]*)(|\/)\]/miU';
    }

    public function parse_attributes( $text )
    {
        if( empty( $text ) )
            return array();

        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $atts = array();
        $text = preg_replace( "/[\x{00a0}\x{200b}]+/u", " ", $text );
        
        if( preg_match_all( $pattern, $text, $match, PREG_SET_ORDER ) )
        {
            foreach( $match as $m )
            {
                if( !empty( $m[1] ) )
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                elseif( !empty( $m[3] ) )
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                elseif( !empty( $m[5] ) )
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                elseif( isset( $m[7] ) and $m[7] != '' )
                    $atts[] = stripcslashes( $m[7] );
                elseif( isset( $m[8] ) )
                    $atts[] = stripcslashes( $m[8] );
            }
        }

        return $atts;
    }

    private function default_parser_node_definition()
    {
        return array(
            'text' => '',
            'path' => '',
            'shortcode' => false, // false or array with shortcode details
            'content' => array(),
        );
    }

    private function parser_extract_node( $str, $matches_arr, $params = false )
    {
        if( empty( $str )
         or empty( $matches_arr ) or !is_array( $matches_arr ) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['shortcodes_stack'] ) or !is_array( $params['shortcodes_stack'] ) )
            $params['shortcodes_stack'] = array();

        $stack_index = count( $params['shortcodes_stack'] );

        if( empty( $params['matches_index'] ) )
            $params['matches_index'] = 0;
        if( empty( $params['str_old_offset'] ) )
            $params['str_old_offset'] = 0;

        if( empty( $matches_arr[$params['matches_index']] ) )
            return false;

        $return_arr = $params;
        $return_arr['matches_index']++;
        $return_arr['errors'] = array();
        $return_arr['nodes'] = array();
        $return_arr['closing_tag'] = false;

        $match_arr = $matches_arr[$params['matches_index']];

        if( $match_arr[0][1] > $return_arr['str_old_offset'] )
        {
            $node_arr = $this->default_parser_node_definition();
            $node_arr['text'] = substr( $str, $return_arr['str_old_offset'], $match_arr[0][1] - $return_arr['str_old_offset'] );

            $return_arr['nodes'][] = $node_arr;
        }

        $return_arr['str_old_offset'] = $match_arr[0][1] + strlen( $match_arr[0][0] );

        // invalid shortcode...
        if( !($shortcode_definition = $this->valid_bb_shortcode( $match_arr[2][0] )) )
        {
            $node_arr = $this->default_parser_node_definition();
            $node_arr['text'] = $match_arr[0][0];

            $return_arr['nodes'][] = $node_arr;

            return $return_arr;
        }

        $shortcode_arr = array();
        $shortcode_arr['shortcode'] = strtolower( $match_arr[2][0] );
        if( !empty( $match_arr[3][0] ) )
        {
            $shortcode_arr['shortcode_attributes'] = $this->parse_attributes( trim( $match_arr[3][0] ) );
            $shortcode_arr['validated_attributes'] = $this->validate_shortcode_attributes( $shortcode_arr['shortcode'], $shortcode_arr['shortcode_attributes'] );
        } else
        {
            $shortcode_arr['shortcode_attributes'] = array();
            $shortcode_arr['validated_attributes'] = array();
        }

        $full_shortcode = $match_arr[0][0];
        $shortcode_offset = $match_arr[0][1];
        $closing_shortcode = ($match_arr[1][0] == '/');
        $self_closing_shortcode = ($match_arr[4][0] == '/');

        if( !empty( $self_closing_shortcode ) )
        {
            // simple shortcode
            $node_arr = $this->default_parser_node_definition();
            $node_arr['shortcode'] = $shortcode_arr;

            $return_arr['nodes'][] = $node_arr;

            return $return_arr;
        }

        if( empty( $closing_shortcode ) )
        {
            $recurring_params = $return_arr;
            $recurring_params['shortcodes_stack'][] = $shortcode_arr['shortcode'];

            $node_arr = $this->default_parser_node_definition();
            $node_arr['shortcode'] = $shortcode_arr;
            $node_arr['path'] = implode( '.', $recurring_params['shortcodes_stack'] );

            while( ($recurring_result = $this->parser_extract_node( $str, $matches_arr, $recurring_params )) )
            {
                $node_arr['content'] = array_merge( $node_arr['content'], $recurring_result['nodes'] );

                $return_arr['str_old_offset'] = $recurring_result['str_old_offset'];
                $return_arr['matches_index'] = $recurring_result['matches_index'];

                if( !empty( $recurring_result['closing_tag'] ) and $recurring_result['closing_tag'] == $shortcode_arr['shortcode'] )
                    break;

                $recurring_params['shortcodes_stack'] = $recurring_result['shortcodes_stack'];
                $recurring_params['str_old_offset'] = $recurring_result['str_old_offset'];
                $recurring_params['matches_index'] = $recurring_result['matches_index'];

                if( !empty( $recurring_result['errors'] ) )
                    $return_arr['errors'] = array_merge( $return_arr['errors'], $recurring_result['errors'] );

                if( empty( $matches_arr[$recurring_result['matches_index']] )
                 or empty( $recurring_result['shortcodes_stack'] ) )
                    break;
            }

            $return_arr['nodes'][] = $node_arr;
        } else
        {
            if( !$stack_index
             or $return_arr['shortcodes_stack'][$stack_index-1] != $shortcode_arr['shortcode'] )
            {
                // invalid closing tag...
                $return_arr['errors'][] = $this->_pt( 'Invalid closing tag [%s] at offset %s.', $shortcode_arr['shortcode'], $shortcode_offset );

                $node_arr = $this->default_parser_node_definition();
                $node_arr['text'] = $full_shortcode;

                $return_arr['nodes'][] = $node_arr;
            } else
            {
                $return_arr['closing_tag'] = array_pop( $return_arr['shortcodes_stack'] );
            }
        }

        return $return_arr;
    }

    public function bb_to_array( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['allow_html_code'] ) )
            $params['allow_html_code'] = false;

        $pattern = $this->get_shortcode_regex();
        if( empty( $params['allow_html_code'] ) )
            $str = strip_tags( $str );

        $result_arr = array();
        $errors_arr = array();
        $str_len = strlen( $str );
        if( preg_match_all( $pattern, $str, $matches_arr, PREG_SET_ORDER|PREG_OFFSET_CAPTURE )
        and !empty( $matches_arr ) and is_array( $matches_arr )
        and ($matches_count = count( $matches_arr )) )
        {
            $parse_params = array();
            $parse_params['matches_index'] = 0;
            $old_offset = 0;
            while( $parse_params['matches_index'] < $matches_count )
            {
                if( !($parse_params = $this->parser_extract_node( $str, $matches_arr, $parse_params ))
                 or !is_array( $parse_params ) )
                    break;

                $old_offset = $parse_params['str_old_offset'];

                if( !empty( $parse_params['errors'] ) )
                    $errors_arr = array_merge( $errors_arr, $parse_params['errors'] );
                if( !empty( $parse_params['nodes'] ) )
                    $result_arr = array_merge( $result_arr, $parse_params['nodes'] );
            }

            if( $old_offset < $str_len )
            {
                $node_arr = $this->default_parser_node_definition();
                $node_arr['text'] = substr( $str, $old_offset );

                $result_arr[] = $node_arr;
            }
        }

        return array(
            'errors' => $errors_arr,
            'result' => $result_arr,
        );
    }

    public function remove_bb_code( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['allow_html_code'] ) )
            $params['allow_html_code'] = false;
        // Internally set...
        if( empty( $params['recurring_level'] ) )
            $params['recurring_level'] = 0;

        if( empty( $params['recurring_level'] )
        and is_string( $str ) )
        {
            if( !strstr( $str, '[' )
             or !($array_result = $this->bb_to_array( $str, array( 'allow_html_code' => (!empty( $params['allow_html_code'] )) ) ))
             or !is_array( $array_result['result'] ) )
                return $str;

            $parts_arr = $array_result['result'];
        }

        if( !empty( $params['recurring_level'] )
        and is_array( $str ) )
            $parts_arr = $str;

        if( empty( $parts_arr ) or !is_array( $parts_arr ) )
        {
            if( is_string( $str ) )
                return $str;

            else
                return '';
        }

        $clean_result = '';
        foreach( $parts_arr as $node_arr )
        {
            $node_str = '';

            if( !empty( $node_arr['text'] ) )
                $node_str .= $node_arr['text'];

            if( !empty( $node_arr['content'] ) and is_array( $node_arr['content'] ) )
            {
                $recurring_params = $params;
                $recurring_params['recurring_level']++;

                $node_str .= $this->remove_bb_code( $node_arr['content'], $recurring_params );
            }

            $clean_result .= $node_str;
        }

        return $clean_result;
    }

    public function bb_to_html( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['allow_html_code'] ) )
            $params['allow_html_code'] = false;
        // Internally set...
        if( empty( $params['recurring_level'] ) )
            $params['recurring_level'] = 0;

        if( empty( $params['recurring_level'] )
        and is_string( $str ) )
        {
            if( !strstr( $str, '[' )
             or !($array_result = $this->bb_to_array( $str, array( 'allow_html_code' => (!empty( $params['allow_html_code'] )) ) ))
             or !is_array( $array_result['result'] ) )
                return $str;

            $parts_arr = $array_result['result'];
        }

        if( !empty( $params['recurring_level'] )
        and is_array( $str ) )
            $parts_arr = $str;

        if( empty( $parts_arr ) or !is_array( $parts_arr ) )
        {
            if( is_string( $str ) )
                return $str;

            else
                return '';
        }

        $html_result = '';
        foreach( $parts_arr as $node_arr )
        {
            if( empty( $node_arr ) or !is_array( $node_arr ) )
                continue;

            $node_str = '';

            if( !empty( $node_arr['shortcode'] ) )
            {
                if( !$this->init_parsed_shortcode_for_html( $node_arr ) )
                    continue;
            }

            if( !empty( $node_arr['text'] ) )
                $node_str .= $node_arr['text'];

            if( !empty( $node_arr['content'] ) and is_array( $node_arr['content'] ) )
            {
                $recurring_params = $params;
                $recurring_params['recurring_level']++;

                $node_str .= $this->bb_to_html( $node_arr['content'], $recurring_params );
            }

            if( !empty( $node_arr['shortcode'] )
            and ($new_node_str = $this->render_parsed_shortcode_to_html( $node_str, $node_arr )) )
                $node_str = $new_node_str;

            $html_result .= $node_str;
        }

        return $html_result;
    }

    public function render_parsed_shortcode_to_html( $content_text, $node_arr )
    {
        if( empty( $node_arr ) or !is_array( $node_arr )
         or empty( $node_arr['shortcode'] ) or !($shortcode_arr = $node_arr['shortcode'])
         or empty( $shortcode_arr ) or !is_array( $shortcode_arr )
         or empty( $shortcode_arr['shortcode'] )
         or !($definition_arr = $this->valid_bb_shortcode( $shortcode_arr['shortcode'] )) )
            return '';

        if( !empty( $definition_arr['callback'] ) )
        {
            if( !is_callable( $definition_arr['callback'] ) )
                return 'ERROR! [Shortcode:'.$shortcode_arr['shortcode'].' invalid callback.]';

            if( empty( $definition_arr['callback_params'] ) )
                $definition_arr['callback_params'] = array();

            if( empty( $shortcode_arr['validated_attributes'] ) or !is_array( $shortcode_arr['validated_attributes'] ) )
                $shortcode_arr['validated_attributes'] = array();

            $definition_arr['callback_params']['node_arr'] = $node_arr;
            $definition_arr['callback_params']['content_text'] = $content_text;

            if( ($html_code = @call_user_func( $definition_arr['callback'], $definition_arr['callback_params'] )) === null
             or !is_string( $html_code ) )
                $html_code = '';

            return $html_code;
        }

        if( empty( $definition_arr['html'] ) )
            return '';

        // render attibutes...
        $mandatory_fields = array();
        if( !empty( $definition_arr['mandatory_attributes'] ) and is_array( $definition_arr['mandatory_attributes'] ) )
        {
            foreach( $definition_arr['mandatory_attributes'] as $field )
                $mandatory_fields[$field] = true;
        }

        $attributes_str = '';
        if( !empty( $shortcode_arr['validated_attributes'] ) and is_array( $shortcode_arr['validated_attributes'] ) )
        {
            foreach( $shortcode_arr['validated_attributes'] as $attr_key => $attr_val )
            {
                if( !empty( $mandatory_fields[$attr_key] ) )
                    unset( $mandatory_fields[$attr_key] );

                $attributes_str .= ' '.$attr_key.'="'.form_str( $attr_val ).'"';
            }
        }

        if( !empty( $mandatory_fields ) and is_array( $mandatory_fields ) )
        {
            foreach( $mandatory_fields as $attr_field => $junk )
            {
                if( !isset( $definition_arr['attributes'][$attr_field] ) )
                    continue;

                $attributes_str .= ' '.$attr_field.'="MANDATORY_N/A"';
            }
        }

        return str_replace( array( '{TAG_ATTRIBUTES}', '{TAG_VALUE}' ), array( $attributes_str, $content_text ), $definition_arr['html'] );
    }

    public function init_parsed_shortcode_for_html( $node_arr )
    {
        // For nodes that are not shortcodes, just say anything is ok...
        if( empty( $node_arr ) or !is_array( $node_arr )
         or empty( $node_arr['shortcode'] ) or !($shortcode_arr = $node_arr['shortcode'])
         or empty( $shortcode_arr ) or !is_array( $shortcode_arr )
         or empty( $shortcode_arr['shortcode'] ) )
            return true;

        // Shortcode is not valid... ignore it...
        if( !($definition_arr = $this->valid_bb_shortcode( $shortcode_arr['shortcode'] )) )
            return false;

        // No init required... everything is fine...
        if( empty( $definition_arr['init_callback'] ) )
            return true;

        // Init function is not callable... ignore shortcode...
        if( !is_callable( $definition_arr['init_callback'] ) )
            return false;

        if( empty( $definition_arr['init_callback_params'] ) )
            $definition_arr['init_callback_params'] = array();

        if( empty( $shortcode_arr['validated_attributes'] ) or !is_array( $shortcode_arr['validated_attributes'] ) )
            $shortcode_arr['validated_attributes'] = array();

        $definition_arr['init_callback_params']['node_arr'] = $node_arr;

        return @call_user_func( $definition_arr['init_callback'], $definition_arr['init_callback_params'] );
    }

    public function default_bb_editor_input_attributes()
    {
        return array(
            'unique_input_id' => '',
            'id' => 'bb_editor',
            'name' => 'bb_editor',
            'class' => '',
            'placeholder' => '',
            'style' => '',
        );
    }
    
    public function bb_editor( $text, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['bb_editor_attributes'] ) or !is_array( $params['bb_editor_attributes'] ) )
            $params['bb_editor_attributes'] = array();
        
        if( empty( $text ) )
            $text = '';

        /** @var \phs\plugins\bbeditor\PHS_Plugin_Bbeditor $plugin_obj */
        if( !($plugin_obj = $this->get_plugin_instance()) )
            return '<div style="color:red"><small>Couldn\'t initialize BB code editor...</small></div><br/>'.$text;

        if( !($bb_editor_attributes = self::validate_array( $params['bb_editor_attributes'], $this->default_bb_editor_input_attributes() )) )
            $bb_editor_attributes = $this->default_bb_editor_input_attributes();

        if( empty( $bb_editor_attributes['unique_input_id'] ) )
        {
            $bb_editor_attributes['unique_input_id'] = str_replace( '.', '', microtime( true ) );
            $bb_editor_attributes['id'] .= $bb_editor_attributes['unique_input_id'];
        }

        $this->bb_editor_attributes( $bb_editor_attributes );

        $data = array(
            'bb_editor_attributes' => $bb_editor_attributes,
            'bb_text' => $text,
            'bb_code_obj' => $this,
        );

        return $plugin_obj->quick_render_template_for_buffer( 'bb_editor', $data );
    }
}
