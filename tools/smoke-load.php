<?php
/**
 * Smoke test: boots the plugin bootstrap file with WordPress stubs.
 *
 * Usage: php smoke-load.php
 *
 * @package excel-importer
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['ei_hooks']       = array();
$GLOBALS['ei_post_types']  = array();
$GLOBALS['ei_meta']        = array();
$GLOBALS['ei_taxonomies']  = array();

/** Captures register_taxonomy() arguments for assertions. */
function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
	$GLOBALS['ei_taxonomies'][ $taxonomy ] = array(
		'object_type' => (array) $object_type,
		'args'        => $args,
	);

	return true;
}

/** Captures register_post_meta() arguments for assertions. */
function register_post_meta( $object_type, $meta_key, $args = array() ) {
	$GLOBALS['ei_meta'][ $meta_key ] = $args;

	return true;
}

function current_user_can( $capability, $args = null ) {
	return true;
}

/** Captures register_post_type() arguments for assertions. */
function register_post_type( $slug, $args = array() ) {
	$GLOBALS['ei_post_types'][ $slug ] = $args;

	return true;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['ei_hooks'][] = $hook;

	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['ei_hooks'][] = $hook;

	return true;
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['ei_hooks'][] = 'activation:' . basename( $file );
}

function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['ei_hooks'][] = 'deactivation:' . basename( $file );
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'http://example.test/wp-content/plugins/idea-network/';
}

function plugin_basename( $file ) {
	return 'idea-network/' . basename( $file );
}

