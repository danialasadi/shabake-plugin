<?php
/**
 * Elementor integration: filter widget registration and Loop Grid filtering.
 *
 * @package excel-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the filter widget and applies the selected filters to Elementor queries.
 */
class Idea_Network_Elementor {

	/**
	 * Elementor widget category slug.
	 *
	 * @var string
	 */
	const WIDGET_CATEGORY = 'idea-network';

	/**
	 * Front-end stylesheet handle.
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'idea-network-loop-filter';

	/**
	 * Front-end script handle.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'idea-network-loop-filter';

	/**
	 * URL parameter holding the selected category slug.
	 *
	 * @var string
	 */
	const PARAM_CATEGORY = 'shabake_cat';

	/**
	 * URL parameter holding the selected province slug.
	 *
	 * @var string
	 */
	const PARAM_PROVINCE = 'shabake_province';

	/**
	 * URL parameter holding the selected city slug.
	 *
	 * @var string
	 */
	const PARAM_CITY = 'shabake_city';

	/**
	 * URL parameter holding the search term.
	 *
	 * @var string
	 */
	const PARAM_SEARCH = 'shabake_s';

	/**
	 * Cookie keeping the last filter (used by Loop Grid AJAX pagination).
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'shabake_filter';

	/**
	 * Query IDs already hooked.
	 *
	 * @var array<string,bool>
	 */
	protected static $query_ids = array();

	/**
	 * Terms already loaded during this request, keyed by taxonomy.
	 *
	 * @var array<string,array<int,WP_Term>>
	 */
	protected static $terms_cache = array();

