<?php

class PHS_Model_Accounts_details extends PHS_Model
{
    const HOOK_LEVELS = 'phs_accounts_levels', HOOK_STATUSES = 'phs_accounts_statuses', HOOK_SETTINGS = 'phs_accounts_settings';

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return array( 'users_details' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'users_details';
    }

    /**
     * Performs any necessary actions when upgrading model from $old_version to $new_version
     *
     * @param string $old_version Old version of model
     * @param string $new_version New version of model
     *
     * @return bool true on success, false on failure
     */
    protected function upgrade( $old_version, $new_version )
    {
        return true;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_insert_prepare_params( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( !empty( $accounts_settings['email_mandatory'] )
        and empty( $params['fields']['email'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide an email.' ) );
            return false;
        }

        if( !empty( $params['fields']['email'] )
        and !PHS_params::check_type( $params['fields']['email'], PHS_params::T_EMAIL ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid email.' ) );
            return false;
        }

        if( empty( $params['fields']['nick'] )
        and !empty( $params['fields']['email'] )
        and !empty( $accounts_settings['replace_nick_with_email'] ) )
            $params['fields']['nick'] = $params['fields']['email'];

        if( empty( $params['fields']['nick'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide an username.' ) );
            return false;
        }

        if( empty( $params['fields']['level'] ) )
            $params['fields']['level'] = self::LVL_MEMBER;
        if( empty( $params['fields']['status'] ) )
        {
            if( empty( $accounts_settings['account_requires_activation'] ) )
                $params['fields']['status'] = self::STATUS_ACTIVE;
            else
                $params['fields']['status'] = self::STATUS_INACTIVE;
        }

        if( !$this->valid_level( $params['fields']['level'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid account level.' ) );
            return false;
        }

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid status.' ) );
            return false;
        }

        if( empty( $params['user_details'] ) )
            $params['user_details'] = array();

        if( empty( $params['fields']['pass'] ) )
            $params['fields']['pass'] = self::generate_password( (!empty( $accounts_settings['min_password_length'] )?$accounts_settings['min_password_length']+3:8) );

        if( empty( $params['fields']['pass_salt'] ) )
            $params['fields']['pass_salt'] = self::generate_password( (!empty( $accounts_settings['pass_salt_length'] )?$accounts_settings['pass_salt_length']+3:8) );

        $params['fields']['pass_clear'] = PHS_crypt::quick_encode( $params['fields']['pass'] );
        $params['fields']['pass'] = self::encode_pass( $params['fields']['pass'], $params['fields']['pass_salt'] );

        $now_date = date( self::DATETIME_DB );

        $params['fields']['status_date'] = $now_date;

        if( empty( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB );
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    final protected function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'users':
                $return_arr = array(
                    self::T_DETAILS_KEY => array(
                        'comment' => 'Account information (minimal details required for login)',
                    ),

                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'nick' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'nullable' => true,
                        'index' => true,
                    ),
                    'pass_salt' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'pass' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'pass_clear' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'nullable' => true,
                    ),
                    'email' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'index' => true,
                        'nullable' => true,
                    ),
                    'email_verified' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'default' => 0,
                    ),
                    'added_by' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ),
                    'level' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                    ),
                    'deleted' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;

            case 'users_details':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'title' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '20',
                        'nullable' => true,
                    ),
                    'fname' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                        'nullable' => true,
                    ),
                    'lname' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                        'nullable' => true,
                    ),
                    'phone' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'company' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                        'nullable' => true,
                    ),
                );
            break;

            case 'online':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'wid' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'index' => true,
                        'nullable' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'auid' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'host' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'idle' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'connected' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'expire_secs' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'location' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                    ),
                );
                break;
        }

        return $return_arr;
    }

}
