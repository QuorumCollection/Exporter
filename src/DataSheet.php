<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\InvalidDataTypeException;

class DataSheet {

	/**
	 * @var resource
	 */
	protected $tmpStream;

	function __construct() {
		$this->tmpStream = fopen("php://temp", "r+");
	}

	public function addRow( array $row ) {
		foreach( $row as $col ) {
			if( !is_scalar($col) ) {
				throw new InvalidDataTypeException;
			}
		}

		fwrite($this->tmpStream, json_encode($row) . "\n");
	}

	public function addRows( $dataSet ) {
		foreach( $dataSet as $row ) {
			$this->addRow($row);
		}
	}

	/**
	 * @return resource
	 */
	public function getTmpStream(){
		return $this->tmpStream;
	}

}
