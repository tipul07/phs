<?php
namespace phs\plugins\sendgrid;

use \phs\PHS;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Params;
use \phs\system\core\views\PHS_View;

class PHS_Plugin_Sendgrid extends PHS_Plugin
{
    const ERR_VALIDATION = 40000, ERR_TEMPLATE = 40001, ERR_SEND = 40002, ERR_ATTACHMENTS = 40003;

    const LOG_CHANNEL = 'sendgrid.log';

    /** @var \phs\plugins\sendgrid\libraries\PHS_Sendgrid $sendgrid_library */
    private $sendgrid_library = false;

    public function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        $this->load_depencies();
    }

    private function load_depencies()
    {
        $this->reset_error();

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\sendgrid\\libraries\\PHS_Sendgrid';
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\sendgrid\libraries\PHS_Sendgrid $smtp_library */
        if( !($this->sendgrid_library = $this->load_library( 'phs_sendgrid', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading SendGrid library.' ) );

            $this->sendgrid_library = false;

            return false;
        }

        return true;
    }

    public function get_settings_keys_to_obfuscate()
    {
        return [ 'sendgrid_api_key' ];
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
            'sendgrid_api_key' => [
                'display_name' => 'SendGrid API Key',
                'display_hint' => 'SendGrid API key used in API requests',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
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

    public function display_test_sending_emails( $params )
    {
        $params = self::validate_array( $params, self::default_custom_renderer_params() );

        if( !($current_settings = $this->get_plugin_settings()) )
            $current_settings = array();

        $testing_error = '';
        $testing_success = false;

        if( !($test_email_sending_email = PHS_Params::_pg( 'test_email_sending_email', PHS_Params::T_EMAIL )) )
            $test_email_sending_email = '';
        if( !($do_test_email_sending_submit = PHS_Params::_p( 'do_test_email_sending_submit' )) )
            $do_test_email_sending_submit = false;

        if( !empty( $do_test_email_sending_submit ) )
        {
            if( empty( $test_email_sending_email )
             or !PHS_Params::check_type( $test_email_sending_email, PHS_Params::T_EMAIL ) )
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
                    '<strong>Note</strong>: this email is sent using SendGrid plugin ('.$this->instance_plugin_name().' v'.$this->get_plugin_version().')<br/>'."\n".
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

        return $this->quick_render_template_for_buffer( 'test_email_sending', $data_arr );
    }

    public static function default_file_attachment()
    {
        return array(
            'file' => '',
            'file_name' => '',
            'content_type' => 'application/octet-stream',
            'transfer_encoding' => 'base64',
            'content_disposition' => 'attachment', // attachment or inline
            'file_base64_buffer' => '',
        );
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
            elseif( !empty( $email_template_obj ) && $email_template_obj->has_error() )
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
                 || !in_array( $file_details['content_disposition'], [ 'attachment', 'inline' ] ) )
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

        if( !($settings_arr = $this->get_plugin_settings()) )
            $settings_arr = [];
        if( !isset( $settings_arr['max_attachment_size'] ) )
            $settings_arr['max_attachment_size'] = 20971520;

        if( empty( $settings_arr['sendgrid_api_key'] ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Please provide a SendGrid API Key in plugin settings.' ) );

            PHS_Logger::logf( 'ERROR ['.$this->get_simple_error_message().']', self::LOG_CHANNEL );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $this->sendgrid_library )
         || !($email_obj = $this->sendgrid_library->get_sendgrid_instance()) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Error loading SendGrid library.' ) );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $hook_args['to'] )
         || !PHS_Params::check_type( $hook_args['to'], PHS_Params::T_EMAIL ) )
        {
            $this->set_error( self::ERR_SEND, $this->_pt( 'Destination is not an email.' ) );

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if( empty( $hook_args['email_html_body'] ) && empty( $hook_args['email_text_body'] ) )
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
            $hook_args['email_text_body'] = strip_tags( preg_replace( "/[\r\n]+/", "\n",
                                                                      str_ireplace( [ '<p>', '</p>', '<br>', '<br/>', '<br />' ],
                                                                                    "\n",
                                                                                    $hook_args['email_html_body'] ) ) );

        $predefined_headers = [];
        $predefined_headers['X-Sender'] = '<'.$hook_args['from_email'].'>';
        $predefined_headers['Return-Path'] = '<'.$hook_args['from_email'].'>';
        $predefined_headers['X-Mailer'] = 'PHP (PHS-MAILER-'.$this->get_plugin_version().')';
        if( !empty( $hook_args['with_priority'] ) )
            $predefined_headers['X-Priority'] = '1';
        $predefined_headers['X-Script-Time'] = (string)time();

        $final_headers_arr = $predefined_headers;
        if( !empty( $hook_args['custom_headers'] ) && is_array( $hook_args['custom_headers'] ) )
        {
            foreach( $hook_args['custom_headers'] as $key => $value )
                $final_headers_arr[$key] = $value;
        }

        $attach_files = [];
        if( !empty( $hook_args['attach_files'] ) && is_array( $hook_args['attach_files'] ) )
        {
            foreach( $hook_args['attach_files'] as $file_details )
            {
                if( empty( $file_details ) || !is_array( $file_details ) )
                    continue;

                if( !empty( $file_details['file_base64_buffer'] ) )
                    $file_encoded = $file_details['file_base64_buffer'];

                else
                {
                    if( false === ($file_size = @filesize( $file_details['file'] ))
                     || (!empty( $settings_arr['max_attachment_size'] ) && (int)$file_size > $settings_arr['max_attachment_size'])
                     || false === ($file_content = @file_get_contents( $file_details['file'] )) )
                    {
                        $this->set_error( self::ERR_SEND, $this->_pt( 'Couldn\'t obtain attachment file content.' ) );

                        $hook_args['hook_errors'] = $this->get_error();

                        return $hook_args;
                    }

                    $file_encoded = @base64_encode( $file_content );
                    // freeup some memory...
                    unset( $file_content );
                }

                $email_attachment = [
                    $file_encoded,
                    $file_details['content_type'],
                    $file_details['file_name'],
                    $file_details['content_disposition'],
                ];

                $attach_files[] = $email_attachment;
            }
        }

        try
        {
            $email_obj->addTo( $hook_args['to'], (!empty( $hook_args['to_name'] )?$hook_args['to_name']:'') );
            $email_obj->setFrom( $hook_args['from_email'], $hook_args['from_name'] );
            $email_obj->setReplyTo( $hook_args['reply_email'], $hook_args['reply_name'] );
            $email_obj->setSubject( $hook_args['subject'] );
            $email_obj->addHeaders( $final_headers_arr );

            if( !empty( $hook_args['email_html_body'] ) )
                $email_obj->addContent( 'text/html', $hook_args['email_html_body'] );
            if( !empty( $hook_args['email_text_body'] ) )
                $email_obj->addContent( 'text/plain', $hook_args['email_text_body'] );

            if( !empty( $attach_files ) )
                $email_obj->addAttachments( $attach_files );

            $sendgrid = new \SendGrid( $settings_arr['sendgrid_api_key'] );
            if( !($response = $sendgrid->send( $email_obj ))
             || !($http_code = $response->statusCode())
             || ($http_code !== 202 && $http_code !== 200) )
            {
                if( empty( $http_code ) )
                    $http_code = 0;

                $this->set_error( self::ERR_SEND, $this->_pt( 'Error sending email with erro code %s.', $http_code ) );

                PHS_Logger::logf( 'ERROR ['.$this->get_simple_error_message().']', self::LOG_CHANNEL );

                $hook_args['hook_errors'] = $this->get_error();

                return $hook_args;
            }
        } catch( \Exception $e )
        {
            $hook_args['hook_errors'] = self::arr_set_error( self::ERR_SEND, $this->_pt( 'Error sending email.' ) );

            PHS_Logger::logf( 'ERROR sending email ['.$e->getMessage().']', self::LOG_CHANNEL );

            return $hook_args;
        }

        $hook_args['internal_vars']['full_headers'] = $final_headers_arr;
        $hook_args['send_result'] = true;

        return $hook_args;
    }
}
