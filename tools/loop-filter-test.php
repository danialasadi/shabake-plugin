<?php
/**
 * Tests for the province/city taxonomies and the Elementor Loop Grid filter.
 *
 * Runs on the PHP CLI without WordPress or Elementor installed.
 *
 * Usage: php loop-filter-test.php
 *
 * @package excel-importer
 */

namespace Elementor {

	/**
	 * Minimal Controls_Manager stub.
	 */
	class Controls_Manager {
		const SWITCHER   = 'switcher';
		const TEXT       = 'text';
		const COLOR      = 'color';
		const SLIDER     = 'slider';
		const DIMENSIONS = 'dimensions';
		const TAB_STYLE  = 'style';
	}

	/**
	 * Minimal typography group control stub.
	 */
	class Group_Control_Typography {
		public static function get_type() {
			return 'typography';
		}
	}

	/**
	 * Minimal border group control stub.
	 */
	class Group_Control_Border {
		public static function get_type() {
			return 'border';
		}
	}

	/**
	 * Minimal Widget_Base stub recording controls and rendered attributes.
	 */
	class Widget_Base {

		protected $settings = array();

		protected $attributes = array();

		public function __construct( $settings = array() ) {
			$this->settings = $settings;
		}

		protected function get_settings_for_display() {
			return $this->settings;
		}

		protected function start_controls_section( $id, $args = array() ) {
			$GLOBALS['widget_sections'][] = $id;
		}

		protected function end_controls_section() {}

		protected function add_control( $id, $args = array() ) {
			$GLOBALS['widget_controls'][ $id ] = $args;
		}

		protected function add_responsive_control( $id, $args = array() ) {
			$GLOBALS['widget_controls'][ $id ] = $args;
		}

		protected function add_group_control( $type, $args = array() ) {
			$GLOBALS['widget_groups'][ $args['name'] ] = $type;
		}

		protected function add_render_attribute( $element, $key_or_attributes, $value = null ) {
			if ( is_array( $key_or_attributes ) ) {
				foreach ( $key_or_attributes as $key => $item ) {
					$this->attributes[ $element ][ $key ] = $item;
				}

				return;
			}

			$this->attributes[ $element ][ $key_or_attributes ] = $value;
		}

		protected function get_render_attribute_string( $element ) {
			if ( empty( $this->attributes[ $element ] ) ) {
				return '';
			}

			$out = '';

			foreach ( $this->attributes[ $element ] as $key => $value ) {
				$out .= ' ' . $key . '="' . htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ) . '"';
			}

			return $out;
		}
	}
}

namespace {

	define( 'ABSPATH', __DIR__ );
	define( 'IDEA_NETWORK_PATH', dirname( __DIR__ ) . '/idea-network/' );
	define( 'IDEA_NETWORK_URL', 'http://example.test/wp-content/plugins/idea-network/' );
	define( 'IDEA_NETWORK_VERSION', '1.3.0' );
	define( 'MINUTE_IN_SECONDS', 60 );

	$GLOBALS['lf_passes']         = 0;
	$GLOBALS['lf_failures']       = 0;
	$GLOBALS['lf_hooks']          = array();
	$GLOBALS['lf_taxonomies']     = array();
	$GLOBALS['lf_terms']          = array();
	$GLOBALS['lf_term_meta']      = array();
	$GLOBALS['lf_object_terms']   = array();
	$GLOBALS['lf_term_counter']   = 0;
	$GLOBALS['lf_options']        = array();
	$GLOBALS['lf_transients']     = array();
	$GLOBALS['lf_post_meta']      = array();
	$GLOBALS['lf_post_types']     = array();
	$GLOBALS['lf_posts']          = array();
	$GLOBALS['lf_ajax']           = false;
	$GLOBALS['lf_admin']          = false;
	$GLOBALS['lf_assets']         = array();
	$GLOBALS['widget_controls']   = array();
	$GLOBALS['widget_groups']     = array();
	$GLOBALS['widget_sections']   = array();

	// -----------------------------------------------------------------------
	// WordPress stubs.
	// -----------------------------------------------------------------------

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['lf_hooks'][ $hook ] = $callback;

