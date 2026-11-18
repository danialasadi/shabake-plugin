<?php
/**
 * Admin page: Excel upload and bulk import.
 *
 * @package excel-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders the importer admin page and handles the import request.
 */
class Idea_Network_Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const SLUG = 'idea-network';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Form action hook suffix.
	 *
	 * @var string
	 */
	const ACTION = 'idea_network_run';

	/**
	 * Import nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'idea_network_import';

	/**
	 * Template download action.
	 *
	 * @var string
	 */
	const TEMPLATE_ACTION = 'idea_network_template';

	/**
	 * Template download nonce action.
	 *
	 * @var string
	 */
	const TEMPLATE_NONCE = 'idea_network_template';

	/**
	 * Transient prefix for the last import result (per user).
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'idea_network_result_';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_' . self::TEMPLATE_ACTION, array( __CLASS__, 'download_template' ) );
	}

	/**
	 * Adds the importer submenu under the custom post type menu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Idea_Network_CPT::POST_TYPE,
			__( 'درون‌ریزی اکسل', 'excel-importer' ),
			__( 'درون‌ریزی اکسل', 'excel-importer' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueues the admin stylesheet on the importer page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		if ( self::SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		wp_enqueue_style(
			'idea-network-admin',
			IDEA_NETWORK_URL . 'assets/admin.css',
			array(),
			IDEA_NETWORK_VERSION
		);
	}

	/**
	 * Returns an empty result structure.
	 *
	 * @return array
	 */
	private static function empty_result() {
		return array(
			'message'       => null,
			'created'       => 0,
			'updated'       => 0,
			'rows'          => 0,
			'skipped'       => array(),
			'skipped_count' => 0,
			'errors'        => array(),
			'errors_count'  => 0,
			'truncated'     => false,
		);
	}

	/**
	 * Handles the import form submission (admin-post.php).
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'excel-importer' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$file = isset( $_FILES['excel_file'] ) && is_array( $_FILES['excel_file'] ) ? $_FILES['excel_file'] : null;

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private' );
		$status           = isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : 'publish';

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'publish';
		}

		$options = array(
			'status'          => $status,
			'has_header'      => ! empty( $_POST['has_header'] ),
			'update_existing' => ! empty( $_POST['update_existing'] ),
		);

		if ( null === $file ) {
			$result           = self::empty_result();
			$result['message'] = __( 'هیچ فایلی انتخاب نشده است.', 'excel-importer' );
		} else {
			$result = self::process_import( $file, $options );
		}

		set_transient( self::TRANSIENT_PREFIX . get_current_user_id(), $result, 15 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . Idea_Network_CPT::POST_TYPE . '&page=' . self::SLUG )
		);
		exit;
	}

	/**
	 * Sends a sample CSV template (UTF-8 with BOM) to the browser.
	 *
	 * @return void
	 */
	public static function download_template() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'excel-importer' ) );
		}

		check_admin_referer( self::TEMPLATE_NONCE );

		$labels = array();

		foreach ( Idea_Network_CPT::get_columns_map() as $field ) {
			$labels[] = $field['label'];
		}

		$sample = array(
			'مرکز نمونه تهران',
			'12',
			'5',
			'dashicons-location',
			'تهران، خیابان نمونه، پلاک 1',
			'021-12345678',
			'https://example.ir',
			'تهران',
			'تهران',
		);

		$stream = fopen( 'php://output', 'w' );

		if ( false === $stream ) {
			wp_die( esc_html__( 'خطا در تولید فایل نمونه.', 'excel-importer' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="excel-importer-template.csv"' );
		header( 'Pragma: no-cache' );

		echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel shows Persian text correctly.

		fputcsv( $stream, $labels );
		fputcsv( $stream, $sample );
		fclose( $stream );
		exit;
	}

	/**
	 * Validates the uploaded file, reads it and creates/updates posts.
	 *
	 * @param array $file    Upload entry from $_FILES.
	 * @param array $options Import options: status, has_header, update_existing.
	 * @return array
	 */
	private static function process_import( $file, array $options ) {
		$result = self::empty_result();

		// 1) Extension validation.
		$extension = isset( $file['name'] ) ? sanitize_key( pathinfo( (string) wp_unslash( $file['name'] ), PATHINFO_EXTENSION ) ) : '';
		$allowed   = (array) apply_filters( 'idea_network_allowed_extensions', array( 'xlsx', 'xlsm', 'csv' ) );

		if ( ! in_array( $extension, $allowed, true ) ) {
			$result['message'] = sprintf(
				/* translators: %s: allowed file extensions. */
				__( 'قالب فایل مجاز نیست. فرمت‌های مجاز: %s هستند.', 'excel-importer' ),
				implode( '، ', $allowed )
			);

			return $result;
		}

		// 2) Upload error handling.
		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK;

		if ( UPLOAD_ERR_OK !== $upload_error ) {
			$messages = array(
				UPLOAD_ERR_INI_SIZE   => __( 'حجم فایل از محدودیت سرور (upload_max_filesize) بیشتر است.', 'excel-importer' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'حجم فایل از محدودیت فرم بیشتر است.', 'excel-importer' ),
				UPLOAD_ERR_PARTIAL    => __( 'فایل به‌صورت کامل آپلود نشد. دوباره تلاش کنید.', 'excel-importer' ),
				UPLOAD_ERR_NO_FILE    => __( 'هیچ فایلی انتخاب نشده است.', 'excel-importer' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'پوشه موقت سرور وجود ندارد. با پشتیبانی هاست تماس بگیرید.', 'excel-importer' ),
				UPLOAD_ERR_CANT_WRITE => __( 'نوشتن فایل روی سرور ممکن نشد. با پشتیبانی هاست تماس بگیرید.', 'excel-importer' ),
			);

			$result['message'] = isset( $messages[ $upload_error ] )
				? $messages[ $upload_error ]
				: __( 'خطای ناشناخته‌ای هنگام آپلود فایل رخ داد.', 'excel-importer' );

			return $result;
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			$result['message'] = __( 'فایل آپلودشده معتبر نیست.', 'excel-importer' );

			return $result;
		}

		// 3) Size validation (default 10 MB).
		$max_size = (int) apply_filters( 'idea_network_max_file_size', 10 * MB_IN_BYTES );

		if ( isset( $file['size'] ) && (int) $file['size'] > $max_size ) {
			$result['message'] = sprintf(
				/* translators: %s: maximum allowed size. */
				__( 'حجم فایل از حد مجاز (%s) بیشتر است.', 'excel-importer' ),
				size_format( $max_size )
			);

			return $result;
		}

		// 4) Parse the file. The uploaded file is only read, never stored.
		try {
			$rows = Idea_Network_Reader::read( $tmp_name, $extension );
		} catch ( Idea_Network_Exception $exception ) {
			$result['message'] = $exception->getMessage();

			return $result;
		}

		// 5) Drop the header row when requested.
		if ( $options['has_header'] && ! empty( $rows ) ) {
			array_shift( $rows );
		}

		$rows = array_values( $rows );

		if ( empty( $rows ) ) {
			$result['message'] = __( 'هیچ ردیف داده‌ای در فایل پیدا نشد.', 'excel-importer' );

			return $result;
		}

		// 6) Row cap to avoid timeouts (filterable).
		$max_rows = (int) apply_filters( 'idea_network_max_rows', 5000 );

		if ( count( $rows ) > $max_rows ) {
			$rows                = array_slice( $rows, 0, $max_rows );
			$result['truncated'] = true;
		}

		$result['rows'] = count( $rows );

		return self::run_import_loop( $rows, $options, $result );
	}

	/**
	 * Creates or updates posts for every parsed row.
	 *
	 * @param array $rows    Parsed rows (already header-stripped and capped).
	 * @param array $options Import options: status, has_header, update_existing.
	 * @param array $result  Result accumulated so far (validation flags).
	 * @return array
	 */
	private static function run_import_loop( array $rows, array $options, array $result ) {
		wp_raise_memory_limit( 'admin' );

		// 7) Import each row.
		foreach ( $rows as $row_data ) {
			$sheet_row = isset( $row_data['row'] ) ? (int) $row_data['row'] : 0;
			$cells     = isset( $row_data['cells'] ) && is_array( $row_data['cells'] ) ? $row_data['cells'] : array();
			$mapped    = Idea_Network_CPT::map_row( $cells );

			// Blank rows are silently ignored.
			if ( self::is_empty_record( $mapped ) ) {
				continue;
			}

			// Rows without a title (first column) cannot become posts.
			if ( '' === $mapped['center_name'] ) {
				++$result['skipped_count'];

				if ( count( $result['skipped'] ) < 30 ) {
					$result['skipped'][] = array(
						'row'    => $sheet_row,
						'reason' => __( 'نام مراکز (ستون اول) خالی است.', 'excel-importer' ),
					);
				}

				continue;
			}

			$existing_id = ! empty( $options['update_existing'] ) ? self::find_by_title( $mapped['center_name'] ) : 0;

			$postarr = array(
				'post_type'   => Idea_Network_CPT::POST_TYPE,
				'post_status' => $options['status'],
				'post_title'  => $mapped['center_name'],
			);

			if ( $existing_id ) {
				$postarr['ID'] = $existing_id;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) ) {
				++$result['errors_count'];

				if ( count( $result['errors'] ) < 30 ) {
					$result['errors'][] = array(
						'row'   => $sheet_row,
						'error' => $post_id->get_error_message(),
					);
				}

				continue;
			}

			foreach ( Idea_Network_CPT::get_meta_fields() as $key => $field ) {
				update_post_meta( $post_id, $field['meta_key'], sanitize_text_field( $mapped[ $key ] ) );

			}

			// Link the center to its province/city terms (created when missing).
			if ( class_exists( 'Idea_Network_Locations' ) ) {
				Idea_Network_Locations::sync_post_terms( $post_id );
			}

			if ( $existing_id ) {
				++$result['updated'];
			} else {
				++$result['created'];
			}
		}

		return $result;
	}

	/**
	 * Finds an existing post of the importer post type by exact title.
	 *
	 * @param string $title Post title.
	 * @return int Post ID or 0.
	 */
	private static function find_by_title( $title ) {
		$query = new WP_Query(
			array(
				'post_type'           => Idea_Network_CPT::POST_TYPE,
				'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'title'               => $title,
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Checks whether all six mapped values are empty.
	 *
	 * @param array $record Mapped record.
	 * @return bool
	 */
	private static function is_empty_record( array $record ) {
		foreach ( $record as $value ) {
			if ( '' !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Renders the importer admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'excel-importer' ) );
		}

		$result_key = self::TRANSIENT_PREFIX . get_current_user_id();
		$result     = get_transient( $result_key );

		if ( false !== $result ) {
			delete_transient( $result_key );
		}

		$template_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::TEMPLATE_ACTION ),
			self::TEMPLATE_NONCE
		);

		?>
		<div class="wrap ei-wrap">
			<h1><?php esc_html_e( 'درون‌ریزی اکسل', 'excel-importer' ); ?></h1>

			<?php if ( is_array( $result ) ) : ?>
				<?php self::render_result( $result ); ?>
			<?php endif; ?>

			<div class="ei-grid">
				<div class="ei-card">
					<h2><?php esc_html_e( 'آپلود فایل و درون‌ریزی گروهی', 'excel-importer' ); ?></h2>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="ei-file"><?php esc_html_e( 'فایل اکسل', 'excel-importer' ); ?></label>
								</th>
								<td class="ei-file-input">
									<input type="file" name="excel_file" id="ei-file" accept=".xlsx,.xlsm,.csv" required />
									<p class="description">
										<?php esc_html_e( 'فرمت‌های مجاز: xlsx، xlsm و csv. فایل‌های قدیمی xls را ابتدا به xlsx تبدیل کنید.', 'excel-importer' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'وضعیت پست‌ها', 'excel-importer' ); ?></th>
								<td>
									<select name="post_status">
										<option value="publish"><?php esc_html_e( 'منتشرشده', 'excel-importer' ); ?></option>
										<option value="draft"><?php esc_html_e( 'پیش‌نویس', 'excel-importer' ); ?></option>
										<option value="pending"><?php esc_html_e( 'در انتظار بررسی', 'excel-importer' ); ?></option>
										<option value="private"><?php esc_html_e( 'خصوصی', 'excel-importer' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'تنظیمات', 'excel-importer' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="has_header" value="1" checked="checked" />
										<?php esc_html_e( 'ردیف اول، سرستون (عنوان ستون‌ها) است و نادیده گرفته شود', 'excel-importer' ); ?>
									</label><br />
									<label>
										<input type="checkbox" name="update_existing" value="1" />
										<?php esc_html_e( 'اگر مرکزی با همین نام وجود دارد، به‌روزرسانی شود (به‌جای ایجاد مجدد)', 'excel-importer' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'درون‌ریزی فایل', 'excel-importer' ) ); ?>
					</form>
				</div>

				<div class="ei-card">
					<h2><?php esc_html_e( 'ساختار ستون‌های فایل', 'excel-importer' ); ?></h2>

					<table class="ei-columns-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ستون', 'excel-importer' ); ?></th>
								<th><?php esc_html_e( 'عنوان ستون در فایل', 'excel-importer' ); ?></th>
								<th><?php esc_html_e( 'ذخیره در', 'excel-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php $ei_index = 1; ?>
							<?php foreach ( Idea_Network_CPT::get_columns_map() as $ei_field ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $ei_index ); ?></td>
									<td><?php echo esc_html( $ei_field['label'] ); ?></td>
									<td>
										<?php
										echo '' === $ei_field['meta_key']
											? esc_html__( 'عنوان پست', 'excel-importer' )
											: esc_html__( 'فیلد متنی (متا)', 'excel-importer' );
										?>
									</td>
								</tr>
								<?php $ei_index++; ?>
							<?php endforeach; ?>
						</tbody>
					</table>

					<ul class="ei-hints">
						<li><?php esc_html_e( 'فقط ۹ ستون اول خوانده می‌شود؛ ستون‌های ۱۰ به بعد کاملاً نادیده گرفته می‌شوند.', 'excel-importer' ); ?></li>
						<li><?php esc_html_e( 'ترتیب ستون‌ها دقیقاً همان ترتیب جدول بالاست (نام مراکز، تعداد مراکز، تعداد استان، ایکون، آدرس، شماره تماس، آدرس وبسایت، نام شهر، نام استان).', 'excel-importer' ); ?></li>
						<li><?php esc_html_e( 'همه فیلدها متنی هستند؛ ردیف‌هایی که ستون اول (نام مراکز) خالی دارند وارد نمی‌شوند.', 'excel-importer' ); ?></li>
						<li><?php esc_html_e( 'ردیف‌های کاملاً خالی به‌صورت خودکار نادیده گرفته می‌شوند.', 'excel-importer' ); ?></li>
					</ul>

					<p>
						<a class="button button-secondary" href="<?php echo esc_url( $template_url ); ?>">
							<?php esc_html_e( 'دانلود نمونه فایل CSV', 'excel-importer' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the result notice of the last import.
	 *
	 * @param array $result Import result.
	 * @return void
	 */
	private static function render_result( array $result ) {
		$is_error     = ! empty( $result['message'] );
		$notice_class = $is_error ? 'is-error' : 'is-success';
		?>
		<div class="ei-notice <?php echo esc_attr( $notice_class ); ?>">
			<h3>
				<?php
				if ( $is_error ) {
					esc_html_e( 'درون‌ریزی انجام نشد', 'excel-importer' );
				} else {
					printf(
						/* translators: 1: number of new posts, 2: number of updated posts. */
						esc_html__( 'درون‌ریزی کامل شد: %1$s مرکز جدید، %2$s مورد به‌روزرسانی شد.', 'excel-importer' ),
						esc_html( number_format_i18n( (int) $result['created'] ) ),
						esc_html( number_format_i18n( (int) $result['updated'] ) )
					);
				}
				?>
			</h3>

			<?php if ( $is_error ) : ?>
				<p><?php echo esc_html( $result['message'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $result['truncated'] ) ) : ?>
				<p><?php esc_html_e( 'تعداد ردیف‌های فایل بیشتر از حد مجاز بود و فقط ردیف‌های اول پردازش شدند. توسعه‌دهندگان می‌توانند با فیلتر idea_network_max_rows این سقف را تغییر دهند.', 'excel-importer' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $result['skipped'] ) ) : ?>
				<p><strong><?php esc_html_e( 'ردیف‌های رد شده:', 'excel-importer' ); ?></strong></p>
				<ul>
					<?php foreach ( $result['skipped'] as $ei_skipped ) : ?>
						<li>
							<?php
							printf(
								/* translators: 1: row number, 2: reason. */
								esc_html__( 'ردیف %1$s — %2$s', 'excel-importer' ),
								esc_html( number_format_i18n( (int) $ei_skipped['row'] ) ),
								esc_html( $ei_skipped['reason'] )
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
				$ei_hidden = (int) $result['skipped_count'] - count( $result['skipped'] );

				if ( $ei_hidden > 0 ) {
					printf(
						/* translators: %s: number of rows. */
						esc_html__( 'و %s ردیف دیگر…', 'excel-importer' ),
						esc_html( number_format_i18n( $ei_hidden ) )
					);
				}
				?>
			<?php endif; ?>

			<?php if ( ! empty( $result['errors'] ) ) : ?>
				<p><strong><?php esc_html_e( 'خطاها:', 'excel-importer' ); ?></strong></p>
				<ul>
					<?php foreach ( $result['errors'] as $ei_error ) : ?>
						<li>
							<?php
							printf(
								/* translators: 1: row number, 2: error message. */
								esc_html__( 'ردیف %1$s — %2$s', 'excel-importer' ),
								esc_html( number_format_i18n( (int) $ei_error['row'] ) ),
								esc_html( $ei_error['error'] )
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
				$ei_hidden = (int) $result['errors_count'] - count( $result['errors'] );

				if ( $ei_hidden > 0 ) {
					printf(
						/* translators: %s: number of rows. */
						esc_html__( 'و %s خطای دیگر…', 'excel-importer' ),
						esc_html( number_format_i18n( $ei_hidden ) )
					);
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}
}
