<?php
/*
 * Plugin Name: WP Simple Backup
 * Description: Create and Download a zip file containing database,files and migration information of any wordpress instalation. To remove the backup deactivate the plugin
 * Author: Miguel Sirvent
 * Author URI: https://www.freelancer.com/u/miguelsirvent.html	
 * Version: 1.2
 */
 
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( "WP_Simple_Backup", 'add_action_link' ), 10, 2 );
add_action("init",array( "WP_Simple_Backup", 'init' ));
register_deactivation_hook( __FILE__ , array( "WP_Simple_Backup", 'deactivation' ));
class WP_Simple_Backup{
	
	static $slug = "/wp-simple-backup/";
	static $db_file_name = "database.sql";
	static $data_file_name = "wpsbdata.txt";
	/**
	 * Catch get variables on plugins.php page to triger backup.
	 */
	static function init() {
		global $pagenow;
		if($pagenow == "plugins.php"){
			
			if(isset($_GET['wpsback'],$_GET['_wpnonce']) && $_GET['wpsback'] == 'backup' && wp_verify_nonce( $_GET['_wpnonce'], 'wpsback' )){
				self::backup_data();
			}
		}
	}
	/**
	 * Create links for backup and download
	 * @param array action links for the plugin
	 * @param string plugin file name
	 */
	static function add_action_link( $links, $file ) {
		if(file_exists(WP_PLUGIN_DIR.self::$slug.self::getname())) {
			$down_link = '<a href="'.WP_PLUGIN_URL.self::$slug.self::getname().'" class="button button-primary">Download</a>';
			array_unshift( $links, $down_link );
		} else {
			$nonce = wp_create_nonce( 'wpsback' );
			$get_link = '<a href="'.get_admin_url().'plugins.php?wpsback=backup&_wpnonce='.$nonce.'" class="button button-primary">Backup</a>';
			array_unshift( $links, $get_link );						
		}
		return $links;
	}
	/**
	 * Main Funcion
	 * Creates a zip file containing all files on site root folder, full database, ad migration information.
	 * Adds a txt file to the zip with 3 lines: backup type, site url, database prefix.
	 * @param string files-db | files | db
	 */
    static function backup_data($bktype = "files-db") {
    	$exe_time = ini_get('max_execution_time');
    	ini_set('max_execution_time', 0);
    	set_time_limit(0);
        $folder = WP_PLUGIN_DIR.self::$slug;
		$site_u = is_multisite() ? site_url() : get_bloginfo('wpurl');
		$name = self::getname();
        $dest = $folder.$name;
        $zip = new ZipArchive;
		//echo $dest;
        $res = $zip->open($dest, ZipArchive::CREATE);
        if($res !== TRUE) {
        	//echo $dest;
            return FALSE;
        }
		self::backup_file($zip);
		$dbtmp = self::backup_db($zip);
		$fname = tempnam(sys_get_temp_dir(),'');
		global $wpdb;
		file_put_contents($fname, $bktype."\n", FILE_APPEND);
		file_put_contents($fname, $site_u."\n", FILE_APPEND);
		file_put_contents($fname, $wpdb->prefix."\n", FILE_APPEND);
		$zip->addFile($fname, self::$data_file_name);
        $zip->close();
		unlink($fname);
		unlink($dbtmp);
		ini_set('max_execution_time', (int)$exe_time);
        return;
    }
    /**
	 * Saves all files of the root folder
	 * This functions calls itself to change the current folder path
	 * @param object Zip instance.
	 * @param string Root path to zip
	 * @param string Current folder
	 */
    static function backup_file(&$zip, $root = ABSPATH, $dir = ABSPATH) {
        $files = scandir($dir);
        foreach($files as $file) {
            if($file == '.' || $file == '..') {
                continue;
            }
			$local = str_replace($root, '', $dir).$file;
            if(is_file($dir.$file)) {
                $zip -> addFile($dir.$file, $local);
            } else {
                self::backup_file($zip, $root, $dir.$file.'/'); 
            } 
        }
		
    }
	/**
	 * Executes mysqldump, and save into the zip file as database.sql
	 * @param object Zip instance.
	 */
    static function backup_db(&$zip){
		$fname = tempnam(sys_get_temp_dir(), 'wpbackup');
		if(self::is_unix())
			exec("mysqldump -u ".DB_USER." --password=".DB_PASSWORD." -h ".DB_HOST." ".DB_NAME." > ".$fname);
		else
			exec("mysqldump.exe –e –u".DB_USER." -p".DB_PASSWORD." -h ".DB_HOST." ".DB_NAME." > ".$fname);
		$zip->addFile($fname, self::$db_file_name);
		return $fname;
    }	
	/*
	 * Name of the zip file
	 * if wp site is http://www.example.com/wp/
	 * file will be named www.example.com-wp.zip
	 */  
	static function getname(){
		$site_u = is_multisite() ? site_url() : get_bloginfo('wpurl');
		$site = trim($site_u,"/");
		$site = str_replace(array("http://","https://","/"), array("","","-"), $site);
		return $site.".zip";
	}
	/*
	 * Check if server is runing on windows or a unix based system.
	 */
	static function is_unix(){
		if (DIRECTORY_SEPARATOR == '/') {//for unix based servers.
		    return true;
		}
		if (DIRECTORY_SEPARATOR == '\\') {//for windows based servers
		    return false;
		}			
	}
	/**
	 * Delete backup on plugin deactivation
	 */	
	static function deactivation(){
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "deactivate-plugin_{$plugin}" );		
		if(file_exists(WP_PLUGIN_DIR.self::$slug.self::getname())) {
			unlink(WP_PLUGIN_DIR.self::$slug.self::getname());
		}		
	}
}
 
 
?>