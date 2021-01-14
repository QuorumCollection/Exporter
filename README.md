# Exporter

[![Latest Stable Version](https://poser.pugx.org/quorum/exporter/version)](https://packagist.org/packages/quorum/exporter)
[![Total Downloads](https://poser.pugx.org/quorum/exporter/downloads)](https://packagist.org/packages/quorum/exporter)
[![License](https://poser.pugx.org/quorum/exporter/license)](https://packagist.org/packages/quorum/exporter)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/QuorumCollection/Exporter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/QuorumCollection/Exporter)
[![Build Status](https://scrutinizer-ci.com/g/QuorumCollection/Exporter/badges/build.png?b=master)](https://scrutinizer-ci.com/g/QuorumCollection/Exporter)


A Streamed Data Export Tool

Supported formats:
- CSV / TSV
- SpreadsheetML "Excel 2004 XML Spreadsheet"
- More to come.



## Requirements

- **maennchen/zipstream-php**: ~0.3.0
- **ext-SPL**: *
- **ext-mbstring**: *
- **ext-dom**: *
- **ext-json**: *
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
function __construct(\Quorum\Exporter\EngineInterface $engine)
```

DataExport is the object used to orchestrate the export process regardless of export format.

##### Parameters:

- ***\Quorum\Exporter\EngineInterface*** `$engine` - The engine by which to export the data sheets.

---

#### Method: DataExport->addSheet

```php
function addSheet(\Quorum\Exporter\DataSheet $sheet [, $sheetTitle = null])
```

Add a Data Sheet to the export.

##### Parameters:

- ***\Quorum\Exporter\DataSheet*** `$sheet` - The DataSheet to add to the export
- ***string*** | ***null*** `$sheetTitle` - Optional Title to give the data export.
Most Engines will interpret this as filename (sans file extension).
If excluded, the name will be left to the engine.

---

#### Method: DataExport->export

```php
function export([ $outputStream = null])
```

Trigger the final export process.

##### Parameters:

- ***resource*** | ***null*** `$outputStream` - The stream resource to export to.
NULL will open a php://output resource.

### Class: \Quorum\Exporter\DataSheet

#### Method: DataSheet->__construct

```php
function __construct([ $name = null])
```

DataSheet is the representation of a Worksheet

##### Parameters:

- ***string*** | ***null*** `$name` - The name to give the sheet. The use is Engine implementation specific but is likely
filename or Sheet name

---

#### Method: DataSheet->getName

```php
function getName()
```

##### Returns:

- ***string*** | ***null***

---

#### Method: DataSheet->addRow

```php
function addRow(array $row)
```

Append a row worth of data to the end of the Worksheet.

##### Parameters:

- ***array*** `$row` - An array of scalars. Otherwise an InvalidDataTypeException will be thrown.

---

#### Method: DataSheet->addRows

```php
function addRows($dataSet)
```

Append multiple rows of data to the end of the Worksheet.

##### Parameters:

- ***array*** | ***\Iterator*** `$dataSet` - An iterable of arrays of scalars.

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

- ***bool***

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
function __construct([ $outputEncoding = self::UTF16LE [, $delimiter = null [, $enclosure = '"' [, $inputEncoding = self::UTF8]]]])
```

The default and highly recommended export format for CSV tab delimited UTF-16LE with leading Byte Order Mark.  
  
While this may seem like an odd choice, the reason for this is cross platform Microsoft Excel compatibility.  

##### You can read more on the topic here

- https://donatstudios.com/CSV-An-Encoding-Nightmare

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

Whether to disable the leading Byte Order Mark for the given encoding from being output.

##### Parameters:

- ***bool*** `$disable`

### Class: \Quorum\Exporter\Engines\SpreadsheetMLEngine

### Class: \Quorum\Exporter\Exceptions\ExportException

### Class: \Quorum\Exporter\Exceptions\InvalidDataTypeException

### Class: \Quorum\Exporter\Exceptions\OutputException

### Class: \Quorum\Exporter\Exceptions\WritableException