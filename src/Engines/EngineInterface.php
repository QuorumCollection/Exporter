<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;

interface EngineInterface {

	public function processSheet( DataSheet $sheet );

	/**
	 * @return resource[]
	 */
	public function getFinalStreams();

}
