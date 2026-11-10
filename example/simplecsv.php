<?php

use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\Csv\MultiSheetStrategy;
use Quorum\Exporter\Engines\CsvEngine;

require __DIR__ . '/../vendor/autoload.php';

$csv      = new CsvEngine(multiSheetStrategy: MultiSheetStrategy::ZIP);
$exporter = new DataExport($csv);

$sheetA = new DataSheet('a');
$sheetB = new DataSheet('b');

$exporter->addSheet($sheetA);
$exporter->addSheet($sheetB);

// Add a single row at a time;
$sheetA->addRow([ 1, 2, 3 ]);
$sheetA->addRow([ "a", "b", "c" ]);

// Add Multiple Rows
$sheetB->addRows([
	[ 4, 5, 6 ],
	[ 7, 8, 9 ],
]);

$exporter->export();
