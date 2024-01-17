<?php
/**
 * Plugin Name: Copy Way
 * Description: Backup you important parts site
 * Version: 1.0.2
 * Author: Daniel Riera
 * Author URI: https://danielriera.net
 * Text Domain: cwp
 * Domain Path: /languages
 * Required WP: 5.0
 * Tested WP: 6.4
 * License: GPLv3
 */
if (!defined('ABSPATH'))
    exit;

define('CWP_URL', plugin_dir_url(__FILE__));
define('CWP_PATH', plugin_dir_path(__FILE__));
define('CWP_VERSION', '1.0.2');

if (!class_exists('CWP')) {
    class CWP
    {
        function __construct()
        {
            add_action('admin_init', array($this, 'load_text_domain'));
            add_action('admin_enqueue_scripts', array($this, 'load_script_admin'));
            add_action('admin_menu', array($this, 'create_menu'));
            add_action( 'wp_ajax_download', array($this, 'download') );
            // add_action( 'wp_ajax_restore', array($this, 'restore_db') );
            add_action( 'wp_ajax_restore_theme', array($this, 'restore_theme') );
            
            add_action( 'wp_ajax_delete_file', array($this, 'delete_file') );
        }

        function load_text_domain()
        {
            $copy_way_folder = get_option('cwp_folder', false);
            if (!$copy_way_folder) {
                update_option('cwp_folder', WP_CONTENT_DIR . '/' . 'cwp_' . wp_generate_password(8, false));
            }

            define('CWP_FOLDER', $copy_way_folder);

            load_plugin_textdomain('cwp', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        function load_script_admin()
        {
            wp_enqueue_script('cwp-admin-script', plugins_url('scripts.js', __FILE__) . '?v=' . CWP_VERSION, array(), false, true);
        }

        function create_menu()
        {
            add_submenu_page('options-general.php', __('Copy Way', 'cwp'), __('Copy Way', 'cwp'), 'manage_options', 'cwp-options', array($this, 'option_page'));
        }

        function option_page()
        {
            require_once(CWP_PATH . 'views/options.php');
        }

        static function get_from_data($file) {
            $result = file_get_contents('zip://'.CWP_FOLDER.'/'.$file.'#data.json');
            return json_decode($result);
        }

        static function print_from_data($file) {
            $result = self::get_from_data($file);

            $text = "";

            if($result->theme) {
                $text .= "<strong>Theme Name:</strong> {$result->theme->name}</br>";
                $text .= "<strong>Theme Version:</strong> {$result->theme->version}</br>";
            }else{
                $text .= "<strong>Theme:</strong> No</br>";
            }

            if($result->plugins) {
                $text .= "<strong>Plugins:</strong> Yes</br>";
            }else{
                $text .= "<strong>Plugins:</strong> No</br>";
            }

            if($result->dump) {
                $text .= "<strong>Database:</strong> Yes</br>";
            }else{
                $text .= "<strong>Database:</strong> No</br>";
            }

            if($result->created_user) {
                $text .= "<strong>Username:</strong> {$result->created_user}</br>";
            }

            return $text;
        }

        static function restore_theme() {
            $file = sanitize_text_field($_GET['file']);
            $result = self::get_from_data($file);
            if($result->theme) {
                $dest = dirname($result->theme->template) . '/';

                $zip = new ZipArchive;
                $temp_folder = CWP_FOLDER . '/' . str_replace(".zip", "", $file) . '/';
                $zip->open(CWP_FOLDER.'/'.$file);
                $extract = $zip->extractTo($temp_folder);
                if($extract) {
                    if(!self::recursiveCopy($temp_folder, $dest)){
                        //TODO ERROR CONTROL
                    }
                }
                $zip->close();
                self::deleteDirectory($temp_folder);
            }else{
                //TODO ERROR CONTROL
            }

            header("Location: ".sanitize_text_field($_SERVER['HTTP_REFERER']));
            exit;
        }

        static function deleteDirectory($dir) {
            if (!file_exists($dir)) {
                return;
            }
        
            $files = array_diff(scandir($dir), array('.', '..'));
        
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? self::deleteDirectory("$dir/$file") : unlink("$dir/$file");
            }
        
            rmdir($dir);
        }

        static function copy_directory($src, $dst) { 
  
            // open the source directory
            $dir = opendir($src); 
          
            // Make the destination directory if not exist
            @mkdir($dst); 
          
            // Loop through the files in source directory
            while( $file = readdir($dir) ) { 
          
                if (( $file != '.' ) && ( $file != '..' )) { 
                    if ( is_dir($src . '/' . $file) ) 
                    { 
          
                        // Recursively calling custom copy function
                        // for sub directory 
                        self::copy_directory($src . '/' . $file, $dst . '/' . $file); 
          
                    } 
                    else { 
                        copy($src . '/' . $file, $dst . '/' . $file); 
                    } 
                } 
            } 
          
            closedir($dir);
        } 

        static function init_copy()
        {
            $is_active = get_option('cwp_activate', 0);
            if (!defined('CWP_FOLDER')) {
                return array('message' => __('Error when create folders system, check you permissions server', 'cwp'), 'error' => true);
            }
            if (!$is_active) {
                return array('message' => __('Copy Way is not active', 'cwp'), 'error' => true);
            }

            $plugins = get_option('cwp_plugin_folder', 0);
            $theme = get_option('cwp_theme_folder', 0);
            $uploads = get_option('cwp_uploads_folder', 0);

            $dump_sql = get_option('cwp_db', 0);

            if (!file_exists(CWP_FOLDER . '/.htaccess')) {
                if (!file_exists(CWP_FOLDER . '/')) {
                    $folderCreated = mkdir(CWP_FOLDER, 0775, true);
                }else{
                    $folderCreated = true;
                }
                if(!$folderCreated) {
                    return array('message' => sprintf(__('Error to create backup folder %s, check permissions', 'cwp'), CWP_FOLDER), 'error' => true);
                }
                $file = fopen(CWP_FOLDER . '/index.php', "w+");
                $generate_htaccess = fopen(CWP_FOLDER . '/.htaccess', "w+");

                if (!$file or !$generate_htaccess) {
                    return array('message' => __('Error to create initial files, check permissions', 'cwp'), 'error' => true);
                }
                fwrite($file, "");
                fwrite($generate_htaccess, "Deny from all");
                fclose($file);
                fclose($generate_htaccess);
            }

            $filename = date('Ymd_His');

            if($plugins) {
                $filename .= '_plugins';
            }
            if($theme) {
                $filename .= '_theme';
            }
            if($uploads) {
                $filename .= '_uploads';
            }
            if($dump_sql) {
                $filename .= '_dump';
            }
            $data = new stdClass();
            $filename .= '.zip';
            $zip = self::create_zip($filename);

            $dump_file = false;
            if ($zip instanceof ZipArchive) {
                if ($plugins) {
                    self::copy_plugins($zip, $data);
                }

                if ($theme) {
                    self::copy_theme($zip, $data);
                }

                if ($uploads) {
                    self::copy_uploads($zip, $data);
                }

                if ($dump_sql) {
                    $dump_file = self::copy_dump($zip, $data);
                }

                $current_user = wp_get_current_user();
                $data->created_user = $current_user->user_login;
                //Save data
                $zip->addFromString('data.json', json_encode($data));

                $zip->close();

                if ($dump_file) {
                    unlink($dump_file);
                }
            } else {
                return $zip;
            }


            return array('message' => __('Copy created success', 'cwp'), 'error' => false);

        }

        static function create_zip($filename = false)
        {   
            if(!$filename) {
                $filename = date('Ymd_His') . '.zip';
            }
            $zip = new ZipArchive();
            $filename = CWP_FOLDER . "/" . $filename;
            if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
                return array('message' => __('I can create files, check permissions', 'cwp'), 'error' => true);
            }

            return $zip;
        }

        /**
         * Sanitize all array
         *
         * @param array $array
         * @return array
         */
        static function sanitize_post_all($array) {
            $keys = array_keys($array);
            $keys = array_map('sanitize_key', $keys);

            $values = array_values($array);
            $values = array_map('sanitize_text_field', $values);

            $array = array_combine($keys, $values);

            return $array;
        }

        static function copy_plugins(&$zip, &$data)
        {
            $data->plugins = true;
            return self::zipDir(WP_PLUGIN_DIR, $zip);
        }

        static function copy_theme(&$zip, &$data)
        {
            $theme = wp_get_theme();
            $data->theme = new stdClass();

            $data->theme->template = get_template_directory();
            $data->theme->version = $theme->get('Version');
            $data->theme->name = $theme->get('Name');
            return self::zipDir($data->theme->template, $zip);
        }

        static function copy_uploads(&$zip, &$data)
        {
            $uploaddir = wp_upload_dir();
            $data->uploads = true;
            return self::zipDir($uploaddir['basedir'], $zip, array(CWP_FOLDER));
        }

        static function copy_dump(&$zip, &$data)
        {
            $dir = CWP_FOLDER . '/dump.sql';
            if (!file_exists($dir)) {
                fopen($dir, "w");
            }

            exec('mysqldump --user=' . DB_USER . ' --password=' . DB_PASSWORD . ' --host=' . DB_HOST . '  ' . DB_NAME . " --result-file={$dir} 2>&1", $output);
            
            $data->dump = true;
            
            $zip->addFile($dir, "dump.sql");
            return $dir;
        }

        // static function restore_db() {
        //     $file = rtrim(CWP_FOLDER, '/') . '/' . sanitize_text_field($_GET['file']);
        //     $z = new ZipArchive();
        //     if ($z->open($file)) {
        //         $fp = $z->getStreamName('dump.sql', ZipArchive::FL_UNCHANGED);
        //         if(!$fp) die($z->getStatusString());
                
        //         exec('mysql -u ' . DB_USER . '  -p wordpress_2 < ' . $fp);
                
                
        //         fclose($fp);
        //     }
        // }

        private static function folderToZip($folder, &$zipFile, $exclusiveLength, $excludes = false)
        {

            $handle = opendir($folder);
            while (false !== $f = readdir($handle)) {
                if ($f != '.' && $f != '..') {
                    $filePath = "$folder/$f";

                    //Exclude backups
                    if (is_array($excludes) and in_array($filePath, $excludes)) {
                        continue;
                    }
                    // Remove prefix from file path before add to zip.
                    $localPath = substr($filePath, $exclusiveLength);

                    if (is_file($filePath)) {
                        $zipFile->addFile($filePath, $localPath);
                    } elseif (is_dir($filePath)) {
                        // Add sub-directory.
                        $zipFile->addEmptyDir($localPath);
                        self::folderToZip($filePath, $zipFile, $exclusiveLength);
                    }
                }
            }
            closedir($handle);
        }

        static function recursiveCopy($src, $dst) {
            $dir = opendir($src);
            @mkdir($dst);
        
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        self::recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        
            closedir($dir);

            return true;
        }

        /**
         * Zip a folder (include itself).
         * Usage:
         *   HZip::zipDir('/path/to/sourceDir', '/path/to/out.zip');
         *
         * @param string $sourcePath Path of directory to be zip.
         * @param string $outZipPath Path of output zip file.
         */

        public static function zipDir($sourcePath, $z, $excludes = false)
        {

            $pathInfo = pathInfo($sourcePath);
            $parentPath = $pathInfo['dirname'];
            $dirName = $pathInfo['basename'];

            $z->addEmptyDir($dirName);
            self::folderToZip($sourcePath, $z, strlen("$parentPath/"), $excludes);

            return true;
        }

        static function get_human_name($name)
        {
            return sprintf(__('Copy created at %s', 'cwp'), substr($name, 0, 4) . '-' . substr($name, 4, 2) . '-' . substr($name, 6, 2) . ' ' . substr($name, 9, 2) . ':' . substr($name, 11, 2) . ':' . substr($name, 13, 2));
        }

        static function human_filesize($bytes, $decimals = 2)
        {
            $factor = floor((strlen($bytes) - 1) / 3);
            if ($factor > 0)
                $sz = 'KMGT';
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
        }

        function download() {
            $file = rtrim(CWP_FOLDER, '/') . '/' . sanitize_text_field($_GET['file']);
            if (file_exists($file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            }
        }

        function delete_file() {
            $file = rtrim(CWP_FOLDER, '/') . '/' . sanitize_text_field($_GET['file']);
            if (file_exists($file)) {
                unlink($file);
            }
            header("Location: ". sanitize_text_field($_SERVER['HTTP_REFERER']));
            exit;
        }

        static function get_options_files($file) {
            $options = "<form action='".admin_url('admin-ajax.php')."'>
                <input type='hidden' name='file' value='{$file}' />
                <button type='submit' class='button' name='action' value='download'>".__('Download', 'cwp')."</button>
            ";

            // if(strstr($file, 'dump')) {
            //     $options .= "<button type='submit' class='button' name='action' value='restore'>".__('Restore DB', 'cwp')."</button>";
            // }

            if(strstr($file, 'theme')) {
                 $options .= "<button type='submit' class='button' name='action' value='restore_theme'>".__('Restore Theme', 'cwp')."</button>";
            }


            

            $options .= "<button type='submit' class='button' name='action' value='delete_file'>".__('Delete', 'cwp')."</button>
            </form>";

            return $options;
            
        }

        static function get_types_human($file) {
            $text = '';
            if(strstr($file, 'plugins')) {
                $text .= 'Plugins, ';
            }
            if(strstr($file, 'theme')) {
                $text .= 'Theme, ';
            }
            if(strstr($file, 'uploads')) {
                $text .= 'Uploads, ';
            }
            if(strstr($file, 'dump')) {
                $text .= 'Dump DB, ';
            }

            if($text) {
                $text = rtrim($text, ", ");
            }else{
                $text = 'N/A';
            }

            return $text;
            
        }
    }

    $CWP = new CWP();
}