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
	 */
	public function export( $outputStream ) {
		foreach( $this->dataSheets as $dataSheet ) {
			$this->engine->processSheet($dataSheet);
		}

		$streams = $this->engine->getFinalStreams();
		foreach( $streams as $stream ) {
			rewind($stream);

			stream_copy_to_stream($stream, $outputStream);
		}
	}

}
