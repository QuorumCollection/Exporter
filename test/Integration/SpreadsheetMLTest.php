<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\SpreadsheetMLEngine;

class SpreadsheetMLTest extends TestCase {

	public function test_SpreadsheetML(): void {
		$engine = new SpreadsheetMLEngine(createdTime: new \DateTimeImmutable('@518395400'));
		$export = new DataExport($engine);
		$sheet  = new DataSheet;
		$export->addSheet($sheet);

		$temp    = tmpfile();
		$meta    = stream_get_meta_data($temp);
		$tmpFile = $meta['uri'];

		$sheet->addRow([ 'test one', '日本語', 'test two' ]);
		$export->export($temp);

		$file = __DIR__ . '/fixtures/basic-spreadsheet-ml.xml';

		$this->assertXmlFileEqualsXmlFile($file, $tmpFile);

		fclose($temp);
	}

	public function test_nonUtf8Input(): void {
		$export = new DataExport(new SpreadsheetMLEngine);
		$sheet = new DataSheet(inputEncoding: 'ISO-8859-1');
		$sheet->addRow([ "caf\xE9" ]);
		$export->addSheet($sheet);
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$output = stream_get_contents($stream);
		$this->assertIsString($output);
		$this->assertStringContainsString('café', $output);
		fclose($stream);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('createdTimeProvider')]
	public function test_createdTime( \DateTimeInterface $createdTime, string $expected ): void {
		$export = new DataExport(new SpreadsheetMLEngine(createdTime: $createdTime));
		$stream = fopen('php://temp', 'r+');
		$this->assertIsResource($stream);
		$export->export($stream);
		rewind($stream);
		$output = stream_get_contents($stream);
		$this->assertIsString($output);
		$this->assertStringContainsString('<Created>' . $expected . '</Created>', $output);
		fclose($stream);
	}

	public static function createdTimeProvider(): array {
		return [
			[ new \DateTimeImmutable('@0'), '1970-01-01T00:00:00+00:00' ],
			[ new \DateTime('2024-02-03T04:05:06-05:00'), '2024-02-03T04:05:06-05:00' ],
		];
	}

}
