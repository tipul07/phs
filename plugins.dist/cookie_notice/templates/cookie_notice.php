<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS_Ajax;

    /** @var \phs\plugins\cookie_notice\PHS_Plugin_Cookie_notice $plugin_obj */
    if( !($plugin_obj = $this->view_var( 'plugin_obj' ))
     or $plugin_obj->agreed_cookies() )
        return '';

    if( !($rejection_url = $this->view_var( 'rejection_url' )) )
        $rejection_url = '';
    if( !($read_more_url = $this->view_var( 'read_more_url' )) )
        $read_more_url = '';
?>
<div id="phs_cookie_policy_agreement">
    <span><?php echo $this->_pt( 'This site uses cookies which help us deliver a better user experience. By browsing the site, you agree to cookies usage.' )?></span>
    <div class="phs_cookie_policy_agreement_actions">
    <?php
    if( !empty( $read_more_url ) )
    {
        ?> <a href="<?php echo $read_more_url?>" class="btn btn-primary btn-small" target="_blank" rel="nofollow" id="phs_cookie_policy_more_link"><?php echo $this->_pt( 'More info' )?></a> <?php
    }
    if( !empty( $rejection_url ) )
    {
        ?> <a href="<?php echo $rejection_url?>" class="btn btn-primary btn-small" rel="nofollow" id="phs_cookie_policy_rejection_link"><?php echo $this->_pt( 'I don\'t agree' )?></a> <?php
    }
    ?>
    <a href="javascript:void(0)" class="btn btn-primary btn-small" onclick="phs_cookie_policy_agree()" id="phs_cookie_policy_agree_link"><?php echo $this->_pt( 'I AGREE' )?></a>
    </div>
</div>
<script type="text/javascript">
function phs_cookie_policy_agree()
{
    var container_obj = $("#phs_cookie_policy_agreement");
    if( !container_obj )
        return;

    container_obj.hide();

    var ajax_params = {
        cache_response: false,
        method: 'post',
        url_data: { agree_cookies: 1 },
        data_type: 'json',

        onsuccess: function( response, status, ajax_obj, response_data ) {
        },

        onfailed: function( ajax_obj, status, error_exception ) {
        }
    };

    var ajax_obj = PHS_JSEN.do_ajax( "<?php echo PHS_Ajax::url( array( 'p' => 'cookie_notice', 'a' => 'cookie_notice_ajax' ) )?>", ajax_params );
}
</script>
