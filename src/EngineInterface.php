<?php

namespace Quorum\Exporter;

interface EngineInterface {

	/**
	 * @param \Quorum\Exporter\DataSheet $sheet
	 * @return mixed
	 * @private
	 */
	public function processSheet( DataSheet $sheet );

	/**
	 * @param resource $outputStream
	 * @private
	 */
	public function outputToStream( $outputStream );

}
