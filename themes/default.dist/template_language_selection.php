<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\libraries\PHS_Language;

    if( !($defined_languages = PHS_Language::get_defined_languages()) )
        return '';

    if( !($current_language = PHS_Language::get_current_language())
     or empty( $defined_languages[$current_language] ) )
        $current_language = PHS_Language::get_default_language();
?>
<li>
    <a href="javascript:void(0);" id="lang_chooser" title="<?php echo $this::_t( 'Select language' )?>">
        <div class="arrow"></div>
        <?php
            if( !empty( $defined_languages[$current_language]['flag_file'] ) and !empty( $defined_languages[$current_language]['dir'] ) and !empty( $defined_languages[$current_language]['www'] )
            and @file_exists( $defined_languages[$current_language]['dir'].$defined_languages[$current_language]['flag_file'] ) )
            {
                ?><span><img src="<?php echo $defined_languages[$current_language]['www'].$defined_languages[$current_language]['flag_file']?>" /></span><?php
            } else
            {
                ?><span><?php echo $defined_languages[$current_language]['title']?></span><?php
            }
        ?>
    </a>
    <div id="lang_popup" class="submenu" style="display: none;">
        <div class="arrow-up" style="left: 100px;"></div>
        <!-- BEGIN: language_item -->
        <?php
        foreach( $defined_languages as $lang => $lang_details )
        {
            $language_flag = '';
            if( !empty( $lang_details['flag_file'] ) and !empty( $lang_details['dir'] ) and !empty( $lang_details['www'] )
            and @file_exists( $lang_details['dir'].$lang_details['flag_file'] ) )
                $language_flag = '<span style="margin: 0 5px;"><img src="'.$lang_details['www'].$lang_details['flag_file'].'" /></span>';

            $language_link = 'javascript:alert( "In work..." )';

            $language_class = ($lang==$current_language?'smlink-active':'smlink');

            ?>
            <div style="float: left; width: 120px; clear:both;"><?php echo $language_flag?><a href="<?php echo $language_link?>" class="<?php echo $language_class?>"><?php echo $lang_details['title']?></a></div>
            <?php
        }
        ?>
        <!-- END: language_item -->
    </div>
</li>
