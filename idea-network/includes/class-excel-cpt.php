<?php
/**
 * Custom post type and record fields.
 *
 * @package excel-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the "idea_center" post type and handles its fields.
 */
class Idea_Network_CPT {

	/**
	 * Post type slug.
	 *
	 * Kept as "idea_center" (new slug) so it never collides with the old
	 * "excel_record" post type from the legacy plugin.
	 *
	 * @var string
	 */
	const POST_TYPE = 'idea_center';

	/**
	 * Category taxonomy slug (kept).
	 *
	 * @var string
	 */
	const TAXONOMY = 'idea_category';

	/**
	 * Province taxonomy slug («استان»).
	 *
	 * @var string
	 */
	const TAXONOMY_PROVINCE = 'idea_province';

	/**
	 * City taxonomy slug («شهر»).
	 *
	 * @var string
	 */
	const TAXONOMY_CITY = 'idea_city';

	/**
	 * Returns the ordered map of the nine supported columns.
	 *
	 * The order of this array defines the column order expected in the spreadsheet.
	 * An empty meta key means the value is stored as the post title.
	 *
	 * @return array<string,array{label:string,meta_key:string}>
	 */
	public static function get_columns_map() {
		return array(
			'center_name'    => array(
				'label'    => __( 'نام مراکز', 'excel-importer' ),
				'meta_key' => '',
			),
			'center_count'   => array(
				'label'    => __( 'تعداد مراکز', 'excel-importer' ),
				'meta_key' => 'ei_center_count',
			),
			'province_count' => array(
				'label'    => __( 'تعداد استان', 'excel-importer' ),
				'meta_key' => 'ei_province_count',
			),
			'icon'           => array(
				'label'    => __( 'ایکون', 'excel-importer' ),
				'meta_key' => 'ei_icon',
			),
			'address'        => array(
				'label'    => __( 'آدرس', 'excel-importer' ),
				'meta_key' => 'ei_address',
			),
			'phone'          => array(
				'label'    => __( 'شماره تماس', 'excel-importer' ),
				'meta_key' => 'ei_phone',
			),
			'website'        => array(
				'label'    => __( 'آدرس وبسایت', 'excel-importer' ),
				'meta_key' => 'ei_website',
			),
			'city'           => array(
				'label'    => __( 'نام شهر', 'excel-importer' ),
				'meta_key' => 'ei_city',
			),
			'province_name'  => array(
				'label'    => __( 'نام استان', 'excel-importer' ),
				'meta_key' => 'ei_province_name',
			),
		);
	}

