# Exporter

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/QuorumCollection/Exporter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/QuorumCollection/Exporter/?branch=master)

A Streamed Data Export Tool

Supported formats:
- CSV
- SpreadsheetML
- More to come.

More details to come. Early Beta.


## Installing

Exporter is available through Packagist via Composer.

```json
"require": {
  "quorum/exporter": "dev-master",
}
```

## Documentation

### Class: DataExport \[ `\Quorum\Exporter` \]

#### Method: `DataExport->__construct($engine)`

##### Parameters:

- ***\Quorum\Exporter\EngineInterface*** `$engine`

---

#### Method: `DataExport->addSheet($sheet [, $sheetTitle = null])`

Add a Data Sheet to the export.

##### Parameters:

- ***\Quorum\Exporter\DataSheet*** `$sheet` - The DataSheet to add to the export
- ***null*** | ***string*** `$sheetTitle` - Optional Title to give the data export.
Most Engines will interpret this as filename (sans file extension).
If excluded, the name will be left to the engine.

---

#### Method: `DataExport->export([ $outputStream = null [, $headerCallback = null]])`

Trigger the final export process.

##### Parameters:

- ***resource*** | ***null*** `$outputStream` - The stream resource to export to.
NULL will open a php://output resource.
- ***callable*** `$headerCallback`

### Class: DataSheet \[ `\Quorum\Exporter` \]



#### Undocumented Method: `DataSheet->__construct([ $name = null])`

---

#### Method: `DataSheet->getName()`

##### Returns:

- ***string***

---

#### Method: `DataSheet->addRows($dataSet)`

##### Parameters:

- ***array*** | ***\Iterator*** `$dataSet`

---

#### Method: `DataSheet->addRow($row)`

##### Parameters:

- ***array*** `$row`

---

#### Method: `DataSheet->current()`

Return the current value

##### Returns:

- ***array***

---

#### Method: `DataSheet->next()`

Move forward to next element

---

#### Method: `DataSheet->key()`

Return the key of the current element

##### Returns:

- ***mixed*** - scalar on success, or null on failure.

---

#### Method: `DataSheet->valid()`

Checks if current position is valid

##### Returns:

- ***boolean***

---

#### Method: `DataSheet->rewind()`

Rewind the Iterator to the first element

### Class: EngineInterface \[ `\Quorum\Exporter` \]

### Class: CsvEngine \[ `\Quorum\Exporter\Engines` \]

#### Method: `CsvEngine->__construct([ $outputEncoding = 'UTF-16LE' [, $delimiter = null [, $enclosure = '"' [, $inputEncoding = 'UTF-8']]]])`

##### Parameters:

- ***string*** `$outputEncoding` - The encoding to output. UTF-16LE is best supported by Excel
- ***string*** | ***null*** `$delimiter` - Character to use as Delimiter. Default varies based on encoding.
- ***string*** `$enclosure` - Character to use as Enclosure.
- ***string*** `$inputEncoding` - The input encoding to convert *from*.

---

#### Method: `CsvEngine->setEnclosure($enclosure)`

##### Parameters:

- ***string*** `$enclosure`

---

#### Method: `CsvEngine->setTmpDir($tmpDir)`

##### Parameters:

- ***string*** `$tmpDir`

---

#### Method: `CsvEngine->getMultiSheetStrategy()`

##### Returns:

- ***string***

---

#### Method: `CsvEngine->setMultiSheetStrategy($multiSheetStrategy)`

##### Parameters:

- ***string*** `$multiSheetStrategy`



---

#### Method: `CsvEngine->outputToStream($outputStream)`

##### Parameters:

- ***resource*** `$outputStream`

---

#### Method: `CsvEngine->getDelimiter()`

Gets delimiter.  If unset, UTF-16 and UTF-32 default to TAB "\t", everything else to COMMA ","

##### Returns:

- ***string***

---

#### Method: `CsvEngine->setDelimiter($delimiter)`

Sets delimiter. Setting to NULL triggers automatic delimiter decision based on recommended encoding rules.

##### Parameters:

- ***string*** | ***null*** `$delimiter` - Delimiter Character. Must be a single byte.

---

#### Method: `CsvEngine->getEnclosure()`

##### Returns:

- ***string***

---

#### Method: `CsvEngine->disableBom([ $disable = true])`

##### Parameters:

- ***bool*** `$disable`

### Class: SpreadsheetMLEngine \[ `\Quorum\Exporter\Engines` \]



#### Method: `SpreadsheetMLEngine->outputToStream($outputStream)`

##### Parameters:

- ***resource*** `$outputStream`

### Class: ExportException \[ `\Quorum\Exporter\Exceptions` \]

### Class: InvalidDataTypeException \[ `\Quorum\Exporter\Exceptions` \]