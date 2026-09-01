<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;
use Quorum\Exporter\Exceptions\OutputException;
use ZipStream\Exception\OverflowException;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

/**
 * Writes a minimal Office Open XML spreadsheet (XLSX) archive.
 *
 * This intentionally mirrors SpreadsheetMLEngine: values are either numbers or
 * strings, empty values leave gaps in their rows, and multiline strings wrap.
 */
class XlsxEngine implements EngineInterface {

	/** @var array<int, array{name: string, stream: resource}> */
	protected array $worksheetData = [];

	protected int $autoIndex = 1;

	protected ?int $createdTime = null;

	public function processSheet( DataSheet $sheet ) : void {
		$outputStream = fopen('php://temp', 'r+');
		fwrite($outputStream, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');

		$rowIndex = 1;
		foreach( $sheet as $dataRow ) {
			fwrite($outputStream, '<row r="' . $rowIndex . '">');

			$columnIndex = 0;
			foreach( $dataRow as $value ) {
				if( $this->not_null($value) ) {
					$cellReference = $this->columnReference($columnIndex) . $rowIndex;
					if( is_numeric($value) ) {
						fwrite($outputStream, '<c r="' . $cellReference . '" t="n"><v>' . $this->xmlEscape($value) . '</v></c>');
					} else {
						$style = strpbrk((string)$value, "\r\n") === false ? '' : ' s="1"';
						fwrite($outputStream, '<c r="' . $cellReference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $this->xmlEscape($value) . '</t></is></c>');
					}
				}

				$columnIndex++;
			}

			fwrite($outputStream, '</row>');
			$rowIndex++;
		}

		fwrite($outputStream, '</sheetData></worksheet>');

		$this->worksheetData[] = [
			'name'   => $sheet->getName() ?: 'Sheet' . ($this->autoIndex++),
			'stream' => $outputStream,
		];
	}

	/**
	 * @throws OutputException
	 */
	public function outputToStream( $outputStream ) : void {
		$options = new Archive;
		$options->setOutputStream($outputStream);
		$zip = new ZipStream('export.xlsx', $options);

		$zip->addFile('[Content_Types].xml', $this->contentTypesXml());
		$zip->addFile('_rels/.rels', $this->rootRelationshipsXml());
		$zip->addFile('docProps/core.xml', $this->corePropertiesXml());
		$zip->addFile('xl/workbook.xml', $this->workbookXml());
		$zip->addFile('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
		$zip->addFile('xl/styles.xml', $this->stylesXml());

		foreach( $this->worksheetData as $index => $sheetData ) {
			rewind($sheetData['stream']);
			$zip->addFileFromStream('xl/worksheets/sheet' . ($index + 1) . '.xml', $sheetData['stream']);
		}

		try {
			$zip->finish();
		}catch( OverflowException $exception ) {
			throw new OutputException('Zip Overflow', $exception->getCode(), $exception);
		}
	}

	private function not_null( $value ) : bool {
		if( is_array($value) ) {
			return sizeof($value) > 0;
		}

		return (is_string($value) || is_int($value)) && ($value != '') && ($value != 'NULL') && (strlen(trim($value)) > 0);
	}

	private function columnReference( int $columnIndex ) : string {
		$reference = '';
		$columnIndex++;

		while( $columnIndex > 0 ) {
			$remainder = ($columnIndex - 1) % 26;
			$reference = chr(65 + $remainder) . $reference;
			$columnIndex = intdiv($columnIndex - 1, 26);
		}

		return $reference;
	}

	private function xmlEscape( $value ) : string {
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}

	private function contentTypesXml() : string {
		$sheetOverrides = '';
		foreach( $this->worksheetData as $index => $sheetData ) {
			$sheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $sheetOverrides . '</Types>';
	}

	private function rootRelationshipsXml() : string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>';
	}

	private function corePropertiesXml() : string {
		$createdTime = gmdate('Y-m-d\TH:i:s\Z', $this->createdTime ?: time());

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dcterms:created xsi:type="dcterms:W3CDTF">' . $createdTime . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdTime . '</dcterms:modified></cp:coreProperties>';
	}

	private function workbookXml() : string {
		$sheets = '';
		foreach( $this->worksheetData as $index => $sheetData ) {
			$sheetIndex = $index + 1;
			$sheets .= '<sheet name="' . $this->xmlEscape($sheetData['name']) . '" sheetId="' . $sheetIndex . '" r:id="rId' . $sheetIndex . '"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>';
	}

	private function workbookRelationshipsXml() : string {
		$relationships = '';
		foreach( $this->worksheetData as $index => $sheetData ) {
			$sheetIndex = $index + 1;
			$relationships .= '<Relationship Id="rId' . $sheetIndex . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetIndex . '.xml"/>';
		}

		$relationships .= '<Relationship Id="rId' . (count($this->worksheetData) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
	}

	private function stylesXml() : string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font/></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="bottom" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	/**
	 * @param int|null $createdTime The timestamp to use for the created time. If null, the current time will be used.
	 */
	public function setCreatedTime( ?int $createdTime ) : void {
		$this->createdTime = $createdTime;
	}

}
