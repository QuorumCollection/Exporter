<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\WritableException;

class DataExport {

	/** @var DataSheet[] */
	protected array $dataSheets = [];

	/**
	 * DataExport is the object used to orchestrate the export process regardless of export format.
	 *
	 * @param \Quorum\Exporter\EngineInterface $engine The engine by which to export the data sheets.
	 */
	public function __construct( protected EngineInterface $engine ) {
	}

	/**
	 * Add a Data Sheet to the export.
	 *
	 * @param DataSheet   $sheet      The DataSheet to add to the export
	 * @param string|null $sheetTitle Optional collection key. A later sheet with the same key replaces the earlier one.
	 *                                Exported sheet names come from the DataSheet name.
	 */
	public function addSheet( DataSheet $sheet, ?string $sheetTitle = null ): void {
		if( is_string($sheetTitle) ) {
			$this->dataSheets[$sheetTitle] = $sheet;
		} else {
			$this->dataSheets[] = $sheet;
		}
	}

	/**
	 * Trigger the final export process.
	 *
	 * @param resource|null $outputStream A writable stream resource. Null opens php://output.
	 */
	public function export( mixed $outputStream = null ): void {
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
