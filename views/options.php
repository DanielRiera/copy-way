<?php
if(!defined('ABSPATH')) { exit; }
$newsletterActive = get_option('copy-way-newsletter', '0');
$user = wp_get_current_user();
if(isset($_POST['action'])) {
    if ( isset($_POST['save_option_nonce']) && wp_verify_nonce(  $_POST['save_option_nonce'], 'copyway_nonce' ) ) {
        if($_POST['action'] == 'save_options') {
           update_option('copyway_activate',sanitize_text_field( isset($_POST['copyway_activate']) ? 1 : 0 ));
           update_option('copyway_plugin_folder',sanitize_text_field( isset($_POST['copyway_plugin_folder']) ? 1 : 0 ));
           update_option('copyway_theme_folder',sanitize_text_field( isset($_POST['copyway_theme_folder']) ? 1 : 0 ));
           update_option('copyway_uploads_folder',sanitize_text_field( isset($_POST['copyway_uploads_folder']) ? 1 : 0 ));
           update_option('copyway_db',sanitize_text_field( isset($_POST['copyway_db']) ? 1 : 0 ));
           
        }

        if($_POST['action'] == 'create_copy') {
            $response = COPYWAY::init_copy();
            var_dump($response);
        }

    }

    if ( isset($_POST['action']) && isset($_POST['add_sub_nonce']) && $_POST['action'] == 'adsub' && wp_verify_nonce(  $_POST['add_sub_nonce'], 'wcv_nonce' ) ) {
        $sub = wp_remote_post( 'https://mailing.danielriera.net', [
            'method'      => 'POST',
            'timeout'     => 2000,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'body'        => array(
                'm' => $_POST['action'],
                'd' => base64_encode(json_encode($_POST))
            ),
            'cookies'     => array()
        ]);
        $result = json_decode($sub['body'],true);

        if($result['error']) {
            $class = 'notice notice-error';
            $message = __( 'An error has occurred, try again.', 'copy-way' );
            printf( '<div class="%s"><p>%s</p></div>', $class, $message );
        }else{
            $class = 'notice notice-success';
            $message = __( 'Welcome newsletter :)', 'copy-way' );
            
            printf( '<div class="%s"><p>%s</p></div>', $class, $message );

            update_option('copy-way-newsletter' , '1');
        }
    }
}
?>
<style>
form#new_subscriber {
    background: #FFF;
    padding: 10px;
    margin-bottom: 50px;
    border-radius: 12px;
    border: 1px solid #CCC;
    text-align: center;
}

form#new_subscriber input.email {
    width: 100%;
    text-align: center;
    padding: 10px;
}

