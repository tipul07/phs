<?php

    if( !defined( 'PHS_VERSION' ) )
        exit;

    use \phs\PHS;
    use \phs\libraries\PHS_Roles;

    if( ($core_models = PHS::get_core_modules())
    and is_array( $core_models ) )
    {
        foreach( $core_models as $core_model )
        {
            if( ($model_obj = PHS::load_model( $core_model )) )
                $model_obj->check_installation();
        }
    }

    //
    //  BEGIN Predefined role units array helper
    //
    $guest_role_units = array(

        PHS_Roles::ROLEU_CONTACT_US => array(
            'name' => 'Contact Us',
            'description' => 'Allow user to use contact us form',
        ),
        PHS_Roles::ROLEU_REGISTER => array(
            'name' => 'Register',
            'description' => 'Allow user to use registration form',
        ),

    );

    $members_role_units = array(

        PHS_Roles::ROLEU_CONTACT_US => array(
            'name' => 'Contact Us',
            'description' => 'Allow user to use contact us form',
        ),

    );

    $admin_role_units = array(

        // Roles...
        PHS_Roles::ROLEU_MANAGE_ROLES => array(
            'name' => 'Manage roles',
            'description' => 'Allow user to define or edit roles',
        ),
        PHS_Roles::ROLEU_LIST_ROLES => array(
            'name' => 'List roles',
            'description' => 'Allow user to view defined roles',
        ),

        // Plugins...
        PHS_Roles::ROLEU_MANAGE_PLUGINS => array(
            'name' => 'Manage plugins',
            'description' => 'Allow user to manage plugins',
        ),
        PHS_Roles::ROLEU_LIST_PLUGINS => array(
            'name' => 'List plugins',
            'description' => 'Allow user to list plugins',
        ),

        // Accounts...
        PHS_Roles::ROLEU_MANAGE_ACCOUNTS => array(
            'name' => 'Manage accounts',
            'description' => 'Allow user to manage accounts',
        ),
        PHS_Roles::ROLEU_LIST_ACCOUNTS => array(
            'name' => 'List accounts',
            'description' => 'Allow user to list accounts',
        ),
        PHS_Roles::ROLEU_LOGIN_SUBACCOUNT => array(
            'name' => 'Login sub-account',
            'description' => 'Allow user to login as other user',
        ),
    );

    $predefined_roles_arr = array(
        PHS_Roles::ROLE_GUEST => array(
            'name' => 'Guests',
            'description' => 'Role used by non-logged visitors',
            'role_units' => array(),
        ),
        PHS_Roles::ROLE_MEMBER => array(
            'name' => 'Member accounts',
            'description' => 'Default functionality role (what normal members can do)',
            'role_units' => array(),
        ),
        PHS_Roles::ROLE_ADMIN => array(
            'name' => 'Admin accounts',
            'description' => 'Role assigned to admin accounts.',
            'role_units' => array(),
        ),
    );
    //
    //  END Predefined role units array helper
    //

    //
    //  BEGIN Define predefined role units
    //
    $guest_role_slugs = array();
    $members_role_slugs = array();
    $admin_role_slugs = array();

    foreach( $guest_role_units as $role_unit_slug => $role_unit_details )
    {
        $role_unit_details_arr = array();
        $role_unit_details_arr['slug'] = $role_unit_slug;
        $role_unit_details_arr['name'] = $role_unit_details['name'];
        $role_unit_details_arr['description'] = $role_unit_details['description'];

        if( !($role_unit = PHS_Roles::register_role_unit( $role_unit_details_arr )) )
            return PHS_Roles::st_get_error();

        $guest_role_slugs[$role_unit['slug']] = true;
    }

    foreach( $members_role_units as $role_unit_slug => $role_unit_details )
    {
        $role_unit_details_arr = array();
        $role_unit_details_arr['slug'] = $role_unit_slug;
        $role_unit_details_arr['name'] = $role_unit_details['name'];
        $role_unit_details_arr['description'] = $role_unit_details['description'];

        if( !($role_unit = PHS_Roles::register_role_unit( $role_unit_details_arr )) )
            return PHS_Roles::st_get_error();

        $members_role_slugs[$role_unit['slug']] = true;
    }

    $admin_role_slugs = array_merge( $guest_role_slugs, $members_role_slugs );

    foreach( $admin_role_units as $role_unit_slug => $role_unit_details )
    {
        $role_unit_details_arr = array();
        $role_unit_details_arr['slug'] = $role_unit_slug;
        $role_unit_details_arr['name'] = $role_unit_details['name'];
        $role_unit_details_arr['description'] = $role_unit_details['description'];

        if( !($role_unit = PHS_Roles::register_role_unit( $role_unit_details_arr )) )
            return PHS_Roles::st_get_error();

        $admin_role_slugs[$role_unit['slug']] = true;
    }
    //
    //  END Define predefined role units
    //

    //
    //  BEGIN Define predefined roles
    //
    $predefined_roles_arr[PHS_Roles::ROLE_GUEST]['role_units'] = array_keys( $guest_role_slugs );
    $predefined_roles_arr[PHS_Roles::ROLE_MEMBER]['role_units'] = array_keys( $members_role_slugs );
    $predefined_roles_arr[PHS_Roles::ROLE_ADMIN]['role_units'] = array_keys( $admin_role_slugs );

    foreach( $predefined_roles_arr as $role_slug => $role_details )
    {
        $role_details_arr = array();
        $role_details_arr['slug'] = $role_slug;
        $role_details_arr['name'] = $role_details['name'];
        $role_details_arr['description'] = $role_details['description'];
        $role_details_arr['predefined'] = 1;
        $role_details_arr['{role_units}'] = $role_details['role_units'];

        if( !($role_arr = PHS_Roles::register_role( $role_details_arr )) )
            return PHS_Roles::st_get_error();
    }
    //
    //  END Define predefined roles
    //

    return true;