	/**
	 * Whether the filter widget was already registered in this request.
	 *
	 * @var bool
	 */
	protected static $widget_registered = false;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/widgets/widgets_registered', array( __CLASS__, 'register_widgets_legacy' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_status_notice' ) );

		// Loop Grid runs its own WP_Query through Elementor_Post_Query, so the filters
		// are applied on pre_get_posts (the elementor/query/{id} hook is added on top).
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 20 );

		// Keep the active filter in a cookie for AJAX pagination requests.
		add_action( 'wp', array( __CLASS__, 'maybe_store_filter_cookie' ) );
	}

	/**
	 * Checks whether the current request renders widgets dynamically (AJAX or REST).
	 *
	 * @return bool
	 */
	public static function is_dynamic_request() {
		if ( wp_doing_ajax() ) {
			return true;
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Normalises a Query ID without changing its letter case.
	 *
	 * Elementor builds the hook name from the Query ID exactly as typed, so
	 * «Centers» and «centers» are two different hooks.
	 *
	 * @param string $query_id Raw Query ID.
	 * @return string
	 */
	public static function normalize_query_id( $query_id ) {
		return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $query_id );
	}

	/**
	 * Adds the «شبکه ایده پردازی» widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public static function register_category( $elements_manager ) {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}

		$elements_manager->add_category(
			self::WIDGET_CATEGORY,
			array(
				'title' => __( 'شبکه ایده پردازی', 'excel-importer' ),
				'icon'  => 'eicon-filter',
			)
		);
	}

	/**
	 * Registers the widget on Elementor 3.5+.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ) {
		if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}

		self::ensure_category();
		self::load_widget_class();

		if ( ! self::$widget_registered && class_exists( 'Idea_Network_Loop_Filter_Widget' ) ) {
			$widgets_manager->register( new Idea_Network_Loop_Filter_Widget() );
			self::$widget_registered = true;
		}
	}

	/**
	 * Registers the widget on Elementor versions older than 3.5.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widgets_legacy( $widgets_manager ) {
		if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register_widget_type' ) ) {
			return;
		}

		self::ensure_category();
		self::load_widget_class();

		if ( ! self::$widget_registered && class_exists( 'Idea_Network_Loop_Filter_Widget' ) ) {
			$widgets_manager->register_widget_type( new Idea_Network_Loop_Filter_Widget() );
			self::$widget_registered = true;
		}
	}

	/**
	 * Loads the widget class; a half-uploaded plugin must not break the site.
	 *
	 * @return void
	 */
	private static function load_widget_class() {
		if ( class_exists( 'Idea_Network_Loop_Filter_Widget' ) ) {
			return;
		}

		$file = IDEA_NETWORK_PATH . 'includes/widgets/class-loop-filter-widget.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Makes sure the «شبکه ایده پردازی» category exists in the Elementor panel.
	 *
	 * A widget whose category is missing may not be listed in the panel, so the
	 * category is also added lazily for when the manager hook was missed.
	 *
	 * @return void
	 */
	private static function ensure_category() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->elements_manager ) ) {
			return;
		}

		$elements_manager = \Elementor\Plugin::$instance->elements_manager;

		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}

		if ( method_exists( $elements_manager, 'get_categories' ) ) {
			$categories = (array) $elements_manager->get_categories();

			if ( isset( $categories[ self::WIDGET_CATEGORY ] ) ) {
				return;
			}
		}

		$elements_manager->add_category(
			self::WIDGET_CATEGORY,
			array(
				'title' => __( 'شبکه ایده پردازی', 'excel-importer' ),
				'icon'  => 'eicon-filter',
			)
		);
	}

	/**
	 * Checks whether the filter widget is registered in Elementor.
	 *
	 * @return bool
	 */
	public static function is_widget_registered() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return false;
		}

		$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;

		if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'get_widget_types' ) ) {
			return false;
		}

		return (bool) $widgets_manager->get_widget_types( 'idea-network-loop-filter' );
	}

	/**
	 * Warns the administrator when the widget cannot appear in the editor.
	 *
	 * @return void
	 */
	public static function render_status_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . Idea_Network_CPT::POST_TYPE !== $screen->id ) {
			return;
		}

		if ( ! did_action( 'elementor/loaded' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'ویجت «فیلتر حلقه (دسته بندی، استان، شهر)» برای نمایش در ویرایشگر به افزونه المنتور نیاز دارد؛ المنتور روی این سایت فعال نیست.', 'excel-importer' ) . '</p></div>';

			return;
		}

		if ( self::is_widget_registered() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'ویجت «فیلتر حلقه» در المنتور ثبت نشد. بررسی کنید فایل idea-network/includes/widgets/class-loop-filter-widget.php روی هاست موجود باشد و نسخه افزونه 1.3.0 باشد، سپس صفحه ویرایشگر را دوباره بارگذاری کنید.', 'excel-importer' ) . '</p></div>';
	}

	/**
	 * Registers the widget stylesheet.
	 *
	 * @return void
	 */
	public static function register_styles() {
		wp_register_style(
			self::STYLE_HANDLE,
			IDEA_NETWORK_URL . 'assets/loop-filter.css',
			array(),
			IDEA_NETWORK_VERSION
		);
	}

	/**
	 * Registers the widget script.
	 *
	 * @return void
	 */
	public static function register_scripts() {
		wp_register_script(
			self::SCRIPT_HANDLE,
			IDEA_NETWORK_URL . 'assets/loop-filter.js',
			array(),
			IDEA_NETWORK_VERSION,
			true
		);
	}

	/**
	 * Hooks the filter into a specific Elementor Query ID.
	 *
	 * @param string $query_id Query ID set on the Loop Grid widget.
	 * @return void
	 */
	public static function register_query_id( $query_id ) {
		$query_id = self::normalize_query_id( $query_id );

		if ( '' === $query_id || isset( self::$query_ids[ $query_id ] ) ) {
			return;
		}

		self::$query_ids[ $query_id ] = true;

		add_action( 'elementor/query/' . $query_id, array( __CLASS__, 'filter_query' ), 20 );
	}

	/**
	 * Returns the URL keys owned by the filter (they are never preserved as hidden fields).
	 *
	 * @return array<int,string>
	 */
	public static function get_reserved_params() {
		return array(
			self::PARAM_CATEGORY,
			self::PARAM_PROVINCE,
			self::PARAM_CITY,
			self::PARAM_SEARCH,
			'paged',
			'page',
			'e-page',
			'elementor-page',
		);
	}

	/**
	 * Returns the current form action (the page URL without the filter parameters).
	 *
	 * @return string
	 */
	public static function get_form_action() {
		return (string) remove_query_arg( self::get_reserved_params() );
	}

	/**
	 * Reads the selected filters from the URL, falling back to the cookie during AJAX.
	 *
	 * The cookie fallback keeps the filter alive when the Loop Grid loads the next
	 * page through AJAX (that request carries no front-end URL parameters).
	 *
	 * @return array{category:string,province:string,city:string,search:string}
	 */
	public static function get_filter_params() {
		$params = array(
			'category' => '',
			'province' => '',
			'city'     => '',
			'search'   => '',
		);

		$slugs = array(
			'category' => self::PARAM_CATEGORY,
			'province' => self::PARAM_PROVINCE,
			'city'     => self::PARAM_CITY,
		);

		foreach ( $slugs as $key => $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$params[ $key ] = sanitize_title( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		if ( isset( $_GET[ self::PARAM_SEARCH ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params['search'] = sanitize_text_field( wp_unslash( $_GET[ self::PARAM_SEARCH ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$is_empty = '' === $params['category'] && '' === $params['province'] && '' === $params['city'] && '' === $params['search'];

		if ( $is_empty && self::is_dynamic_request() ) {
			$cookie = self::get_cookie_params();

			if ( $cookie ) {
				$params = $cookie;
			}
		}

		return self::normalize_params( $params );
	}

	/**
	 * Keeps the province and the city consistent.
	 *
	 * The city is a child of a province: a city slug alone (a shared link, a
	 * cookie from another page) fills its province, a city that belongs to
	 * another province replaces the selected one, and an unknown city is dropped
	 * so a stale link can never filter by an unrelated city.
	 *
	 * @param array $params Filter parameters.
	 * @return array
	 */
	public static function normalize_params( array $params ) {
		$city = isset( $params['city'] ) ? (string) $params['city'] : '';

		if ( '' === $city ) {
			return $params;
		}

		$province = self::get_city_province_slug( $city );

		if ( '' !== $province ) {
			$params['province'] = $province;
		} else {
			$params['city'] = '';
		}

		return $params;
	}

	/**
	 * Returns the slug of the province a city term belongs to.
	 *
	 * @param string $city_slug City term slug.
	 * @return string Province slug, or an empty string when unknown.
	 */
	public static function get_city_province_slug( $city_slug ) {
		$city_slug = sanitize_title( (string) $city_slug );

		if ( '' === $city_slug ) {
			return '';
		}

		$city = get_term_by( 'slug', $city_slug, Idea_Network_CPT::TAXONOMY_CITY );

		if ( ! $city || is_wp_error( $city ) ) {
			return '';
		}

		$province_id = (int) get_term_meta( (int) $city->term_id, Idea_Network_Locations::PROVINCE_META, true );

		if ( ! $province_id ) {
			return '';
		}

		$province = get_term( $province_id, Idea_Network_CPT::TAXONOMY_PROVINCE );

		if ( ! $province || is_wp_error( $province ) ) {
			return '';
		}

		return (string) $province->slug;
	}

	/**
	 * Builds the cookie value that keeps the filter during AJAX pagination.
	 *
	 * @param array $params Filter parameters.
	 * @return string
	 */
	public static function build_cookie_payload( array $params ) {
		return rawurlencode(
			(string) wp_json_encode(
				array(
					'category' => isset( $params['category'] ) ? $params['category'] : '',
					'province' => isset( $params['province'] ) ? $params['province'] : '',
					'city'     => isset( $params['city'] ) ? $params['city'] : '',
					'search'   => isset( $params['search'] ) ? $params['search'] : '',
				)
			)
		);
	}

	/**
	 * Stores the active filter in a cookie.
	 *
	 * Loop Grid AJAX pagination and «Load More» run the query again in a request
	 * that carries no URL parameters; the cookie keeps the filter for those requests.
	 *
	 * @return void
	 */
	public static function maybe_store_filter_cookie() {
		if ( self::is_dynamic_request() || headers_sent() ) {
			return;
		}

		$params = self::get_filter_params();

		if ( '' === $params['category'] && '' === $params['province'] && '' === $params['city'] && '' === $params['search'] ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			self::build_cookie_payload( $params ),
			array(
				'expires'  => time() + 86400,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Reads the filter cookie written by the front-end script.
	 *
	 * @return array{category:string,province:string,city:string,search:string}|null
	 */
	public static function get_cookie_params() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$raw   = rawurldecode( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = json_decode( $raw, true );

		if ( ! is_array( $value ) ) {
			return null;
		}

		return array(
			'category' => isset( $value['category'] ) ? sanitize_title( $value['category'] ) : '',
			'province' => isset( $value['province'] ) ? sanitize_title( $value['province'] ) : '',
			'city'     => isset( $value['city'] ) ? sanitize_title( $value['city'] ) : '',
			'search'   => isset( $value['search'] ) ? sanitize_text_field( $value['search'] ) : '',
		);
	}

	/**
	 * Builds the tax_query array for the selected filters.
	 *
	 * @param array $params Filter parameters from get_filter_params().
	 * @return array
	 */
	public static function build_tax_query( array $params ) {
		$tax_query = array();

		if ( ! empty( $params['category'] ) ) {
			$tax_query[] = array(
				'taxonomy'         => Idea_Network_CPT::TAXONOMY,
				'field'            => 'slug',
				'terms'            => array( $params['category'] ),
				'include_children' => true,
			);
		}

		if ( ! empty( $params['province'] ) ) {
			$tax_query[] = array(
				'taxonomy'         => Idea_Network_CPT::TAXONOMY_PROVINCE,
				'field'            => 'slug',
				'terms'            => array( $params['province'] ),
				'include_children' => false,
			);
		}

		if ( ! empty( $params['city'] ) ) {
			$tax_query[] = array(
				'taxonomy'         => Idea_Network_CPT::TAXONOMY_CITY,
				'field'            => 'slug',
				'terms'            => array( $params['city'] ),
				'include_children' => false,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	/**
	 * Checks whether a query belongs to the centers (a Loop Grid or a posts widget).
	 *
	 * @param WP_Query $query Query to inspect.
	 * @return bool
	 */
	public static function is_centers_query( $query ) {
		$post_types = array_filter( (array) $query->get( 'post_type' ) );

		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		return in_array( Idea_Network_CPT::POST_TYPE, $post_types, true ) || in_array( 'any', $post_types, true );
	}

	/**
	 * Applies the filters to a query.
	 *
	 * @param WP_Query $query Query instance.
	 * @return void
	 */
	public static function filter_query( $query ) {
		if ( ! $query instanceof WP_Query ) {
			return;
		}

		if ( $query->get( 'idea_network_filtered' ) ) {
			return;
		}

		if ( is_admin() && ! self::is_dynamic_request() ) {
			return;
		}

		if ( ! self::is_centers_query( $query ) ) {
			return;
		}

		$params    = self::get_filter_params();
		$tax_query = self::build_tax_query( $params );

		if ( empty( $tax_query ) && '' === $params['search'] ) {
			return;
		}

		$query->set( 'idea_network_filtered', 1 );

		if ( ! empty( $tax_query ) ) {
			$query->set( 'tax_query', self::merge_tax_query( $query->get( 'tax_query' ), $tax_query ) );
		}

		if ( '' !== $params['search'] ) {
			$query->set( 's', $params['search'] );
		}
	}

	/**
	 * Merges the plugin clauses into the query's existing tax_query.
	 *
	 * Elementor may set its own clauses with a relation of OR (for example on a
	 * category loop); the existing clauses are therefore kept in a nested group so
	 * they can never turn the selected province/city/category into an OR.
	 *
	 * @param mixed $existing Existing tax_query value.
	 * @param array $new      Clauses from build_tax_query().
	 * @return array
	 */
	public static function merge_tax_query( $existing, array $new ) {
		$existing = is_array( $existing ) ? $existing : array();

		if ( empty( $new ) ) {
			return $existing;
		}

		if ( empty( $existing ) ) {
			return $new;
		}

		return array(
			'relation' => 'AND',
			$existing,
			$new,
		);
	}

	/**
	 * Filters every loop query on the front end.
	 *
	 * @param WP_Query $query Query instance.
	 * @return void
	 */
	public static function pre_get_posts( $query ) {
		self::filter_query( $query );
	}

	/**
	 * Returns terms of a taxonomy without ever returning a WP_Error.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<int,WP_Term>
	 */
	public static function get_terms( $taxonomy ) {
		if ( isset( self::$terms_cache[ $taxonomy ] ) ) {
			return self::$terms_cache[ $taxonomy ];
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$terms = is_wp_error( $terms ) || ! is_array( $terms ) ? array() : $terms;

		self::$terms_cache[ $taxonomy ] = $terms;

		return $terms;
	}

	/**
	 * Returns the center categories.
	 *
	 * @return array<int,WP_Term>
	 */
	public static function get_categories_terms() {
		return self::get_terms( Idea_Network_CPT::TAXONOMY );
	}

	/**
	 * Returns the province terms.
	 *
	 * @return array<int,WP_Term>
	 */
	public static function get_provinces() {
		return self::get_terms( Idea_Network_CPT::TAXONOMY_PROVINCE );
	}

	/**
	 * Returns the city terms.
	 *
	 * @return array<int,WP_Term>
	 */
	public static function get_cities() {
		return self::get_terms( Idea_Network_CPT::TAXONOMY_CITY );
	}

	/**
	 * Returns the cities with their province, for the dependent city dropdown.
	 *
	 * @return array<int,array{slug:string,name:string,province:string}>
	 */
	public static function get_cities_payload() {
		$province_slugs = array();

		foreach ( self::get_provinces() as $province ) {
			$province_slugs[ (int) $province->term_id ] = (string) $province->slug;
		}

		$cities = self::get_cities();

		// Prime the term meta cache so the loop below does not run one query per city.
		if ( $cities && function_exists( 'update_termmeta_cache' ) ) {
			update_termmeta_cache( wp_list_pluck( $cities, 'term_id' ) );
		}

		$payload = array();

		foreach ( $cities as $city ) {
			$province_id = (int) get_term_meta( $city->term_id, Idea_Network_Locations::PROVINCE_META, true );

			$payload[] = array(
				'slug'     => (string) $city->slug,
				'name'     => (string) $city->name,
				'province' => isset( $province_slugs[ $province_id ] ) ? $province_slugs[ $province_id ] : '',
			);
		}

		return $payload;
	}
}
