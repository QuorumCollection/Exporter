<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\Csv\Encoding;
use Quorum\Exporter\Exceptions\InvalidDataTypeException;

class DataSheetTest extends TestCase {

	public function test_streamOpensWhenFirstRowIsAdded(): void {
		$sheet = new DataSheet('Report');
		$streamProperty = new \ReflectionProperty(DataSheet::class, 'tmpStream');

		$this->assertNull($streamProperty->getValue($sheet));
		$this->assertSame('Report', $sheet->getName());
		$this->assertSame([], iterator_to_array($sheet));
		$sheet->next();
		$this->assertNull($sheet->current());
		$this->assertNull($streamProperty->getValue($sheet));

		$sheet->addRow([ 'first' ]);
		$stream = $streamProperty->getValue($sheet);
		$this->assertIsResource($stream);
		$sheet->addRow([ 'second' ]);
		$this->assertSame($stream, $streamProperty->getValue($sheet));
		$this->assertSame([ [ 'first' ], [ 'second' ] ], iterator_to_array($sheet, false));
		$this->assertSame([ [ 'first' ], [ 'second' ] ], iterator_to_array($sheet, false));
	}

	public function test_currentIsNullBeforeAndAfterIteration(): void {
		$sheet = new DataSheet;
		$this->assertNull($sheet->current());
		$sheet->addRow([ 'first' ]);
		$sheet->rewind();
		$this->assertSame([ 'first' ], $sheet->current());
		$sheet->next();
		$this->assertNull($sheet->current());
	}

	public function test_iterationRetainsStringValues(): void {
		$sheet = new DataSheet;
		$sheet->addRow([ 'text' => 'hello', 'count' => 42, 'active' => true, 'empty' => null ]);
		$sheet->rewind();
		$this->assertSame([ 'text' => 'hello', 'count' => '42', 'active' => '1', 'empty' => '' ], $sheet->current());
		$sheet->next();
		$this->assertNull($sheet->current());
		$sheet->rewind();
		$this->assertSame([ 'text' => 'hello', 'count' => '42', 'active' => '1', 'empty' => '' ], $sheet->current());
	}

	public function test_addRowRejectsNonScalarValues(): void {
		$sheet = new DataSheet;
		$this->expectException(InvalidDataTypeException::class);
		$sheet->addRow([ new \stdClass ]);
	}

	public function test_nonUtf8RowsAreStoredAsUtf8(): void {
		$sheet = new DataSheet(inputEncoding: 'ISO-8859-1');
		$sheet->addRows([
			[ "café\n" => "caf\xE9\0\n,\"", 2 => false ],
			[ "d\xE9j\xE0", 123 ],
		]);
		$expected = [
			[ "café\n" => "café\0\n,\"", 2 => '' ],
			[ 'déjà', '123' ],
		];
		$this->assertSame($expected, iterator_to_array($sheet, false));
		$this->assertSame($expected, iterator_to_array($sheet, false));
	}

	public function test_multibyteInputPreservesScalarValues(): void {
		$sheet = new DataSheet(inputEncoding: Encoding::UTF16LE);
		$sheet->addRow([ mb_convert_encoding('café', 'UTF-16LE', 'UTF-8'), 42, 3.5, true, false, null ]);
		$this->assertSame([ [ 'café', '42', '3.5', '1', '', '' ] ], iterator_to_array($sheet, false));
	}

	public function test_invalidInputEncoding(): void {
		$this->expectException(\InvalidArgumentException::class);
		new DataSheet(inputEncoding: 'invalid-encoding');
	}

	public function test_invalidUtf8IsRejected(): void {
		$sheet = new DataSheet;
		$this->expectException(InvalidDataTypeException::class);
		$sheet->addRow([ "caf\xE9" ]);
	}

	public function test_nonUtf8KeysAreRejected(): void {
		$sheet = new DataSheet(inputEncoding: 'ISO-8859-1');
		$this->expectException(InvalidDataTypeException::class);
		$sheet->addRow([ "caf\xE9" => 'value' ]);
	}

	public function test_addRowsAcceptsGenerators(): void {
		$sheet = new DataSheet(name: 'Report');
		$rows = (static function (): \Generator {
			yield [ 'name' => 'First', 'count' => 2, 'active' => true, 'empty' => null ];
			yield [ 'Second', 3.5, false ];
		})();

		$sheet->addRows($rows);

		$this->assertSame('Report', $sheet->getName());
		$this->assertSame([
			[ 'name' => 'First', 'count' => '2', 'active' => '1', 'empty' => '' ],
			[ 'Second', '3.5', '' ],
		], iterator_to_array($sheet, false));
	}

}
