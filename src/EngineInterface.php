<?php

namespace Quorum\Exporter;

interface EngineInterface {

	public function processSheet( DataSheet $sheet );

	/**
	 * @return resource[]
	 */
	public function getFinalStreams();

}
