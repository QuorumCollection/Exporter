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

	/**
	 * DataSheet is the representation of a Worksheet
	 *
	 * @param string|null $name The name to give the sheet. The use is Engine implementation specific but is likely
	 *     filename or Sheet name
	 */
	public function __construct( $name = null ) {
		$this->name      = $name;
		$this->tmpStream = fopen("php://temp", "r+");
	}

	/**
	 * @return string|null
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Append a row worth of data to the end of the Worksheet.
	 *
	 * @param array $row An array of scalars. Otherwise an InvalidDataTypeException will be thrown.
	 * @throws InvalidDataTypeException
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
	 * Append multiple rows of data to the end of the Worksheet.
	 *
	 * @param array|\Iterator $dataSet An iterable of arrays of scalars.
	 */
	public function addRows( $dataSet ) {
		foreach( $dataSet as $row ) {
			$this->addRow($row);
		}
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
	 * @return bool
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
