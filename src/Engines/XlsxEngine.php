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

	private const CONTENT_TYPES_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/content-types';
	private const CORE_PROPERTIES_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
	private const DCTERMS_NAMESPACE = 'http://purl.org/dc/terms/';
	private const RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';
	private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	private const WORKBOOK_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';
	private const XML_SCHEMA_INSTANCE_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

	/** @var array<int, array{name: string, stream: resource}> */
	protected array $worksheetData = [];

	protected int $autoIndex = 1;

	protected ?int $createdTime = null;

	public function processSheet( DataSheet $sheet ) : void {
		$outputStream = fopen('php://temp', 'r+');
		$worksheet = $this->newDocument();
		$worksheetRoot = $worksheet->createElementNS(self::SPREADSHEET_NAMESPACE, 'worksheet');
		$worksheet->appendChild($worksheetRoot);
		$sheetData = $this->appendElement($worksheet, $worksheetRoot, self::SPREADSHEET_NAMESPACE, 'sheetData');
		$this->appendElement($worksheet, $sheetData, self::SPREADSHEET_NAMESPACE, 'Replace_This_Element_With_Rows');
		$documentParts = preg_split('%(?:</?Replace_This_Element_With_Rows/?>){1,2}%', $worksheet->saveXML());
		fwrite($outputStream, $documentParts[0]);

		$rowIndex = 1;
		foreach( $sheet as $dataRow ) {
			$rowDocument = $this->newDocument();
			$row = $rowDocument->createElementNS(self::SPREADSHEET_NAMESPACE, 'row');
			$row->setAttribute('r', (string)$rowIndex);
			$rowDocument->appendChild($row);

			$columnIndex = 0;
			foreach( $dataRow as $value ) {
				if( $this->not_null($value) ) {
					$cellReference = $this->columnReference($columnIndex) . $rowIndex;
					$cell = $this->appendElement($rowDocument, $row, self::SPREADSHEET_NAMESPACE, 'c', [
						'r' => $cellReference,
						't' => is_numeric($value) ? 'n' : 'inlineStr',
					]);

					if( is_numeric($value) ) {
						$cellValue = $this->appendElement($rowDocument, $cell, self::SPREADSHEET_NAMESPACE, 'v');
						$cellValue->appendChild($rowDocument->createTextNode((string)$value));
					} else {
						if( strpbrk((string)$value, "\r\n") !== false ) {
							$cell->setAttribute('s', '1');
						}

						$inlineString = $this->appendElement($rowDocument, $cell, self::SPREADSHEET_NAMESPACE, 'is');
						$text = $this->appendElement($rowDocument, $inlineString, self::SPREADSHEET_NAMESPACE, 't');
						$text->setAttributeNS(self::XML_NAMESPACE, 'xml:space', 'preserve');
						$text->appendChild($rowDocument->createTextNode((string)$value));
					}
				}

				$columnIndex++;
			}

			fwrite($outputStream, $rowDocument->saveXML($row));
			$rowIndex++;
		}

		fwrite($outputStream, end($documentParts));

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

	private function newDocument() : \DOMDocument {
		return new \DOMDocument('1.0', 'UTF-8');
	}

	/**
	 * @param array<string, string> $attributes
	 */
	private function appendElement(
		\DOMDocument $document,
		\DOMNode $parent,
		string $namespace,
		string $name,
		array $attributes = []
	) : \DOMElement {
		$element = $document->createElementNS($namespace, $name);
		foreach( $attributes as $attribute => $value ) {
			$element->setAttribute($attribute, $value);
		}

		$parent->appendChild($element);

		return $element;
	}

	private function contentTypesXml() : string {
		$document = $this->newDocument();
		$types = $document->createElementNS(self::CONTENT_TYPES_NAMESPACE, 'Types');
		$document->appendChild($types);

		$this->appendElement($document, $types, self::CONTENT_TYPES_NAMESPACE, 'Default', [
			'Extension'   => 'rels',
			'ContentType' => 'application/vnd.openxmlformats-package.relationships+xml',
		]);
		$this->appendElement($document, $types, self::CONTENT_TYPES_NAMESPACE, 'Default', [
			'Extension'   => 'xml',
			'ContentType' => 'application/xml',
		]);
		$this->appendElement($document, $types, self::CONTENT_TYPES_NAMESPACE, 'Override', [
			'PartName'    => '/docProps/core.xml',
			'ContentType' => 'application/vnd.openxmlformats-package.core-properties+xml',
		]);
		$this->appendElement($document, $types, self::CONTENT_TYPES_NAMESPACE, 'Override', [
			'PartName'    => '/xl/workbook.xml',
			'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
		]);
		$this->appendElement($document, $types, self::CONTENT_TYPES_NAMESPACE, 'Override', [
			'PartName'    => '/xl/styles.xml',
			'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml',
		]);

		foreach( $this->worksheetData as $index => $sheetData ) {
			$this->appendElement($document, $types, self::CONTENT_TYPES_NAMESPACE, 'Override', [
				'PartName'    => '/xl/worksheets/sheet' . ($index + 1) . '.xml',
				'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
			]);
		}

		return $document->saveXML();
	}

	private function rootRelationshipsXml() : string {
		$document = $this->newDocument();
		$relationships = $document->createElementNS(self::RELATIONSHIPS_NAMESPACE, 'Relationships');
		$document->appendChild($relationships);
		$this->appendElement($document, $relationships, self::RELATIONSHIPS_NAMESPACE, 'Relationship', [
			'Id'     => 'rId1',
			'Type'   => self::WORKBOOK_RELATIONSHIPS_NAMESPACE . '/officeDocument',
			'Target' => 'xl/workbook.xml',
		]);
		$this->appendElement($document, $relationships, self::RELATIONSHIPS_NAMESPACE, 'Relationship', [
			'Id'     => 'rId2',
			'Type'   => self::WORKBOOK_RELATIONSHIPS_NAMESPACE . '/metadata/core-properties',
			'Target' => 'docProps/core.xml',
		]);

		return $document->saveXML();
	}

	private function corePropertiesXml() : string {
		$document = $this->newDocument();
		$coreProperties = $document->createElementNS(self::CORE_PROPERTIES_NAMESPACE, 'cp:coreProperties');
		$coreProperties->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dcterms', self::DCTERMS_NAMESPACE);
		$coreProperties->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XML_SCHEMA_INSTANCE_NAMESPACE);
		$document->appendChild($coreProperties);

		$createdTime = gmdate('Y-m-d\TH:i:s\Z', $this->createdTime ?: time());
		foreach( [ 'created', 'modified' ] as $property ) {
			$date = $this->appendElement($document, $coreProperties, self::DCTERMS_NAMESPACE, 'dcterms:' . $property);
			$date->setAttributeNS(self::XML_SCHEMA_INSTANCE_NAMESPACE, 'xsi:type', 'dcterms:W3CDTF');
			$date->appendChild($document->createTextNode($createdTime));
		}

		return $document->saveXML();
	}

	private function workbookXml() : string {
		$document = $this->newDocument();
		$workbook = $document->createElementNS(self::SPREADSHEET_NAMESPACE, 'workbook');
		$workbook->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:r', self::WORKBOOK_RELATIONSHIPS_NAMESPACE);
		$document->appendChild($workbook);
		$sheets = $this->appendElement($document, $workbook, self::SPREADSHEET_NAMESPACE, 'sheets');

		foreach( $this->worksheetData as $index => $sheetData ) {
			$sheetIndex = $index + 1;
			$sheet = $this->appendElement($document, $sheets, self::SPREADSHEET_NAMESPACE, 'sheet', [
				'name'    => $sheetData['name'],
				'sheetId' => (string)$sheetIndex,
			]);
			$sheet->setAttributeNS(self::WORKBOOK_RELATIONSHIPS_NAMESPACE, 'r:id', 'rId' . $sheetIndex);
		}

		return $document->saveXML();
	}

	private function workbookRelationshipsXml() : string {
		$document = $this->newDocument();
		$relationships = $document->createElementNS(self::RELATIONSHIPS_NAMESPACE, 'Relationships');
		$document->appendChild($relationships);

		foreach( $this->worksheetData as $index => $sheetData ) {
			$sheetIndex = $index + 1;
			$this->appendElement($document, $relationships, self::RELATIONSHIPS_NAMESPACE, 'Relationship', [
				'Id'     => 'rId' . $sheetIndex,
				'Type'   => self::WORKBOOK_RELATIONSHIPS_NAMESPACE . '/worksheet',
				'Target' => 'worksheets/sheet' . $sheetIndex . '.xml',
			]);
		}

		$this->appendElement($document, $relationships, self::RELATIONSHIPS_NAMESPACE, 'Relationship', [
			'Id'     => 'rId' . (count($this->worksheetData) + 1),
			'Type'   => self::WORKBOOK_RELATIONSHIPS_NAMESPACE . '/styles',
			'Target' => 'styles.xml',
		]);

		return $document->saveXML();
	}

	private function stylesXml() : string {
		$document = $this->newDocument();
		$styleSheet = $document->createElementNS(self::SPREADSHEET_NAMESPACE, 'styleSheet');
		$document->appendChild($styleSheet);

		$fonts = $this->appendElement($document, $styleSheet, self::SPREADSHEET_NAMESPACE, 'fonts', [ 'count' => '1' ]);
		$this->appendElement($document, $fonts, self::SPREADSHEET_NAMESPACE, 'font');
		$fills = $this->appendElement($document, $styleSheet, self::SPREADSHEET_NAMESPACE, 'fills', [ 'count' => '2' ]);
		foreach( [ 'none', 'gray125' ] as $patternType ) {
			$fill = $this->appendElement($document, $fills, self::SPREADSHEET_NAMESPACE, 'fill');
			$this->appendElement($document, $fill, self::SPREADSHEET_NAMESPACE, 'patternFill', [ 'patternType' => $patternType ]);
		}

		$borders = $this->appendElement($document, $styleSheet, self::SPREADSHEET_NAMESPACE, 'borders', [ 'count' => '1' ]);
		$this->appendElement($document, $borders, self::SPREADSHEET_NAMESPACE, 'border');
		$cellStyleXfs = $this->appendElement($document, $styleSheet, self::SPREADSHEET_NAMESPACE, 'cellStyleXfs', [ 'count' => '1' ]);
		$this->appendElement($document, $cellStyleXfs, self::SPREADSHEET_NAMESPACE, 'xf', [
			'numFmtId' => '0',
			'fontId'   => '0',
			'fillId'   => '0',
			'borderId' => '0',
		]);
		$cellXfs = $this->appendElement($document, $styleSheet, self::SPREADSHEET_NAMESPACE, 'cellXfs', [ 'count' => '2' ]);
		$this->appendElement($document, $cellXfs, self::SPREADSHEET_NAMESPACE, 'xf', [
			'numFmtId' => '0',
			'fontId'   => '0',
			'fillId'   => '0',
			'borderId' => '0',
			'xfId'     => '0',
		]);
		$wrappedText = $this->appendElement($document, $cellXfs, self::SPREADSHEET_NAMESPACE, 'xf', [
			'numFmtId'       => '0',
			'fontId'         => '0',
			'fillId'         => '0',
			'borderId'       => '0',
			'xfId'           => '0',
			'applyAlignment' => '1',
		]);
		$this->appendElement($document, $wrappedText, self::SPREADSHEET_NAMESPACE, 'alignment', [
			'vertical' => 'bottom',
			'wrapText' => '1',
		]);
		$cellStyles = $this->appendElement($document, $styleSheet, self::SPREADSHEET_NAMESPACE, 'cellStyles', [ 'count' => '1' ]);
		$this->appendElement($document, $cellStyles, self::SPREADSHEET_NAMESPACE, 'cellStyle', [
			'name'      => 'Normal',
			'xfId'      => '0',
			'builtinId' => '0',
		]);

		return $document->saveXML();
	}

	/**
	 * @param int|null $createdTime The timestamp to use for the created time. If null, the current time will be used.
	 */
	public function setCreatedTime( ?int $createdTime ) : void {
		$this->createdTime = $createdTime;
	}

}
