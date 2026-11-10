# Exporter

[![Latest Stable Version](https://poser.pugx.org/quorum/exporter/version)](https://packagist.org/packages/quorum/exporter)
[![Total Downloads](https://poser.pugx.org/quorum/exporter/downloads)](https://packagist.org/packages/quorum/exporter)
[![License](https://poser.pugx.org/quorum/exporter/license)](https://packagist.org/packages/quorum/exporter)
[![ci.yml](https://github.com/QuorumCollection/Exporter/actions/workflows/ci.yml/badge.svg)](https://github.com/QuorumCollection/Exporter/actions/workflows/ci.yml)


A Streamed Data Export Tool

Supported formats:
- CSV / TSV
- SpreadsheetML "Excel 2004 XML Spreadsheet"
- More to come.

The ZipStream dependency requires 64-bit PHP and the zlib extension.



## Requirements

- **php**: >=8.1
- **ext-SPL**: *
- **ext-dom**: *
- **ext-json**: *
- **ext-mbstring**: *
- **maennchen/zipstream-php**: ^3.0

## Installing

Install the latest version with:

```bash
composer require 'quorum/exporter'
```

## Example

### Simple CSV Export

```php
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

```

## Documentation

### Class: Quorum\Exporter\DataExport

#### Method: DataExport->__construct

```php
function __construct(\Quorum\Exporter\EngineInterface $engine)
```

DataExport is the object used to orchestrate the export process regardless of export format.

##### Parameters

- ***\Quorum\Exporter\EngineInterface*** `$engine` - The engine by which to export the data sheets.

---

#### Method: DataExport->addSheet

```php
function addSheet(
	\Quorum\Exporter\DataSheet $sheet,
	?string $sheetTitle = null,
): void
```

Add a Data Sheet to the export.

##### Parameters

- ***\Quorum\Exporter\DataSheet*** `$sheet` - The DataSheet to add to the export
- ***string*** | ***null*** `$sheetTitle` - Optional collection key. A later sheet with the same key replaces the earlier one.
Exported sheet names come from the DataSheet name.

---

#### Method: DataExport->export

```php
function export($outputStream = null): void
```

Trigger the final export process.

##### Parameters

- ***resource*** | ***null*** `$outputStream` - A writable stream resource. Null opens php://output.

### Class: Quorum\Exporter\DataSheet



#### Method: DataSheet->__construct

```php
function __construct(?string $name = null)
```

DataSheet is the representation of a Worksheet

##### Parameters

- ***string*** | ***null*** `$name` - The name to give the sheet. The use is Engine implementation specific but is likely
filename or Sheet name

---

#### Method: DataSheet->getName

```php
function getName(): ?string
```

Get the name of the sheet. Use thereof is Engine Specific

---

#### Method: DataSheet->addRow

```php
function addRow(array $row): void
```

Append a row worth of data to the end of the Worksheet.

##### Parameters

- ***mixed[]*** `$row` - An array of scalar values or null. Values are converted to strings.

**Throws**: `\Quorum\Exporter\Exceptions\InvalidDataTypeException`

---

#### Method: DataSheet->addRows

```php
function addRows($dataSet): void
```

Append rows from an iterable or an object with public row properties.

##### Parameters

- ***iterable<mixed[]>*** `$dataSet` - An iterable of rows.

---

#### Method: DataSheet->current

```php
function current(): ?array
```

Return the current value

---

#### Method: DataSheet->next

```php
function next(): void
```

Move forward to next element

---

#### Method: DataSheet->key

```php
function key(): int
```

Return the key of the current element

---

#### Method: DataSheet->valid

```php
function valid(): bool
```

Checks if current position is valid

---

#### Method: DataSheet->rewind

```php
function rewind(): void
```

Rewind the Iterator to the first element

### Class: Quorum\Exporter\EngineInterface

#### Method: EngineInterface->processSheet

```php
function processSheet(\Quorum\Exporter\DataSheet $sheet): void
```

Process a sheet for export.

---

#### Method: EngineInterface->outputToStream

```php
function outputToStream($outputStream): void
```

Write the processed sheets to a stream.

##### Parameters

- ***resource*** `$outputStream` - A writable stream resource.

### Class: Quorum\Exporter\Engines\CsvEngine

```php
<?php
namespace Quorum\Exporter\Engines;

class CsvEngine {
	public const STRATEGY_CONCAT = 'stat-concat';
	public const STRATEGY_ZIP = 'stat-zip';
	public const UTF8 = 'UTF-8';
	public const UTF16 = 'UTF-16';
	public const UTF16BE = 'UTF-16BE';
	public const UTF16LE = 'UTF-16LE';
	public const UTF32 = 'UTF-32';
	public const UTF32BE = 'UTF-32BE';
	public const UTF32LE = 'UTF-32LE';
}
```

#### Method: CsvEngine->__construct

```php
function __construct(
	string $outputEncoding = self::UTF16LE,
	?string $delimiter = null,
	string $enclosure = '"',
	string $inputEncoding = self::UTF8,
)
```

The default and highly recommended export format for CSV tab delimited UTF-16LE with leading Byte Order Mark.  
  
While this may seem like an odd choice, the reason for this is cross platform Microsoft Excel compatibility.  

##### You can read more on the topic here

- https://donatstudios.com/CSV-An-Encoding-Nightmare

##### Parameters

- ***string*** `$outputEncoding` - The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
- ***string*** | ***null*** `$delimiter` - Character to use as Delimiter. Default varies based on encoding.
- ***string*** `$enclosure` - Character to use as Enclosure.
- ***string*** `$inputEncoding` - The encoding of the input going into the CSVs.

---

#### Method: CsvEngine->setEnclosure

```php
function setEnclosure(string $enclosure): void
```

Character to use as CSV value enclosure. Commonly this will be `"`

---

#### Method: CsvEngine->setTmpDir

```php
function setTmpDir(string $tmpDir): void
```

Set the directory to check before creating a ZIP export. Defaults to `sys_get_temp_dir`.

---

#### Method: CsvEngine->getMultiSheetStrategy

```php
function getMultiSheetStrategy(): string
```

Get the current strategy for Multi-Sheet export

---

#### Method: CsvEngine->setMultiSheetStrategy

```php
function setMultiSheetStrategy(string $multiSheetStrategy): void
```

Set the strategy for allowing multiple sheets.  
  
Supported strategies are `CsvEngine::STRATEGY_ZIP` and `CsvEngine::STRATEGY_CONCAT`  
  
- `CsvEngine::STRATEGY_ZIP` will output a single zipfile containing every sheet as a separate CSV file.  
- `CsvEngine::STRATEGY_CONCAT` will output a single CSV file with every sheet one after the next.

##### Parameters

- ***string*** `$multiSheetStrategy` - Use the constant `CsvEngine::STRATEGY_ZIP` or `CsvEngine::STRATEGY_CONCAT`

---

#### Method: CsvEngine->processSheet

```php
function processSheet(\Quorum\Exporter\DataSheet $sheet): void
```

Process a sheet for export.

---

#### Method: CsvEngine->outputToStream

```php
function outputToStream($outputStream): void
```

Write the processed sheets to a stream.

##### Parameters

- ***resource*** `$outputStream` - A writable stream resource.

**Throws**: `\Quorum\Exporter\Exceptions\OutputException`

---

#### Method: CsvEngine->getDelimiter

```php
function getDelimiter(): string
```

Gets delimiter.  If unset, UTF-16 and UTF-32 default to TAB "\t", everything else to COMMA ","

---

#### Method: CsvEngine->setDelimiter

```php
function setDelimiter(?string $delimiter): void
```

Sets delimiter. Setting to NULL triggers automatic delimiter decision based on recommended encoding rules.

##### Parameters

- ***string*** | ***null*** `$delimiter` - Delimiter Character. Must be a single byte.

---

#### Method: CsvEngine->getEnclosure

```php
function getEnclosure(): string
```

Get the current character used for enclosure.

---

#### Method: CsvEngine->disableBom

```php
function disableBom(bool $disable = true): void
```

Whether to disable the leading Byte Order Mark for the given encoding from being output.

### Class: Quorum\Exporter\Engines\SpreadsheetMLEngine

#### Method: SpreadsheetMLEngine->processSheet

```php
function processSheet(\Quorum\Exporter\DataSheet $sheet): void
```

Process a sheet for export.

---

#### Method: SpreadsheetMLEngine->outputToStream

```php
function outputToStream($outputStream): void
```

Write the processed sheets to a stream.

##### Parameters

- ***resource*** `$outputStream` - A writable stream resource.

---

#### Method: SpreadsheetMLEngine->setCreatedTime

```php
function setCreatedTime(?int $createdTime): void
```

##### Parameters

- ***int*** | ***null*** `$createdTime` - The timestamp to use for the created time. If null or zero, the current time is used.

### Class: Quorum\Exporter\Exceptions\ExportException

### Class: Quorum\Exporter\Exceptions\InvalidDataTypeException

### Class: Quorum\Exporter\Exceptions\OutputException

### Class: Quorum\Exporter\Exceptions\WritableException