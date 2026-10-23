<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Scope;

abstract class PHS_Action_Autocomplete extends PHS_Action
{
    private array $autocomplete_params = [
        // Data to be used when we have a single record to be displayed (e.g. when user selected one record)
        'data' => [],

        // This is where autocomplete will make AJAX call (e.g. array( 'p' => 'plugin', 'c' => 'controller', 'a' => 'action' )
        'route_arr' => [],
        // An array with parameters to be sent to autocomplete action along with autocomplete predefined parameters (if required)
        'route_params_arr' => [],

        'id_id'     => 'phs_autocomplete_id_id',
        'id_name'   => 'phs_autocomplete_id_name',
        'text_id'   => 'phs_autocomplete_text_id',
        'text_name' => 'phs_autocomplete_text_name',

        'onclick_attribute' => 'onclick',
        'onfocus_attribute' => 'onfocus',

        'include_js_script_tags' => true,
        'include_js_on_ready'    => true,
        'lock_on_init'           => true,
        'show_loading_animation' => true,

        // styling
        'text_css_classes' => '',
        'text_css_style'   => '',
        // Class that should be used while autocomplete Ajax call is running
        'loading_animation_class' => '',

        'id_value' => 0,
        // Default value will say what value should lock the input when initializing autocomplete input
        'default_value'    => 0,
        'text_value'       => '',
        'text_placeholder' => '',

        'min_text_length' => 1,
        'allow_view_all'  => false,

        // Format used when rendering found records (used in child action class as required)
        'text_format' => '',

        'search_term' => '',
    ];

    /**
     * This function is called first in execute method and should check if user is logged in, if user has rights for this call, etc
     * It returns:
     *  - true if action is ok to be executed
     *  - false if action should stop execution and notifications are set in child class
     *  - array with an action result to be returned by execute() method
     * If method returns false, any notifications (e.g. PHS_Notifications::add_error_notice()) should be done inside child class
     * @return bool|array
     */
    abstract public function before_execute(): bool | array;

    /**
     * This is the actual method which should return list of records to be rendered in autocomplete input
     * Array returned should be an array of arrays. Node will have id, value and label keys.
     * id key value will be used in id input (to be used to identify what autocomplete record is)
     * label key value will be used to render record in autocomplete text input (what is actually seen by end-user) can be HTML
     * value key value will be displayed in text input to end-user once an item is selected from autocomplete list should be plain text
     * @return array[]{ id: int|string, label: string, value: string }
     */
    abstract public function get_results_for_ajax_call(): array;

    /**
     * This method should render given data as specified by $format and $as_html parameters.
     * This method is used when another script should present selected data to end-user in order to keep data formatting same
     * in aotocomplete list and when presenting selected input after submit
     *
     * @param null|int|array|PHS_Record_data $data Data to be formatted, if this is null
     * @param null|string $format What format should be used when rendering data (if any)
     * @param bool $as_html Tells if formatted data should be in HTML or not
     *
     * @return string
     */
    abstract public function format_data(
        null | int | array | PHS_Record_data $data = null,
        ?string $format = null,
        bool $as_html = true,
    ): string;

    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    /**
     * This method is called from action/view where autocomplete is needed and sets where AJAX call will be sent when requiring autocomplete functionality
     * @param array $route_arr
     * @param array $route_params_arr
     */
    public function set_ajax_route(array $route_arr, array $route_params_arr = []): void
    {
        $route_arr = PHS::validate_route_from_parts($route_arr, true);

        $this->autocomplete_params([
            'route_arr'        => $route_arr,
            'route_params_arr' => $route_params_arr,
        ]);
    }

    /**
     * Returns value sent in GET or POST for id input
     * @return null|string
     */
    public function get_id_input_value(): ?string
    {
        if (!($id_name = $this->autocomplete_params('id_name'))
            || null === ($id_val = PHS_Params::_pg($id_name, PHS_Params::T_NOHTML))) {
            return null;
        }

        return $id_val;
    }

