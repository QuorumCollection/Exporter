<?php

namespace Integration;

use PHPUnit\Framework\TestCase;
use Quorum\Exporter\DataExport;
use Quorum\Exporter\DataSheet;
use Quorum\Exporter\Engines\XlsxEngine;

class XlsxTest extends TestCase {

	public function test_Xlsx() : void {
		$engine = new XlsxEngine;
		$engine->setCreatedTime(518395400);
		$export = new DataExport($engine);

		$firstSheet = new DataSheet('First & Last');
		$firstSheet->addRow([ 'test one', '日本語', 'test two' ]);
		$firstSheet->addRow([ 'one', '', 3, "multi\nline" ]);
		$export->addSheet($firstSheet);

		$secondSheet = new DataSheet('Second');
		$secondSheet->addRow([ 5 => 'another sheet' ]);
		$export->addSheet($secondSheet);

		$temp = tmpfile();
		$meta = stream_get_meta_data($temp);
		$export->export($temp);
		fflush($temp);

		$zip = new \ZipArchive;
		$this->assertSame(true, $zip->open($meta['uri']) === true);

		$this->assertSame([
			'[Content_Types].xml',
			'_rels/.rels',
			'docProps/core.xml',
			'xl/workbook.xml',
			'xl/_rels/workbook.xml.rels',
			'xl/styles.xml',
			'xl/worksheets/sheet1.xml',
			'xl/worksheets/sheet2.xml',
		], $this->zipFileNames($zip));

		$workbook = $this->xmlDocument($zip->getFromName('xl/workbook.xml'));
		$workbookXPath = new \DOMXPath($workbook);
		$workbookXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$this->assertSame('First & Last', $workbookXPath->evaluate('string(/x:workbook/x:sheets/x:sheet[1]/@name)'));
		$this->assertSame('Second', $workbookXPath->evaluate('string(/x:workbook/x:sheets/x:sheet[2]/@name)'));

		$sheet = $this->xmlDocument($zip->getFromName('xl/worksheets/sheet1.xml'));
		$sheetXPath = new \DOMXPath($sheet);
		$sheetXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$this->assertSame('test one', $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[1]/x:c[@r="A1"]/x:is/x:t)'));
		$this->assertSame('日本語', $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[1]/x:c[@r="B1"]/x:is/x:t)'));
		$this->assertSame('3', $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[2]/x:c[@r="C2"]/x:v)'));
		$this->assertSame('n', $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[2]/x:c[@r="C2"]/@t)'));
		$this->assertSame('', $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[2]/x:c[@r="B2"]/@r)'));
		$this->assertSame('1', $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[2]/x:c[@r="D2"]/@s)'));
		$this->assertSame("multi\nline", $sheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row[2]/x:c[@r="D2"]/x:is/x:t)'));

		$secondSheetXml = $this->xmlDocument($zip->getFromName('xl/worksheets/sheet2.xml'));
		$secondSheetXPath = new \DOMXPath($secondSheetXml);
		$secondSheetXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
		$this->assertSame('another sheet', $secondSheetXPath->evaluate('string(/x:worksheet/x:sheetData/x:row/x:c[@r="A1"]/x:is/x:t)'));

		$coreProperties = $this->xmlDocument($zip->getFromName('docProps/core.xml'));
		$corePropertiesXPath = new \DOMXPath($coreProperties);
		$corePropertiesXPath->registerNamespace('dcterms', 'http://purl.org/dc/terms/');
		$this->assertSame('1986-06-05T22:43:20Z', $corePropertiesXPath->evaluate('string(/*/dcterms:created)'));

		$zip->close();
		fclose($temp);
	}

	private function xmlDocument( string $xml ) : \DOMDocument {
		$document = new \DOMDocument;
		$this->assertTrue($document->loadXML($xml));

		return $document;
	}

	/**
	 * @return string[]
	 */
	private function zipFileNames( \ZipArchive $zip ) : array {
		$fileNames = [];
		for( $index = 0; $index < $zip->numFiles; $index++ ) {
			$fileNames[] = $zip->getNameIndex($index);
		}

		return $fileNames;
	}

}
