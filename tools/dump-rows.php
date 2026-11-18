<?php
/**
 * Dumps the parsed rows of a spreadsheet (debug/inspection helper).
 *
 * Usage: php dump-rows.php <file> [extension]
 *
 * @package excel-importer
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) {
	return $text;
}

require __DIR__ . '/../idea-network/includes/class-excel-reader.php';

if ( $argc < 2 ) {
	fwrite( STDERR, "Usage: php dump-rows.php <file> [extension]\n" );
	exit( 2 );
}

$file     = $argv[1];
$extension = isset( $argv[2] ) ? $argv[2] : pathinfo( $file, PATHINFO_EXTENSION );

try {
	$rows = Idea_Network_Reader::read( $file, $extension );
} catch ( Idea_Network_Exception $exception ) {
	fwrite( STDERR, 'ERROR: ' . $exception->getMessage() . "\n" );
	exit( 1 );
}

foreach ( $rows as $row ) {
	echo $row['row'] . "\t" . implode( ' | ', $row['cells'] ) . "\n";
}

echo '-- ' . count( $rows ) . " rows\n";
