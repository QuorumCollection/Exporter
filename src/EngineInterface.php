<?php

namespace Quorum\Exporter;

interface EngineInterface {

	/**
	 * @param \Quorum\Exporter\DataSheet $sheet
	 * @access private
	 */
	public function processSheet( DataSheet $sheet ) : void;

	/**
	 * @param resource $outputStream
	 * @access private
	 */
	public function outputToStream( $outputStream ) : void;

}
