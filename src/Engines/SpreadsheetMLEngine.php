<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;

class SpreadsheetMLEngine implements EngineInterface {

	protected $worksheetData = [];

	protected $autoIndex = 1;

	protected ?int $createdTime = null;

	public function processSheet( DataSheet $sheet ) : void {
		$outputStream = fopen("php://temp", "r+");
		foreach( $sheet as $dataRow ) {

			$doc = new \DOMDocument;
			$row = $doc->createElement('Row');
			$doc->appendChild($row);
			$cell_index = 0;
			$wasEmpty   = false;

			foreach( $dataRow as $value ) {
				if( $this->not_null($value) ) {
					$rowCell = $doc->createElement('Cell');
					$row->appendChild($rowCell);
					if( $wasEmpty ) {
						$rowCell->setAttribute('ss:Index', $cell_index + 1);
					}

					$cellData = $doc->createElement('Data');
					$cellData->setAttribute('ss:Type', is_numeric($value) ? 'Number' : 'String');
					$cellData->appendChild($doc->createTextNode($value));
					$rowCell->appendChild($cellData);
					if( stripos($value, "\n") !== false ) {
						$rowCell->setAttribute('ss:StyleID', 's22');
					}

					$wasEmpty = false;
				} else {
					$wasEmpty = true;
				}

				$cell_index++;
			}

			// Allows you to output without an XML Declaration
			$xmlData = $doc->saveXML($doc->documentElement);
			$xmlData = preg_replace('/\r\n|\r|\n/', '&#13;', $xmlData);
			fwrite($outputStream, $xmlData);
		}

		$this->worksheetData[] = [
			'name'   => $sheet->getName() ?: 'Sheet' . ($this->autoIndex++),
			'stream' => $outputStream,
		];
	}

	public function outputToStream( $outputStream ) : void {
		$baseXml = $this->generateBaseXmlDocument();

		$splitDocument = preg_split('%(?:</?Replace_This_Element_With_Worksheet\d+/?>){1,2}%', $baseXml);

		foreach( $this->worksheetData as $index => $sheetData ) {
			fwrite($outputStream, $splitDocument[$index]);
			rewind($sheetData['stream']);
			stream_copy_to_stream($sheetData['stream'], $outputStream);
		}

		fwrite($outputStream, end($splitDocument));
	}

	private function not_null( $value ) : bool {
		if( is_array($value) ) {
			return  sizeof($value) > 0;
		}

		return  (is_string($value) || is_int($value)) && ($value != '') && ($value != 'NULL') && (strlen(trim($value)) > 0);
	}

	protected function generateBaseXmlDocument() : string {
		$doc = new \DOMDocument;
//		$doc->formatOutput = true;
		$doc->appendChild($doc->createProcessingInstruction('mso-application', 'progid="Excel.Sheet"'));

		$workbook = $doc->createElement('Workbook');
		$workbook->setAttribute('xmlns', 'urn:schemas-microsoft-com:office:spreadsheet');
		$workbook->setAttribute('xmlns:o', 'urn:schemas-microsoft-com:office:office');
		$workbook->setAttribute('xmlns:x', 'urn:schemas-microsoft-com:office:excel');
		$workbook->setAttribute('xmlns:ss', 'urn:schemas-microsoft-com:office:spreadsheet');
//		$workbook->setAttribute('xmlns:html', 'http://www.w3.org/TR/REC-html40');
		$doc->appendChild($workbook);

		$documentProperties = $doc->createElement('DocumentProperties');
		$documentProperties->setAttribute('xmlns', 'urn:schemas-microsoft-com:office:office');
		$documentProperties->appendChild($doc->createElement('Created', date('c', $this->createdTime ?: time())));
		$workbook->appendChild($documentProperties);

		$styles = $doc->createElement('Styles');
		$styles = $workbook->appendChild($styles);

		// Default
		$style = $doc->createElement('Style');
		$style->setAttribute('ss:ID', 'Default');
		$style->setAttribute('ss:Name', 'Normal');
		$style->appendChild($doc->createElement('Alignment'))->setAttribute('ss:Vertical', 'Bottom');
		$styles->appendChild($style);

		// Headers
		$style = $doc->createElement('Style');
		$style->setAttribute('ss:ID', 's21');
		$style->appendChild($doc->createElement('Font'))->setAttribute('ss:Bold', '1');
		$styles->appendChild($style);

		// Multi-line
		$style = $doc->createElement('Style');
		$style->setAttribute('ss:ID', 's22');
		$align = $doc->createElement('Alignment');
		$style->appendChild($align);
		$align->setAttribute('ss:Vertical', 'Bottom');
		$align->setAttribute('ss:WrapText', '1');
		$styles->appendChild($style);

		foreach( $this->worksheetData as $index => $WData ) {

			$worksheet = $doc->createElement('Worksheet');
			$workbook->appendChild($worksheet);
			$worksheet->setAttribute('ss:Name', $WData['name']);

			$table = $doc->createElement('Table');
			$worksheet->appendChild($table);

			$replaceElement = $doc->createElement('Replace_This_Element_With_Worksheet' . $index);
			$table->appendChild($replaceElement);

//			if( isset($WData['headers']) && is_array($WData['headers']) ) {
//				$row = $doc->createElement('Row');
//				$row = $table->appendChild($row);
//				$row->setAttribute('ss:StyleID', 's21');
//
//				foreach( $WData['headers'] as $header ) {
//					$Cell = $row->appendChild($doc->createElement('Cell'));
//					$Data = $Cell->appendChild($doc->createElement('Data'));
//					$Data->setAttribute('ss:Type', 'String');
//					$Data->appendChild($doc->createTextNode($header));
//
//				}
//			}
		}

		return $doc->saveXML();
	}

	/**
	 * @param int|null $createdTime The timestamp to use for the created time. If null, the current time will be used.
	 */
	public function setCreatedTime( ?int $createdTime ) : void {
		$this->createdTime = $createdTime;
	}

}