function load_plugin_textdomain( $domain, $absorb = false, $rel = '' ) {
	return true;
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function esc_html_e( $text, $domain = null ) {
	echo $text;
}

$failed = 0;

require dirname( __DIR__ ) . '/idea-network/idea-network.php';

// 1) All classes from the includes must be loaded.
$expected_classes = array(
	'Idea_Network_Exception',
	'Idea_Network_Reader',
	'Idea_Network_CPT',
	'Idea_Network_Admin',
);

foreach ( $expected_classes as $class ) {
	if ( class_exists( $class, false ) ) {
		echo "PASS  class {$class} loaded\n";
	} else {
		echo "FAIL  class {$class} missing\n";
		++$failed;
	}
}

// 2) The bootstrap must register the expected hooks.
$required_hooks = array(
	'init',
	'add_meta_boxes',
	'admin_menu',
	'admin_enqueue_scripts',
	'admin_post_idea_network_run',
	'admin_post_idea_network_template',
	'manage_idea_center_posts_columns',
	'manage_idea_center_posts_custom_column',
	'save_post_idea_center',
	'plugins_loaded',
);

foreach ( $required_hooks as $hook ) {
	if ( in_array( $hook, $GLOBALS['ei_hooks'], true ) ) {
		echo "PASS  hook {$hook} registered\n";
	} else {
		echo "FAIL  hook {$hook} not registered\n";
		++$failed;
	}
}

// 3) Activation/deactivation hooks must be wired.
$has_activation = false;

foreach ( $GLOBALS['ei_hooks'] as $hook ) {
	if ( 0 === strpos( (string) $hook, 'activation:' ) ) {
		$has_activation = true;
	}
}

if ( $has_activation ) {
	echo "PASS  activation hook registered\n";
} else {
	echo "FAIL  activation hook missing\n";
	++$failed;
}

// 4) Column map sanity (nine columns, only the first without a meta key).
$map = Idea_Network_CPT::get_columns_map();

if ( 9 === count( $map ) ) {
	echo "PASS  column map has exactly nine columns\n";
} else {
	echo 'FAIL  column map has ' . count( $map ) . " columns\n";
	++$failed;
}

$meta_less = array_keys( array_filter(
	$map,
	function ( $field ) {
		return '' === $field['meta_key'];
	}
) );

if ( array( 'center_name' ) === $meta_less ) {
	echo "PASS  only the center_name column has no meta key\n";
} else {
	echo 'FAIL  unexpected meta-less columns: ' . implode( ',', $meta_less ) . "\n";
	++$failed;
}

// 5) The post type must expose every field of a standard post + a valid icon.
Idea_Network_CPT::register();

$pt_args    = isset( $GLOBALS['ei_post_types']['idea_center'] ) ? $GLOBALS['ei_post_types']['idea_center'] : array();
$supports   = isset( $pt_args['supports'] ) ? $pt_args['supports'] : array();
$required   = array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions' );
$missing    = array_diff( $required, $supports );

if ( empty( $missing ) ) {
	echo "PASS  supports: all standard post fields enabled\n";
} else {
	echo 'FAIL  supports missing: ' . implode( ',', $missing ) . "\n";
	++$failed;
}

if ( isset( $pt_args['menu_icon'] ) && 'dashicons-media-spreadsheet' === $pt_args['menu_icon'] ) {
	echo "PASS  menu icon is dashicons-media-spreadsheet\n";
} else {
	echo 'FAIL  menu icon is: ' . ( isset( $pt_args['menu_icon'] ) ? $pt_args['menu_icon'] : '(none)' ) . "\n";
	++$failed;
}

if ( isset( $pt_args['labels']['name'] ) && 'مراکز' === $pt_args['labels']['name'] && isset( $pt_args['labels']['singular_name'] ) && 'مرکز' === $pt_args['labels']['singular_name'] ) {
	echo "PASS  post type labels are مرکز / مراکز\n";
} else {
	echo "FAIL  post type labels are not مرکز / مراکز\n";
	++$failed;
}

// 6) Elementor Loop Grid compatibility: queryable post type + REST meta.
$loop_requirements = array(
	'public'             => true,
	'publicly_queryable' => true,
	'show_ui'            => true,
	'show_in_rest'       => true,
);
$loop_missing       = array();

foreach ( $loop_requirements as $flag => $needed ) {
	if ( ! isset( $pt_args[ $flag ] ) || $needed !== $pt_args[ $flag ] ) {
		$loop_missing[] = $flag;
	}
}

$loop_supports = array( 'title', 'thumbnail' );
$loop_missing  = array_merge( $loop_missing, array_diff( $loop_supports, $supports ) );

if ( empty( $loop_missing ) ) {
	echo "PASS  post type is queryable by Elementor Loop Grid (public/show_ui/rest/thumbnail)\n";
} else {
	echo 'FAIL  Loop Grid requirements missing: ' . implode( ',', $loop_missing ) . "\n";
	++$failed;
}

Idea_Network_CPT::register_meta();

$expected_meta = array( 'ei_center_count', 'ei_province_count', 'ei_icon', 'ei_address', 'ei_phone', 'ei_website', 'ei_city', 'ei_province_name' );
$not_registered = array_diff( $expected_meta, array_keys( $GLOBALS['ei_meta'] ) );

if ( empty( $not_registered ) && count( $GLOBALS['ei_meta'] ) === count( $expected_meta ) ) {
	echo "PASS  all eight fields registered via register_post_meta()\n";
} else {
	echo 'FAIL  register_post_meta entries: ' . implode( ',', array_keys( $GLOBALS['ei_meta'] ) ) . "\n";
	++$failed;
}

$rest_visible = true;

foreach ( $GLOBALS['ei_meta'] as $meta_key => $meta_args ) {
	if ( empty( $meta_args['show_in_rest'] ) ) {
		$rest_visible = false;
		echo "FAIL  meta {$meta_key} is not visible in REST\n";
	}
}

if ( $rest_visible ) {
	echo "PASS  all meta keys are visible in the REST API (Elementor ready)\n";
} else {
	++$failed;
}

// 7) «دسته بندی ها» taxonomy for managing categories.
Idea_Network_CPT::register_taxonomies();

$tax = isset( $GLOBALS['ei_taxonomies'][ Idea_Network_CPT::TAXONOMY ] )
	? $GLOBALS['ei_taxonomies'][ Idea_Network_CPT::TAXONOMY ]
	: null;

if (
	is_array( $tax )
	&& ! empty( $tax['args']['hierarchical'] )
	&& ! empty( $tax['args']['show_admin_column'] )
	&& ! empty( $tax['args']['show_in_rest'] )
	&& in_array( 'idea_center', $tax['object_type'], true )
) {
	echo "PASS  taxonomy idea_category registered (hierarchical, admin column, REST)\n";
} else {
	echo "FAIL  taxonomy idea_category not properly registered\n";
	++$failed;
}

if ( 'idea_category' === Idea_Network_CPT::TAXONOMY ) {
	echo "PASS  taxonomy constant kept (idea_category)\n";
} else {
	echo "FAIL  taxonomy constant missing\n";
	++$failed;
}

echo "\n" . ( 0 === $failed ? "smoke test passed\n" : "smoke test failed: {$failed} problem(s)\n" );
exit( $failed > 0 ? 1 : 0 );
