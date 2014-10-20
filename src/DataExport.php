<?php

namespace Quorum\Exporter;

class DataExport {

	/**
	 * @var DataSheet[]
	 */
	protected $dataSheets = [ ];

	/**
	 * @var \Quorum\Exporter\EngineInterface
	 */
	protected $engine;

	function __construct( EngineInterface $engine ) {
		$this->engine = $engine;
	}

	public function addSheet( DataSheet $sheet, $sheetTitle = null ) {
		if( is_string($sheetTitle) ) {
			$this->dataSheets[$sheetTitle] = $sheet;
		} else {
			$this->dataSheets[] = $sheet;
		}
	}

	/**
	 * @param resource $outputStream
	 * @param callable $headerCallback
	 */
	public function export( $outputStream, callable $headerCallback = null ) {
		foreach( $this->dataSheets as $dataSheet ) {
			$this->engine->processSheet($dataSheet);
		}

		if($headerCallback) {
			$headerCallback('');
		}

		$this->engine->outputToStream($outputStream, $headerCallback);
	}

}
