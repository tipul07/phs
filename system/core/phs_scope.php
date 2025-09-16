<?php
namespace phs;

use phs\libraries\PHS_Action;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;

abstract class PHS_Scope extends PHS_Instantiable
{
    public const ERR_ACTION = 20000;

    public const DEFAULT_SCOPE_KEY = 'default_scope', SCOPE_FLOW_KEY = 'scope_flow', SCOPE_EMULATION_FLOW_KEY = 'scope_emulation_flow';

    public const SCOPE_VAR_PREFIX = '__scp_pre_';

    public const SCOPE_WEB = 1, SCOPE_BACKGROUND = 2, SCOPE_AJAX = 3, SCOPE_API = 4,
        SCOPE_AGENT = 5, SCOPE_TESTS = 6, SCOPE_CLI = 7, SCOPE_REMOTE = 8, SCOPE_GRAPHQL = 9;

    private static array $SCOPES_ARR = [
        self::SCOPE_WEB => [
            'title'      => 'Web',
            'plugin'     => null,
            'class_name' => 'web',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'web',
        ],
        self::SCOPE_BACKGROUND => [
            'title'      => 'Background',
            'plugin'     => null,
            'class_name' => 'background',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'background',
        ],
        self::SCOPE_AJAX => [
            'title'      => 'Ajax',
            'plugin'     => null,
            'class_name' => 'ajax',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'ajax',
        ],
        self::SCOPE_API => [
            'title'      => 'API',
            'plugin'     => null,
            'class_name' => 'api',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'api',
        ],
        self::SCOPE_AGENT => [
            'title'      => 'Agent',
            'plugin'     => null,
            'class_name' => 'agent',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'agent',
        ],
        self::SCOPE_TESTS => [
            'title'      => 'Test Suite',
            'plugin'     => null,
            'class_name' => 'test',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'test',
        ],
        self::SCOPE_CLI => [
            'title'      => 'CLI',
            'plugin'     => null,
            'class_name' => 'cli',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'cli',
        ],
        self::SCOPE_REMOTE => [
            'title'      => 'Remote PHS',
            'plugin'     => null,
            'class_name' => 'remote',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'remote',
        ],
        self::SCOPE_GRAPHQL => [
            'title'      => 'GraphQL',
            'plugin'     => null,
            'class_name' => 'graphql',
            // Value of PHS_SCRIPT_SCOPE constant defined in entry script (if required)
            'constant_name' => 'graphql',
        ],
    ];

    /**
     * @return int
     */
    abstract public function get_scope_type() : int;

    /**
     * @param false|array $action_result
     * @param false|array $static_error_arr
     *
     * @return mixed
     */
    abstract public function process_action_result($action_result, $static_error_arr = false);

    final public function instance_type() : string
    {
        return self::INSTANCE_TYPE_SCOPE;
    }

    /**
     * @param null|false|array $action_result
     * @param null|array $end_user_error_arr
     * @param null|array $technical_error_arr
     *
     * @return array
     */
    public function generate_response($action_result, ?array $end_user_error_arr = null, ?array $technical_error_arr = null) : array
    {
        $this->reset_error();

        if (!$action_result) {
            if (!($action_obj = PHS::running_action())) {
                $action_result = PHS_Action::default_action_result();
                $action_result['buffer'] = self::_t('Unknown running action.');
            } else {
                if (!($action_result = $action_obj->get_action_result())) {
                    $action_result = PHS_Action::default_action_result();
                    $action_result['buffer'] = self::_t('Couldn\'t obtain action result.');
                }

                // In case we have an error in action set an error notice in notifications class,
                // so it can be used in response as scope class considers
                if ($action_obj->has_error()) {
                    PHS_Notifications::add_error_notice($action_obj->get_simple_error_message());
                }
            }
        }

        $action_result = PHS_Action::validate_action_result($action_result);

        $action_result = $this->process_action_result($action_result, $end_user_error_arr);

        $action_result = PHS_Action::validate_action_result($action_result);

        if ($end_user_error_arr || $technical_error_arr) {
            $action_result
                = PHS_Action::set_action_result_errors($action_result, $end_user_error_arr, $technical_error_arr);
        }

        PHS::set_data(PHS::PHS_END_TIME, microtime(true));

        return $action_result;
    }

