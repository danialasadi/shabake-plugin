<?php
/**
 * Builds a small but valid .xlsx file used by the automated tests.
 *
 * Usage: php build-sample-xlsx.php [output-path]
 *
 * @package excel-importer
 */

if ( ! function_exists( 'ei_build_sample_xlsx' ) ) {

	/**
	 * Creates the sample xlsx: header row, shared strings, integers, an inline
	 * string, rich text, a float, a row with a missing first cell and a sparse
	 * row (r="7") — plus two extra columns that must be ignored.
	 *
	 * Columns: 9 mapped fields + 2 extra (ignored) columns.
	 *
	 * @param string $path Output file path.
	 * @return string The output path.
	 */
	function ei_build_sample_xlsx( $path ) {
		$shared = array(
			'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'H7', 'H8', 'H9',
			'EX1', 'EX2',
			'R1C1', 'R1C2', 'R1C3', 'R1C5', 'R1C6', 'R1C7', 'R1C8', 'R1C9',
			'EXV1', 'EXV2',
			'R2C2', 'R2C3', 'R2C5', 'R2C6', 'R2C7', 'R2C8', 'R2C9',
			'R3C1', // rich text below
			'R4C1', 'R4C2', 'R4C5', 'R4C6', 'R4C7', 'R4C8', 'R4C9',
			'R5C2', 'R5C3', 'R5C5', 'R5C6', 'R5C7', 'R5C8',
		);

		$shared_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $shared ) . '" uniqueCount="' . count( $shared ) . '">';

		foreach ( $shared as $index => $string ) {
			if ( 28 === $index ) {
				$shared_xml .= '<si><r><t xml:space="preserve">R3</t></r><r><t>C1</t></r></si>';
			} else {
				$shared_xml .= '<si><t xml:space="preserve">' . htmlspecialchars( $string, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></si>';
			}
		}

		$shared_xml .= '</sst>';

		$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
			. '<row r="1">'
			. '<c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c>'
			. '<c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c><c r="H1" t="s"><v>7</v></c>'
			. '<c r="I1" t="s"><v>8</v></c><c r="J1" t="s"><v>9</v></c><c r="K1" t="s"><v>10</v></c>'
			. '</row>'
			. '<row r="2">'
			. '<c r="A2" t="s"><v>11</v></c><c r="B2" t="s"><v>12</v></c><c r="C2" t="s"><v>13</v></c><c r="D2"><v>1250000</v></c>'
			. '<c r="E2" t="s"><v>14</v></c><c r="F2" t="s"><v>15</v></c><c r="G2" t="s"><v>16</v></c><c r="H2" t="s"><v>17</v></c>'
			. '<c r="I2" t="s"><v>18</v></c><c r="J2" t="s"><v>19</v></c><c r="K2" t="s"><v>20</v></c>'
			. '</row>'
			. '<row r="3">'
			. '<c r="B3" t="s"><v>21</v></c><c r="C3" t="s"><v>22</v></c><c r="D3" t="inlineStr"><is><t>500</t></is></c>'
			. '<c r="E3" t="s"><v>23</v></c><c r="F3" t="s"><v>24</v></c><c r="G3" t="s"><v>25</v></c>'
			. '<c r="H3" t="s"><v>26</v></c><c r="I3" t="s"><v>27</v></c>'
			. '</row>'
			. '<row r="4">'
			. '<c r="A4" t="s"><v>28</v></c><c r="B4" t="s"><v>12</v></c><c r="C4" t="s"><v>13</v></c><c r="D4"><v>2000000000000</v></c>'
			. '<c r="E4" t="s"><v>29</v></c><c r="F4" t="s"><v>30</v></c><c r="G4" t="s"><v>31</v></c><c r="H4" t="s"><v>32</v></c>'
			. '<c r="I4" t="s"><v>33</v></c><c r="J4" t="s"><v>19</v></c><c r="K4" t="s"><v>20</v></c>'
			. '</row>'
			. '<row r="7">'
			. '<c r="A7" t="inlineStr"><is><t>R5C1</t></is></c><c r="B7" t="s"><v>34</v></c><c r="C7" t="s"><v>35</v></c><c r="D7"><v>3.5</v></c>'
			. '<c r="E7" t="s"><v>36</v></c><c r="F7" t="s"><v>37</v></c><c r="G7" t="s"><v>38</v></c><c r="H7" t="s"><v>39</v></c>'
			. '<c r="I7" t="s"><v>9</v></c><c r="J7" t="s"><v>10</v></c>'
			. '</row>'
			. '</sheetData></worksheet>';

		$workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';

		$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
			. '</Relationships>';

		$root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';

		$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
			. '</Types>';

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Cannot create archive: ' . $path );
		}

		$zip->addFromString( '[Content_Types].xml', $content_types );
		$zip->addFromString( '_rels/.rels', $root_rels );
		$zip->addFromString( 'xl/workbook.xml', $workbook_xml );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
		$zip->addFromString( 'xl/sharedStrings.xml', $shared_xml );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
		$zip->close();

		return $path;
	}
}

if ( isset( $argv[0] ) && __FILE__ === realpath( $argv[0] ) ) {
	$output = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/samples/sample.xlsx';
	$dir    = dirname( $output );

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}

	echo ei_build_sample_xlsx( $output ) . "\n";
}
