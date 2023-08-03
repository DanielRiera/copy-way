<?php
/**
 * Plugin Name: Copy Way
 * Description: Backup you important parts site
 * Version: 1.0.0
 * Author: Daniel Riera
 * Author URI: https://danielriera.net
 * Text Domain: copy-way
 * Domain Path: /languages
 * Required WP: 5.0
 * Tested WP: 6.2.2
 */
if (!defined('ABSPATH'))
    exit;

define('COPYWAY_URL', plugin_dir_url(__FILE__));
define('COPYWAY_PATH', plugin_dir_path(__FILE__));
define('COPYWAY_VERSION', '1.0.0');

if (!class_exists('COPYWAY')) {
    class COPYWAY
    {
        function __construct()
        {
            add_action('admin_init', array($this, 'load_text_domain'));
            add_action('admin_enqueue_scripts', array($this, 'load_script_admin'));
            add_action('admin_menu', array($this, 'create_menu'));
            add_action( 'wp_ajax_download', array($this, 'download') );
            // add_action( 'wp_ajax_restore', array($this, 'restore_db') );
            add_action( 'wp_ajax_delete_file', array($this, 'delete_file') );
        }

        function load_text_domain()
        {
            $copy_way_folder = get_option('copyway_folder', false);
            if (!$copy_way_folder) {
                $uploaddir = wp_upload_dir();
                update_option('copyway_folder', $uploaddir['basedir'] . '/' . 'copyway_' . wp_generate_password(8, false));
            }

            define('COPYWAY_FOLDER', $copy_way_folder);

            load_plugin_textdomain('copy-way', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        function load_script_admin()
        {
            wp_enqueue_script('copyway-admin-script', plugins_url('scripts.js', __FILE__) . '?v=' . COPYWAY_VERSION, array(), false, true);
        }

        function create_menu()
        {
            add_submenu_page('options-general.php', __('Copy Way', 'copy-way'), __('Copy Way', 'copy-way'), 'manage_options', 'copyway-options', array($this, 'option_page'));
        }

        function option_page()
        {
            require_once(COPYWAY_PATH . 'views/options.php');
        }

        static function init_copy()
        {
            $is_active = get_option('copyway_activate', 0);
            if (!defined('COPYWAY_FOLDER')) {
                return array('message' => __('Error when create folders system, check you permissions server', 'copy-way'));
            }
            if (!$is_active) {
                return array('message' => __('Copy Way is not active', 'copy-way'));
            }

            $plugins = get_option('copyway_plugin_folder', 0);
            $theme = get_option('copyway_theme_folder', 0);
            $uploads = get_option('copyway_uploads_folder', 0);

            $dump_sql = get_option('copyway_db', 0);

            if (!file_exists(COPYWAY_FOLDER . '/.htaccess')) {
                if (!file_exists(COPYWAY_FOLDER . '/')) {
                    mkdir(COPYWAY_FOLDER . '/', 775);
                }
                $file = fopen(COPYWAY_FOLDER . '/index.php', "w");
                $generate_htaccess = fopen(COPYWAY_FOLDER . '/.htaccess', "w");

                if (!$file or !$generate_htaccess) {
                    return array('message' => __('Error to create backup folder, check permissions', 'copy-way'));
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

            $filename .= '.zip';
            $zip = self::create_zip($filename);

            $dump_file = false;
            if ($zip instanceof ZipArchive) {
                if ($plugins) {
                    self::copy_plugins($zip);
                }

                if ($theme) {
                    self::copy_theme($zip);
                }

                if ($uploads) {
                    self::copy_uploads($zip);
                }

                if ($dump_sql) {
                    $dump_file = self::copy_dump($zip);
                }

                $zip->close();

                if ($dump_file) {
                    unlink($dump_file);
                }
            } else {
                return $zip;
            }


            return array('message' => __('Folder created', 'copy-way'));

        }

        static function create_zip($filename = false)
        {   
            if(!$filename) {
                $filename = date('Ymd_His') . '.zip';
            }
            $zip = new ZipArchive();
            $filename = COPYWAY_FOLDER . "/" . $filename;
            if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
                return array('message' => __('I can create files, check permissions', 'copy-way'));
            }

            return $zip;
        }

        static function copy_plugins(&$zip)
        {
            return self::zipDir(WP_PLUGIN_DIR, $zip);
        }

        static function copy_theme(&$zip)
        {
            return self::zipDir(get_template_directory(), $zip);
        }

        static function copy_uploads(&$zip)
        {
            $uploaddir = wp_upload_dir();
            return self::zipDir($uploaddir['basedir'], $zip, array(COPYWAY_FOLDER));
        }

        static function copy_dump(&$zip)
        {
            $dir = COPYWAY_FOLDER . '/dump.sql';
            if (!file_exists($dir)) {
                fopen($dir, "w");
            }
            exec('mysqldump -R --user=' . DB_USER . ' --password=' . DB_PASSWORD . ' --host=' . DB_HOST . '  ' . DB_NAME . " --result-file={$dir} 2>&1", $output);
            $zip->addFile($dir, "dump.sql");
            return $dir;
        }

        // static function restore_db() {
        //     $file = rtrim(COPYWAY_FOLDER, '/') . '/' . $_GET['file'];
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
            return sprintf(__('Copy created at %s', 'copy-way'), substr($name, 0, 4) . '-' . substr($name, 4, 2) . '-' . substr($name, 6, 2) . ' ' . substr($name, 9, 2) . ':' . substr($name, 11, 2) . ':' . substr($name, 13, 2));
        }

        static function human_filesize($bytes, $decimals = 2)
        {
            $factor = floor((strlen($bytes) - 1) / 3);
            if ($factor > 0)
                $sz = 'KMGT';
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
        }

        function download() {
            $file = rtrim(COPYWAY_FOLDER, '/') . '/' . $_GET['file'];
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
            $file = rtrim(COPYWAY_FOLDER, '/') . '/' . $_GET['file'];
            if (file_exists($file)) {
                unlink($file);
            }
            header("Location: ".$_SERVER['HTTP_REFERER']);
            exit;
        }

        static function get_options_files($file) {
            $options = "<form action='".admin_url('admin-ajax.php')."'>
                <input type='hidden' name='file' value='{$file}' />
                <button type='submit' class='button' name='action' value='download'>".__('Download', 'copy-way')."</button>
            ";

            // if(strstr($file, 'dump')) {
            //     $options .= "<button type='submit' class='button' name='action' value='restore'>".__('Restore DB', 'copy-way')."</button>";
            // }

            $options .= "<button type='submit' class='button' name='action' value='delete_file'>".__('Delete', 'copy-way')."</button>
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

    $COPYWAY = new COPYWAY();
}