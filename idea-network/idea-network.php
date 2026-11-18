<?php
/**
 * Plugin Name:       معرفی شبکه ایده پردازی
 * Description:       یک پست‌تایپ سفارشی به نام «مرکز» می‌سازد و درون‌ریزی گروهی مراکز را از فایل اکسل (xlsx) یا CSV با نه فیلد متنی (نام مراکز، تعداد مراکز، تعداد استان، ایکون، آدرس، شماره تماس، آدرس وبسایت، نام شهر، نام استان) انجام می‌دهد. فقط ۹ ستون اول خوانده می‌شود و ستون‌های اضافه نادیده گرفته می‌شوند.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DANIAL
 * License:           GPL-2.0-or-later
 * Text Domain:       excel-importer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'IDEA_NETWORK_VERSION', '1.3.0' );
define( 'IDEA_NETWORK_PATH', plugin_dir_path( __FILE__ ) );
define( 'IDEA_NETWORK_URL', plugin_dir_url( __FILE__ ) );

require_once IDEA_NETWORK_PATH . 'includes/class-excel-reader.php';
require_once IDEA_NETWORK_PATH . 'includes/class-excel-cpt.php';
require_once IDEA_NETWORK_PATH . 'includes/class-locations.php';
require_once IDEA_NETWORK_PATH . 'includes/class-elementor-loop-filter.php';
require_once IDEA_NETWORK_PATH . 'includes/class-excel-importer-admin.php';

/**
 * Registers the custom post type and flushes rewrite rules on activation.
 *
 * @return void
 */
if ( ! function_exists( 'idea_network_activate' ) ) {
	function idea_network_activate() {
		Idea_Network_CPT::register();
		Idea_Network_CPT::register_taxonomies();
		Idea_Network_Locations::seed();
		update_option( Idea_Network_Locations::OPTION_PENDING, 0, false );
		flush_rewrite_rules();
	}
}
register_activation_hook( __FILE__, 'idea_network_activate' );

/**
 * Flushes rewrite rules on deactivation.
 *
 * @return void
 */
if ( ! function_exists( 'idea_network_deactivate' ) ) {
	function idea_network_deactivate() {
		flush_rewrite_rules();
	}
}
register_deactivation_hook( __FILE__, 'idea_network_deactivate' );

/**
 * Loads the plugin translations.
 *
 * @return void
 */
if ( ! function_exists( 'idea_network_load_textdomain' ) ) {
	function idea_network_load_textdomain() {
		load_plugin_textdomain( 'excel-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}

add_action( 'plugins_loaded', 'idea_network_load_textdomain' );

// Custom post type.
add_action( 'init', array( 'Idea_Network_CPT', 'register' ), 5 );
add_action( 'init', array( 'Idea_Network_CPT', 'register_meta' ), 6 );
add_action( 'init', array( 'Idea_Network_CPT', 'register_taxonomies' ), 6 );
add_action( 'add_meta_boxes', array( 'Idea_Network_CPT', 'add_meta_box' ) );
add_action( 'save_post_' . Idea_Network_CPT::POST_TYPE, array( 'Idea_Network_CPT', 'save_meta' ), 10, 2 );
add_filter( 'manage_' . Idea_Network_CPT::POST_TYPE . '_posts_columns', array( 'Idea_Network_CPT', 'list_columns' ) );
add_action( 'manage_' . Idea_Network_CPT::POST_TYPE . '_posts_custom_column', array( 'Idea_Network_CPT', 'list_column_value' ), 10, 2 );

// Provinces, cities and their term sync.
Idea_Network_Locations::init();

// Elementor Loop Grid filter widget.
Idea_Network_Elementor::init();

// Admin importer page.
Idea_Network_Admin::init();
