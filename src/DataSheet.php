<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\InvalidDataTypeException;

class DataSheet implements \Iterator {

	/** @var resource */
	protected $tmpStream;

	protected ?string $name;
	/** The row counter. */
	protected int $rowIndex = 0;
	/** The current iterator value */
	protected ?array $currentValue;

	/**
	 * DataSheet is the representation of a Worksheet
	 *
	 * @param string|null $name The name to give the sheet. The use is Engine implementation specific but is likely
	 *                          filename or Sheet name
	 */
	public function __construct( ?string $name = null ) {
		$this->name      = $name;
		$this->tmpStream = fopen("php://temp", "r+");
	}

	/**
	 * Get the name of the sheet. Use thereof is Engine Specific
	 */
	public function getName() : ?string {
		return $this->name;
	}

	/**
	 * Append a row worth of data to the end of the Worksheet.
	 *
	 * @param array $row An array of scalars.
	 * @throws InvalidDataTypeException
	 */
	public function addRow( array $row ) : void {
		foreach( $row as &$col ) {
			if( !is_scalar($col) && $col !== null ) {
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
	public function addRows( $dataSet ) : void {
		foreach( $dataSet as $row ) {
			$this->addRow($row);
		}
	}

	/**
	 * Return the current value
	 */
	public function current() : ?array {
		return $this->currentValue;
	}

	/**
	 * Move forward to next element
	 */
	public function next() : void {
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
	 * @see http://php.net/manual/en/iterator.key.php
	 */
	public function key() : int {
		return $this->rowIndex;
	}

	/**
	 * Checks if current position is valid
	 */
	public function valid() : bool {
		return $this->currentValue !== null;
	}

	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind() : void {
		$this->rowIndex = 0;
		rewind($this->tmpStream);
		$this->next();
	}

}
