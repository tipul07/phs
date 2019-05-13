<?php
    /** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Roles;

    /** @var \phs\plugins\avt_mobile\PHS_Plugin_Avt_mobile $plugin_obj */
    if( !($plugin_obj = $this->parent_plugin()) )
        return $this->_pt( 'Couldn\'t get parent plugin object.' );

    $cuser_arr = PHS::current_user();

    $can_list_companies = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_LIST_COMPANIES );
    $can_list_addresses = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_LIST_ADDRESSES );
    $can_manage_addresses = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_MANAGE_ADDRESSES );
    $can_list_contacts = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_LIST_CONTACTS );
    $can_manage_contacts = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_MANAGE_CONTACTS );
    $can_list_company_types = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_LIST_COMPANY_TYPES );
    $can_manage_company_types = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_MANAGE_COMPANY_TYPES );
    $can_view_company_reports = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_COMPANY_REPORTS_VIEW );
    $can_export_company_reports = PHS_Roles::user_has_role_units( $cuser_arr, $plugin_obj::ROLEU_COMPANY_REPORTS_EXPORT );

    if( !$can_list_companies
    and !$can_list_addresses and !$can_manage_addresses
    and !$can_list_contacts and !$can_manage_contacts
    and !$can_list_company_types and !$can_manage_company_types
    and !$can_view_company_reports and !$can_export_company_reports )
        return '';

?>
<li><?php echo $this->_pt( 'Companies' )?>
    <ul>
        <?php
        if( $can_list_companies )
        {
            ?><li><a href="<?php echo PHS::url( array(
                                                    'p' => 's2p_companies',
                                                    'c' => 'admin',
                                                    'a' => 'companies_list'
                                                ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'List companies' )?></a></li><?php
        }
        if( $can_view_company_reports or $can_export_company_reports )
        {
            ?><li><a href="<?php echo PHS::url( array(
                                                    'p' => 's2p_companies',
                                                    'c' => 'admin',
                                                    'a' => 'companies_progress'
                                                ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'Companies\' progress' )?></a></li><?php
            ?><li><a href="<?php echo PHS::url( array(
                                                    'p' => 's2p_companies',
                                                    'c' => 'admin',
                                                    'a' => 'companies_financial_report'
                                                ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'Financial report' )?></a></li><?php
        }
        if( $can_list_addresses or $can_manage_addresses )
        {
            ?><li><a href="<?php echo PHS::url( array(
                                                    'p' => 's2p_companies',
                                                    'c' => 'admin',
                                                    'a' => 'addresses_list'
                                                ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'List addresses' )?></a></li><?php
        }
        if( $can_list_contacts or $can_manage_contacts )
        {
            ?><li><a href="<?php echo PHS::url( array(
                                                    'p' => 's2p_companies',
                                                    'c' => 'admin',
                                                    'a' => 'contacts_list'
                                                ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'List contacts' )?></a></li><?php
        }
        if( $can_list_company_types or $can_manage_company_types )
        {
            ?><li><a href="<?php echo PHS::url( array(
                                                    'p' => 's2p_companies',
                                                    'c' => 'admin',
                                                    'a' => 'company_types_list'
                                                ) ) ?>" onfocus="this.blur();"><?php echo $this->_pt( 'List company types' )?></a></li><?php
        }
        ?>
    </ul>
</li>