	/**
	 * Returns only the meta fields (all columns except the title).
	 *
	 * @return array<string,array{label:string,meta_key:string}>
	 */
	public static function get_meta_fields() {
		$fields = array();

		foreach ( self::get_columns_map() as $key => $field ) {
			if ( '' !== $field['meta_key'] ) {
				$fields[ $key ] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Maps one spreadsheet row to the nine supported values.
	 *
	 * Only the first nine cells are used; every extra column is ignored.
	 *
	 * @param array $cells Raw cells of a row.
	 * @return array<string,string>
	 */
	public static function map_row( array $cells ) {
		$cells = array_slice( array_values( $cells ), 0, 9 );
		$cells = array_pad( $cells, 9, '' );
		$keys  = array_keys( self::get_columns_map() );

		$mapped = array();

		foreach ( $keys as $index => $key ) {
			$mapped[ $key ] = trim( str_replace( "\0", '', (string) $cells[ $index ] ) );
		}

		return $mapped;
	}

	/**
	 * Reads a field value for the current post.
	 *
	 * Falls back to the legacy protected key ("_ei_*") so data imported by
	 * older versions of the plugin keeps working.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $field   Field definition from get_columns_map().
	 * @return string
	 */
	public static function get_field_value( $post_id, $field ) {
		$value = get_post_meta( $post_id, $field['meta_key'], true );

		if ( '' === $value || null === $value ) {
			$legacy = get_post_meta( $post_id, '_' . $field['meta_key'], true );

			if ( '' !== $legacy && null !== $legacy ) {
				return $legacy;
			}
		}

		return $value;
	}

	/**
	 * Registers the custom post type.
	 *
	 * Post type slug is "idea_center" so it never collides with the legacy
	 * "excel_record" type. Labels are «مرکز / مراکز» and rewrite slug is "markaz".
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'                     => __( 'مراکز', 'excel-importer' ),
			'singular_name'            => __( 'مرکز', 'excel-importer' ),
			'menu_name'                => __( 'مراکز', 'excel-importer' ),
			'add_new'                  => __( 'افزودن', 'excel-importer' ),
			'add_new_item'             => __( 'افزودن مرکز جدید', 'excel-importer' ),
			'edit_item'                => __( 'ویرایش مرکز', 'excel-importer' ),
			'new_item'                 => __( 'مرکز جدید', 'excel-importer' ),
			'view_item'                => __( 'مشاهده مرکز', 'excel-importer' ),
			'view_items'               => __( 'مشاهده مراکز', 'excel-importer' ),
			'search_items'             => __( 'جستجوی مراکز', 'excel-importer' ),
			'not_found'                => __( 'مرکزی یافت نشد.', 'excel-importer' ),
			'not_found_in_trash'       => __( 'مرکزی در سطل زباله یافت نشد.', 'excel-importer' ),
			'all_items'                => __( 'همه مراکز', 'excel-importer' ),
			'item_updated'             => __( 'مرکز به‌روزرسانی شد.', 'excel-importer' ),
			'item_published'           => __( 'مرکز منتشر شد.', 'excel-importer' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'markaz' ),
				'show_ui'             => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-media-spreadsheet',
				// All the fields of a standard blog post (same as the "post" type).
				'supports'            => array(
					'title',
					'editor',
					'author',
					'thumbnail',
					'excerpt',
					'trackbacks',
					'custom-fields',
					'comments',
					'revisions',
				),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Registers the «دسته بندی ها» taxonomy (hierarchical, like post categories).
	 *
	 * Adds a "دسته بندی ها" submenu under the post type menu, a taxonomy meta
	 * box on the edit screen and a list-table column (show_admin_column).
	 *
	 * @return void
	 */
	public static function register_taxonomies() {
		$labels = array(
			'name'                       => __( 'دسته بندی ها', 'excel-importer' ),
			'singular_name'              => __( 'دسته بندی', 'excel-importer' ),
			'menu_name'                  => __( 'دسته بندی ها', 'excel-importer' ),
			'all_items'                  => __( 'همه دسته بندی ها', 'excel-importer' ),
			'edit_item'                  => __( 'ویرایش دسته بندی', 'excel-importer' ),
			'view_item'                  => __( 'مشاهده دسته بندی', 'excel-importer' ),
			'update_item'                => __( 'به‌روزرسانی دسته بندی', 'excel-importer' ),
			'add_new_item'               => __( 'افزودن دسته بندی جدید', 'excel-importer' ),
			'new_item_name'              => __( 'نام دسته بندی جدید', 'excel-importer' ),
			'parent_item'                => __( 'دسته بندی والد', 'excel-importer' ),
			'parent_item_colon'          => __( 'دسته بندی والد:', 'excel-importer' ),
			'search_items'               => __( 'جستجوی دسته بندی ها', 'excel-importer' ),
			'not_found'                  => __( 'دسته بندی‌ای یافت نشد.', 'excel-importer' ),
			'back_to_items'              => __( 'بازگشت به دسته بندی ها', 'excel-importer' ),
			'popular_items'              => __( 'دسته بندی های پرکاربرد', 'excel-importer' ),
			'separate_items_with_commas' => __( 'دسته بندی ها را با کاما جدا کنید', 'excel-importer' ),
			'add_or_remove_items'        => __( 'افزودن یا حذف دسته بندی', 'excel-importer' ),
			'choose_from_most_used'      => __( 'انتخاب از پرکاربردها', 'excel-importer' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'publicly_queryable' => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'markaz-category' ),
			)
		);

		self::register_location_taxonomies();
	}

	/**
	 * Registers the «استان» and «شهر» taxonomies used by the Loop Grid filter.
	 *
	 * Both are flat (one province and one city per center) and get their terms
	 * from the importer columns «نام استان» and «نام شهر», plus the full list of
	 * Iranian provinces and cities.
	 *
	 * @return void
	 */
	public static function register_location_taxonomies() {
		$province_labels = array(
			'name'          => __( 'استان ها', 'excel-importer' ),
			'singular_name' => __( 'استان', 'excel-importer' ),
			'menu_name'     => __( 'استان ها', 'excel-importer' ),
			'all_items'     => __( 'همه استان ها', 'excel-importer' ),
			'edit_item'     => __( 'ویرایش استان', 'excel-importer' ),
			'view_item'     => __( 'مشاهده استان', 'excel-importer' ),
			'add_new_item'  => __( 'افزودن استان جدید', 'excel-importer' ),
			'new_item_name' => __( 'نام استان جدید', 'excel-importer' ),
			'search_items'  => __( 'جستجوی استان ها', 'excel-importer' ),
			'not_found'     => __( 'استانی یافت نشد.', 'excel-importer' ),
		);

		register_taxonomy(
			self::TAXONOMY_PROVINCE,
			array( self::POST_TYPE ),
			array(
				'labels'             => $province_labels,
				'hierarchical'       => false,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'rewrite'            => array( 'slug' => 'markaz-province' ),
			)
		);

		$city_labels = array(
			'name'          => __( 'شهرها', 'excel-importer' ),
			'singular_name' => __( 'شهر', 'excel-importer' ),
			'menu_name'     => __( 'شهرها', 'excel-importer' ),
			'all_items'     => __( 'همه شهرها', 'excel-importer' ),
			'edit_item'     => __( 'ویرایش شهر', 'excel-importer' ),
			'view_item'     => __( 'مشاهده شهر', 'excel-importer' ),
			'add_new_item'  => __( 'افزودن شهر جدید', 'excel-importer' ),
			'new_item_name' => __( 'نام شهر جدید', 'excel-importer' ),
			'search_items'  => __( 'جستجوی شهرها', 'excel-importer' ),
			'not_found'     => __( 'شهری یافت نشد.', 'excel-importer' ),
		);

		register_taxonomy(
			self::TAXONOMY_CITY,
			array( self::POST_TYPE ),
			array(
				'labels'             => $city_labels,
				'hierarchical'       => false,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'rewrite'            => array( 'slug' => 'markaz-city' ),
			)
		);
	}

	/**
	 * Assigns comma separated terms to a post (creating missing terms).
	 *
	 * Accepts Persian (،), Latin (,), pipe (|) and slash (/) separators.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $value    Raw cell value.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public static function assign_terms( $post_id, $value, $taxonomy ) {
		$parts = preg_split( '/[,،|\/]+/u', (string) $value );
		$names = array();

		foreach ( (array) $parts as $part ) {
			$part = trim( $part );

			if ( '' !== $part ) {
				$names[] = $part;
			}
		}

		if ( empty( $names ) ) {
			return;
		}

		// Missing terms are created automatically.
		wp_set_object_terms( $post_id, $names, $taxonomy, false );
	}

	/**
	 * Registers the eight fields as custom post meta.
	 *
	 * This exposes them to the REST API (show_in_rest) which Elementor and
	 * other builders rely on, and sanitizes any value written through REST.
	 *
	 * @return void
	 */
	public static function register_meta() {
		foreach ( self::get_meta_fields() as $field ) {
			register_post_meta(
				self::POST_TYPE,
				$field['meta_key'],
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Adds the meta box for the eight text fields.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'idea-network-record-fields',
			__( 'مشخصات مرکز', 'excel-importer' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box fields.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'idea_network_save_meta', 'idea_network_meta_nonce' );

		echo '<p class="description">';
		echo esc_html__(
			'برای استفاده در المنتور: از پویاساز «فیلد سفارشی / Custom Field» استفاده کنید و کلید (meta key) هر فیلد را که کنارش نوشته شده وارد کنید. «نام مراکز» در عنوان پست ذخیره می‌شود.',
			'excel-importer'
		);

		echo ' ';
		echo esc_html__(
			'«دسته بندی» اکنون یک دسته‌بندی واقعی است: آن را در جعبه «دسته بندی ها» زیر ویرایشگر یا از منوی «مراکز ← دسته بندی ها» مدیریت کنید.',
			'excel-importer'
		);
		echo '</p>';

		foreach ( self::get_meta_fields() as $key => $field ) {

			$value = self::get_field_value( $post->ID, $field );

			printf(
				'<p><label for="%1$s"><strong>%2$s</strong> &nbsp; <code>%3$s</code></label><br /><input type="text" class="widefat" id="%1$s" name="%1$s" value="%4$s" /></p>',
				esc_attr( $key ),
				esc_html( $field['label'] ),
				esc_html( $field['meta_key'] ),
				esc_attr( $value )
			);
		}
	}

	/**
	 * Saves the meta box fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['idea_network_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['idea_network_meta_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'idea_network_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'revision' === $post->post_type ) {
			return;
		}

		foreach ( self::get_meta_fields() as $key => $field ) {
			$raw   = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$value = sanitize_text_field( $raw );

			update_post_meta( $post_id, $field['meta_key'], $value );

			// Drop the legacy protected key ("_ei_*") after migrating to the visible key.
			delete_post_meta( $post_id, '_' . $field['meta_key'] );
		}
	}

	/**
	 * Replaces the list table columns with the import fields.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function list_columns( $columns ) {
		$new_columns = array(
			'cb'    => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title' => __( 'نام مراکز', 'excel-importer' ),
		);

		foreach ( self::get_meta_fields() as $key => $field ) {

			$new_columns[ $key ] = $field['label'];
		}

		$new_columns['date'] = __( 'تاریخ', 'excel-importer' );

		return $new_columns;
	}

	/**
	 * Prints a custom column value in the list table.
	 *
	 * @param string $column_name Column slug.
	 * @param int    $post_id     Post ID.
	 * @return void
	 */
	public static function list_column_value( $column_name, $post_id ) {
		$fields = self::get_meta_fields();

		if ( ! isset( $fields[ $column_name ] ) ) {
			return;
		}

		echo esc_html( self::get_field_value( $post_id, $fields[ $column_name ] ) );
	}
}

