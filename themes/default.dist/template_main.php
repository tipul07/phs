<?php
    /** @var \phs\system\core\views\PHS_View $this */
    /** @var array $_VIEW_CONTEXT */

    use \phs\libraries\PHS_Action;
    use \phs\libraries\PHS_Language;

    if( !empty( $_VIEW_CONTEXT['action_result'] ) )
        $action_result = $this::validate_array( $_VIEW_CONTEXT['action_result'], PHS_Action::default_action_result() );
    else
        $action_result = PHS_Action::default_action_result();

?><!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo PHS_Language::get_current_language_key( 'browser_lang' )?>" lang="<?php echo PHS_Language::get_current_language_key( 'browser_lang' )?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo PHS_Language::get_current_language_key( 'browser_charset' )?>" />
    <meta name="HandheldFriendly"   content="true" />
    <meta name="MobileOptimized"    content="320">
    <meta name="viewport"       content="user-scalable=no, width=device-width, initial-scale=1.0" />
    <meta name="title"          content="<?php echo $action_result['page_title']?>" />
    <meta name="description"    content="{PAGE_INFO.page_description}" />
    <meta name="keywords"       content="{PAGE_INFO.page_keywords}" />
    <meta name="copyright"      content="Copyright 2013 {PAGE_INFO.site_name}. All Right Reserved." />
    <meta name="author"         content="SQnP.net" />
    <meta name="revisit-after"  content="1 days" />

    <link href="{THEME: _url images/icons/favicon.png}" rel="shortcut icon" />

    <!-- BEGIN: jquery_fancy_upload_style --><link href="{THEME: _url fileuploader.css}" rel="stylesheet" type="text/css" /><!-- END: jquery_fancy_upload_style -->
    <link href="{THEME: _url jquery-ui.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url jquery-ui.theme.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url jquery.checkbox.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url jquery.multiselect.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url css/grid.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url css/animate.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url css/responsive.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url iconsfont/iconsfont.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url css/extra.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url css/style.css}" rel="stylesheet" type="text/css" />
    <link href="{THEME: _url css/style-colors.css}" rel="stylesheet" type="text/css" />

    <script type="text/javascript" src="{PAGE_INFO.template_js}jquery.js"></script>
    <script type="text/javascript" src="{PAGE_INFO.template_js}jquery-ui.js"></script>
    <script type="text/javascript" src="{PAGE_INFO.template_js}jquery.validate.js"></script>
    <script type="text/javascript" src="{PAGE_INFO.template_js}jquery.checkbox.js"></script>
    <script type="text/javascript" src="{PAGE_INFO.template_js}jquery.multiselect.js"></script>

    <script  src="{THEME: _url js/jquery.placeholder.min.js}"></script>
    <script  src="{THEME: _url js/include.js}" ></script>

    <!-- BEGIN: jquery_fancy_upload_javascripts --><script type="text/javascript" src="{PAGE_INFO.template_js}fileuploader.js"></script><!-- END: jquery_fancy_upload_javascripts -->
    <script type="text/javascript" src="{PAGE_INFO.js_datepicker_lang}"></script>
    <script type="text/javascript" src="{PAGE_INFO.js_engine}"></script>
    <script type="text/javascript" src="{PAGE_INFO.template_js}base.js.php"></script>

    <script type="text/javascript">
        $(document).ready(function(){
            $('input:checkbox[rel="skin_chck_big"]').checkbox({cls:'jqcheckbox-big', empty:'{THEME: _url images/empty.png}'});
            $('input:checkbox[rel="skin_chck_small"]').checkbox({cls:'jqcheckbox-small', empty:'{THEME: _url images/empty.png}'});
            $('input:checkbox[rel="skin_checkbox"]').checkbox({cls:'jqcheckbox-checkbox', empty:'{THEME: _url images/empty.png}'});
            $('input:radio[rel="skin_radio"]').checkbox({cls:'jqcheckbox-radio', empty:'{THEME: _url images/empty.png}'});
            $('select[rel="skin_multiple"]').multiselect();
            $('select[rel="skin_single"]').multiselect({header: false, multiple: false, selectedList: 1 });
            // $('*[rel="skin_uniform"]').uniform();

            $.datepicker.setDefaults( $.datepicker.regional["{LANG: @get_language_detail, lang}"] );
        });
    </script>

    <script type="text/javascript">
        $(document).ready(function(){
            $('#login_ptrigger').click(function(){
                $('#lang_popup').hide();
                $('#login_popup').toggle();
            });

            $('#lang_chooser').click(function(){
                $('#login_popup').hide();
                $('#lang_popup').toggle();
            });

            $("#login_frm").validate();
        });
    </script>

    <title><?php echo $action_result['page_title']?></title>
    {PAGE_INFO.page_in_header}
</head>

