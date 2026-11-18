<?php

/**

 * Test suite for the Excel importer (reader, row mapping, import loop).

 *

 * Usage: php run-tests.php

 *

 * @package excel-importer

 */



define( 'ABSPATH', __DIR__ );



// ---------------------------------------------------------------------------

// Minimal WordPress stubs – only what the tested code paths touch.

// ---------------------------------------------------------------------------

function __( $text, $domain = null ) {

	return $text;

}



function esc_html__( $text, $domain = null ) {

	return $text;

}



function esc_html_e( $text, $domain = null ) {

	echo $text;

}



function sanitize_text_field( $text ) {

	return trim( strip_tags( (string) $text ) );

}



function wp_raise_memory_limit( $context = '' ) {

	return true;

}



function is_wp_error( $thing ) {

	return $thing instanceof WP_Error;

}



class WP_Error {

	private $message;



	public function __construct( $message = '' ) {

		$this->message = $message;

	}



	public function get_error_message() {

		return $this->message;

	}

}



/** Records created/updated posts and meta writes. */

class EI_Import_Registry {

	public static $next_id         = 100;

	public static $inserts         = array();

	public static $updates         = array();

	public static $meta            = array();

	public static $fail_titles     = array();

	public static $existing_titles = array();

	public static $terms           = array();

}



function wp_insert_post( $postarr, $wp_error = false ) {

	if ( in_array( $postarr['post_title'], EI_Import_Registry::$fail_titles, true ) ) {

		return $wp_error ? new WP_Error( 'db_error', 'خطای پایگاه داده (نمونه).' ) : 0;

	}



	$id                                      = EI_Import_Registry::$next_id++;

	EI_Import_Registry::$inserts[ $id ] = $postarr;



	return $id;

}



function wp_update_post( $postarr, $wp_error = false ) {

	EI_Import_Registry::$updates[ $postarr['ID'] ] = $postarr;



	return $postarr['ID'];

}



function update_post_meta( $post_id, $meta_key, $meta_value ) {

	EI_Import_Registry::$meta[ $post_id ][ $meta_key ] = $meta_value;



	return true;

}



function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {

	EI_Import_Registry::$terms[ $object_id ][ $taxonomy ] = $terms;



	return $terms;

}



/** Stub used by find_by_title() when update_existing is enabled. */

class WP_Query {

	public $posts = array();



	public function __construct( $args = array() ) {

		$title = isset( $args['title'] ) ? $args['title'] : '';



		if ( isset( EI_Import_Registry::$existing_titles[ $title ] ) ) {

			$this->posts = array( EI_Import_Registry::$existing_titles[ $title ] );

		}

	}

}



require __DIR__ . '/../idea-network/includes/class-excel-reader.php';

require __DIR__ . '/../idea-network/includes/class-excel-cpt.php';

require __DIR__ . '/../idea-network/includes/class-excel-importer-admin.php';

require __DIR__ . '/build-sample-xlsx.php';



// ---------------------------------------------------------------------------

// Tiny assertion helpers.

// ---------------------------------------------------------------------------

$ei_passes   = 0;

$ei_failures = 0;



function ei_assert( $condition, $label ) {

	global $ei_passes, $ei_failures;



	if ( $condition ) {

		++$ei_passes;

		echo "PASS  {$label}\n";

	} else {

		++$ei_failures;

		echo "FAIL  {$label}\n";

	}

}



function ei_same( $expected, $actual, $label ) {

	$ok = $expected === $actual;



	ei_assert(

		$ok,

		$ok

			? $label

			: $label . ' | expected: ' . var_export( $expected, true ) . ' | actual: ' . var_export( $actual, true )

	);

}



$samples_dir = __DIR__ . '/samples';



if ( ! is_dir( $samples_dir ) ) {

	mkdir( $samples_dir, 0777, true );

}



// ===========================================================================

// 1) XLSX parsing

// ===========================================================================

$xlsx = ei_build_sample_xlsx( $samples_dir . '/sample.xlsx' );

$rows = Idea_Network_Reader::read( $xlsx, 'xlsx' );



ei_same( 5, count( $rows ), 'xlsx: five rows parsed' );