		return true;
	}

	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['lf_hooks'][ $hook ] = $callback;

		return true;
	}

	function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
		$GLOBALS['lf_taxonomies'][ $taxonomy ] = array(
			'object_type' => (array) $object_type,
			'args'        => $args,
		);

		return true;
	}

	function register_post_type( $slug, $args = array() ) {
		return true;
	}

	function register_post_meta( $object_type, $meta_key, $args = array() ) {
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

	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ) {
		return (string) $url;
	}

	function esc_url_raw( $url ) {
		return (string) $url;
	}

	function selected( $selected, $current = true, $display = true ) {
		$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';

		if ( $display ) {
			echo $result;
		}

		return $result;
	}

	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}

	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}

	function sanitize_title( $title ) {
		$title = trim( (string) $title );
		$title = preg_replace( '/\s+/u', '-', $title );

		return strtolower( (string) $title );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function wp_json_encode( $value ) {
		return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}

	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	function is_admin() {
		return (bool) $GLOBALS['lf_admin'];
	}

	function wp_doing_ajax() {
		return (bool) $GLOBALS['lf_ajax'];
	}

	function current_user_can( $capability, $args = null ) {
		return true;
	}

	function wp_die( $message = '' ) {
		throw new Exception( (string) $message );
	}

	function check_admin_referer( $action = '', $query_arg = '_wpnonce' ) {
		return true;
	}

	function admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function wp_nonce_url( $url, $action = '', $name = '_wpnonce' ) {
		return $url . '&_wpnonce=test';
	}

	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}

	function plugin_dir_url( $file ) {
		return IDEA_NETWORK_URL;
	}

	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['lf_options'] ) ? $GLOBALS['lf_options'][ $name ] : $default;
	}

	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['lf_options'][ $name ] = $value;

		return true;
	}

	function delete_option( $name ) {
		unset( $GLOBALS['lf_options'][ $name ] );

		return true;
	}

	function get_transient( $name ) {
		return array_key_exists( $name, $GLOBALS['lf_transients'] ) ? $GLOBALS['lf_transients'][ $name ] : false;
	}

	function set_transient( $name, $value, $expiration = 0 ) {
		$GLOBALS['lf_transients'][ $name ] = $value;

		return true;
	}

	function delete_transient( $name ) {
		unset( $GLOBALS['lf_transients'][ $name ] );

		return true;
	}

	function number_format_i18n( $number ) {
		return (string) $number;
	}

	function wp_register_style( $handle, $src, $deps = array(), $version = false ) {
		$GLOBALS['lf_assets'][ $handle ] = $src;

		return true;
	}

	function wp_register_script( $handle, $src, $deps = array(), $version = false, $footer = false ) {
		$GLOBALS['lf_assets'][ $handle ] = $src;

		return true;
	}

	function wp_safe_redirect( $url ) {
		return true;
	}

	function wp_is_post_revision( $post_id ) {
		return false;
	}

	function wp_is_post_autosave( $post_id ) {
		return false;
	}

	function get_post_type( $post_id ) {
		return isset( $GLOBALS['lf_post_types'][ $post_id ] ) ? $GLOBALS['lf_post_types'][ $post_id ] : 'idea_center';
	}

	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return isset( $GLOBALS['lf_post_meta'][ $post_id ] ) ? $GLOBALS['lf_post_meta'][ $post_id ] : array();
		}

		return isset( $GLOBALS['lf_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['lf_post_meta'][ $post_id ][ $key ] : '';
	}

	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['lf_post_meta'][ $post_id ][ $key ] = $value;

		return true;
	}

	function get_edit_term_link( $term_id, $taxonomy = '', $object_type = '' ) {
		return 'http://example.test/wp-admin/term.php?term_id=' . (int) $term_id;
	}

	function get_posts( $args = array() ) {
		return $GLOBALS['lf_posts'];
	}

	function remove_query_arg( $keys, $url = '' ) {
		$keys = (array) $keys;

		if ( '' === $url ) {
			$url = isset( $_SERVER['REQUEST_URI'] ) ? 'http://example.test' . $_SERVER['REQUEST_URI'] : 'http://example.test/';
		}

		$parts = parse_url( $url );
		$base  = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' ) .
			( isset( $parts['host'] ) ? $parts['host'] : '' ) .
			( isset( $parts['path'] ) ? $parts['path'] : '' );
		$args  = array();

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $args );
		}

		foreach ( $keys as $key ) {
			unset( $args[ $key ] );
		}

		return $args ? $base . '?' . http_build_query( $args ) : $base;
	}

	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		private $message;

		public function __construct( $message = '' ) {
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}

	/**
	 * Minimal WP_Term stub.
	 */
	class WP_Term {
		public $term_id;
		public $name;
		public $slug;
		public $taxonomy;
		public $parent;
		public $count = 0;

		public function __construct( $term_id, $name, $slug, $taxonomy, $parent = 0 ) {
			$this->term_id  = (int) $term_id;
			$this->name     = $name;
			$this->slug     = $slug;
			$this->taxonomy = $taxonomy;
			$this->parent   = (int) $parent;
		}
	}

	/**
	 * Minimal WP_Query stub recording the query vars.
	 */
	class WP_Query {
		public $query_vars = array();
		public $main       = false;

		public function __construct( $args = array() ) {
			$this->query_vars = (array) $args;
		}

		public function get( $key, $default = '' ) {
			return array_key_exists( $key, $this->query_vars ) ? $this->query_vars[ $key ] : $default;
		}

		public function set( $key, $value ) {
			$this->query_vars[ $key ] = $value;
		}

		public function is_main_query() {
			return (bool) $this->main;
		}
	}

	function term_exists( $term, $taxonomy = '', $parent = null ) {
		$terms = isset( $GLOBALS['lf_terms'][ $taxonomy ] ) ? $GLOBALS['lf_terms'][ $taxonomy ] : array();

		foreach ( $terms as $id => $item ) {
			if ( $item->name === $term ) {
				return array(
					'term_id'          => $id,
					'term_taxonomy_id' => $id,
				);
			}
		}

		return null;
	}

	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		$id = ++$GLOBALS['lf_term_counter'];

		$GLOBALS['lf_terms'][ $taxonomy ][ $id ] = new WP_Term(
			$id,
			$term,
			sanitize_title( $term ) . '-' . $id,
			$taxonomy,
			isset( $args['parent'] ) ? (int) $args['parent'] : 0
		);

		return array(
			'term_id'          => $id,
			'term_taxonomy_id' => $id,
		);
	}

	function get_term( $term_id, $taxonomy = '' ) {
		return isset( $GLOBALS['lf_terms'][ $taxonomy ][ $term_id ] ) ? $GLOBALS['lf_terms'][ $taxonomy ][ $term_id ] : null;
	}

	function get_terms( $args = array() ) {
		$taxonomy = is_array( $args ) && isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';
		$terms    = isset( $GLOBALS['lf_terms'][ $taxonomy ] ) ? array_values( $GLOBALS['lf_terms'][ $taxonomy ] ) : array();

		usort(
			$terms,
			function ( $a, $b ) {
				return strcmp( $a->name, $b->name );
			}
		);

		return $terms;
	}

	function get_term_meta( $term_id, $key = '', $single = false ) {
		return isset( $GLOBALS['lf_term_meta'][ $term_id ][ $key ] ) ? $GLOBALS['lf_term_meta'][ $term_id ][ $key ] : '';
	}

	function update_term_meta( $term_id, $key, $value ) {
		$GLOBALS['lf_term_meta'][ $term_id ][ $key ] = $value;

		return true;
	}

	function wp_set_object_terms( $post_id, $terms, $taxonomy, $append = false ) {
		$terms = array_map( 'intval', (array) $terms );

		if ( $append && isset( $GLOBALS['lf_object_terms'][ $post_id ][ $taxonomy ] ) ) {
			$terms = array_unique( array_merge( $GLOBALS['lf_object_terms'][ $post_id ][ $taxonomy ], $terms ) );
		}

		$GLOBALS['lf_object_terms'][ $post_id ][ $taxonomy ] = array_values( $terms );

		return $terms;
	}

	/**
	 * Fake Elementor widgets manager.
	 */
	class Fake_Widgets_Manager {
		public $registered = array();

		public function register( $widget ) {
			$this->registered[] = $widget;

			return true;
		}

		public function register_widget_type( $widget ) {
			$this->registered[] = $widget;

			return true;
		}
	}

	/**
	 * Fake Elementor elements manager.
	 */
	class Fake_Elements_Manager {
		public $categories = array();

		public function add_category( $slug, $args = array() ) {
			$this->categories[ $slug ] = $args;
		}
	}

	// -----------------------------------------------------------------------
	// Assertions.
	// -----------------------------------------------------------------------

	function lf_assert( $condition, $label ) {
		if ( $condition ) {
			++$GLOBALS['lf_passes'];
			echo "PASS  {$label}\n";
		} else {
			++$GLOBALS['lf_failures'];
			echo "FAIL  {$label}\n";
		}
	}

	function lf_same( $expected, $actual, $label ) {
		$ok = $expected === $actual;

		lf_assert(
			$ok,
			$ok
				? $label
				: $label . ' | expected: ' . var_export( $expected, true ) . ' | actual: ' . var_export( $actual, true )
		);
	}

	require IDEA_NETWORK_PATH . 'includes/class-excel-cpt.php';
	require IDEA_NETWORK_PATH . 'includes/class-locations.php';
	require IDEA_NETWORK_PATH . 'includes/class-elementor-loop-filter.php';

	// =======================================================================
	// 1) Taxonomies
	// =======================================================================
	Idea_Network_CPT::register_taxonomies();

	$province_tax = isset( $GLOBALS['lf_taxonomies']['idea_province'] ) ? $GLOBALS['lf_taxonomies']['idea_province'] : null;
	$city_tax     = isset( $GLOBALS['lf_taxonomies']['idea_city'] ) ? $GLOBALS['lf_taxonomies']['idea_city'] : null;

	lf_assert( is_array( $province_tax ) && is_array( $city_tax ), 'province and city taxonomies are registered' );
	lf_assert(
		is_array( $province_tax ) && in_array( 'idea_center', $province_tax['object_type'], true ) && ! empty( $province_tax['args']['show_admin_column'] ) && ! empty( $province_tax['args']['show_in_rest'] ),
		'province taxonomy is attached to centers with an admin column and REST support'
	);
	lf_assert(
		is_array( $city_tax ) && in_array( 'idea_center', $city_tax['object_type'], true ) && ! empty( $city_tax['args']['show_admin_column'] ) && ! empty( $city_tax['args']['show_in_rest'] ),
		'city taxonomy is attached to centers with an admin column and REST support'
	);
	lf_same( 'idea_province', Idea_Network_CPT::TAXONOMY_PROVINCE, 'province taxonomy constant' );
	lf_same( 'idea_city', Idea_Network_CPT::TAXONOMY_CITY, 'city taxonomy constant' );

	// =======================================================================
	// 2) Name normalisation
	// =======================================================================
	lf_same( 'فارس', Idea_Network_Locations::normalize_name( '  استان فارس ' ), 'normalize_name strips the استان prefix and spaces' );
	lf_same( 'کرمانشاه', Idea_Network_Locations::normalize_name( 'كرمانشاه' ), 'normalize_name converts Arabic letters' );
	lf_same( 'تهران', Idea_Network_Locations::normalize_name( 'تهران' ), 'normalize_name keeps a plain name' );

	// =======================================================================
	// 3) Seeding provinces and cities
	// =======================================================================
	$seed = Idea_Network_Locations::seed();

	lf_same( 31, $seed['provinces'], 'seed creates the 31 provinces of Iran' );
	lf_assert( $seed['cities'] > 500, 'seed creates the cities of every province (' . $seed['cities'] . ')' );

	$provinces = get_terms( array( 'taxonomy' => 'idea_province' ) );
	lf_same( 31, count( $provinces ), 'province taxonomy holds exactly 31 terms' );
	lf_assert( '' !== get_option( Idea_Network_Locations::OPTION_VERSION ), 'seed records the seeded data version' );

	$fars = Idea_Network_Locations::get_or_create_province( 'فارس' );
	lf_assert( $fars instanceof WP_Term && 'فارس' === $fars->name, 'province term «فارس» exists after seeding' );

	$shiraz = Idea_Network_Locations::get_or_create_city( 'شیراز', $fars );
	lf_assert( $shiraz > 0, 'city «شیراز» exists in فارس' );
	lf_same( (int) $fars->term_id, (int) get_term_meta( $shiraz, Idea_Network_Locations::PROVINCE_META, true ), 'city term is linked to its province' );

	// «فیروزآباد» exists in both فارس and کرمانشاه.
	$kermanshah = Idea_Network_Locations::get_or_create_province( 'کرمانشاه' );
	$duplicate  = Idea_Network_Locations::get_or_create_city( 'فیروزآباد', $kermanshah );
	$duplicate_term = get_term( $duplicate, 'idea_city' );

	lf_assert( $duplicate_term instanceof WP_Term, 'duplicate city term was created' );
	lf_same( 'فیروزآباد (کرمانشاه)', $duplicate_term->name, 'duplicate city name is suffixed with its province' );
	lf_same( (int) $kermanshah->term_id, (int) get_term_meta( $duplicate, Idea_Network_Locations::PROVINCE_META, true ), 'suffixed city is linked to the right province' );

	$same_city = Idea_Network_Locations::get_or_create_city( 'فیروزآباد', $kermanshah );
	lf_same( (int) $duplicate, (int) $same_city, 'creating the same city twice reuses its term' );

	$orphan = Idea_Network_Locations::get_or_create_city( 'شهر بدون استان' );
	lf_assert( $orphan > 0 && '' === get_term_meta( $orphan, Idea_Network_Locations::PROVINCE_META, true ), 'a city without a province is created without a link' );

	// =======================================================================
	// 4) Syncing a center from its meta fields
	// =======================================================================
	$GLOBALS['lf_post_types'][501] = 'idea_center';
	$GLOBALS['lf_post_meta'][501]  = array(
		'ei_province_name' => 'استان فارس',
		'ei_city'          => 'شیراز',
		'ei_phone'         => '071-1',
	);

	Idea_Network_Locations::sync_post_terms( 501 );

	$province_ids = isset( $GLOBALS['lf_object_terms'][501]['idea_province'] ) ? $GLOBALS['lf_object_terms'][501]['idea_province'] : array();
	$city_ids     = isset( $GLOBALS['lf_object_terms'][501]['idea_city'] ) ? $GLOBALS['lf_object_terms'][501]['idea_city'] : array();

	lf_same( array( (int) $fars->term_id ), $province_ids, 'sync assigns the province term from «استان فارس»' );
	lf_same( array( (int) $shiraz ), $city_ids, 'sync assigns the city term of the center' );

	$GLOBALS['lf_post_meta'][501]['ei_city']          = 'فیروزآباد';
	$GLOBALS['lf_post_meta'][501]['ei_province_name'] = 'کرمانشاه';

	Idea_Network_Locations::sync_post_terms( 501 );

	lf_same( array( (int) $kermanshah->term_id ), $GLOBALS['lf_object_terms'][501]['idea_province'], 'sync replaces the province when the meta changes' );
	lf_same( array( (int) $duplicate ), $GLOBALS['lf_object_terms'][501]['idea_city'], 'sync links the province-specific city term' );

	$GLOBALS['lf_post_types'][502] = 'post';
	Idea_Network_Locations::sync_post_terms( 502 );
	lf_assert( empty( $GLOBALS['lf_object_terms'][502] ), 'sync ignores other post types' );

	// =======================================================================
	// 5) Tax query building
	// =======================================================================
	$tax_query = Idea_Network_Elementor::build_tax_query(
		array(
			'category' => 'canon',
			'province' => 'fars',
			'city'     => 'shiraz',
			'search'   => '',
		)
	);

	lf_same( 4, count( $tax_query ), 'three filters produce three clauses plus the AND relation' );
	lf_same( 'AND', $tax_query['relation'], 'clauses are combined with AND' );
	lf_same( 'idea_category', $tax_query[0]['taxonomy'], 'category clause uses the category taxonomy' );
	lf_assert( true === $tax_query[0]['include_children'], 'category clause includes sub categories' );
	lf_same( 'idea_province', $tax_query[1]['taxonomy'], 'province clause uses the province taxonomy' );
	lf_same( 'idea_city', $tax_query[2]['taxonomy'], 'city clause uses the city taxonomy' );

	$single = Idea_Network_Elementor::build_tax_query(
		array(
			'category' => '',
			'province' => 'fars',
			'city'     => '',
			'search'   => '',
		)
	);

	lf_same( 1, count( $single ), 'a single filter produces one clause without a relation' );
	lf_assert( ! isset( $single['relation'] ), 'no relation key for a single clause' );

	lf_same( array(), Idea_Network_Elementor::build_tax_query( array( 'category' => '', 'province' => '', 'city' => '', 'search' => '' ) ), 'no filters produce no tax query' );

	// =======================================================================
	// 6) Applying the filters to a query
	// =======================================================================
	$_GET          = array(
		'shabake_province' => 'fars',
		'shabake_s'        => 'کانون',
	);
	$_COOKIE       = array();
	$GLOBALS['lf_ajax'] = false;
	$GLOBALS['lf_admin'] = false;

	$loop = new WP_Query( array( 'post_type' => 'idea_center' ) );

	Idea_Network_Elementor::filter_query( $loop );

	$loop_tax = $loop->get( 'tax_query' );
	lf_same( 'fars', $loop_tax[0]['terms'][0], 'loop query receives the selected province' );
	lf_same( 'کانون', $loop->get( 's' ), 'loop query receives the search term' );
	lf_same( 1, (int) $loop->get( 'idea_network_filtered' ), 'filtered query is marked to avoid double filtering' );

	Idea_Network_Elementor::filter_query( $loop );
	lf_same( 1, count( $loop->get( 'tax_query' ) ), 'the filter is not applied twice to the same query' );

	// Elementor may already filter the loop (e.g. a category loop with OR); our
	// clause must be ANDed with that group instead of inheriting its relation.
	$with_elementor_filters = new WP_Query(
		array(
			'post_type' => 'idea_center',
			'tax_query' => array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'idea_category',
					'field'    => 'slug',
					'terms'    => array( 'canon' ),
				),
				array(
					'taxonomy' => 'idea_category',
					'field'    => 'slug',
					'terms'    => array( 'mohar' ),
				),
			),
		)
	);

	Idea_Network_Elementor::filter_query( $with_elementor_filters );

	$merged_tax = $with_elementor_filters->get( 'tax_query' );

	lf_same( 'AND', $merged_tax['relation'], 'Elementor clauses and the plugin filter are ANDed' );
	lf_same( 'OR', $merged_tax[0]['relation'], 'the existing Elementor OR group is preserved as a nested group' );
	lf_same( 'idea_province', $merged_tax[1][0]['taxonomy'], 'the province clause is added next to the Elementor group' );

	// A main query for the centers archive (Elementor «Current Query») is filtered too.
	$main = new WP_Query( array( 'post_type' => 'idea_center' ) );
	$main->main = true;
	Idea_Network_Elementor::filter_query( $main );
	lf_assert( ! empty( $main->get( 'tax_query' ) ), 'a centers main query (current query loops) is filtered' );

	$page_query = new WP_Query( array( 'post_type' => 'page' ) );
	$page_query->main = true;
	Idea_Network_Elementor::filter_query( $page_query );
	lf_assert( '' === $page_query->get( 'tax_query' ), 'the main query of other post types is not touched' );

	$posts = new WP_Query( array( 'post_type' => 'post' ) );
	Idea_Network_Elementor::filter_query( $posts );
	lf_assert( '' === $posts->get( 'tax_query' ), 'queries of other post types are not touched' );

	$admin = new WP_Query( array( 'post_type' => 'idea_center' ) );
	$GLOBALS['lf_admin'] = true;
	Idea_Network_Elementor::filter_query( $admin );
	lf_assert( '' === $admin->get( 'tax_query' ), 'admin queries are not filtered' );
	$GLOBALS['lf_admin'] = false;

	// AJAX pagination has no URL parameters: the cookie keeps the filter alive.
	$_GET               = array();
	$GLOBALS['lf_ajax'] = true;
	$_COOKIE            = array(
		Idea_Network_Elementor::COOKIE_NAME => rawurlencode(
			json_encode(
				array(
					'category' => '',
					'province' => 'kermanshah',
					'city'     => '',
					'search'   => '',
				)
			)
		),
	);

	$ajax = new WP_Query( array( 'post_type' => 'idea_center' ) );
	Idea_Network_Elementor::filter_query( $ajax );

	$ajax_tax = $ajax->get( 'tax_query' );
	lf_assert( ! empty( $ajax_tax ) && 'kermanshah' === $ajax_tax[0]['terms'][0], 'AJAX pagination keeps the filter through the cookie' );

	$_COOKIE            = array();
	$GLOBALS['lf_ajax'] = false;

	// =======================================================================
	// 7) Filter parameters
	// =======================================================================
	$_GET = array(
		'shabake_cat'      => 'Canon',
		'shabake_province' => 'fars',
		'shabake_city'     => 'shiraz',
		'shabake_s'        => '  کانون مهر  ',
	);

	$params = Idea_Network_Elementor::get_filter_params();

	lf_same( 'canon', $params['category'], 'category parameter is sanitised as a slug' );
	lf_same( 'fars', $params['province'], 'province parameter is read' );
	lf_same( 'shiraz', $params['city'], 'city parameter is read' );
	lf_same( 'کانون مهر', $params['search'], 'search parameter is trimmed' );

	$_SERVER['REQUEST_URI'] = '/markaz/?custom=1&paged=2&shabake_province=fars';
	$action                 = Idea_Network_Elementor::get_form_action();

	lf_assert( false !== strpos( $action, 'custom=1' ), 'form action keeps unrelated parameters' );
	lf_assert( false === strpos( $action, 'shabake_province' ), 'form action drops the filter parameters' );
	lf_assert( false === strpos( $action, 'paged' ), 'form action drops the pagination' );

	// =======================================================================
	// 8) Cities payload for the dependent dropdown
	// =======================================================================
	$payload    = Idea_Network_Elementor::get_cities_payload();
	$by_name    = array();
	$without    = 0;

	foreach ( $payload as $item ) {
		$by_name[ $item['name'] ] = $item;

		if ( '' === $item['province'] ) {
			++$without;
		}
	}

	lf_assert( count( $payload ) > 500, 'payload lists every city' );
	lf_assert( 1 === $without, 'only the city created without a province has no province slug' );
	lf_assert( isset( $by_name['شیراز'] ) && (string) $fars->slug === $by_name['شیراز']['province'], 'city payload links شیراز to the فارس slug' );

	// =======================================================================
	// 9) Query ID hook
	// =======================================================================
	Idea_Network_Elementor::register_query_id( 'centers' );
	lf_assert( isset( $GLOBALS['lf_hooks']['elementor/query/centers'] ), 'query ID hook elementor/query/centers is registered' );

	// Elementor uses the Query ID verbatim, so its letter case must be kept.
	Idea_Network_Elementor::register_query_id( 'Centers' );
	lf_assert( isset( $GLOBALS['lf_hooks']['elementor/query/Centers'] ), 'query ID hook keeps the letter case (Centers)' );
	lf_same( 'Centers-1', Idea_Network_Elementor::normalize_query_id( ' Centers-1! ' ), 'query ID normalisation drops unsupported characters only' );

	// -----------------------------------------------------------------------
	// 9b) Simulating the Loop Grid query flow (Elementor_Post_Query)
	// -----------------------------------------------------------------------
	$_GET                = array(
		'shabake_province' => 'fars',
		'shabake_city'     => 'shiraz',
	);
	$_COOKIE             = array();
	$GLOBALS['lf_ajax']  = false;
	$GLOBALS['lf_admin'] = false;

	// With a Query ID, Elementor fires do_action( "elementor/query/{$query_id}" ).
	$loop_grid_query = new WP_Query(
		array(
			'post_type'      => 'idea_center',
			'posts_per_page' => 6,
		)
	);

	call_user_func( $GLOBALS['lf_hooks']['elementor/query/centers'], $loop_grid_query, null );

	$grid_tax = $loop_grid_query->get( 'tax_query' );

	lf_same( 'AND', $grid_tax['relation'], 'Loop Grid: province and city are ANDed' );
	lf_same( 'idea_province', $grid_tax[0]['taxonomy'], 'Loop Grid: the province clause reaches the grid query' );
	lf_same( 'idea_city', $grid_tax[1]['taxonomy'], 'Loop Grid: the city clause reaches the grid query' );

	// Without a Query ID only pre_get_posts runs, which must still filter the grid.
	$no_id_query = new WP_Query(
		array(
			'post_type'      => 'idea_center',
			'posts_per_page' => 6,
		)
	);

	Idea_Network_Elementor::pre_get_posts( $no_id_query );

	lf_assert( ! empty( $no_id_query->get( 'tax_query' ) ), 'Loop Grid without a Query ID is filtered through pre_get_posts' );

	// A grid showing other post types must stay untouched.
	$other_grid = new WP_Query( array( 'post_type' => 'post' ) );
	Idea_Network_Elementor::pre_get_posts( $other_grid );
	lf_assert( '' === $other_grid->get( 'tax_query' ), 'a Loop Grid of other post types is not filtered' );

	$payload = Idea_Network_Elementor::build_cookie_payload(
		array(
			'category' => '',
			'province' => 'fars',
			'city'     => 'shiraz',
			'search'   => 'کانون',
		)
	);
	$decoded = json_decode( rawurldecode( $payload ), true );

	lf_same( 'fars', $decoded['province'], 'the filter cookie keeps the province for AJAX pagination' );
	lf_same( 'کانون', $decoded['search'], 'the filter cookie keeps the search term' );

	// =======================================================================
	// 10) Widget registration and rendering
	// =======================================================================
	$manager = new Fake_Widgets_Manager();
	Idea_Network_Elementor::register_widgets( $manager );

	lf_same( 1, count( $manager->registered ), 'the filter widget is registered with Elementor' );

	// Elementor may fire both the new and the deprecated registration hook.
	Idea_Network_Elementor::register_widgets( $manager );
	Idea_Network_Elementor::register_widgets_legacy( $manager );
	lf_same( 1, count( $manager->registered ), 'the widget is never registered twice' );

	lf_assert( false === Idea_Network_Elementor::is_widget_registered(), 'without Elementor the widget is not reported as registered' );

	$elements = new Fake_Elements_Manager();
	Idea_Network_Elementor::register_category( $elements );
	lf_assert( isset( $elements->categories['idea-network'] ), 'the widget category «شبکه ایده پردازی» is registered' );

	$widget = $manager->registered[0];
	lf_same( 'idea-network-loop-filter', $widget->get_name(), 'widget name' );
	lf_same( 'idea-network', $widget->get_categories()[0], 'widget is listed in the plugin category' );
	lf_same( 'idea-network-loop-filter', $widget->get_script_depends()[0], 'widget depends on its script handle' );
	lf_same( 'idea-network-loop-filter', $widget->get_style_depends()[0], 'widget depends on its style handle' );

	$controls = new ReflectionMethod( 'Idea_Network_Loop_Filter_Widget', 'register_controls' );
	$controls->setAccessible( true );
	$controls->invoke( $widget );

	lf_assert( isset( $GLOBALS['widget_controls']['category_label'] ), 'category label control exists' );
	lf_assert( isset( $GLOBALS['widget_controls']['province_all_label'] ), 'province «all» label control exists' );
	lf_assert( isset( $GLOBALS['widget_controls']['city_all_label'] ), 'city «all» label control exists' );
	lf_assert( isset( $GLOBALS['widget_controls']['query_id'] ), 'query ID control exists' );
	lf_assert( isset( $GLOBALS['widget_controls']['cities_by_province'] ), 'dependent city control exists' );
	lf_assert( isset( $GLOBALS['widget_groups']['field_typography'] ), 'typography style control exists' );

	$_GET                   = array(
		'shabake_province' => $fars->slug,
		'custom'           => '1',
	);
	$_SERVER['REQUEST_URI'] = '/markaz/';

	$render_widget = new Idea_Network_Loop_Filter_Widget(
		array(
			'query_id'           => 'centers',
			'show_category'      => 'yes',
			'show_province'      => 'yes',
			'show_city'          => 'yes',
			'show_search'        => 'yes',
			'show_submit'        => 'yes',
			'auto_submit'        => 'yes',
			'cities_by_province' => 'yes',
			'category_label'     => 'دسته بندی',
			'category_all_label' => 'همه دسته بندی ها',
			'province_label'     => 'استان',
			'province_all_label' => 'همه استان ها',
			'city_label'         => 'شهر',
			'city_all_label'     => 'همه شهرها',
			'search_placeholder' => 'جستجوی نام مرکز',
			'submit_label'       => 'اعمال فیلتر',
		)
	);

	$render = new ReflectionMethod( 'Idea_Network_Loop_Filter_Widget', 'render' );
	$render->setAccessible( true );

	ob_start();
	$render->invoke( $render_widget );
	$html = ob_get_clean();

	lf_assert( false !== strpos( $html, 'class="shabake-loop-filter"' ), 'widget renders the filter form' );
	lf_assert( false !== strpos( $html, 'name="shabake_cat"' ), 'form has the category select' );
	lf_assert( false !== strpos( $html, 'name="shabake_province"' ), 'form has the province select' );
	lf_assert( false !== strpos( $html, 'name="shabake_city"' ), 'form has the city select' );
	lf_assert( false !== strpos( $html, 'name="shabake_s"' ), 'form has the search field' );
	lf_assert( false !== strpos( $html, 'data-filter="province"' ), 'province select is wired for the dependent city list' );
	lf_assert( false !== strpos( $html, 'data-cities=' ), 'city select carries the province mapping' );
	lf_assert( false !== strpos( $html, 'همه استان ها' ), 'province «all» option is rendered' );
	lf_assert( false !== strpos( $html, 'همه شهرها' ), 'city «all» option is rendered' );
	lf_assert( false !== strpos( $html, 'selected="selected"' ), 'the selected province is kept after filtering' );
	lf_assert( false !== strpos( $html, 'name="custom" value="1"' ), 'unrelated URL parameters survive in the form' );

	$hidden_widget = new Idea_Network_Loop_Filter_Widget(
		array(
			'show_category' => '',
			'show_province' => '',
			'show_city'     => '',
			'show_search'   => '',
			'show_submit'   => '',
		)
	);

	ob_start();
	$render->invoke( $hidden_widget );
	$hidden_html = ob_get_clean();

	lf_assert( false === strpos( $hidden_html, 'name="shabake_cat"' ), 'disabled category filter is not rendered' );
	lf_assert( false === strpos( $hidden_html, 'name="shabake_s"' ), 'disabled search field is not rendered' );

	// =======================================================================
	echo "\n{$GLOBALS['lf_passes']} passed, {$GLOBALS['lf_failures']} failed\n";

	exit( $GLOBALS['lf_failures'] > 0 ? 1 : 0 );
}
