<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($img_width = $this->context_var( 'default_widht' )) )
        $img_width = 200;
    if( !($img_height = $this->context_var( 'default_height' )) )
        $img_height = 50;

?><img src="<?php echo PHS::url( array( 'p' => 'captcha' ) );?>" style="width: <?php echo $img_width;?>;height: <?php echo $img_height;?>;" class="captcha-img" />
