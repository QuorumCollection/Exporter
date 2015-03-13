<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\InvalidDataTypeException;

class DataSheet implements \Iterator {

	/**
	 * @var resource
	 */
	protected $tmpStream;

	/**
	 * @var string|null
	 */
	protected $name;
	/**
	 * The row counter.
	 *
	 * @var int
	 */
	protected $rowIndex = 0;
	/**
	 * The current iterator value
	 *
	 * @var array|null
	 */
	protected $currentValue = null;

	public function __construct( $name = null ) {
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
		foreach( $row as &$col ) {
			if( !is_scalar($col) && !is_null($col) ) {
				throw new InvalidDataTypeException;
			}

			$col = (string)$col;
		}

		fwrite($this->tmpStream, json_encode($row) . "\n");
	}

	/**
	 * Return the current value
	 *
	 * @return array
	 */
	public function current() {
		return $this->currentValue;
	}

	/**
	 * Move forward to next element
	 */
	public function next() {
		$string = fgets($this->tmpStream);

		if( $string === false ) {
			$this->currentValue = null;
		} else {
			$this->currentValue = json_decode($string, true);
			$this->rowIndex++;
		}
	}

	/**
	 * Return the key of the current element
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 */
	public function key() {
		return $this->rowIndex;
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid() {
		return $this->currentValue !== null;
	}

	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind() {
		$this->rowIndex = 0;
		rewind($this->tmpStream);
		$this->next();
	}
}
