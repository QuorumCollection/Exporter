<?php

namespace Quorum\Exporter;

interface EngineInterface {

	/**
	 * @param \Quorum\Exporter\DataSheet $sheet
	 * @return mixed
	 * @access private
	 */
	public function processSheet( DataSheet $sheet );

	/**
	 * @param resource $outputStream
	 * @access private
	 */
	public function outputToStream( $outputStream );

}
