<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\Csv\MultiSheetStrategy;
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
		$engine = new CsvEngine(CsvEngine::UTF8, multiSheetStrategy: MultiSheetStrategy::ZIP);
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

	public function test_multiSheetStrategy(): void {
		$engine = new CsvEngine(CsvEngine::UTF8);
		$this->assertSame(MultiSheetStrategy::CONCAT, $engine->getMultiSheetStrategy());
		$engine->setMultiSheetStrategy(MultiSheetStrategy::ZIP);
		$this->assertSame(MultiSheetStrategy::ZIP, $engine->getMultiSheetStrategy());
		$engine->setMultiSheetStrategy(MultiSheetStrategy::CONCAT);
		$this->assertSame(MultiSheetStrategy::CONCAT, $engine->getMultiSheetStrategy());
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('bomProvider')]
	public function test_bomDefaultsAndOverrides( string $encoding, ?bool $bomEnabled, string $bom ): void {
		$engine = new CsvEngine(outputEncoding: $encoding, bomEnabled: $bomEnabled);
		$export = new DataExport($engine);
		$sheet = new DataSheet;
		$sheet->addRow([ 'hello' ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame($bom . mb_convert_encoding("hello\n", $encoding, 'UTF-8'), stream_get_contents($stream));
		fclose($stream);
	}

	public static function bomProvider(): array {
		return [
			[ CsvEngine::UTF8, null, '' ],
			[ CsvEngine::UTF8, false, '' ],
			[ CsvEngine::UTF8, true, "\xEF\xBB\xBF" ],
			[ CsvEngine::UTF16LE, null, "\xFF\xFE" ],
			[ CsvEngine::UTF16LE, false, '' ],
			[ CsvEngine::UTF16LE, true, "\xFF\xFE" ],
			[ 'ISO-8859-1', true, '' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('genericEncodingProvider')]
	public function test_nativeByteOrder( string $encoding, string $littleEndianBom, string $bigEndianBom ): void {
		$engine = new CsvEngine($encoding);
		$export = new DataExport($engine);
		$sheet = new DataSheet;
		$sheet->addRow([ 'hello' ]);
		$sheet->addRow([ 'world' ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$output = stream_get_contents($stream);
		$this->assertIsString($output);
		$bom = unpack('S', "\x01\x00") === [ 1 => 1 ] ? $littleEndianBom : $bigEndianBom;
		$this->assertStringStartsWith($bom, $output);
		$this->assertSame("hello\nworld\n", mb_convert_encoding($output, 'UTF-8', $encoding));
		fclose($stream);
	}

	public static function genericEncodingProvider(): array {
		return [
			[ CsvEngine::UTF16, "\xFF\xFE", "\xFE\xFF" ],
			[ CsvEngine::UTF32, "\xFF\xFE\0\0", "\0\0\xFE\xFF" ],
		];
	}

}
