<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;

class Xml2003Engine implements EngineInterface {

	protected $WorksheetData = [ ];

	public function processSheet( DataSheet $sheet ) {
		$stream = $sheet->getTmpStream();
		rewind($stream);

//		$outputStream = fopen("php://temp", "r+");

		$data = [ ];
		while( ($buffer = fgets($stream)) !== false ) {
			$data[] = json_decode($buffer, true);
		}


		$this->WorksheetData[] = [
			'name' => 'bbq' . rand(),
			'data' => $data
		];


	}

	/**
	 * @param resource $outputStream
	 */
	public function outputToStream( $outputStream ) {

		$doc               = new \DOMDocument;
//		$doc->formatOutput = true;
		$doc->appendChild($doc->createProcessingInstruction('mso-application', 'progid="Excel.Sheet"'));

		$workbook = $doc->createElement('Workbook');
		$workbook->setAttribute('xmlns', 'urn:schemas-microsoft-com:office:spreadsheet');
		$workbook->setAttribute('xmlns:o', 'urn:schemas-microsoft-com:office:office');
		$workbook->setAttribute('xmlns:x', 'urn:schemas-microsoft-com:office:excel');
		$workbook->setAttribute('xmlns:ss', 'urn:schemas-microsoft-com:office:spreadsheet');
		$workbook->setAttribute('xmlns:html', 'http://www.w3.org/TR/REC-html40');
		$workbook = $doc->appendChild($workbook);

		$documentProperties = $doc->createElement('DocumentProperties');
		$documentProperties = $workbook->appendChild($documentProperties);
		$documentProperties->setAttribute('xmlns', 'urn:schemas-microsoft-com:office:office');
//		$documentProperties->appendChild($doc->createElement('Author', 'Jesse Donat'));
//		$documentProperties->appendChild($doc->createElement('Description', 'Created By XSpreadsheet 1'));
		$documentProperties->appendChild($doc->createElement('Created', date('c')));
//		$documentProperties->appendChild($doc->createElement('Version', '11.8132'));


		$styles = $doc->createElement('Styles');
		$styles = $workbook->appendChild($styles);

		//Default
		$style = $doc->createElement('Style');
		$style = $styles->appendChild($style);
		$style->setAttribute('ss:ID', 'Default');
		$style->setAttribute('ss:Name', 'Normal');
		$style->appendChild($doc->createElement('Alignment'))->setAttribute('ss:Vertical', 'Bottom');

		//Headers
		$style = $doc->createElement('Style');
		$style = $styles->appendChild($style);
		$style->setAttribute('ss:ID', 's21');
		$style->appendChild($doc->createElement('Font'))->setAttribute('ss:Bold', '1');

		//Multiline
		$style = $doc->createElement('Style');
		$style = $styles->appendChild($style);
		$style->setAttribute('ss:ID', 's22');
		$align = $style->appendChild($doc->createElement('Alignment'));
		$align->setAttribute('ss:Vertical', 'Bottom');
		$align->setAttribute('ss:WrapText', '1');

		foreach( $this->WorksheetData as $WData ) {

			$worksheet = $doc->createElement('Worksheet');
			$worksheet = $workbook->appendChild($worksheet);
			$worksheet->setAttribute('ss:Name', $WData['name']);

			$Table = $doc->createElement('Table');
			$Table = $worksheet->appendChild($Table);

			if( isset($WData['headers']) && is_array($WData['headers']) ) {
				$Row = $doc->createElement('Row');
				$Row = $Table->appendChild($Row);
				$Row->setAttribute('ss:StyleID', 's21');

				foreach( $WData['headers'] as $header ) {
					$Cell = $Row->appendChild($doc->createElement('Cell'));
					$Data = $Cell->appendChild($doc->createElement('Data'));
					$Data->setAttribute('ss:Type', 'String');
					$Data->appendChild($doc->createTextNode($header));

				}
			}

			foreach( $WData['data'] as $dataRow ) {

				$Row        = $doc->createElement('Row');
				$Row        = $Table->appendChild($Row);
				$cell_index = 0;
				$wasEmpty   = false;

				foreach( $dataRow as $value ) {
					$newlines = false;
					if( $this->not_null($value) ) {
						$Cell = $Row->appendChild($doc->createElement('Cell'));
						if( $wasEmpty ) $Cell->setAttribute('ss:Index', $cell_index + 1);
						$Data = $Cell->appendChild($doc->createElement('Data'));
						$Data->setAttribute('ss:Type', is_numeric($value) ? 'Number' : 'String');
						$Data->appendChild($doc->createTextNode($value));
						if( $newlines > 0 ) {
							$Cell->setAttribute('ss:StyleID', 's22');
						}
						$wasEmpty = false;
					} else {
						$wasEmpty = true;
					}
					$cell_index++;
				}

			}
		}

		fwrite($outputStream, $doc->saveXML());

//		$this->xmlDoc = $this->post_process( $doc->saveXML() );

	}


	private function not_null( $value ) {
		if( is_array($value) ) {
			if( sizeof($value) > 0 ) {
				return true;
			} else {
				return false;
			}
		} else {
			if( (is_string($value) || is_int($value)) && ($value != '') && ($value != 'NULL') && (strlen(trim($value)) > 0) ) {
				return true;
			} else {
				return false;
			}
		}
	}

}
