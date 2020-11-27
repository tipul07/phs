<?php

namespace phs\plugins\emails;

use \phs\PHS;
use \phs\PHS_Crypt;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Error;
use \phs\system\core\views\PHS_View;
use \phs\plugins\emails\libraries\PHS_Smtp;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Params;

class PHS_Plugin_Emails extends PHS_Plugin
{
    const ERR_VALIDATION = 40000, ERR_TEMPLATE = 40001, ERR_SEND = 40002, ERR_ATTACHMENTS = 40003;

    const DEFAULT_ROUTE = 'default';

    const UNCHANGED_SMTP_PASS = '**********';

    const LOG_CHANNEL = 'emails.log';

    public static $MAIL_AUTH_KEY = 'XMailAuth';

    /** @var \phs\plugins\emails\libraries\PHS_Smtp $smtp_library */
    private $smtp_library = false;

    public function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        $this->load_depencies();
    }

    private function load_depencies()
    {
        $this->reset_error();

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\emails\\libraries\\PHS_Smtp';
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\emails\libraries\PHS_Smtp $smtp_library */
        if( !($this->smtp_library = $this->load_library( 'phs_smtp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading SMTP library.' ) );

            $this->smtp_library = false;

            return false;
        }

        return true;
    }

    public static function mail_auth_key( $key = false )
    {
        if( $key === false )
            return self::$MAIL_AUTH_KEY;

        self::$MAIL_AUTH_KEY = $key;
        return self::$MAIL_AUTH_KEY;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            // default template
            'template_main' => [
                'display_name' => 'Emails main template',
                'display_hint' => 'What template should be used when sending emails',
                'type' => PHS_Params::T_ASIS,
                'input_type' => self::INPUT_TYPE_TEMPLATE,
                'default' => $this->template_resource_from_file( 'template_emails' ),
            ],
            'email_vars' => [
                'display_name' => 'Emails variables',
                'display_hint' => 'These variables will be available in email template',
                'input_type' => self::INPUT_TYPE_KEY_VAL_ARRAY,
                'default' => [
                    'site_name' => PHS_SITE_NAME,
                    'from_name' => PHS_SITE_NAME,
                    'from_email' => 'office@'.PHS_DOMAIN,
                    'from_noreply' => 'noreply@'.PHS_DOMAIN,
                ],
            ],
            'routes' => [
                'display_name' => 'Emails routes',
                'custom_renderer' => [ $this, 'display_settings_routes' ],
                'custom_save' => [ $this, 'save_settings_routes' ],
                'default' => [ self::DEFAULT_ROUTE => self::get_default_smtp_settings() ],
            ],
            'max_attachment_size' => [
                'display_name' => 'Max Attachment file size',
                'display_hint' => 'Maximum size allowed for a file attachment (in bytes). Default: 20971520 bytes = 20 Mb, 0 to disable this check',
                'type' => PHS_Params::T_INT,
                'default' => 20971520, // 20Mb
            ],
            'test_email_sending' => [
                'display_name' => 'Test sending emails',
                'custom_renderer' => [ $this, 'display_test_sending_emails' ],
                'default' => false,
                'ignore_field_value' => true,
            ],
        ];
    }

    public static function get_default_smtp_settings()
    {
        return [
            'localhost' => '',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_host' => '',
            'smtp_port' => 25,
            'smtp_timeout' => 30,
            'smtp_encryption' => PHS_Smtp::ENCRYPTION_NONE,
            'smtp_authentication' => PHS_Smtp::AUTH_AUTO_DETECT,
        ];
    }

    public function save_settings_routes( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return null;

        $params = self::merge_array_assoc( self::st_default_custom_save_params(), $params );

        if( empty( $params['field_name'] )
         || empty( $params['form_data'] ) || !is_array( $params['form_data'] )
         || !in_array( $params['field_name'], [ 'routes' ], true ) )
            return null;

        if( !array_key_exists( 'field_value', $params )
         || $params['field_value'] === null )
            $old_values = null;
        else
            $old_values = $params['field_value'];

        $return_data = null;
        if( $params['field_name'] === 'routes' )
        {
            if( empty( $params['form_data']['routes'] ) || !is_array( $params['form_data']['routes'] ) )
                return null;

            if( $old_values === null
             || !is_array( $old_values ) )
                $old_values = array();

            $default_smtp_settings = self::get_default_smtp_settings();
            $return_data = array();
            foreach( $params['form_data']['routes'] as $route_name => $route_arr )
            {
                $route_arr = self::merge_array_assoc( $default_smtp_settings, $route_arr );

                // Check if we have a password change
                if( !empty( $old_values )
                and !empty( $old_values[$route_name] ) && is_array( $old_values[$route_name] ) )
                {
                    if( !empty( $old_values[$route_name]['smtp_pass'] )
                     && ($decrypted_pass = PHS_Crypt::quick_decode( $old_values[$route_name]['smtp_pass'] ))
                     && $route_arr['smtp_pass'] === self::UNCHANGED_SMTP_PASS )
                        $route_arr['smtp_pass'] = $decrypted_pass;
                }

                $route_arr['smtp_pass'] = PHS_Crypt::quick_encode( $route_arr['smtp_pass'] );

                $return_data[$route_name] = $route_arr;
            }
        }

        return $return_data;
    }

    public function display_settings_routes( $params )
    {
        $params = self::validate_array( $params, self::default_custom_renderer_params() );

        $default_smtp_settings = self::get_default_smtp_settings();

        if( empty( $params['field_value'] ) || !is_array( $params['field_value'] ) )
            $routes_arr = [ self::DEFAULT_ROUTE => $default_smtp_settings ];
        else
            $routes_arr = $params['field_value'];

        if( empty( $routes_arr[self::DEFAULT_ROUTE] ) || !is_array( $routes_arr[self::DEFAULT_ROUTE] ) )
            $routes_arr[self::DEFAULT_ROUTE] = $default_smtp_settings;

        $email_routes = [];
        foreach( $routes_arr as $route_name => $route_arr )
        {
            $route_arr = self::merge_array_assoc( $default_smtp_settings, $route_arr );

            $email_routes[$route_name] = $route_arr;
        }

        $data_arr = [];
        $data_arr['email_routes'] = $email_routes;
        $data_arr['smtp_library'] = $this->smtp_library;

        return $this->quick_render_template_for_buffer( 'routes_settings', $data_arr );
    }

    public function display_test_sending_emails( $params )
    {
        $params = self::validate_array( $params, self::default_custom_renderer_params() );

        if( !($current_settings = $this->get_plugin_settings()) )
            $current_settings = array();

        $default_route = array();
        if( !empty( $current_settings['routes'] )
         && !empty( $current_settings['routes'][self::DEFAULT_ROUTE] )
         && is_array( $current_settings['routes'][self::DEFAULT_ROUTE] ) )
            $default_route = $current_settings['routes'][self::DEFAULT_ROUTE];

        $testing_error = '';
        $testing_success = false;

        if( !($test_email_sending_email = PHS_Params::_pg( 'test_email_sending_email', PHS_Params::T_EMAIL )) )
            $test_email_sending_email = '';
        if( !($do_test_email_sending_submit = PHS_Params::_p( 'do_test_email_sending_submit' )) )
            $do_test_email_sending_submit = false;

        if( !empty( $do_test_email_sending_submit ) )
        {
            if( empty( $test_email_sending_email )
             || !PHS_Params::check_type( $test_email_sending_email, PHS_Params::T_EMAIL ) )
                $testing_error .= ($testing_error!==''?'<br/>':'').$this->_pt( 'Please provide a valid email address.' );

            else
            {
                $previous_error = self::st_stack_error();
                self::st_reset_error();

                $hook_args = [];
                $hook_args['subject'] = 'Site test email';
                $hook_args['to'] = $test_email_sending_email;
                $hook_args['to_name'] = self::_t( 'Site test email' );
                $hook_args['body_buffer'] = 'Hello,<br/>'."\n".
                    '<br/>'."\n".
                    'This is a test email sent from '.PHS_SITE_NAME.' ('.PHS::url().')<br/>'."\n".
                    '<br/>'."\n".
                    'Best wishes,<br/>'."\n".
                    PHS_SITE_NAME.' team<br/>'."\n";

                if( !($hook_results = PHS_Hooks::trigger_email( $hook_args ))
                 || !is_array( $hook_results )
                 || empty( $hook_results['send_result'] ) )
                {
                    if( empty( $hook_results )
                     && self::st_has_error() )
                        $testing_error .= ($testing_error!==''?'<br/>':'').self::st_get_error_message();

                    elseif( !empty( $hook_results )
                     && !empty( $hook_results['hook_errors'] ) && is_array( $hook_results['hook_errors'] )
                     && self::arr_has_error( $hook_results['hook_errors'] ) )
                        $testing_error .= ($testing_error!==''?'<br/>':'').self::arr_get_error_message( $hook_results['hook_errors'] );

                    else
                        $testing_error .= ($testing_error!==''?'<br/>':'').$this->_pt( 'Error sending email to provided email address.' );
                } else
                    $testing_success = true;

                self::st_restore_errors( $previous_error );
            }
        }

        $data_arr = [];
        $data_arr['test_email_sending_error'] = $testing_error;
        $data_arr['test_email_sending_success'] = $testing_success;
        $data_arr['test_email_sending_email'] = $test_email_sending_email;
        $data_arr['default_route'] = $default_route;
        $data_arr['smtp_library'] = $this->smtp_library;

        return $this->quick_render_template_for_buffer( 'test_email_sending', $data_arr );
    }

    public static function valid_smtp_settings( $settings )
    {
        if( empty( $settings ) || !is_array( $settings )
         || empty( $settings['smtp_host'] ) || empty( $settings['smtp_port'] ) )
            return false;

        return true;
    }

    public function get_smtp_routes_settings()
    {
        static $defined_routes = false;

        if( !empty( $defined_routes ) )
            return $defined_routes;

        if( !($plugin_settings = $this->get_db_settings())
         || empty( $plugin_settings['routes'] ) || !is_array( $plugin_settings['routes'] ) )
            return [];

        $route_settings_arr = [];
        foreach( $plugin_settings['routes'] as $route_key => $route_arr )
        {
            $route_arr = self::validate_array( $route_arr, self::get_default_smtp_settings() );
            if( !self::valid_smtp_settings( $route_arr ) )
                continue;

            $route_settings_arr[$route_key] = $route_arr;
        }

        $defined_routes = $route_settings_arr;

        return $defined_routes;
    }

    public function get_defined_smtp_routes()
    {
        if( !($routes_settings = $this->get_smtp_routes_settings()) )
            return [];

        return array_keys( $routes_settings );
    }

    public function get_smtp_route_settings( $route )
    {
        if( !($routes_settings = $this->get_smtp_routes_settings())
         || !is_array( $routes_settings )
         || empty( $routes_settings[$route] ) )
            return false;

        return $routes_settings[$route];
    }

    public static function default_file_attachment()
    {
        return [
            'file' => '',
            'file_name' => '',
            'content_type' => 'application/octet-stream',
            'transfer_encoding' => 'base64',
            'content_disposition' => 'attachment', // attachment or inline
            'file_attachment_buffer' => '',
        ];
    }

    public function init_email_hook_args( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() );

        if( !($settings_arr = $this->get_db_settings())
         || empty( $settings_arr['template_main'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Couldn\'t load template from plugin settings.' ) );

            PHS_Logger::logf( 'Couldn\'t load template from plugin settings.', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $template_params = [];
        $template_params['theme_relative_dirs'] = [ PHS_EMAILS_DIRS ];

        if( !($email_main_template = PHS_View::validate_template_resource( $settings_arr['template_main'], $template_params )) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed validating main email template file.' ) );

            PHS_Logger::logf( 'Failed validating main email template file.', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $hook_args['body_buffer'] )
         && (empty( $hook_args['template'] )
            || !($email_template = PHS_View::validate_template_resource( $hook_args['template'], $template_params ))
            ) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_TEMPLATE );
            else
                $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed validating email template file.' ) );

            PHS_Logger::logf( 'Email template error ['.$this->get_error_message().'].', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed validating email template file.' ) );

            return $hook_args;
        }

        if( $hook_args['route'] === false )
            $hook_args['route'] = self::DEFAULT_ROUTE;

        if( empty( $hook_args['native_mail_function'] ) )
        {
            if( empty( $hook_args['route'] )
             || !($route_settings = $this->get_smtp_route_settings( $hook_args['route'] )) )
            {
                PHS_Logger::logf( 'Invalid SMTP route ['.$hook_args['route'].'].', self::LOG_CHANNEL );

                $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Invalid SMTP route.' ) );

                $hook_args['hook_errors'] = $this->get_error();

                return $hook_args;
            }

            $hook_args['route_settings'] = $route_settings;
        }

        if( empty( $hook_args['from_name'] ) )
            $hook_args['from_name'] = $settings_arr['email_vars']['site_name'];
        if( empty( $hook_args['from_email'] ) )
            $hook_args['from_email'] = $settings_arr['email_vars']['from_email'];
        if( empty( $hook_args['from_noreply'] ) )
            $hook_args['from_noreply'] = $settings_arr['email_vars']['from_noreply'];

        if( empty( $hook_args['email_vars'] ) || !is_array( $hook_args['email_vars'] ) )
            $hook_args['email_vars'] = [];

        $hook_args['email_vars'] = self::validate_array( $hook_args['email_vars'], $settings_arr['email_vars'] );

        if( empty( $hook_args['subject'] ) )
            $hook_args['subject'] = 'Email from '.(!empty( $hook_args['email_vars']['site_name'] )?$hook_args['email_vars']['site_name']:PHS_SITE_NAME);

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = [
            'hook_args' => $hook_args,
            'email_content' => '',
        ];

        if( !empty( $hook_args['body_buffer'] ) )
            $email_content_buffer = $hook_args['body_buffer'];

        elseif( empty( $email_template )
         || !($email_template_obj = PHS_View::init_view( $email_template, $view_params ))
         || !($email_content_buffer = $email_template_obj->render()) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            elseif( !empty( $email_template_obj ) and $email_template_obj->has_error() )
                $this->copy_error( $email_template_obj );

            if( !$this->has_error() )
                $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Rendering template %s resulted in empty buffer.', (!empty( $email_template_obj )?$email_template_obj->get_template():'(???)') ) );

            PHS_Logger::logf( 'Email template render error ['.$this->get_error_message().'].', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_TEMPLATE, $this->_pt( 'Rendering template resulted in empty buffer.' ) );

            return $hook_args;
        }

        $view_params['template_data']['email_content'] = $email_content_buffer;

        if( !($main_template_obj = PHS_View::init_view( $email_main_template, $view_params ))
         || !($email_html_body = $main_template_obj->render()) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            elseif( !empty( $main_template_obj ) && $main_template_obj->has_error() )
                $this->copy_error( $main_template_obj );

            if( !$this->has_error() )
                $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Rendering template %s resulted in empty buffer.', ($main_template_obj?$main_template_obj->get_template():'(???)') ) );

            PHS_Logger::logf( 'Email main template render error ['.$this->get_error_message().'].', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_TEMPLATE, $this->_pt( 'Rendering main template resulted in empty buffer.' ) );

            return $hook_args;
        }

        $hook_args['email_html_body'] = $email_html_body;

        $attach_files = [];
        if( !empty( $hook_args['attach_files'] ) && is_array( $hook_args['attach_files'] ) )
        {
            $default_file_details = self::default_file_attachment();
            foreach( $hook_args['attach_files'] as $knti => $file_details )
            {
                if( empty( $file_details ) || !is_array( $file_details ) )
                    continue;

                $file_details = self::validate_array( $file_details, $default_file_details );

                if( !empty( $file_details['file_attachment_buffer'] ) )
                    continue;

                if( !empty( $file_details['file'] ) && empty( $file_details['file_name'] ) )
                    $file_details['file_name'] = @basename( $file_details['file'] );

                if( (empty( $file_details['file_base64_buffer'] ) && empty( $file_details['file'] ))
                 || (!empty( $file_details['file_base64_buffer'] ) && empty( $file_details['file_name'] ))
                 || (!empty( $file_details['file'] ) && !@file_exists( $file_details['file'] )) )
                {
                    $this->set_error( self::ERR_ATTACHMENTS, $this->_pt( 'Invalid parameters for attachment #%s.', $knti ) );

                    $hook_args['hook_errors'] = self::arr_set_error( self::ERR_ATTACHMENTS, $this->get_simple_error_message() );

                    return $hook_args;
                }

                if( empty( $file_details['content_disposition'] )
                 or !in_array( $file_details['content_disposition'], [ 'attachment', 'inline' ] ) )
                    $file_details['content_disposition'] = 'attachment';

                $attach_files[] = $file_details;
            }
        }
        $hook_args['attach_files'] = $attach_files;

        if( !empty( $hook_args['also_send'] ) )
            $hook_args = $this->send_email( $hook_args );

        return $hook_args;
    }

    public function send_email( $hook_args )
    {
        $this->reset_error();

        $hook_args = PHS_Hooks::reset_email_hook_args( self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() ) );

        if( empty( $hook_args['to'] )
         or !PHS_Params::check_type( $hook_args['to'], PHS_Params::T_EMAIL ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Destination is not an email.' ) );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $hook_args['email_html_body'] ) and empty( $hook_args['email_text_body'] ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Email body is empty.' ) );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $hook_args['reply_email'] ) )
            $hook_args['reply_email'] = $hook_args['from_email'];
        if( empty( $hook_args['reply_name'] ) )
            $hook_args['reply_name'] = $hook_args['from_name'];

        // Convert HTML version to text and text to HTML if necessary
        if( empty( $hook_args['email_html_body'] ) && !empty( $hook_args['email_text_body'] ) )
            $hook_args['email_html_body'] = str_replace( '  ', ' &nbsp;', nl2br( $hook_args['email_text_body'] ) );
        elseif( !empty( $hook_args['email_html_body'] ) && empty( $hook_args['email_text_body'] ) )
            $hook_args['email_text_body'] = strip_tags( preg_replace( "/[\r\n]+/",
                                                                      "\n",
                                                                      str_ireplace( [ '<p>', '</p>', '<br>', '<br/>', '<br />' ],
                                                                                    "\n",
                                                                                    $hook_args['email_html_body'] ) ) );

        // set multipart boundary
        $hash = md5( microtime() );
        $mime_boundary = '==MULTIPART_BOUNDARY_' . $hash;
        $mime_boundary_header = chr(34) . $mime_boundary . chr(34);

        $predefined_headers = array();
        $predefined_headers['From'] = $hook_args['from_name'].' <'.$hook_args['from_email'].'>';
        $predefined_headers['X-Sender'] = '<'.$hook_args['from_email'].'>';
        $predefined_headers['Return-Path'] = '<'.$hook_args['from_email'].'>';
        $predefined_headers['Reply-To'] = $hook_args['reply_name'].' <'.$hook_args['reply_email'].'>';
        $predefined_headers['X-Mailer'] = 'PHP (PHS-MAILER-'.$this->get_plugin_version().')';
        if( !empty( $hook_args['with_priority'] ) )
            $predefined_headers['X-Priority'] = '1';
        $predefined_headers['MIME-Version'] = '1.0';
        $predefined_headers['Content-Type'] = 'multipart/alternative; boundary=' . $mime_boundary_header;
        $predefined_headers['Content-Transfer-Encoding'] = '7bit';
        $predefined_headers['X-Script-Time'] = time();

        if( empty( $params['skip_mail_authentication'] ) )
            // for single emails it's ok, but when sending multiple emails it might take too much time
            $predefined_headers['X-Mail-ID'] = PHS_Crypt::quick_encode( self::mail_auth_key().':'.time() );
        else
            $predefined_headers['X-SMail-ID'] = md5( self::mail_auth_key().':'.time() );

        $final_headers_arr = $predefined_headers;
        if( !empty( $hook_args['custom_headers'] ) && is_array( $hook_args['custom_headers'] ) )
        {
            foreach( $hook_args['custom_headers'] as $key => $value )
                $final_headers_arr[$key] = $value;
        }

        $hook_args['full_body'] = 'This is a multi-part message in MIME format.' . "\n\n" .
                                  '--' . $mime_boundary . "\n" .
                                  'Content-Type: text/plain; charset=UTF-8' . "\n" .
                                  'Content-Transfer-Encoding: 7bit' . "\n\n" .
                                  $hook_args['email_text_body'] . "\n\n" .
                                  '--' . $mime_boundary . "\n" .
                                  'Content-Type: text/html; charset=UTF-8' . "\n" .
                                  'Content-Transfer-Encoding: 7bit' . "\n\n" .
                                  $hook_args['email_html_body'] . "\n\n";

        $attach_files = [];
        if( !empty( $hook_args['attach_files'] ) && is_array( $hook_args['attach_files'] ) )
        {
            foreach( $hook_args['attach_files'] as $file_details )
            {
                if( empty( $file_details ) || !is_array( $file_details ) )
                    continue;

                if( !empty( $file_details['file_attachment_buffer'] ) )
                    $hook_args['full_body'] .= $file_details['file_attachment_buffer'];

                else
                {
                    $file_encoded = '';
                    if( !empty( $file_details['file_base64_buffer'] ) )
                        $file_encoded = $file_details['file_base64_buffer'];

                    elseif( !empty( $file_details['file'] ) )
                    {
                        if( false === ($file_size = @filesize( $file_details['file'] ))
                         || (!empty( $settings_arr['max_attachment_size'] ) && (int)$file_size > $settings_arr['max_attachment_size'])
                         || false === ($file_content = @file_get_contents( $file_details['file'] )) )
                        {
                            $this->set_error( self::ERR_SEND, $this->_pt( 'Couldn\'t obtain attachment file content.' ) );

                            $hook_args['hook_errors'] = $this->get_error();

                            return $hook_args;
                        }

                        if( $file_details['transfer_encoding'] === 'base64' )
                            $file_encoded = @base64_encode( $file_content );
                        else
                            $file_encoded = $file_content;

                        // Free up some memory
                        unset( $file_content );
                    }

                    $file_details['file_attachment_buffer'] = '--' . $mime_boundary . "\n" .
                        'Content-Type: '.$file_details['content_type'].';'."\n\t".' name="'.$file_details['file_name'].'"'."\n".
                        'Content-Transfer-Encoding: '.$file_details['transfer_encoding']."\n".
                        'Content-Disposition: '.$file_details['content_disposition'].';'."\n\t".' filename="'.$file_details['file_name'].'"'."\n\n".
                        chunk_split( $file_encoded );

                    $hook_args['full_body'] .= $file_details['file_attachment_buffer'];
                }

                $attach_files[] = $file_details;
            }
        }
        $hook_args['attach_files'] = $attach_files;

        $hook_args['full_body'] .= '--' . $mime_boundary . "--\n\n";

        $hook_args['internal_vars']['full_headers'] = $final_headers_arr;

        $hook_args['internal_vars']['mime_boundary'] = $mime_boundary;

        $hook_args['internal_vars']['to_full_value'] = '';
        if( !empty( $hook_args['to_name'] ) )
            $hook_args['internal_vars']['to_full_value'] .= $hook_args['to_name'].' ';
        $hook_args['internal_vars']['to_full_value'] .= '<'.$hook_args['to'].'>';


        if( !empty( $hook_args['native_mail_function'] ) )
            $hook_args = $this->_send_email_native( $hook_args );
        else
            $hook_args = $this->_send_email_smtp( $hook_args );

        if( $this->has_error() )
            PHS_Logger::logf( 'Sending email error ('.(!empty( $hook_args['native_mail_function'] ) ? 'native' : 'SMTP').') ['.$this->get_error_message().'].', self::LOG_CHANNEL );

        return $hook_args;
    }

    private function _send_email_smtp( $hook_args )
    {
        $this->reset_error();

        if( empty( $hook_args ) || !is_array( $hook_args )
         || empty( $hook_args['route_settings'] )
         || empty( $hook_args['to'] )
         || !isset( $hook_args['subject'] )
         || empty( $hook_args['full_body'] )
         || empty( $hook_args['internal_vars']['full_headers'] ) || !is_array( $hook_args['internal_vars']['full_headers'] )
         || empty( $hook_args['internal_vars']['mime_boundary'] ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Mandatory SMTP parameters not set.' ) );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $library_params = [];
        $library_params['full_class_name'] = '\\phs\\plugins\\emails\\libraries\\PHS_Smtp';
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\emails\libraries\PHS_Smtp $smtp_library */
        if( !($smtp_library = $this->load_library( 'phs_smtp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading SMTP library.' ) );

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_SEND, $this->_pt( 'Error loading SMTP library.' ) );

            return $hook_args;
        }

        $smtp_settings = $hook_args['route_settings'];
        if( !empty( $smtp_settings['smtp_pass'] ) )
            $smtp_settings['smtp_pass'] = PHS_Crypt::quick_decode( $smtp_settings['smtp_pass'] );

        $smtp_library->settings( $smtp_settings );

        $email_settings = [
            'headers' => $hook_args['internal_vars']['full_headers'],
            'to_name' => (!empty( $hook_args['to_name'] )?$hook_args['to_name']:''),
            'to_email' => $hook_args['to'],
            'reply_to' => $hook_args['reply_email'],
            'reply_name' => $hook_args['reply_name'],
            'from_name' => $hook_args['from_name'],
            'from_email' => $hook_args['from_email'],
            'subject' => $hook_args['subject'],
            'mime_boundary' => $hook_args['internal_vars']['mime_boundary'],
            'body_html' => $hook_args['email_html_body'],
            'body_txt' => $hook_args['email_text_body'],
            'body_full' => $hook_args['full_body'],
        ];

        $smtp_library->email_details( $email_settings );

        if( $smtp_library->send() )
            $hook_args['send_result'] = true;

        else
        {
            $hook_args['send_result'] = false;

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_SEND, $this->_pt( 'Error sending email using SMTP library.' ) );

            if( $smtp_library->has_error() )
                $this->copy_error( $smtp_library, self::ERR_SEND );

            if( !$this->has_error() )
                $this->set_error( self::ERR_SEND, $this->_pt( 'Error sending email using SMTP library.' ) );
        }

        return $hook_args;
    }

    private function _send_email_native( $hook_args )
    {
        $this->reset_error();

        if( empty( $hook_args ) || !is_array( $hook_args )
         || empty( $hook_args['to'] )
         || !isset( $hook_args['subject'] )
         || empty( $hook_args['full_body'] )
         || empty( $hook_args['internal_vars']['full_headers'] ) || !is_array( $hook_args['internal_vars']['full_headers'] ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Mandatory native mail parameters not set.' ) );

            return $hook_args;
        }

        $full_headers_str = '';
        foreach( $hook_args['internal_vars']['full_headers'] as $key => $value )
            $full_headers_str .= $key.': '.$value."\n";
        $full_headers_str .= "\n";

        if( !empty( $hook_args['internal_vars']['to_full_value'] ) )
            $to = $hook_args['internal_vars']['to_full_value'];
        else
            $to = $hook_args['to'];

        $hook_args['send_result'] = (@mail( $to, $hook_args['subject'], $hook_args['full_body'], $full_headers_str )?true:false);

        return $hook_args;
    }
}
