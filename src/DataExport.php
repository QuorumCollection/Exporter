<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\WritableException;

class DataExport {

	/** @var DataSheet[] */
	protected array $dataSheets = [];

	protected EngineInterface $engine;

	/**
	 * DataExport is the object used to orchestrate the export process regardless of export format.
	 *
	 * @param \Quorum\Exporter\EngineInterface $engine The engine by which to export the data sheets.
	 */
	public function __construct( EngineInterface $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Add a Data Sheet to the export.
	 *
	 * @param DataSheet   $sheet      The DataSheet to add to the export
	 * @param string|null $sheetTitle Optional Title to give the data export.
	 *                                Most Engines will interpret this as filename (sans file extension).
	 *                                If excluded, the name will be left to the engine.
	 */
	public function addSheet( DataSheet $sheet, ?string $sheetTitle = null ) : void {
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
	 *                                    NULL will open a php://output resource.
	 */
	public function export( $outputStream = null ) : void {
		if( $outputStream === null ) {
			$outputStream = fopen('php://output', 'w');
		}

		if( !is_resource($outputStream) ) {
			throw new WritableException('expected resource, got ' . gettype($outputStream));
		}

		foreach( $this->dataSheets as $dataSheet ) {
			$this->engine->processSheet($dataSheet);
		}

		$this->engine->outputToStream($outputStream);
	}

}
