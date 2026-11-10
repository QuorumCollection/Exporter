<?php

namespace Quorum\Exporter;

interface EngineInterface {

	/**
	 * Process a sheet for export.
	 *
	 * @internal
	 */
	public function processSheet( DataSheet $sheet ): void;

	/**
	 * Write the processed sheets to a stream.
	 *
	 * @param resource $outputStream A writable stream resource.
	 * @internal
	 */
	public function outputToStream( mixed $outputStream ): void;

}
