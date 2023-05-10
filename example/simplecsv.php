<?php

use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\CsvEngine;

require __DIR__ . '/../vendor/autoload.php';

$csv      = new CsvEngine;
$exporter = new DataExport($csv);

// Output a ZIP of CSV's for Multiple Sheets
$csv->setMultiSheetStrategy(CsvEngine::STRATEGY_ZIP);

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
