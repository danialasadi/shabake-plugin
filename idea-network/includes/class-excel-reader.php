<?php
/**
 * Minimal spreadsheet reader (xlsx/xlsm/csv) with no external dependencies.
 *
 * @package excel-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Exception thrown by the spreadsheet reader.
 */
class Idea_Network_Exception extends RuntimeException {}

/**
 * Reads spreadsheet rows and returns them as plain UTF-8 strings.
 */
class Idea_Network_Reader {

	/**
	 * SpreadsheetML main namespace.
	 *
	 * @var string
	 */
	const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

	/**
	 * Office document relationships namespace.
	 *
	 * @var string
	 */
	const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	/**
	 * OPC package relationships namespace.
	 *
	 * @var string
	 */
	const NS_PKG = 'http://schemas.openxmlformats.org/package/2006/relationships';

	/**
	 * Reads a spreadsheet file and returns its rows.
	 *
	 * @param string $file      Absolute path of the (temporary) uploaded file.
	 * @param string $extension File extension: xlsx, xlsm, csv or txt.
	 * @return array[] List of rows. Each row is array( 'row' => int, 'cells' => string[] ).
	 * @throws Idea_Network_Exception When the file cannot be read.
	 */
	public static function read( $file, $extension ) {
		$extension = strtolower( trim( (string) $extension ) );

		if ( in_array( $extension, array( 'csv', 'txt' ), true ) ) {
			return self::read_delimited( $file );
		}

		if ( in_array( $extension, array( 'xlsx', 'xlsm' ), true ) ) {
			return self::read_xlsx( $file );
		}

		if ( 'xls' === $extension ) {
			throw new Idea_Network_Exception(
				__( 'فرمت قدیمی xls پشتیبانی نمی‌شود. فایل را در اکسل با گزینه Save As و فرمت Excel Workbook (*.xlsx) ذخیره کنید.', 'excel-importer' )
			);
		}

		throw new Idea_Network_Exception(
			__( 'قالب فایل پشتیبانی نمی‌شود. فرمت‌های مجاز: xlsx، xlsm و csv هستند.', 'excel-importer' )
		);
	}

	/**
	 * Reads a delimited text file (CSV/TSV/TXT).
	 *
	 * @param string $file Absolute path of the file.
	 * @return array[]
	 * @throws Idea_Network_Exception When the file cannot be read.
	 */
	private static function read_delimited( $file ) {
		if ( ! is_readable( $file ) ) {
			throw new Idea_Network_Exception( __( 'فایل قابل خواندن نیست یا وجود ندارد.', 'excel-importer' ) );
		}

		$raw = file_get_contents( $file );

		if ( false === $raw || '' === $raw ) {
			throw new Idea_Network_Exception( __( 'فایل خالی است یا قابل خواندن نیست.', 'excel-importer' ) );
		}

		$raw       = self::to_utf8( $raw );
		$delimiter = self::detect_delimiter( $raw );

		$stream = fopen( 'php://temp/maxmemory:8388608', 'w+' );

		if ( false === $stream ) {
			throw new Idea_Network_Exception( __( 'خطا در باز کردن فایل CSV.', 'excel-importer' ) );
		}

		fwrite( $stream, $raw );
		rewind( $stream );

		$rows        = array();
		$line_number = 0;

		while ( false !== ( $line = fgetcsv( $stream, 0, $delimiter, '"', '' ) ) ) {
			++$line_number;

			if ( ! is_array( $line ) ) {
				// Blank line.
				$rows[] = array(
					'row'   => $line_number,
					'cells' => array(),
				);
				continue;
			}

			$cells = array();
			foreach ( $line as $value ) {
				$cells[] = (string) ( null === $value ? '' : $value );
			}

			$rows[] = array(
				'row'   => $line_number,
				'cells' => $cells,
			);
		}

		fclose( $stream );

		return $rows;
	}