<body {PAGE_INFO.page_body_extra_tags}>
<div id="container">
    <header id="header">
        <!-- BEGIN: page_header -->
        <div id="header_content">
            <div id="logo">
                <a href="{PAGE_LINKS.main_page}"><img src="{THEME: _url images/logo.png}" alt="{PAGE_INFO.site_name}" title="{PAGE_INFO.site_name}" /></a>
                <div class="clearfix"></div>
            </div>

            <div id="menu">
                <nav>
                    <a id="menu-icon" href="javascript:void(0);"></a>
                    <ul>
                        <!-- BEGIN: guest_menu -->
                        <li><a href="{PAGE_LINKS.main_page}" >{LANG: MT_FT_HOME}</a></li>
                        <li><a href="{PAGE_LINKS.aboutus_page}" >{LANG: MT_FT_ABOUT_US}</a></li>
                        <li><a href="{PAGE_LINKS.how_it_works_page}" >{LANG: MT_FT_HOW_IT_WORKS}</a></li>
                        <li><a href="{PAGE_LINKS.contact_page}" >{LANG: MT_FT_CONTACT_US}</a></li>
                        <!-- END: guest_menu -->
                        <!-- BEGIN: user_menu -->
                        <li><a href="{PAGE_LINKS.dashboard_page}" >{LANG: MT_FT_REPORTS}</a>
                            <ul>
                                <li><a href="{PAGE_LINKS.dashboard_page}">{LANG: MT_FT_REPORTS_DASHBOARD}</a></li>
                                <li><a href="{PAGE_LINKS.reports_smsout_page}">{LANG: MT_FT_REPORTS_SMSOUT}</a></li>
                                <li><a href="#">{LANG: MT_FT_REPORTS_CC}</a></li>
                            </ul></li>
                        <li><a href="javascript:void(0);" >{LANG: MT_FT_ACTIONS}</a>
                            <ul>
                                <li><a href="{PAGE_LINKS.sendsms_page}">{LANG: MT_FT_ACTIONS_SEND_SMS}</a></li>
                            </ul></li>
                        <!-- END: user_menu -->
                    </ul>
                </nav>
                <div id="user_info">
                    <ul>
                        <!-- BEGIN: admin_link -->
                        <li><a href="{PAGE_LINKS.admin_menu}">{LANG: MT_ADMIN_MENU}</a></li>
                        <!-- END: admin_link -->

                        <!-- BEGIN: quick_profile -->
                        <li><a href="{PAGE_LINKS.edit_profile}" title="{LANG: MT_EDIT_PROFILE}">{LANG: MT_HELLO} {USER_INFO.nick}</a></li>
                        <li><a href="{PAGE_LINKS.logout}">{LANG: MAIN_MENU_LOGOUT}</a></li>
                        <!-- END: quick_profile -->

                        <!-- BEGIN: quick_login -->
                        <li><a href="{PAGE_LINKS.join_page}">{LANG: REGISTER}</a></li>
                        <li><a href="javascript:void(0);" id="login_ptrigger" title="{LANG: LOGIN}">
                                <div class="arrow"></div>
                                <span>{LANG: LOGIN}</span>
                                <span style="padding: 0 5px;"><img src="{THEME: _url images/ico_man.gif}" style="vertical-align:middle"/></span>
                            </a>
                            <div id="login_popup" class="submenu" style="display: none; right: 35px; padding-top:20; position: absolute; z-index: 10;">
                                <div class="arrow-up" style="left: 200px;"></div>
                                <form id="login_frm" method="post" action="{PAGE_LINKS.login}" class="wpcf7">
                                    <div>
                                        <label>
                                            <input id="nick" class="wpcf7-text" type="text" placeholder="{LANG: FORM_USERNAME}" name="nick" required title="{LANG: FORM_VALID_NICK_REQURIED}">
                                        </label>
                                    </div>
                                    <div>
                                        <label>
                                            <input id="pass" class="wpcf7-text" type="password" placeholder="{LANG: FORM_PASSWORD}" name="pass" required title="{LANG: FORM_VALID_PASS_REQURIED}">
                                        </label>
                                    </div>
                                    <div>
                                        <div style="float: left;"><a href="{PAGE_LINKS.forgot}" class="smlink" style="line-height: 32px;">{LANG: LOGIN_FORGOT_PASSWORD}</a></div>
                                        <div style="float: right; height: 40px;">
                                            <input type="submit" value="{LANG: LOGIN}" name="submit" />
                                        </div>
                                        <div class="clearfix"> </div>
                                    </div>
                                </form>
                            </div>
                        </li>
                        <!-- END: quick_login -->

                        <!-- BEGIN: language_selection -->
                        <?php echo $this->sub_view( 'template_language_selection' ); ?>
                        <!-- END: language_selection -->

                    </ul>
                </div>
            </div>

            <div class="clearfix"></div>
        </div>
        <!-- END: page_header -->
    </header>

    <div id="content"><?php echo $action_result['buffer']?></div>

    <footer id="footer">
        <!-- BEGIN: page_footer -->
        <div id="footer_content">
            <div class="footerlinks">
                <a href="{PAGE_LINKS.contact_page}" >{LANG: MT_FT_CONTACT_US}</a> |
                <a href="{PAGE_LINKS.aboutus_page}" >{LANG: MT_FT_ABOUT_US}</a> |
                <a href="{PAGE_LINKS.contact_page}?cdomi=1" >{LANG: MT_FT_SITE_SUPPORT}</a> |
                <a href="{PAGE_LINKS.terms_and_conditions}" >{LANG: MT_FT_TERMS_AND_CONDS}</a> |
                <a href="{PAGE_LINKS.faq_page}" >{LANG: MT_FT_FAQ}</a>
            </div>
            <div class="clearfix"></div>
            <div style="float: right"><?php echo PHS_SITE_NAME?> &copy; <?php echo date( 'Y' )?> <?php echo $this::_t( 'All rights reserved.' )?> {PAGE_INFO.debug_data} &nbsp;</div>
        </div>
        <!-- END: page_footer -->
    </footer>
</div>
</body>
</html>
<!-- END: main -->

