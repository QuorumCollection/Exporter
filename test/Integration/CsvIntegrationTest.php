<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\CsvEngine;

class CsvIntegrationTest extends TestCase {

	#[\PHPUnit\Framework\Attributes\DataProvider('encodingProvider')]
	public function test_csv( string $encoding ): void {
		$export = new DataExport(new CsvEngine($encoding));
		$sheet  = new DataSheet;
		$export->addSheet($sheet);

		$temp    = tmpfile();
		$meta    = stream_get_meta_data($temp);
		$tmpFile = $meta['uri'];

		$sheet->addRow([ 'test one', '日本語', 'test two' ]);
		$export->export($temp);

		$file = __DIR__ . '/fixtures/basic-csv-' . $encoding . '.csv';

		$this->assertFileEquals($file, $tmpFile);

		fclose($temp);
	}

	public static function encodingProvider(): array {
		return [
			[ CsvEngine::UTF8 ],
			[ CsvEngine::UTF16BE ],
			[ CsvEngine::UTF16LE ],
			[ CsvEngine::UTF32BE ],
			[ CsvEngine::UTF32LE ],
		];
	}

	public function test_zipExportContainsNamedSheets(): void {
		$engine = new CsvEngine(CsvEngine::UTF8);
		$engine->setMultiSheetStrategy(CsvEngine::STRATEGY_ZIP);
		$export = new DataExport($engine);
		$first = new DataSheet('first');
		$first->addRow([ 'one', '日本語' ]);
		$second = new DataSheet('second');
		$second->addRow([ 'two', 42 ]);
		$export->addSheet($first);
		$export->addSheet($second);

		$stream = tmpfile();
		$this->assertIsResource($stream);
		$path = stream_get_meta_data($stream)['uri'];
		$export->export($stream);

		$archive = new \ZipArchive;
		$this->assertTrue($archive->open($path));
		$this->assertSame(2, $archive->numFiles);
		$this->assertSame("one,日本語\n", $archive->getFromName('first.csv'));
		$this->assertSame("two,42\n", $archive->getFromName('second.csv'));
		$archive->close();
		fclose($stream);
	}

}
