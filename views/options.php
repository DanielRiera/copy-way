<?php
if(!defined('ABSPATH')) { exit; }
$newsletterActive = get_option('cwp-newsletter', '0');
$user = wp_get_current_user();
if(isset($_POST['action'])) {
    if ( isset($_POST['save_option_nonce']) and wp_verify_nonce(  sanitize_text_field($_POST['save_option_nonce']), 'cwp_nonce' ) and current_user_can('manage_options')  ) {
        if(sanitize_text_field($_POST['action']) == 'save_options') {
           update_option('cwp_activate',sanitize_text_field( isset($_POST['cwp_activate']) ? 1 : 0 ));
           update_option('cwp_plugin_folder',sanitize_text_field( isset($_POST['cwp_plugin_folder']) ? 1 : 0 ));
           update_option('cwp_theme_folder',sanitize_text_field( isset($_POST['cwp_theme_folder']) ? 1 : 0 ));
           update_option('cwp_uploads_folder',sanitize_text_field( isset($_POST['cwp_uploads_folder']) ? 1 : 0 ));
           update_option('cwp_db',sanitize_text_field( isset($_POST['cwp_db']) ? 1 : 0 ));
           
        }

        if($_POST['action'] == 'create_copy') {
            $response = CWP::init_copy();
            $class = 'notice notice-success';
            if(isset($response['error']) and $response['error']) {
                $class = 'notice notice-error';
            }
            $message = sprintf( '<div class="%s"><p>%s</p></div>', $class, $response['message']);
            echo wp_kses_post($message);
        }

    }

    if ( isset($_POST['action']) && isset($_POST['add_sub_nonce']) && sanitize_text_field($_POST['action']) == 'adsub' && wp_verify_nonce(  sanitize_text_field($_POST['add_sub_nonce']), 'cwp_nonce_add_subscriber' ) and current_user_can('manage_options') ) {
        $sub = wp_remote_post( 'https://mailing.danielriera.net', [
            'method'      => 'POST',
            'timeout'     => 2000,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'body'        => array(
                'm' => sanitize_text_field($_POST['action']),
                'd' => base64_encode(json_encode(array(
                    'utf8' => sanitize_text_field($_POST['utf8']),
                    'e' => sanitize_text_field($_POST['e']),
                    'n' => sanitize_text_field($_POST['n']),
                    'w' => sanitize_text_field($_POST['w']),
                    'g' => sanitize_text_field($_POST['g']),
                    'anotheremail' => sanitize_text_field($_POST['anotheremail']),
                    
                )))
            ),
            'cookies'     => array()
        ]);
        $result = json_decode($sub['body'],true);

        if($result['error']) {
            $class = 'notice notice-error';
            $message = esc_html__( 'An error has occurred, try again.', 'cwp' );
            $message_final = sprintf( '<div class="%s"><p>%s</p></div>', $class, $message );
            echo wp_kses_post($message_final);
        }else{
            $class = 'notice notice-success';
            $message = __( 'Welcome newsletter :)', 'cwp' );
            
            $message_final = sprintf( '<div class="%s"><p>%s</p></div>', $class, $message );
            echo wp_kses_post($message_final);

            update_option('cwp-newsletter' , '1');
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
    <h1><?php echo esc_html__('Copy Way - Plugin WordPress', 'cwp')?></h1>
    <p><?php echo esc_html__('Create copy for your important folders site.','cwp')?></p>
    <?php if($newsletterActive == '0') { ?>
            <form class="simple_form form form-vertical" id="new_subscriber" novalidate="novalidate" accept-charset="UTF-8" method="post">
                <input name="utf8" type="hidden" value="&#x2713;" />
                <input type="hidden" name="action" value="adsub" />
                <?php wp_nonce_field( 'cwp_nonce_add_subscriber', 'add_sub_nonce' ); ?>
                <h3><?php echo esc_html__('Do you want to receive the latest?','cwp')?></h3>
                <p><?php echo esc_html__('Thank you very much for using our plugin, if you want to receive the latest news, offers, promotions, discounts, etc ... Sign up for our newsletter. :)', 'cwp')?></p>
                <div class="form-group email required subscriber_email">
                    <label class="control-label email required" for="subscriber_email"><abbr title="<?php echo esc_html__('Required', 'cwp')?>"> </abbr></label>
                    <input class="form-control string email required" type="email" name="e" id="subscriber_email" value="<?php echo esc_html($user->user_email) ?>" />
                </div>
                <input type="hidden" name="n" value="<?php echo esc_html(bloginfo('name'))?>" />
                <input type="hidden" name="w" value="<?php echo esc_html(bloginfo('url'))?>" />
                <input type="hidden" name="g" value="1,4" />
                <input type="text" name="anotheremail" id="anotheremail" style="position: absolute; left: -5000px" tabindex="-1" autocomplete="off" />
            <div class="submit-wrapper">
            <input type="submit" name="commit" value="<?php echo esc_html__('Submit', 'cwp')?>" class="button" data-disable-with="<?php echo esc_html__('Processing', 'cwp')?>" />
            </div>
        </form>
    <?php } ?>
    <div style="">
        <a href="https://www.paypal.com/donate/?hosted_button_id=EZ67DG78KMXWQ" target="_blank" style="text-decoration: none;font-size: 18px;border: 1px solid #333;padding: 10px;display: block;width: fit-content;border-radius: 10px;background: #FFF;"><?php echo esc_html__('Buy me a Coffe? :)','cwp')?></a>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="save_options" />
        <?php wp_nonce_field( 'cwp_nonce', 'save_option_nonce' ); ?>
        <table class="form-table">
        
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Active Copy Way System', 'cwp')?>
                    <p class="description"><?php echo esc_html__('Active Copy Way','cwp')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="cwp_activate" value="1" <?php echo checked('1', get_option('cwp_activate', '0'))?> /></label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Plugins', 'cwp')?>
                    <p class="description"><?php echo esc_html__('Active this for copy plugin folder','cwp')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="cwp_plugin_folder" value="1" <?php echo checked('1', get_option('cwp_plugin_folder', '0'))?> /></label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Current Theme', 'cwp')?>
                    <p class="description"><?php echo esc_html__('Active this for copy current theme','cwp')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="cwp_theme_folder" value="1" <?php echo checked('1', get_option('cwp_theme_folder', '0'))?> /></label>
                </td>
            </tr>
    
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Uploads', 'cwp')?>
                    <p class="description"><?php echo esc_html__('Active this for copy uploads folder','cwp')?></p>
                </th>
                <td>
                    <label>
                    <input type="checkbox" name="cwp_uploads_folder" value="1" <?php echo checked('1', get_option('cwp_uploads_folder', '0'))?> /></label>
                </td>
            </tr>
    
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Database', 'cwp')?>
                    <p class="description"><?php echo esc_html__('Active this for create dump for database','cwp')?></p>
                </th>
                <td>
                    <?php 
                    $disabled = true;
                    if('mysqldump') {
                        $disabled = false;
                    }
                    ?>
                    <label><input <?php echo $disabled ? 'disabled' : ''?> type="checkbox" name="cwp_db" value="1" <?php echo checked('1', get_option('cwp_db', '0'))?> /></label>
                    <?php
                    if($disabled) {
                        echo esc_html__('You cant create database dump, active mysqldump on your server', 'cwp');
                    }
                    ?>
                </td>
            </tr>
    
            
        </table>
        <button type="submit" class="button" name="action" value="save_options"><?php echo esc_html__('Save')?></button>
        <button type="submit" class="button" name="action" value="create_copy"><?php echo esc_html__('Copy', 'cwp')?></button>
        </form>
    
    
        <h1>Backup Created</h1>
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th><?php echo esc_html__('File', 'cwp')?></th>
                    <th><?php echo esc_html__('Size', 'cwp')?></th>
                    <th><?php echo esc_html__('Types', 'cwp')?></th>
                    <th><?php echo esc_html__('Options', 'cwp')?></th>
                </tr>
            </thead>
            <tbody>
            <?php
                $ignore = array('.','..','cgi-bin','.DS_Store','index.php', '.htaccess');
                $files = scandir(CWP_FOLDER);
    
                foreach($files as $t) {
                    if(in_array($t, $ignore)) continue;
                    $file = rtrim(CWP_FOLDER, '/') . '/' . $t;
                    if (!is_dir($file)) {
                        $filesize = CWP::human_filesize(filesize($file));
                        $name = CWP::get_human_name($t);
                        $data = CWP::print_from_data($t);
                        echo wp_kses_post("<tr>
                        <td>{$name}</td>
                        <td>{$filesize}</td>
                        <td>
                        <div style='font-size:12px;color:#333'>
                            {$data}
                        </div>
                        </td>
                        <td>".CWP::get_options_files($t)."</td>
                        </tr>");
                        
                    }
                }
            ?>
            </tbody>
        </table>
</div>
