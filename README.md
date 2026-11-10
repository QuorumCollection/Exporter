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

- ***array<int|string,scalar|null>*** `$row` - An array of scalar values or null. Values are converted to strings.

**Throws**: `\Quorum\Exporter\Exceptions\InvalidDataTypeException`

---

#### Method: DataSheet->addRows

```php
function addRows(iterable $dataSet): void
```

Append rows from an iterable.

##### Parameters

- ***iterable<array<int|string,scalar|null>>*** `$dataSet` - An iterable of rows.

---

#### Method: DataSheet->current

```php
function current(): ?array
```

Return the current value

##### Return Value

- ***array<int|string,string>*** | ***null***

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

### Enum: Quorum\Exporter\Engines\Csv\ByteOrderMark

Byte order marks used by CSV output encodings.

```php
<?php
namespace Quorum\Exporter\Engines\Csv;

enum ByteOrderMark: string {
	/** UTF-8 byte order mark: EF BB BF. */
	case UTF8 = "﻿";
	case UTF16BE = "\xfe\xff";
	case UTF16LE = "\xff\xfe";
	case UTF32BE = "\x00\x00\xfe\xff";
	case UTF32LE = "\xff\xfe\x00\x00";
}
```

### Enum: Quorum\Exporter\Engines\Csv\Encoding

Character encodings supported by the CSV engine.

```php
<?php
namespace Quorum\Exporter\Engines\Csv;

enum Encoding: string {
	case UTF8 = 'UTF-8';
	case UTF16 = 'UTF-16';
	case UTF16BE = 'UTF-16BE';
	case UTF16LE = 'UTF-16LE';
	case UTF32 = 'UTF-32';
	case UTF32BE = 'UTF-32BE';
	case UTF32LE = 'UTF-32LE';
}
```

### Enum: Quorum\Exporter\Engines\Csv\MultiSheetStrategy

Strategies for exporting multiple CSV sheets.

```php
<?php
namespace Quorum\Exporter\Engines\Csv;

enum MultiSheetStrategy {
	/** Write all sheets to a single CSV file. */
	case CONCAT;
	/** Write each sheet to a separate CSV file inside a ZIP archive. */
	case ZIP;
}
```

### Class: Quorum\Exporter\Engines\CsvEngine

#### Method: CsvEngine->__construct

```php
function __construct(
	\Quorum\Exporter\Engines\Csv\Encoding $outputEncoding = \Quorum\Exporter\Engines\Csv\Encoding::UTF16LE,
	?string $delimiter = null,
	string $enclosure = '"',
	\Quorum\Exporter\Engines\Csv\Encoding|string $inputEncoding = \Quorum\Exporter\Engines\Csv\Encoding::UTF8,
	?bool $bomEnabled = null,
	\Quorum\Exporter\Engines\Csv\MultiSheetStrategy $multiSheetStrategy = \Quorum\Exporter\Engines\Csv\MultiSheetStrategy::CONCAT,
)
```

The default and highly recommended export format for CSV tab delimited UTF-16LE with leading Byte Order Mark.  
  
While this may seem like an odd choice, the reason for this is cross platform Microsoft Excel compatibility.  

##### You can read more on the topic here

- https://donatstudios.com/CSV-An-Encoding-Nightmare

##### Parameters

- ***\Quorum\Exporter\Engines\Csv\Encoding*** `$outputEncoding` - The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
- ***string*** | ***null*** `$delimiter` - Character to use as Delimiter. Default varies based on encoding.
- ***string*** `$enclosure` - Character to use as Enclosure.
- ***\Quorum\Exporter\Engines\Csv\Encoding*** | ***string*** `$inputEncoding` - The encoding of the input going into the CSVs.
- ***bool*** | ***null*** `$bomEnabled` - Whether to include a byte order mark. Null disables it for UTF-8 and enables it for other encodings.
- ***\Quorum\Exporter\Engines\Csv\MultiSheetStrategy*** `$multiSheetStrategy` - How to export multiple sheets. Defaults to concatenation.

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
function getMultiSheetStrategy(
): \Quorum\Exporter\Engines\Csv\MultiSheetStrategy
```

Get the current multi-sheet strategy.

---

#### Method: CsvEngine->setMultiSheetStrategy

```php
function setMultiSheetStrategy(
	\Quorum\Exporter\Engines\Csv\MultiSheetStrategy $multiSheetStrategy,
): void
```

Set the multi-sheet strategy. ZIP creates an archive. CONCAT writes all sheets to one CSV file.

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

Enable or disable the leading byte order mark. By default it is disabled for UTF-8 and enabled for other encodings.

### Class: Quorum\Exporter\Engines\SpreadsheetMLEngine

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