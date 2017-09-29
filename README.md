# Exporter

[![Latest Stable Version](https://poser.pugx.org/quorum/exporter/version)](https://packagist.org/packages/quorum/exporter)
[![Total Downloads](https://poser.pugx.org/quorum/exporter/downloads)](https://packagist.org/packages/quorum/exporter)
[![License](https://poser.pugx.org/quorum/exporter/license)](https://packagist.org/packages/quorum/exporter)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/QuorumCollection/Exporter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/QuorumCollection/Exporter)
[![Build Status](https://scrutinizer-ci.com/g/QuorumCollection/Exporter/badges/build.png?b=master)](https://scrutinizer-ci.com/g/QuorumCollection/Exporter)


A Streamed Data Export Tool

Supported formats:
- CSV
- SpreadsheetML
- More to come.

More details to come. Early Beta.


## Requirements

- **maennchen/zipstream-php**: ~0.3.0
- **ext-SPL**: *
- **ext-mbstring**: *
- **php**: >=5.4

## Installing

Install the latest version with:

```bash
composer require 'quorum/exporter'
```

## Documentation

### Class: \Quorum\Exporter\DataExport

#### Method: DataExport->__construct

```php
function __construct($engine)
```

##### Parameters:

- ***\Quorum\Exporter\EngineInterface*** `$engine`

---

#### Method: DataExport->addSheet

```php
function addSheet($sheet [, $sheetTitle = null])
```

Add a Data Sheet to the export.

##### Parameters:

- ***\Quorum\Exporter\DataSheet*** `$sheet` - The DataSheet to add to the export
- ***null*** | ***string*** `$sheetTitle` - Optional Title to give the data export.
Most Engines will interpret this as filename (sans file extension).
If excluded, the name will be left to the engine.

---

#### Method: DataExport->export

```php
function export([ $outputStream = null [, $headerCallback = null]])
```

Trigger the final export process.

##### Parameters:

- ***resource*** | ***null*** `$outputStream` - The stream resource to export to.
NULL will open a php://output resource.
- ***callable*** `$headerCallback`

### Class: \Quorum\Exporter\DataSheet



#### Undocumented Method: `DataSheet->__construct([ $name = null])`

---

#### Method: DataSheet->getName

```php
function getName()
```

##### Returns:

- ***string***

---

#### Method: DataSheet->addRows

```php
function addRows($dataSet)
```

##### Parameters:

- ***array*** | ***\Iterator*** `$dataSet`

---

#### Method: DataSheet->addRow

```php
function addRow($row)
```

##### Parameters:

- ***array*** `$row`

---

#### Method: DataSheet->current

```php
function current()
```

Return the current value

##### Returns:

- ***array***

---

#### Method: DataSheet->next

```php
function next()
```

Move forward to next element

---

#### Method: DataSheet->key

```php
function key()
```

Return the key of the current element

##### Returns:

- ***mixed*** - scalar on success, or null on failure.

---

#### Method: DataSheet->valid

```php
function valid()
```

Checks if current position is valid

##### Returns:

- ***boolean***

---

#### Method: DataSheet->rewind

```php
function rewind()
```

Rewind the Iterator to the first element

### Class: \Quorum\Exporter\EngineInterface

### Class: \Quorum\Exporter\Engines\CsvEngine

```php
<?php
namespace Quorum\Exporter\Engines;

class CsvEngine {
	const STRATEGY_CONCAT = 'stat-concat';
	const STRATEGY_ZIP = 'stat-zip';
	const UTF8 = 'UTF-8';
	const UTF16 = 'UTF-16';
	const UTF16BE = 'UTF-16BE';
	const UTF16LE = 'UTF-16LE';
	const UTF32 = 'UTF-32';
	const UTF32BE = 'UTF-32BE';
	const UTF32LE = 'UTF-32LE';
}
```

#### Method: CsvEngine->__construct

```php
function __construct([ $outputEncoding = self::UTF16LE [, $delimiter = null [, $enclosure = '"' [, $inputEncoding = self::UTF8]]]])
```

##### Parameters:

- ***string*** `$outputEncoding` - The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
- ***string*** | ***null*** `$delimiter` - Character to use as Delimiter. Default varies based on encoding.
- ***string*** `$enclosure` - Character to use as Enclosure.
- ***string*** `$inputEncoding` - The encoding of the input going into the CSVs.

---

#### Method: CsvEngine->setEnclosure

```php
function setEnclosure($enclosure)
```

##### Parameters:

- ***string*** `$enclosure`

---

#### Method: CsvEngine->setTmpDir

```php
function setTmpDir($tmpDir)
```

##### Parameters:

- ***string*** `$tmpDir`

---

#### Method: CsvEngine->getMultiSheetStrategy

```php
function getMultiSheetStrategy()
```

##### Returns:

- ***string***

---

#### Method: CsvEngine->setMultiSheetStrategy

```php
function setMultiSheetStrategy($multiSheetStrategy)
```

Set the strategy for allowing multiple sheets.  
Supported strategies are `CsvEngine::STRATEGY_ZIP` and `CsvEngine::STRATEGY_CONCAT`  
  
- `CsvEngine::STRATEGY_ZIP` will output a single zipfile containing every sheet as a seperate CSV file.  
- `CsvEngine::STRATEGY_CONCAT` will output a single CSV file with every sheet one after the next.

##### Parameters:

- ***string*** `$multiSheetStrategy` - Use the constant `CsvEngine::STRATEGY_ZIP` or `CsvEngine::STRATEGY_CONCAT`

---

#### Method: CsvEngine->processSheet

```php
function processSheet($sheet)
```

---

#### Method: CsvEngine->outputToStream

```php
function outputToStream($outputStream)
```

---

#### Method: CsvEngine->getDelimiter

```php
function getDelimiter()
```

Gets delimiter.  If unset, UTF-16 and UTF-32 default to TAB "\t", everything else to COMMA ","

##### Returns:

- ***string***

---

#### Method: CsvEngine->setDelimiter

```php
function setDelimiter($delimiter)
```

Sets delimiter. Setting to NULL triggers automatic delimiter decision based on recommended encoding rules.

##### Parameters:

- ***string*** | ***null*** `$delimiter` - Delimiter Character. Must be a single byte.

---

#### Method: CsvEngine->getEnclosure

```php
function getEnclosure()
```

##### Returns:

- ***string***

---

#### Method: CsvEngine->disableBom

```php
function disableBom([ $disable = true])
```

##### Parameters:

- ***bool*** `$disable`

### Class: \Quorum\Exporter\Engines\SpreadsheetMLEngine

#### Method: SpreadsheetMLEngine->processSheet

```php
function processSheet($sheet)
```

---

#### Method: SpreadsheetMLEngine->outputToStream

```php
function outputToStream($outputStream)
```

### Class: \Quorum\Exporter\Exceptions\ExportException

### Class: \Quorum\Exporter\Exceptions\InvalidDataTypeException

### Class: \Quorum\Exporter\Exceptions\WritableException