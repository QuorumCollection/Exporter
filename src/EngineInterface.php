<?php

namespace Quorum\Exporter;

interface EngineInterface {

	/**
	 * @access private
	 */
	public function processSheet( DataSheet $sheet ) : void;

	/**
	 * @param resource $outputStream
	 * @access private
	 */
	public function outputToStream( $outputStream ) : void;

}
