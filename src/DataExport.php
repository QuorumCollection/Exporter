<?php

namespace Quorum\Exporter;

class DataExport {

	/**
	 * @var DataSheet[]
	 */
	protected $dataSheets = [ ];

	/**
	 * @var EngineInterface
	 */
	protected $engine;

	/**
	 * @param EngineInterface $engine
	 */
	public function __construct( EngineInterface $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Add a Data Sheet to the export.
	 *
	 * @param DataSheet   $sheet The DataSheet to add to the export
	 * @param null|string $sheetTitle Optional Title to give the data export.
	 * Most Engines will interpret this as filename (sans file extension).
	 * If excluded, the name will be left to the engine.
	 */
	public function addSheet( DataSheet $sheet, $sheetTitle = null ) {
		if( is_string($sheetTitle) ) {
			$this->dataSheets[$sheetTitle] = $sheet;
		} else {
			$this->dataSheets[] = $sheet;
		}
	}

	/**
	 * Trigger the final export process.
	 *
	 * @param resource|null $outputStream The stream resource to export to.
	 * NULL will open a php://output resource.
	 * @param callable      $headerCallback
	 */
	public function export( $outputStream = null, callable $headerCallback = null ) {
		if( is_null($outputStream) ) {
			$outputStream = fopen('php://output', 'w');
		}

		foreach( $this->dataSheets as $dataSheet ) {
			$this->engine->processSheet($dataSheet);
		}

		if( $headerCallback ) {
			$headerCallback('');
		}

		$this->engine->outputToStream($outputStream, $headerCallback);
	}
}