ei_same( array( 1, 2, 3, 4, 7 ), array_column( $rows, 'row' ), 'xlsx: original sheet row numbers preserved' );

ei_same(

	array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'H7', 'H8', 'H9', 'EX1', 'EX2' ),

	$rows[0]['cells'],

	'xlsx: header row (11 columns: 9 + 2 extras)'

);

ei_same(

	array( 'R1C1', 'R1C2', 'R1C3', '1250000', 'R1C5', 'R1C6', 'R1C7', 'R1C8', 'R1C9', 'EXV1', 'EXV2' ),

	$rows[1]['cells'],

	'xlsx: shared strings + integer cell'

);

ei_same(

	array( '', 'R2C2', 'R2C3', '500', 'R2C5', 'R2C6', 'R2C7', 'R2C8', 'R2C9' ),

	$rows[2]['cells'],

	'xlsx: missing A-cell keeps alignment; inline string read'

);

ei_same( 'R3C1', $rows[3]['cells'][0], 'xlsx: rich text runs concatenated' );

ei_same( '2000000000000', $rows[3]['cells'][3], 'xlsx: large integer stays exact' );

ei_same(

	array( 'R5C1', 'R5C2', 'R5C3', '3.5', 'R5C5', 'R5C6', 'R5C7', 'R5C8', 'EX1', 'EX2' ),

	$rows[4]['cells'],

	'xlsx: inline strings, float, sparse row r=7'

);



// ===========================================================================

// 2) Row mapping – only the first nine columns

// ===========================================================================

$mapped = Idea_Network_CPT::map_row( $rows[1]['cells'] );



ei_same( 9, count( $mapped ), 'map_row: exactly nine values' );

ei_same(

	array( 'center_name', 'center_count', 'province_count', 'icon', 'address', 'phone', 'website', 'city', 'province_name' ),

	array_keys( $mapped ),

	'map_row: key order matches column order'

);

ei_same( 'مرکز الف', $mapped['center_name'], 'map_row: center name from column 1' );

ei_same( 'dashicons-location', $mapped['icon'], 'map_row: icon from column 4' );

ei_same( '021-1', $mapped['phone'], 'map_row: phone from column 6 (extras dropped)' );



$mapped_short = Idea_Network_CPT::map_row( array( 'فقط نام' ) );

ei_same( 9, count( $mapped_short ), 'map_row: short rows padded to nine values' );

ei_same( '', $mapped_short['province_name'], 'map_row: missing values become empty strings' );



// Meta keys must be visible to Elementor (no protected underscore prefix).

$protected_keys = array_keys( array_filter(

	Idea_Network_CPT::get_meta_fields(),

	function ( $field ) {

		return 0 === strpos( $field['meta_key'], '_' );

	}

) );

ei_same( array(), $protected_keys, 'meta keys have no protected underscore prefix (Elementor friendly)' );

ei_same(

	array( 'ei_center_count', 'ei_province_count', 'ei_icon', 'ei_address', 'ei_phone', 'ei_website', 'ei_city', 'ei_province_name' ),

	array_values( array_map(

		function ( $field ) {

			return $field['meta_key'];

		},

		Idea_Network_CPT::get_meta_fields()

	) ),

	'meta keys are the documented ei_* keys'

);



// ===========================================================================

// 3) CSV parsing (delimiter detection + encodings)

// ===========================================================================

$header = 'نام مراکز;تعداد مراکز;تعداد استان;ایکون;آدرس;شماره تماس;آدرس وبسایت;نام شهر;نام استان;ستون ۱۰;ستون ۱۱';

$row_a  = 'مرکز کویر;3;2;dashicons-a;ADR;111;https://a.ir;تهران;تهران;z10;z11';

$row_b  = '"مرکز با ; نقطه";4;2;dashicons-b;ADR2;222;https://b.ir;شیراز;فارس;z10;z11';

$csv    = $samples_dir . '/sample-utf8.csv';



file_put_contents( $csv, "\xEF\xBB\xBF" . implode( "\r\n", array( $header, $row_a, $row_b ) ) . "\r\n" );



$csv_rows = Idea_Network_Reader::read( $csv, 'csv' );



ei_same( 3, count( $csv_rows ), 'csv: three rows parsed' );