form#new_subscriber input[type='submit'] {
    width: 100%;
    margin-top: 10px;
    border: 0;
    background: #3c853c;
    color: #FFF;
}
table th {
    min-width:350px
}
</style>
<div class="wrap">
    <h1><?=__('Copy Way - Plugin WordPress', 'copy-way')?></h1>
    <p><?=__('Create copy for your important folders site.','copy-way')?></p>
    <?php if($newsletterActive == '0') { ?>
            <form class="simple_form form form-vertical" id="new_subscriber" novalidate="novalidate" accept-charset="UTF-8" method="post">
                <input name="utf8" type="hidden" value="&#x2713;" />
                <input type="hidden" name="action" value="adsub" />
                <?php wp_nonce_field( 'wcv_nonce', 'add_sub_nonce' ); ?>
                <h3><?=__('Do you want to receive the latest?','copy-way')?></h3>
                <p><?=__('Thank you very much for using our plugin, if you want to receive the latest news, offers, promotions, discounts, etc ... Sign up for our newsletter. :)', 'copy-way')?></p>
                <div class="form-group email required subscriber_email">
                    <label class="control-label email required" for="subscriber_email"><abbr title="<?=__('Required', 'copy-way')?>"> </abbr></label>
                    <input class="form-control string email required" type="email" name="e" id="subscriber_email" value="<?=$user->user_email?>" />
                </div>
                <input type="hidden" name="n" value="<?=bloginfo('name')?>" />
                <input type="hidden" name="w" value="<?=bloginfo('url')?>" />
                <input type="hidden" name="g" value="1" />
                <input type="text" name="anotheremail" id="anotheremail" style="position: absolute; left: -5000px" tabindex="-1" autocomplete="off" />
            <div class="submit-wrapper">
            <input type="submit" name="commit" value="<?=__('Submit', 'copy-way')?>" class="button" data-disable-with="<?=__('Processing', 'copy-way')?>" />
            </div>
        </form>
    <?php } ?>
    <div style="">
        <a href="https://www.paypal.com/donate/?hosted_button_id=EZ67DG78KMXWQ" target="_blank" style="text-decoration: none;font-size: 18px;border: 1px solid #333;padding: 10px;display: block;width: fit-content;border-radius: 10px;background: #FFF;"><?=__('Buy me a Coffe? :)','copy-way')?></a>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="save_options" />
        <?php wp_nonce_field( 'copyway_nonce', 'save_option_nonce' ); ?>
        <table class="form-table">
        
            <tr valign="top">
                <th scope="row"><?=__('Active Copy Way System', 'copy-way')?>
                    <p class="description"><?=__('Active Copy Way','copy-way')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="copyway_activate" value="1" <?=checked('1', get_option('copyway_activate', '0'))?> /></label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?=__('Plugins', 'copy-way')?>
                    <p class="description"><?=__('Active this for copy plugin folder','copy-way')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="copyway_plugin_folder" value="1" <?=checked('1', get_option('copyway_plugin_folder', '0'))?> /></label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?=__('Current Theme', 'copy-way')?>
                    <p class="description"><?=__('Active this for copy current theme','copy-way')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="copyway_theme_folder" value="1" <?=checked('1', get_option('copyway_theme_folder', '0'))?> /></label>
                </td>
            </tr>
    
            <tr valign="top">
                <th scope="row"><?=__('Uploads', 'copy-way')?>
                    <p class="description"><?=__('Active this for copy uploads folder','copy-way')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="copyway_uploads_folder" value="1" <?=checked('1', get_option('copyway_uploads_folder', '0'))?> /></label>
                </td>
            </tr>
    
            <tr valign="top">
                <th scope="row"><?=__('Database', 'copy-way')?>
                    <p class="description"><?=__('Active this for create dump for database','copy-way')?></p>
                    
                </th>
                <td>
                    <?php 
                    $disabled = true;
                    if(`mysqldump`) {
                        $disabled = false;
                    }else{
                        if(`/Applications/MAMP/Library/bin/mysqldump`) {
                            $disabled = false;
                        }
                    }
                    ?>
                    <label><input <?=$disabled ? 'disabled' : ''?> type="checkbox" name="copyway_db" value="1" <?=checked('1', get_option('copyway_db', '0'))?> /></label>
                    <?php
                    if($disabled) {
                        echo __('You cant create database dump, active `mysqldump` on your server', 'copy-way');
                    }
                    ?>
                </td>
            </tr>
    
            
        </table>
        <button type="submit" class="button" name="action" value="save_options"><?=__('Save')?></button>
        <button type="submit" class="button" name="action" value="create_copy"><?=__('Copy')?></button>
        </form>
    
    
        <h1>Backup Created</h1>
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th><?=__('File', 'copy-way')?></th>
                    <th><?=__('Size', 'copy-way')?></th>
                    <th><?=__('Types', 'copy-way')?></th>
                    <th><?=__('Options', 'copy-way')?></th>
                </tr>
            </thead>
            <tbody>
            <?php
                $ignore = array('.','..','cgi-bin','.DS_Store','index.php', '.htaccess');
                $files = scandir(COPYWAY_FOLDER);
    
                foreach($files as $t) {
                    if(in_array($t, $ignore)) continue;
                    $file = rtrim(COPYWAY_FOLDER, '/') . '/' . $t;
                    if (!is_dir($file)) {
                        $filesize = COPYWAY::human_filesize(filesize($file));
                        $name = COPYWAY::get_human_name($t);
                        echo "<tr>
                        <td>{$name}</td>
                        <td>{$filesize}</td>
                        <td>".COPYWAY::get_types_human($t)."</td>
                        <td>".COPYWAY::get_options_files($t)."</td>
                        </tr>";
                        
                    }
                }
            ?>
            </tbody>
        </table>
</div>
