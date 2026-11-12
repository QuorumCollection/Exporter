<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\Csv\Encoding;
use Quorum\Exporter\Engines\Csv\MultiSheetStrategy;
use Quorum\Exporter\Engines\CsvEngine;

class CsvIntegrationTest extends TestCase {

	#[\PHPUnit\Framework\Attributes\DataProvider('encodingProvider')]
	public function test_csv( Encoding $encoding ): void {
		$export = new DataExport(new CsvEngine($encoding));
		$sheet  = new DataSheet;
		$export->addSheet($sheet);

		$temp    = tmpfile();
		$meta    = stream_get_meta_data($temp);
		$tmpFile = $meta['uri'];

		$sheet->addRow([ 'test one', '日本語', 'test two' ]);
		$export->export($temp);

		$file = __DIR__ . '/fixtures/basic-csv-' . $encoding->value . '.csv';

		$this->assertFileEquals($file, $tmpFile);

		fclose($temp);
	}

	public static function encodingProvider(): array {
		return [
			[ Encoding::UTF8 ],
			[ Encoding::UTF16BE ],
			[ Encoding::UTF16LE ],
			[ Encoding::UTF32BE ],
			[ Encoding::UTF32LE ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('disabledBomEncodingProvider')]
	public function test_disableBom( Encoding $encoding, string $expectedEncoding ): void {
		$engine = new CsvEngine($encoding);
		$engine->disableBom();
		$export = new DataExport($engine);
		$sheet = new DataSheet;
		$sheet->addRow([ 'hello' ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame(mb_convert_encoding("hello\n", $expectedEncoding, 'UTF-8'), stream_get_contents($stream));
		fclose($stream);
	}

	public static function disabledBomEncodingProvider(): array {
		return [
			[ Encoding::UTF8, 'UTF-8' ],
			[ Encoding::UTF16BE, 'UTF-16BE' ],
			[ Encoding::UTF16LE, 'UTF-16LE' ],
			[ Encoding::UTF32BE, 'UTF-32BE' ],
			[ Encoding::UTF32LE, 'UTF-32LE' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('disabledBomEncodingProvider')]
	public function test_disableBomInConstructor( Encoding $encoding, string $expectedEncoding ): void {
		$engine = new CsvEngine(outputEncoding: $encoding, bomEnabled: false);
		$export = new DataExport($engine);
		$sheet = new DataSheet;
		$sheet->addRow([ 'hello' ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame(mb_convert_encoding("hello\n", $expectedEncoding, 'UTF-8'), stream_get_contents($stream));
		fclose($stream);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('bomConstructorProvider')]
	public function test_bomConstructorDefaultsAndOverrides( Encoding $encoding, ?bool $bomEnabled, string $bom ): void {
		$engine = new CsvEngine(outputEncoding: $encoding, bomEnabled: $bomEnabled);
		$export = new DataExport($engine);
		$sheet = new DataSheet;
		$sheet->addRow([ 'hello' ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame($bom . mb_convert_encoding("hello\n", $encoding->value, 'UTF-8'), stream_get_contents($stream));
		fclose($stream);
	}

	public static function bomConstructorProvider(): array {
		return [
			[ Encoding::UTF8, null, '' ],
			[ Encoding::UTF8, false, '' ],
			[ Encoding::UTF8, true, "\xEF\xBB\xBF" ],
			[ Encoding::UTF16LE, null, "\xFF\xFE" ],
			[ Encoding::UTF16LE, false, '' ],
			[ Encoding::UTF16LE, true, "\xFF\xFE" ],
		];
	}

	public function test_customInputEncoding(): void {
		$engine = new CsvEngine(outputEncoding: Encoding::UTF8);
		$export = new DataExport($engine);
		$sheet = new DataSheet(inputEncoding: 'ISO-8859-1');
		$sheet->addRow([ "caf\xE9", "d\xE9j\xE0, vu" ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame("café,\"déjà, vu\"\n", stream_get_contents($stream));
		fclose($stream);
	}

	public function test_multibyteInputEncoding(): void {
		$engine = new CsvEngine(outputEncoding: Encoding::UTF8);
		$export = new DataExport($engine);
		$sheet = new DataSheet(inputEncoding: Encoding::UTF16LE);
		$sheet->addRow([
			mb_convert_encoding('café', 'UTF-16LE', 'UTF-8'),
			mb_convert_encoding('one,two', 'UTF-16LE', 'UTF-8'),
			42,
			3.5,
			true,
			false,
			null,
			mb_convert_encoding('42', 'UTF-16LE', 'UTF-8'),
		]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame("café,\"one,two\",42,3.5,1,,,42\n", stream_get_contents($stream));
		fclose($stream);
	}

	public function test_usesSheetIterator(): void {
		$sheet = new class extends DataSheet {

			public function current(): ?array {
				$row = parent::current();

				return $row === null ? null : array_map('strtoupper', $row);
			}

		};
		$sheet->addRow([ 'hello' ]);
		$export = new DataExport(new CsvEngine(Encoding::UTF8));
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame("HELLO\n", stream_get_contents($stream));
		fclose($stream);
	}

	public function test_rejectsNonAsciiDelimiter(): void {
		$this->expectException(\InvalidArgumentException::class);
		new CsvEngine(outputEncoding: Encoding::UTF8, delimiter: "\xA7");
	}

	public function test_rejectsNonAsciiEnclosure(): void {
		$this->expectException(\InvalidArgumentException::class);
		new CsvEngine(outputEncoding: Encoding::UTF8, enclosure: "\xA7");
	}

	public function test_zipExportContainsNamedSheets(): void {
		$engine = new CsvEngine(Encoding::UTF8, multiSheetStrategy: MultiSheetStrategy::ZIP);
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

	public function test_strategyUsesEnumCases(): void {
		$engine = new CsvEngine(Encoding::UTF8);
		$this->assertSame(MultiSheetStrategy::CONCAT, $engine->getMultiSheetStrategy());
		$engine->setMultiSheetStrategy(MultiSheetStrategy::ZIP);
		$this->assertSame(MultiSheetStrategy::ZIP, $engine->getMultiSheetStrategy());
		$engine->setMultiSheetStrategy(MultiSheetStrategy::CONCAT);
		$this->assertSame(MultiSheetStrategy::CONCAT, $engine->getMultiSheetStrategy());
	}

	public function test_concatStrategyExportsBothSheets(): void {
		$engine = new CsvEngine(Encoding::UTF8);
		$engine->setMultiSheetStrategy(MultiSheetStrategy::CONCAT);
		$export = new DataExport($engine);
		$first = new DataSheet('first');
		$first->addRow([ 'one' ]);
		$second = new DataSheet('second');
		$second->addRow([ 'two' ]);
		$export->addSheet($first);
		$export->addSheet($second);

		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$this->assertSame("one\ntwo\n", stream_get_contents($stream));
		fclose($stream);
	}

}
