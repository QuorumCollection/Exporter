<?php

namespace Quorum\Exporter;

interface EngineInterface {

	/**
	 * @param \Quorum\Exporter\DataSheet $sheet
	 * @return void
	 * @access private
	 */
	public function processSheet( DataSheet $sheet );

	/**
	 * @param resource $outputStream
	 * @return void
	 * @access private
	 */
	public function outputToStream( $outputStream );

}
