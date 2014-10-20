<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\InvalidDataTypeException;

class DataSheet {

	/**
	 * @var resource
	 */
	protected $tmpStream;

	/**
	 * @var string|null
	 */
	protected $name;

	function __construct( $name = null ) {
		$this->name      = $name;
		$this->tmpStream = fopen("php://temp", "r+");
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param array|\Iterator $dataSet
	 */
	public function addRows( $dataSet ) {
		foreach( $dataSet as $row ) {
			$this->addRow($row);
		}
	}

	/**
	 * @param array $row
	 */
	public function addRow( array $row ) {
		foreach( $row as $col ) {
			if( !is_scalar($col) ) {
				throw new InvalidDataTypeException;
			}
		}

		fwrite($this->tmpStream, json_encode($row) . "\n");
	}

	/**
	 * @return resource
	 */
	public function getTmpStream() {
		return $this->tmpStream;
	}

}