    /**
     * Returns value sent in GET or POST for text input
     * @return null|string
     */
    public function get_text_input_value(): ?string
    {
        if (!($text_name = $this->autocomplete_params('text_name'))
            || null === ($text_val = PHS_Params::_pg($text_name))) {
            return null;
        }

        return is_scalar($text_val) ? (string)$text_val : '';
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        if (true !== ($before_action = $this->before_execute())) {
            if (!$before_action || !is_array($before_action)) {
                return self::default_action_result();
            }

            return $before_action;
        }

        $term = PHS_Params::_g('term', PHS_Params::T_REMSQL_CHARS) ?? '';
        $_f = PHS_Params::_g('_f', PHS_Params::T_NOHTML) ?? '';

        $this->autocomplete_params([
            'search_term' => $term,
            'text_format' => $_f,
        ]);

        if (!($list_arr = $this->get_results_for_ajax_call())) {
            if ($this->has_error()) {
                PHS_Notifications::add_error_notice($this->_pt('Result error: %s', $this->get_simple_error_message()));

                return self::default_action_result();
            }

            $list_arr = [];
        }

        $ajax_result = [];
        foreach ($list_arr as $el_arr) {
            $label = ($el_arr['label'] ?? '');
            $value = ($el_arr['value'] ?? $label);

            $ajax_result[] = [
                'id'    => ($el_arr['id'] ?? ''),
                'label' => $label,
                'value' => $value,
            ];
        }

        return $this->send_ajax_response($ajax_result);
    }

    public function autocomplete_params(null | string | array $key = null, mixed $val = null): mixed
    {
        if ($key === null) {
            return $this->autocomplete_params;
        }

        if ($val === null) {
            if (!is_array($key)) {
                if (is_scalar($key)
                    && array_key_exists($key, $this->autocomplete_params)) {
                    return $this->autocomplete_params[$key];
                }

                return null;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)
                    || !array_key_exists($kkey, $this->autocomplete_params)
                    || in_array($key, ['route_arr', 'route_params_arr'], true)) {
                    continue;
                }

                $this->autocomplete_params[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)
            || !array_key_exists($key, $this->autocomplete_params)
            || in_array($key, ['route_arr', 'route_params_arr'], true)) {
            return null;
        }

        $this->autocomplete_params[$key] = $val;

        return true;
    }

    public function js_all_functionality(array $data = []): string
    {
        return $this->js_generic_functionality($data).$this->js_autocomplete_functionality($data);
    }

    public function js_generic_functionality(array $data = []): string
    {
        if (($params_arr = $this->autocomplete_params())
            && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('autocomplete_generic_js', $data))) {
            return '<!-- Couldn\'t obtain PHS autocomplete generic JS functionality: '.$this->get_simple_error_message().' -->';
        }

        return $action_result['buffer'] ?? '';
    }

    public function js_autocomplete_functionality(array $data = []): string
    {
        if (($params_arr = $this->autocomplete_params())
         && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('autocomplete_js', $data))) {
            return '<!-- Couldn\'t obtain PHS autocomplete JS functionality: '.$this->get_simple_error_message().' -->';
        }

        return $action_result['buffer'] ?? '';
    }

    public function autocomplete_inputs(array $data = []): string
    {
        if (($params_arr = $this->autocomplete_params())
         && is_array($params_arr)) {
            foreach ($params_arr as $key => $val) {
                $data[$key] = $val;
            }
        }

        if (!($action_result = $this->quick_render_template('autocomplete_input', $data))) {
            return '<!-- Couldn\'t obtain PHS autocomplete inputs: '.$this->get_simple_error_message().' -->';
        }

        return $action_result['buffer'] ?? '';
    }

    protected function _highlight_data(string $str, string $term): string
    {
        if (!$term) {
            return $str;
        }

        return preg_replace('/('.preg_quote($term, '/').')/i', '<strong>${1}</strong>', $str);
    }
}
