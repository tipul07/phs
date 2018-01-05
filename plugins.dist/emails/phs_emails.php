<?php

namespace phs\plugins\emails;

use \phs\PHS;
use \phs\PHS_crypt;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Error;
use \phs\system\core\views\PHS_View;
use \phs\plugins\emails\libraries\PHS_smtp;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_params;

class PHS_Plugin_Emails extends PHS_Plugin
{
    const ERR_VALIDATION = 40000, ERR_TEMPLATE = 40001, ERR_SEND = 40002;

    const MAX_ATTACHMENT_FILE_SIZE = 20971520; // 20 Mb

    const DEFAULT_ROUTE = 'default';

    const UNCHANGED_SMTP_PASS = '**********';

    const LOG_CHANNEL = 'emails.log';

    public static $MAIL_AUTH_KEY = 'XMailAuth';

    /** @var \phs\plugins\emails\libraries\PHS_smtp $smtp_library */
    private $smtp_library = false;

    function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        $this->load_depencies();
    }

    private function load_depencies()
    {
        $this->reset_error();

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\emails\\libraries\\PHS_smtp';
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\emails\libraries\PHS_smtp $smtp_library */
        if( !($this->smtp_library = $this->load_library( 'phs_smtp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading SMTP library.' ) );

            $this->smtp_library = false;

            return false;
        }

        return true;
    }

    static function mail_auth_key( $key = false )
    {
        if( $key === false )
            return self::$MAIL_AUTH_KEY;

        self::$MAIL_AUTH_KEY = $key;
        return self::$MAIL_AUTH_KEY;
    }

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.2';
    }

    /**
     * @inheritdoc
     */
    public function get_models()
    {
        return array();
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'vendor_id' => 'phs',
            'vendor_name' => 'PHS',
            'name' => 'Email Sending Plugin',
            'description' => 'Handles all emailing functionality in platform.',
        );
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
            // default template
            'template_main' => array(
                'display_name' => 'Emails main template',
                'display_hint' => 'What template should be used when sending emails',
                'type' => PHS_params::T_ASIS,
                'input_type' => self::INPUT_TYPE_TEMPLATE,
                'default' => $this->template_resource_from_file( 'template_emails' ),
            ),
            'email_vars' => array(
                'display_name' => 'Emails variables',
                'display_hint' => 'These variables will be available in email template',
                'input_type' => self::INPUT_TYPE_KEY_VAL_ARRAY,
                'default' => array(
                    'site_name' => PHS_SITE_NAME,
                    'from_name' => PHS_SITE_NAME,
                    'from_email' => 'office@'.PHS_DOMAIN,
                    'from_noreply' => 'noreply@'.PHS_DOMAIN,
                ),
            ),
            'routes' => array(
                'display_name' => 'Emails routes',
                'custom_renderer' => array( $this, 'display_settings_routes' ),
                'custom_save' => array( $this, 'save_settings_routes' ),
                'default' => array( self::DEFAULT_ROUTE => self::get_default_smtp_settings() ),
            ),
            'test_email_sending' => array(
                'display_name' => 'Test sending emails',
                'custom_renderer' => array( $this, 'display_test_sending_emails' ),
                'default' => false,
            ),
        );
    }

    static function get_default_smtp_settings()
    {
        return array(
            'localhost' => '',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_host' => '',
            'smtp_port' => 25,
            'smtp_timeout' => 30,
            'smtp_encryption' => PHS_smtp::ENCRYPTION_NONE,
            'smtp_authentication' => PHS_smtp::AUTH_AUTO_DETECT,
        );
    }

    public function save_settings_routes( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return null;

        $params = self::merge_array_assoc( self::st_default_custom_save_params(), $params );

        if( empty( $params['field_name'] )
         or empty( $params['form_data'] ) or !is_array( $params['form_data'] )
         or !in_array( $params['field_name'], array( 'routes' ) ) )
            return null;

        if( !array_key_exists( 'field_value', $params )
         or $params['field_value'] === null )
            $old_values = null;
        else
            $old_values = $params['field_value'];

        $return_data = null;
        if( $params['field_name'] == 'routes' )
        {
            if( empty( $params['form_data']['routes'] ) or !is_array( $params['form_data']['routes'] ) )
                return null;

            if( $old_values === null
             or !is_array( $old_values ) )
                $old_values = array();

            $default_smtp_settings = self::get_default_smtp_settings();
            $return_data = array();
            foreach( $params['form_data']['routes'] as $route_name => $route_arr )
            {
                $route_arr = self::merge_array_assoc( $default_smtp_settings, $route_arr );

                // Check if we have a password change
                if( !empty( $old_values )
                and !empty( $old_values[$route_name] ) and is_array( $old_values[$route_name] ) )
                {
                    if( !empty( $old_values[$route_name]['smtp_pass'] )
                    and ($decrypted_pass = PHS_crypt::quick_decode( $old_values[$route_name]['smtp_pass'] ))
                    and $route_arr['smtp_pass'] == self::UNCHANGED_SMTP_PASS )
                        $route_arr['smtp_pass'] = $decrypted_pass;
                }

                $route_arr['smtp_pass'] = PHS_crypt::quick_encode( $route_arr['smtp_pass'] );

                $return_data[$route_name] = $route_arr;
            }
        }

        return $return_data;
    }

    public function display_settings_routes( $params )
    {
        $params = self::validate_array( $params, $this->default_custom_renderer_params() );

        $default_smtp_settings = self::get_default_smtp_settings();

        if( empty( $params['field_value'] ) or !is_array( $params['field_value'] ) )
            $routes_arr = array( self::DEFAULT_ROUTE => $default_smtp_settings );
        else
            $routes_arr = $params['field_value'];

        if( empty( $routes_arr[self::DEFAULT_ROUTE] ) or !is_array( $routes_arr[self::DEFAULT_ROUTE] ) )
            $routes_arr[self::DEFAULT_ROUTE] = $default_smtp_settings;

        $email_routes = array();
        foreach( $routes_arr as $route_name => $route_arr )
        {
            $route_arr = self::merge_array_assoc( $default_smtp_settings, $route_arr );

            $email_routes[$route_name] = $route_arr;
        }

        $data_arr = array();
        $data_arr['email_routes'] = $email_routes;
        $data_arr['smtp_library'] = $this->smtp_library;

        return $this->quick_render_template_for_buffer( 'routes_settings', $data_arr );
    }

    public function display_test_sending_emails( $params )
    {
        $params = self::validate_array( $params, $this->default_custom_renderer_params() );

        if( !($current_settings = $this->get_plugin_settings()) )
            $current_settings = array();

        $default_route = array();
        if( !empty( $current_settings['routes'] )
        and !empty( $current_settings['routes'][self::DEFAULT_ROUTE] )
        and is_array( $current_settings['routes'][self::DEFAULT_ROUTE] ) )
            $default_route = $current_settings['routes'][self::DEFAULT_ROUTE];

        $testing_error = '';
        $testing_success = false;

        if( !($test_email_sending_email = PHS_params::_pg( 'test_email_sending_email', PHS_params::T_EMAIL )) )
            $test_email_sending_email = '';
        if( !($do_test_email_sending_submit = PHS_params::_p( 'do_test_email_sending_submit' )) )
            $do_test_email_sending_submit = false;

        if( !empty( $do_test_email_sending_submit ) )
        {
            if( empty( $test_email_sending_email )
             or !PHS_params::check_type( $test_email_sending_email, PHS_params::T_EMAIL ) )
                $testing_error .= ($testing_error!=''?'<br/>':'').$this->_pt( 'Please provide a valid email address.' );

            else
            {
                $previous_error = self::st_stack_error();
                self::st_reset_error();

                $hook_args = array();
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
                 or !is_array( $hook_results )
                 or empty( $hook_results['send_result'] ) )
                {
                    if( empty( $hook_results )
                    and self::st_has_error() )
                        $testing_error .= ($testing_error!=''?'<br/>':'').self::st_get_error_message();

                    elseif( !empty( $hook_results )
                    and !empty( $hook_results['hook_errors'] ) and is_array( $hook_results['hook_errors'] )
                    and self::arr_has_error( $hook_results['hook_errors'] ) )
                        $testing_error .= ($testing_error!=''?'<br/>':'').self::arr_get_error_message( $hook_results['hook_errors'] );

                    else
                        $testing_error .= ($testing_error!=''?'<br/>':'').$this->_pt( 'Error sending email to provided email address.' );
                } else
                    $testing_success = true;

                self::st_restore_errors( $previous_error );
            }
        }

        $data_arr = array();
        $data_arr['test_email_sending_error'] = $testing_error;
        $data_arr['test_email_sending_success'] = $testing_success;
        $data_arr['test_email_sending_email'] = $test_email_sending_email;
        $data_arr['default_route'] = $default_route;
        $data_arr['smtp_library'] = $this->smtp_library;

        return $this->quick_render_template_for_buffer( 'test_email_sending', $data_arr );
    }

    static function valid_smtp_settings( $settings )
    {
        if( empty( $settings ) or !is_array( $settings )
         or empty( $settings['smtp_host'] ) or empty( $settings['smtp_port'] ) )
            return false;

        return true;
    }

    public function get_smtp_routes_settings()
    {
        static $defined_routes = false;

        if( !empty( $defined_routes ) )
            return $defined_routes;

        if( !($plugin_settings = $this->get_db_settings())
         or empty( $plugin_settings['routes'] ) or !is_array( $plugin_settings['routes'] ) )
            return array();

        $route_settings_arr = array();
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
            return array();

        return array_keys( $routes_settings );
    }

    public function get_smtp_route_settings( $route )
    {
        if( !($routes_settings = $this->get_smtp_routes_settings())
         or !is_array( $routes_settings )
         or empty( $routes_settings[$route] ) )
            return false;

        return $routes_settings[$route];
    }

    public static function default_file_attachment()
    {
        return array(
            'file' => '',
            'file_name' => '',
            'content_type' => 'application/octet-stream',
            'transfer_encoding' => 'base64',
            'content_disposition' => 'attachment', // attachment or inline
            'file_attachment_buffer' => '',
        );
    }

    public function init_email_hook_args( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() );

        if( !($settings_arr = $this->get_db_settings())
         or empty( $settings_arr['template_main'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Couldn\'t load template from plugin settings.' ) );

            PHS_Logger::logf( 'Couldn\'t load template from plugin settings.', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $template_params = array();
        $template_params['theme_relative_dirs'] = array( PHS_EMAILS_DIRS );

        if( !($email_main_template = PHS_View::validate_template_resource( $settings_arr['template_main'], $template_params )) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed validating main email template file.' ) );

            PHS_Logger::logf( 'Failed validating main email template file.', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $hook_args['body_buffer'] )
        and (empty( $hook_args['template'] )
            or !($email_template = PHS_View::validate_template_resource( $hook_args['template'], $template_params ))
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
             or !($route_settings = $this->get_smtp_route_settings( $hook_args['route'] )) )
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

        if( empty( $hook_args['email_vars'] ) or !is_array( $hook_args['email_vars'] ) )
            $hook_args['email_vars'] = array();

        $hook_args['email_vars'] = self::validate_array( $hook_args['email_vars'], $settings_arr['email_vars'] );

        if( empty( $hook_args['subject'] ) )
            $hook_args['subject'] = 'Email from '.(!empty( $hook_args['email_vars']['site_name'] )?$hook_args['email_vars']['site_name']:PHS_SITE_NAME);

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = array(
            'hook_args' => $hook_args,
            'email_content' => '',
        );

        if( !empty( $hook_args['body_buffer'] ) )
            $email_content_buffer = $hook_args['body_buffer'];

        elseif( empty( $email_template )
         or !($email_template_obj = PHS_View::init_view( $email_template, $view_params ))
         or !($email_content_buffer = $email_template_obj->render()) )
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
         or !($email_html_body = $main_template_obj->render()) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            elseif( !empty( $main_template_obj ) and $main_template_obj->has_error() )
                $this->copy_error( $main_template_obj );

            if( !$this->has_error() )
                $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Rendering template %s resulted in empty buffer.', ($main_template_obj?$main_template_obj->get_template():'(???)') ) );

            PHS_Logger::logf( 'Email main template render error ['.$this->get_error_message().'].', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_TEMPLATE, $this->_pt( 'Rendering main template resulted in empty buffer.' ) );

            return $hook_args;
        }

        $hook_args['email_html_body'] = $email_html_body;

        $attach_files = array();
        if( !empty( $hook_args['attach_files'] ) and is_array( $hook_args['attach_files'] ) )
        {
            $default_file_details = self::default_file_attachment();
            foreach( $hook_args['attach_files'] as $file_details )
            {
                if( empty( $file_details ) or !is_array( $file_details ) )
                    continue;

                if( !empty( $file_details['file_attachment_buffer'] ) )
                    continue;

                $file_details = self::validate_array( $file_details, $default_file_details );
                if( empty( $file_details['file'] ) or !@file_exists( $file_details['file'] ) )
                    continue;

                if( empty( $file_details['file_name'] ) )
                    $file_details['file_name'] = @basename( $file_details['file'] );

                if( empty( $file_details['content_disposition'] )
                 or !in_array( $file_details['content_disposition'], array( 'attachment', 'inline' ) ) )
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
         or !PHS_params::check_type( $hook_args['to'], PHS_params::T_EMAIL ) )
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
        if( empty( $hook_args['email_html_body'] ) and !empty( $hook_args['email_text_body'] ) )
            $hook_args['email_html_body'] = str_replace( '  ', ' &nbsp;', nl2br( $hook_args['email_text_body'] ) );
        elseif( !empty( $hook_args['email_html_body'] ) and empty( $hook_args['email_text_body'] ) )
            $hook_args['email_text_body'] = strip_tags( preg_replace( "/[\r\n]+/", "\n", str_ireplace( array( '<p>', '</p>', '<br>', '<br/>', '<br />' ), "\n", $hook_args['email_html_body'] ) ) );

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
            $predefined_headers['X-Mail-ID'] = PHS_crypt::quick_encode( self::mail_auth_key().':'.time() );
        else
            $predefined_headers['X-SMail-ID'] = md5( self::mail_auth_key().':'.time() );

        $final_headers_arr = $predefined_headers;
        if( !empty( $hook_args['custom_headers'] ) and is_array( $hook_args['custom_headers'] ) )
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

        $attach_files = array();
        if( !empty( $hook_args['attach_files'] ) and is_array( $hook_args['attach_files'] ) )
        {
            $valid_attachments = false;
            foreach( $hook_args['attach_files'] as $file_details )
            {
                if( empty( $file_details ) or !is_array( $file_details )
                 or empty( $file_details['file'] ) or !@file_exists( $file_details['file'] ) )
                    continue;

                $valid_attachments = true;

                if( !empty( $file_details['file_attachment_buffer'] ) )
                    $hook_args['full_body'] .= $file_details['file_attachment_buffer'];

                else
                {
                    if( ($file_size = @filesize( $file_details['file'] )) === false
                     or intval( $file_size ) > self::MAX_ATTACHMENT_FILE_SIZE
                     or !($file_content = @file_get_contents( $file_details['file'] )) )
                    {
                        $this->set_error( self::ERR_SEND, $this->_pt( 'Couldn\'t read attachment file.' ) );

                        $hook_args['hook_errors'] = $this->get_error();

                        return $hook_args;
                    }

                    if( $file_details['transfer_encoding'] == 'base64' )
                        $file_content = base64_encode( $file_content );

                    $file_details['file_attachment_buffer'] = '--' . $mime_boundary . "\n" .
                        'Content-Type: '.$file_details['content_type'].';'."\n\t".' name="'.$file_details['file_name'].'"'."\n".
                        'Content-Transfer-Encoding: '.$file_details['transfer_encoding']."\n".
                        'Content-Disposition: '.$file_details['content_disposition'].';'."\n\t".' filename="'.$file_details['file_name'].'"'."\n\n".
                        chunk_split( $file_content );

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

        if( empty( $hook_args ) or !is_array( $hook_args )
         or empty( $hook_args['route_settings'] )
         or empty( $hook_args['to'] )
         or !isset( $hook_args['subject'] )
         or empty( $hook_args['full_body'] )
         or empty( $hook_args['internal_vars']['full_headers'] ) or !is_array( $hook_args['internal_vars']['full_headers'] )
         or empty( $hook_args['internal_vars']['mime_boundary'] ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Mandatory SMTP parameters not set.' ) );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\emails\\libraries\\PHS_smtp';
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\emails\libraries\PHS_smtp $smtp_library */
        if( !($smtp_library = $this->load_library( 'phs_smtp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading SMTP library.' ) );

            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_SEND, $this->_pt( 'Error loading SMTP library.' ) );

            return $hook_args;
        }

        $smtp_settings = $hook_args['route_settings'];
        if( !empty( $smtp_settings['smtp_pass'] ) )
            $smtp_settings['smtp_pass'] = PHS_crypt::quick_decode( $smtp_settings['smtp_pass'] );

        $smtp_library->settings( $smtp_settings );

        $email_settings = array(
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
        );

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

        if( empty( $hook_args ) or !is_array( $hook_args )
         or empty( $hook_args['to'] )
         or !isset( $hook_args['subject'] )
         or empty( $hook_args['full_body'] )
         or empty( $hook_args['internal_vars']['full_headers'] ) or !is_array( $hook_args['internal_vars']['full_headers'] ) )
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
