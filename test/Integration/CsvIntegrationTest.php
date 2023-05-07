<?php

namespace Integration;

use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\CsvEngine;

class CsvIntegrationTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider encodingProvider
	 */
	public function test_csv( string $encoding ) : void {
		$export = new DataExport(new CsvEngine($encoding));
		$sheet  = new DataSheet();
		$export->addSheet($sheet);

		$temp    = tmpfile();
		$meta    = stream_get_meta_data($temp);
		$tmpFile = $meta['uri'];

		$sheet->addRow([ 'test one', '日本語', 'test two' ]);
		$export->export($temp);

		$file = __DIR__ . '/data/basic-csv-' . $encoding . '.csv';

		$this->assertFileEquals($file, $tmpFile);

		fclose($temp);
	}

	public function encodingProvider() : array {
		return [
			[ CsvEngine::UTF8 ],
			[ CsvEngine::UTF16BE ],
			[ CsvEngine::UTF16LE ],
			[ CsvEngine::UTF32BE ],
			[ CsvEngine::UTF32LE ],
		];
	}

}
