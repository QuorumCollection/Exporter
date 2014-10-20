<?php

namespace Quorum\Exporter;

interface EngineInterface {

	public function processSheet( DataSheet $sheet );

	/**
	 * @param resource $outputStream
	 */
	public function outputToStream( $outputStream );

}