	/**
	 * Normalizes a byte string to UTF-8.
	 *
	 * Handles UTF-8 BOM, UTF-16 BOMs and Windows-1256 (legacy Persian Excel CSV).
	 *
	 * @param string $raw Raw file contents.
	 * @return string
	 */
	private static function to_utf8( $raw ) {
		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
			return substr( $raw, 3 );
		}

		if ( 0 === strpos( $raw, "\xFF\xFE" ) ) {
			return self::convert_encoding( substr( $raw, 2 ), 'UTF-16LE' );
		}

		if ( 0 === strpos( $raw, "\xFE\xFF" ) ) {
			return self::convert_encoding( substr( $raw, 2 ), 'UTF-16BE' );
		}

		if ( ! self::is_utf8( $raw ) ) {
			return self::convert_encoding( $raw, 'Windows-1256' );
		}

		return $raw;
	}

	/**
	 * Checks whether a string is valid UTF-8.
	 *
	 * @param string $string String to check.
	 * @return bool
	 */
	private static function is_utf8( $string ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			return mb_check_encoding( $string, 'UTF-8' );
		}

		return (bool) preg_match( '//u', $string );
	}

	/**
	 * Converts a string to UTF-8.
	 *
	 * @param string $string        Source string.
	 * @param string $from_encoding Source encoding name.
	 * @return string
	 */
	private static function convert_encoding( $string, $from_encoding ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			try {
				$converted = mb_convert_encoding( $string, 'UTF-8', $from_encoding );

				if ( is_string( $converted ) ) {
					return $converted;
				}
			} catch ( ValueError $exception ) {
				// mbstring does not know this encoding (e.g. Windows-1256); fall back to iconv.
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$converted = iconv( $from_encoding, 'UTF-8//IGNORE', $string );

			if ( false !== $converted ) {
				return $converted;
			}
		}

		return $string;
	}

	/**
	 * Detects the column delimiter of a delimited text file.
	 *
	 * @param string $raw File contents (UTF-8).
	 * @return string
	 */
	private static function detect_delimiter( $raw ) {
		$newline  = strpos( $raw, "\n" );
		$first    = false === $newline ? $raw : substr( $raw, 0, $newline );
		$tab      = substr_count( $first, "\t" );
		$comma    = substr_count( $first, ',' );
		$semicolon = substr_count( $first, ';' );

		if ( $tab > 0 && $tab >= $comma && $tab >= $semicolon ) {
			return "\t";
		}

		if ( $semicolon > 0 && $semicolon >= $comma ) {
			return ';';
		}

		return ',';
	}

	/**
	 * Reads the first worksheet of an xlsx/xlsm file.
	 *
	 * @param string $file Absolute path of the file.
	 * @return array[]
	 * @throws Idea_Network_Exception When the file cannot be read.
	 */
	private static function read_xlsx( $file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Idea_Network_Exception(
				__( 'افزونه PHP zip (ZipArchive) روی سرور فعال نیست. لطفاً به مدیر هاست اطلاع دهید.', 'excel-importer' )
			);
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file ) ) {
			throw new Idea_Network_Exception( __( 'فایل اکسل قابل باز شدن نیست یا خراب است.', 'excel-importer' ) );
		}

		$shared     = self::read_shared_strings( $zip );
		$sheet_path = self::resolve_sheet_path( $zip );
		$sheet_xml  = $zip->getFromName( $sheet_path );

		if ( false === $sheet_xml || '' === $sheet_xml ) {
			// Fallback: first worksheet part inside the package.
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $zip->getNameIndex( $i );
				if ( 0 === strpos( (string) $name, 'xl/worksheets/' ) ) {
					$sheet_xml = $zip->getFromIndex( $i );
					break;
				}
			}
		}

		$zip->close();

		if ( false === $sheet_xml || '' === $sheet_xml ) {
			throw new Idea_Network_Exception( __( 'شیت اکسل در فایل پیدا نشد.', 'excel-importer' ) );
		}

		return self::parse_sheet( $sheet_xml, $shared );
	}

	/**
	 * Resolves the XML part name of the first worksheet.
	 *
	 * @param ZipArchive $zip Opened archive.
	 * @return string
	 */
	private static function resolve_sheet_path( $zip ) {
		$default  = 'xl/worksheets/sheet1.xml';
		$workbook = $zip->getFromName( 'xl/workbook.xml' );

		if ( false === $workbook || '' === $workbook ) {
			return $default;
		}

		$workbook_xml = self::load_xml( $workbook );

		if ( false === $workbook_xml ) {
			return $default;
		}

		$relationship_id = '';

		foreach ( $workbook_xml->children( self::NS_MAIN ) as $sheets_element ) {
			foreach ( $sheets_element->children( self::NS_MAIN ) as $sheet ) {
				if ( 'sheet' !== $sheet->getName() ) {
					continue;
				}

				$attributes = $sheet->attributes( self::NS_REL );

				if ( isset( $attributes['id'] ) ) {
					$relationship_id = (string) $attributes['id'];
					break 2;
				}
			}
		}

		if ( '' === $relationship_id ) {
			return $default;
		}

		$relationships = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );

		if ( false === $relationships || '' === $relationships ) {
			return $default;
		}

		$relationships_xml = self::load_xml( $relationships );

		if ( false === $relationships_xml ) {
			return $default;
		}

		foreach ( $relationships_xml->children( self::NS_PKG ) as $relationship ) {
			$attributes = $relationship->attributes();

			if ( ! isset( $attributes['Id'], $attributes['Target'] ) ) {
				continue;
			}

			if ( $relationship_id !== (string) $attributes['Id'] ) {
				continue;
			}

			$target = (string) $attributes['Target'];

			if ( 0 === strpos( $target, '/' ) ) {
				return ltrim( $target, '/' );
			}

			return 'xl/' . $target;
		}

		return $default;
	}

	/**
	 * Reads the shared strings table of the archive.
	 *
	 * @param ZipArchive $zip Opened archive.
	 * @return string[]
	 */
	private static function read_shared_strings( $zip ) {
		$part = $zip->locateName( 'xl/sharedStrings.xml' );

		if ( false === $part ) {
			return array();
		}

		$xml = $zip->getFromName( 'xl/sharedStrings.xml' );

		if ( false === $xml || '' === $xml ) {
			return array();
		}

		$shared_xml = self::load_xml( $xml );

		if ( false === $shared_xml ) {
			return array();
		}

		$strings = array();

		foreach ( $shared_xml->children( self::NS_MAIN ) as $item ) {
			if ( 'si' !== $item->getName() ) {
				continue;
			}

			$strings[] = self::collect_text( $item );
		}

		return $strings;
	}

	/**
	 * Parses worksheet XML into rows.
	 *
	 * @param string   $sheet_xml Worksheet XML.
	 * @param string[] $shared    Shared strings table.
	 * @return array[]
	 * @throws Idea_Network_Exception When the XML is invalid.
	 */
	private static function parse_sheet( $sheet_xml, array $shared ) {
		$worksheet = self::load_xml( $sheet_xml );

		if ( false === $worksheet ) {
			throw new Idea_Network_Exception( __( 'ساختار فایل اکسل نامعتبر است.', 'excel-importer' ) );
		}

		$rows             = array();
		$fallback_row_num = 0;

		foreach ( $worksheet->children( self::NS_MAIN ) as $child ) {
			if ( 'sheetData' !== $child->getName() ) {
				continue;
			}

			foreach ( $child->children( self::NS_MAIN ) as $row_element ) {
				if ( 'row' !== $row_element->getName() ) {
					continue;
				}

				++$fallback_row_num;

				$row_attributes    = $row_element->attributes();
				$row_number        = isset( $row_attributes['r'] ) ? (int) $row_attributes['r'] : $fallback_row_num;
				$values            = array();
				$next_column_index = 0;

				foreach ( $row_element->children( self::NS_MAIN ) as $cell ) {
					if ( 'c' !== $cell->getName() ) {
						continue;
					}

					$cell_attributes = $cell->attributes();
					$reference       = isset( $cell_attributes['r'] ) ? (string) $cell_attributes['r'] : '';
					$column_index    = '' !== $reference ? self::column_index( $reference ) : $next_column_index;

					$values[ $column_index ] = self::cell_value( $cell, $shared );
					$next_column_index       = $column_index + 1;
				}

				$cells = array();

				if ( ! empty( $values ) ) {
					$last_index = max( array_keys( $values ) );

					for ( $i = 0; $i <= $last_index; $i++ ) {
						$cells[ $i ] = isset( $values[ $i ] ) ? $values[ $i ] : '';
					}
				}

				$rows[] = array(
					'row'   => $row_number,
					'cells' => $cells,
				);
			}
		}

		return $rows;
	}

	/**
	 * Extracts the text value of a single cell.
	 *
	 * @param SimpleXMLElement $cell   Cell element.
	 * @param string[]         $shared Shared strings table.
	 * @return string
	 */
	private static function cell_value( $cell, array $shared ) {
		$attributes = $cell->attributes();
		$type       = isset( $attributes['t'] ) ? (string) $attributes['t'] : '';

		if ( 'inlineStr' === $type ) {
			foreach ( $cell->children( self::NS_MAIN ) as $child ) {
				if ( 'is' === $child->getName() ) {
					return self::collect_text( $child );
				}
			}

			return '';
		}

		$raw = null;

		foreach ( $cell->children( self::NS_MAIN ) as $child ) {
			if ( 'v' === $child->getName() ) {
				$raw = (string) $child;
				break;
			}
		}

		if ( null === $raw ) {
			return '';
		}

		switch ( $type ) {
			case 's':
				$index = (int) $raw;
				return isset( $shared[ $index ] ) ? $shared[ $index ] : '';
			case 'b':
				return '1' === $raw ? 'true' : 'false';
			case 'e':
				return '';
			case 'str':
				return $raw;
			default:
				return self::format_number( $raw );
		}
	}

	/**
	 * Concatenates all text runs of a rich text element.
	 *
	 * @param SimpleXMLElement $element Element containing t/r children.
	 * @return string
	 */
	private static function collect_text( $element ) {
		$text = '';

		foreach ( $element->children( self::NS_MAIN ) as $child ) {
			$name = $child->getName();

			if ( 't' === $name ) {
				$text .= (string) $child;
			} elseif ( 'r' === $name ) {
				$text .= self::collect_text( $child );
			}
		}

		return $text;
	}

	/**
	 * Formats a numeric cell value as a plain string.
	 *
	 * @param string $raw Raw cell value.
	 * @return string
	 */
	private static function format_number( $raw ) {
		if ( ! is_numeric( $raw ) ) {
			return $raw;
		}

		if ( preg_match( '/^-?\d+$/', $raw ) ) {
			return $raw;
		}

		$number   = (float) $raw;
		$formatted = rtrim( rtrim( sprintf( '%.10F', $number ), '0' ), '.' );

		return '' === $formatted || '-0' === $formatted ? '0' : $formatted;
	}

	/**
	 * Converts a cell reference like "AB12" to a zero based column index.
	 *
	 * @param string $reference Cell reference.
	 * @return int
	 */
	private static function column_index( $reference ) {
		$letters = preg_replace( '/[^A-Za-z]/', '', $reference );
		$letters = strtoupper( (string) $letters );

		if ( '' === $letters ) {
			return 0;
		}

		$index = 0;

		for ( $i = 0, $length = strlen( $letters ); $i < $length; $i++ ) {
			$index = ( $index * 26 ) + ( ord( $letters[ $i ] ) - 64 );
		}

		return $index - 1;
	}

	/**
	 * Loads an XML string safely (no network, no entity expansion).
	 *
	 * @param string $xml XML source.
	 * @return SimpleXMLElement|false
	 */
	private static function load_xml( $xml ) {
		$previous = libxml_use_internal_errors( true );
		$element  = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $element;
	}
}