ei_same( 'نام مراکز', $csv_rows[0]['cells'][0], 'csv: UTF-8 BOM stripped from first cell' );

ei_same( 11, count( $csv_rows[1]['cells'] ), 'csv: semicolon delimiter detected (11 columns)' );

ei_same( 'مرکز کویر', $csv_rows[1]['cells'][0], 'csv: first cell value' );



$csv_mapped = Idea_Network_CPT::map_row( $csv_rows[2]['cells'] );

ei_same( 'مرکز با ; نقطه', $csv_mapped['center_name'], 'csv: quoted delimiter stays inside value' );

ei_same( 9, count( $csv_mapped ), 'csv: first nine columns mapped, extras dropped' );



// Windows-1256 CSV (legacy Persian Excel export).

// Note: Windows-1256 lacks Persian-specific letters (e.g. ی ک چ پ ژ گ), so the

// sample only uses characters that exist in that code page.

$w12_path = $samples_dir . '/sample-win1256.csv';

file_put_contents( $w12_path, iconv( 'UTF-8', 'Windows-1256', "نام سازمان;شهر\r\nسازمان برق;تهران\r\n" ) );



$w12_rows = Idea_Network_Reader::read( $w12_path, 'csv' );

ei_same( 'نام سازمان', $w12_rows[0]['cells'][0], 'csv: Windows-1256 converted to UTF-8' );

ei_same( 'تهران', $w12_rows[1]['cells'][1], 'csv: Windows-1256 data row' );



// UTF-16LE CSV with BOM (Excel "Unicode Text" export).

$u16_path = $samples_dir . '/sample-utf16.csv';

file_put_contents( $u16_path, "\xFF\xFE" . iconv( 'UTF-8', 'UTF-16LE', "عنوان;استان\r\nالف;تهران\r\n" ) );



$u16_rows = Idea_Network_Reader::read( $u16_path, 'csv' );

ei_same( 'الف', $u16_rows[1]['cells'][0], 'csv: UTF-16LE converted to UTF-8' );



// ===========================================================================

// 4) Rejected inputs

// ===========================================================================

$threw = false;



try {

	Idea_Network_Reader::read( $xlsx, 'xls' );

} catch ( Idea_Network_Exception $exception ) {

	$threw = ( false !== strpos( $exception->getMessage(), 'xls' ) );

}

ei_assert( $threw, 'legacy .xls rejected with a helpful message' );



$threw = false;



try {

	Idea_Network_Reader::read( $xlsx, 'docx' );

} catch ( Idea_Network_Exception $exception ) {

	$threw = true;

}

ei_assert( $threw, 'unknown extension rejected' );



$threw = false;



try {

	Idea_Network_Reader::read( $samples_dir . '/missing.csv', 'csv' );

} catch ( Idea_Network_Exception $exception ) {

	$threw = true;

}

ei_assert( $threw, 'missing file rejected gracefully' );



// ===========================================================================

// 5) Bulk import loop (creates posts + meta)

// ===========================================================================

$empty_method = new ReflectionMethod( 'Idea_Network_Admin', 'empty_result' );

$empty_method->setAccessible( true );



$loop_method = new ReflectionMethod( 'Idea_Network_Admin', 'run_import_loop' );

$loop_method->setAccessible( true );



$import_rows = array(

	array( 'row' => 2, 'cells' => $rows[1]['cells'] ),   // Valid.

	array( 'row' => 3, 'cells' => $rows[2]['cells'] ),   // Empty title -> skipped.

	array( 'row' => 4, 'cells' => $rows[3]['cells'] ),   // Valid.

	array( 'row' => 5, 'cells' => array( '', '', '' ) ), // Blank row -> ignored.

	array( 'row' => 6, 'cells' => array( 'مرکز خطا', '3', '2', 'dashicons-warning', 'آدرس خطا', '000', 'https://bad.invalid', 'شهر خطا', 'استان خطا' ) ), // Insert fails.

	array( 'row' => 8, 'cells' => array( 'مرکز چهار', '4', '2', 'dashicons-store', 'آدرس چهار', '111', 'https://c4.invalid', 'کرمان', 'کرمان' ) ), // Valid.

);



EI_Import_Registry::$fail_titles = array( 'مرکز خطا' );



