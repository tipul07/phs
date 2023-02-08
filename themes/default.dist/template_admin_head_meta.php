<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Language;

$action_result = $this->get_action_result();
?>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo PHS_Language::get_current_language_key('browser_charset'); ?>" />
<meta name="HandheldFriendly"   content="true" />
<meta name="MobileOptimized"    content="320" />
<meta name="viewport"           content="user-scalable=no, width=device-width, initial-scale=1.0" />
<meta name="title"              content="<?php echo $action_result['page_settings']['page_title']; ?>" />
<meta name="description"        content="<?php echo $action_result['page_settings']['page_description']; ?>" />
<meta name="keywords"           content="<?php echo $action_result['page_settings']['page_keywords']; ?>" />
<meta name="copyright"          content="Copyright <?php echo date('Y').' - '.PHS_SITE_NAME; ?>. All Right Reserved." />
<meta name="author"             content="PHS Framework" />
<meta name="revisit-after"      content="1 days" />
<?php
if (($favicon_url = $this->get_resource_url('images/favicon.ico'))
&& ($favicon_file = $this->get_resource_path('images/favicon.ico'))
&& @file_exists($favicon_file)) {
    ?>
    <link href="<?php echo $favicon_url; ?>" rel="shortcut icon" />
    <?php
} elseif (($favicon_url = $this->get_resource_url('images/favicon.png'))
&& ($favicon_file = $this->get_resource_path('images/favicon.png'))
&& @file_exists($favicon_file)) {
    ?>
    <link href="<?php echo $favicon_url; ?>" rel="shortcut icon" />
    <?php
}
