<?php

namespace phs\plugins\emails;

use phs\libraries\PHS_params;
use \phs\PHS;
use \phs\PHS_crypt;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Error;
use \phs\system\core\views\PHS_View;
use \phs\plugins\emails\libraries\PHS_smtp;

class PHS_Plugin_Emails extends PHS_Plugin
{
    const ERR_VALIDATION = 40000, ERR_TEMPLATE = 40001, ERR_SEND = 40002;

    const DEFAULT_ROUTE = 'default';

    public static $MAIL_AUTH_KEY = 'XMailAuth';

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
        if( !($smtp_library = $this->load_library( 'phs_smtp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, self::_t( 'Error loading SMTP library.' ) );

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
        return '1.0.0';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'name' => 'Email Sending Plugin',
            'description' => 'Handles all emailing functionality in platform.',
        );
    }

    public function get_models()
    {
        return array();
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     * @return array
     */
    public function get_default_settings()
    {
        return array(
            'template_main' => $this->template_resource_from_file( 'template_emails' ), // default template
            'email_vars' => array(
                'site_name' => PHS_SITE_NAME,
                'from_name' => PHS_SITE_NAME,
                'from_email' => 'office@'.PHS_DOMAIN,
                'from_noreply' => 'noreply@'.PHS_DOMAIN,
            ),
            'routes' => array( self::DEFAULT_ROUTE => self::get_default_smtp_settings() ),
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

        if( !($plugin_settings = $this->get_plugin_db_settings())
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

    public function init_email_hook_args( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() );

        if( !($settings_arr = $this->get_plugin_db_settings())
         or empty( $settings_arr['template_main'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, self::_t( 'Couldn\'t load template from plugin settings.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        $template_params = array();
        $template_params['theme_relative_dirs'] = array( PHS_EMAILS_DIRS );

        if( !($email_main_template = PHS_View::validate_template_resource( $settings_arr['template_main'], $template_params )) )
        {
            $this->set_error( self::ERR_TEMPLATE, self::_t( 'Failed validating main email template file.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( empty( $hook_args['template'] )
         or !($email_template = PHS_View::validate_template_resource( $hook_args['template'], $template_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_TEMPLATE );
            else
                $this->set_error( self::ERR_TEMPLATE, self::_t( 'Failed validating email template file.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( $hook_args['route'] === false )
            $hook_args['route'] = self::DEFAULT_ROUTE;

        if( empty( $hook_args['native_mail_function'] ) )
        {
            if( empty( $hook_args['route'] )
             or !($route_settings = $this->get_smtp_route_settings( $hook_args['route'] )) )
            {
                $this->set_error( self::ERR_TEMPLATE, self::_t( 'Invalid SMTP route.' ) );

                $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

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
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = array(
            'hook_args' => $hook_args,
            'email_content' => '',
        );

        if( !($email_template_obj = PHS_View::init_view( $email_template, $view_params ))
         or !($email_content_buffer = $email_template_obj->render()) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            elseif( !empty( $email_template_obj ) and $email_template_obj->has_error() )
                $this->copy_error( $email_template_obj );

            if( !$this->has_error() )
                $this->set_error( self::ERR_TEMPLATE, self::_t( 'Rendering template %s resulted in empty buffer.', ($email_template_obj?$email_template_obj->get_template():'(???)') ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

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
                $this->set_error( self::ERR_TEMPLATE, self::_t( 'Rendering template %s resulted in empty buffer.', ($main_template_obj?$main_template_obj->get_template():'(???)') ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        $hook_args['email_html_body'] = $email_html_body;

        if( !empty( $hook_args['also_send'] ) )
        {
            if( !($new_hook_args = $this->send_email( $hook_args )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_SEND, self::_t( 'Error sending email.' ) );

                $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

                return false;
            }

            $hook_args = $new_hook_args;
        }

        return $hook_args;
    }

    public function send_email( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() );

        if( empty( $hook_args['to'] )
         or !PHS_params::check_type( $hook_args['to'], PHS_params::T_EMAIL ) )
        {
            $this->set_error( self::ERR_SEND, self::_t( 'To parameter is not an email.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( empty( $hook_args['email_html_body'] ) and empty( $hook_args['email_text_body'] ) )
        {
            $this->set_error( self::ERR_SEND, self::_t( 'Email body is empty.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

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
                                  $hook_args['email_html_body'] . "\n\n" .
                                  '--' . $mime_boundary . '--' . "\n";

        $hook_args['internal_vars']['full_headers'] = $final_headers_arr;

        $hook_args['internal_vars']['mime_boundary'] = $mime_boundary;

        $hook_args['internal_vars']['to_full_value'] = '';
        if( !empty( $hook_args['to_name'] ) )
            $hook_args['internal_vars']['to_full_value'] .= $hook_args['to_name'].' ';
        $hook_args['internal_vars']['to_full_value'] .= '<'.$hook_args['to'].'>';

        if( !empty( $hook_args['native_mail_function'] ) )
        {
            if( !($new_hook_args = $this->_send_email_native( $hook_args )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_SEND, self::_t( 'Error sending email using native function.' ) );

                $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

                return $hook_args;
            }

            $hook_args = $new_hook_args;
        } else
        {
            if( !($new_hook_args = $this->_send_email_smtp( $hook_args )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_SEND, self::_t( 'Error sending email using SMTP function.' ) );

                $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

                return $hook_args;
            }

            $hook_args = $new_hook_args;
        }

        return $hook_args;
    }

    private function _send_email_smtp( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() );

        if( empty( $hook_args['route_settings'] )
         or empty( $hook_args['to'] )
         or !isset( $hook_args['subject'] )
         or empty( $hook_args['full_body'] )
         or empty( $hook_args['internal_vars']['full_headers'] ) or !is_array( $hook_args['internal_vars']['full_headers'] )
         or empty( $hook_args['internal_vars']['mime_boundary'] ) )
        {
            $this->set_error( self::ERR_SEND, self::_t( 'Mandatory SMTP parameters not set.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\emails\\libraries\\PHS_smtp';
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\emails\libraries\PHS_smtp $smtp_library */
        if( !($smtp_library = $this->load_library( 'phs_smtp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, self::_t( 'Error loading SMTP library.' ) );

            return false;
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

        if( !($hook_args['send_result'] = $smtp_library->send()) )
        {
            if( $smtp_library->has_error() )
                $this->copy_error( $smtp_library );

            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, self::_t( 'Error sending email using SMTP library.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        return $hook_args;
    }

    private function _send_email_native( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_init_email_hook_args() );

        if( empty( $hook_args['to'] )
         or !isset( $hook_args['subject'] )
         or empty( $hook_args['full_body'] )
         or empty( $hook_args['internal_vars']['full_headers'] ) or !is_array( $hook_args['internal_vars']['full_headers'] ) )
        {
            $this->set_error( self::ERR_SEND, self::_t( 'Mandatory native mail parameters not set.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

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

        $hook_args['send_result'] = @mail( $to, $hook_args['subject'], $hook_args['full_body'], $full_headers_str );

        return $hook_args;
    }
}