$import_result = $loop_method->invokeArgs(

	null,

	array(

		$import_rows,

		array(

			'status'          => 'publish',

			'has_header'      => true,

			'update_existing' => false,

		),

		$empty_method->invoke( null ),

	)

);



ei_same( 3, $import_result['created'], 'import: three new posts created' );

ei_same( 0, $import_result['updated'], 'import: no updates without update_existing' );

ei_same( 1, $import_result['skipped_count'], 'import: row without title counted as skipped' );

ei_same( 1, $import_result['errors_count'], 'import: insert error counted' );

ei_same( 3, count( EI_Import_Registry::$inserts ), 'import: blank row and failure created no posts' );

ei_same( $mapped['center_name'], EI_Import_Registry::$inserts[100]['post_title'], 'import: first post title' );

ei_same( 'publish', EI_Import_Registry::$inserts[100]['post_status'], 'import: requested post status used' );

ei_same( $mapped['center_count'], EI_Import_Registry::$meta[100]['ei_center_count'], 'import: center count meta written' );

ei_same( $mapped['city'], EI_Import_Registry::$meta[100]['ei_city'], 'import: city meta written' );

ei_same( $mapped['province_name'], EI_Import_Registry::$meta[100]['ei_province_name'], 'import: province name meta written' );

ei_same( Idea_Network_CPT::map_row( $rows[3]['cells'] )['center_name'], EI_Import_Registry::$inserts[101]['post_title'], 'import: second post title' );

ei_same( 'مرکز چهار', EI_Import_Registry::$inserts[102]['post_title'], 'import: third post created' );

ei_same( 3, $import_result['skipped'][0]['row'], 'import: skipped row reports sheet row number' );

ei_same( 6, $import_result['errors'][0]['row'], 'import: failed row reports sheet row number' );



// update_existing => updates the post with the same title instead of inserting.

EI_Import_Registry::$existing_titles = array( $mapped['center_name'] => 555 );



$update_result = $loop_method->invokeArgs(

	null,

	array(

		array( array( 'row' => 2, 'cells' => $rows[1]['cells'] ) ),

		array(

			'status'          => 'draft',

			'has_header'      => false,

			'update_existing' => true,

		),

		$empty_method->invoke( null ),

	)

);



ei_same( 1, $update_result['updated'], 'import: update_existing updates the matching post' );

ei_same( 0, $update_result['created'], 'import: no new post when the title already exists' );

ei_same( 555, array_key_first( EI_Import_Registry::$updates ), 'import: existing post ID used' );

ei_same( 'draft', EI_Import_Registry::$updates[555]['post_status'], 'import: status applied on update' );



// ===========================================================================

// 6) Cross-validation with a genuine openpyxl-generated file (optional)

// ===========================================================================

$real = $samples_dir . '/real.xlsx';



if ( file_exists( $real ) ) {

	$real_rows = Idea_Network_Reader::read( $real, 'xlsx' );



	ei_same( 6, count( $real_rows ), 'real xlsx: six rows parsed' );

	ei_same( 11, count( $real_rows[0]['cells'] ), 'real xlsx: eleven columns read (9 + 2 extras)' );

	ei_same( 'R1C1', $real_rows[1]['cells'][0], 'real xlsx: first data title' );

	ei_same( '5000', $real_rows[1]['cells'][3], 'real xlsx: integer value' );

	ei_same( '', $real_rows[2]['cells'][0], 'real xlsx: empty title cell' );

	ei_same( '12345.5', $real_rows[3]['cells'][3], 'real xlsx: float value' );

	ei_same( 6, $real_rows[5]['row'], 'real xlsx: row number after blank row' );



	$real_mapped = Idea_Network_CPT::map_row( $real_rows[1]['cells'] );

	ei_same( 9, count( $real_mapped ), 'real xlsx: nine columns mapped, extras dropped' );

	ei_same( 'R1C8', $real_mapped['city'], 'real xlsx: city value from column 8' );

} else {

	echo "SKIP  real xlsx fixture not built (run: python tools/build-real-xlsx.py)\n";

}



// ---------------------------------------------------------------------------

echo "\n{$ei_passes} passed, {$ei_failures} failed\n";

exit( $ei_failures > 0 ? 1 : 0 );