    public static function get_scopes() : array
    {
        return self::$SCOPES_ARR;
    }

    public static function valid_scope(int $scope) : array
    {
        if (!($scopes_arr = self::get_scopes())) {
            return [];
        }

        return $scopes_arr[$scope] ?? [];
    }

    public static function valid_constant_scope(string $const_scope) : int
    {
        $const_scope = strtolower(trim($const_scope));
        if (!($scopes_arr = self::get_scopes())) {
            return 0;
        }

        foreach ($scopes_arr as $scope_id => $scope_details) {
            if (!empty($scope_details['constant_name'])
                && $scope_details['constant_name'] === $const_scope) {
                return $scope_id;
            }
        }

        return 0;
    }

    public static function default_scope_params() : array
    {
        return [
            'title'          => '',
            'plugin'         => null,
            'class_name'     => '',
            'front_template' => '',
            'admin_template' => '',
        ];
    }

    public static function register_scope(array $scope_params) : ?array
    {
        self::st_reset_error();

        if (empty($scope_params)
            || !($scope_params = self::validate_array($scope_params, self::default_scope_params()))
            || empty($scope_params['title'])
            || empty($scope_params['class_name'])) {
            self::_t('Invalid scope parameters.');

            return null;
        }

        if (empty($scope_params['plugin'])
            || $scope_params['plugin'] === PHS_Instantiable::CORE_PLUGIN) {
            $scope_params['plugin'] = null;
        }

        $scope_key = count(self::$SCOPES_ARR);

        self::$SCOPES_ARR[] = $scope_params;

        return ['scope_key' => $scope_key, 'scope_params' => $scope_params];
    }

    public static function default_scope(?int $scope = null) : int
    {
        if ($scope === null) {
            if (!($default_scope = self::get_data(self::DEFAULT_SCOPE_KEY))) {
                self::set_data(self::DEFAULT_SCOPE_KEY, self::SCOPE_WEB);

                return self::SCOPE_WEB;
            }

            return $default_scope;
        }

        if (!self::valid_scope($scope)) {
            return 0;
        }

        self::set_data(self::DEFAULT_SCOPE_KEY, $scope);

        return $scope;
    }

    public static function current_scope(?int $scope = null) : int
    {
        if ($scope === null) {
            if (!($current_scope = self::get_data(self::SCOPE_FLOW_KEY))) {
                return self::default_scope();
            }

            return $current_scope;
        }

        if (!self::valid_scope($scope)) {
            return 0;
        }

        self::set_data(self::SCOPE_FLOW_KEY, $scope);

        return $scope;
    }

    public static function current_scope_is_set() : bool
    {
        return (bool)self::get_data(self::SCOPE_FLOW_KEY);
    }

    public static function emulated_scope(?int $scope = null) : ?int
    {
        if ($scope === null) {
            if (!($emulated_scope = self::get_data(self::SCOPE_EMULATION_FLOW_KEY))) {
                return null;
            }

            return $emulated_scope;
        }

        if ($scope !== 0
            && !self::valid_scope($scope)) {
            return null;
        }

        self::set_data(self::SCOPE_EMULATION_FLOW_KEY, $scope);

        return $scope;
    }

    public static function spawn_scope_instance(?int $scope = null) : ?self
    {
        if ($scope === null) {
            $scope = self::current_scope();
        }

        if (!($scope_details = self::valid_scope($scope))
            || empty($scope_details['class_name'])
            || !($scope_instance = PHS::load_scope($scope_details['class_name'], $scope_details['plugin']))) {
            self::st_set_error_if_not_set(self::ERR_INSTANCE, self::_t('Error spawning scope instance.'));

            return null;
        }

        return $scope_instance;
    }

    public static function get_scope_instance() : ?self
    {
        static $one_scope = null;

        if ($one_scope === null) {
            $one_scope = self::spawn_scope_instance();
        }

        return $one_scope;
    }
}
