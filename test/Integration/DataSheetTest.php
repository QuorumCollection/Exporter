<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Exceptions\InvalidDataTypeException;

class DataSheetTest extends TestCase {

	public function test_currentIsNullBeforeAndAfterIteration(): void {
		$sheet = new DataSheet;
		$this->assertNull($sheet->current());
		$sheet->addRow([ 'first' ]);
		$sheet->rewind();
		$this->assertSame([ 'first' ], $sheet->current());
		$sheet->next();
		$this->assertNull($sheet->current());
	}

	public function test_addRowRejectsNonScalarValues(): void {
		$sheet = new DataSheet;
		$this->expectException(InvalidDataTypeException::class);
		$sheet->addRow([ new \stdClass ]);
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

	public function test_addRowsAcceptsObjectProperties(): void {
		$sheet = new DataSheet;
		$sheet->addRows((object)[
			'first' => [ 'First', 1 ],
			'second' => [ 'Second', 2 ],
		]);

		$this->assertNull($sheet->getName());
		$this->assertSame([
			[ 'First', '1' ],
			[ 'Second', '2' ],
		], iterator_to_array($sheet, false));
	}

}
