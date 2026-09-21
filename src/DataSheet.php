<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\InvalidDataTypeException;

/**
 * @implements \Iterator<int, array<int|string, string>>
 */
class DataSheet implements \Iterator {

	/** @var resource */
	protected $tmpStream;

	protected ?string $name;
	/** The row counter. */
	protected int $rowIndex = 0;
	/** @var array<int|string, string>|null The current iterator value */
	protected ?array $currentValue;

	/**
	 * DataSheet is the representation of a Worksheet
	 *
	 * @param string|null $name The name to give the sheet. The use is Engine implementation specific but is likely
	 *                          filename or Sheet name
	 */
	public function __construct( ?string $name = null ) {
		$this->name = $name;

		$tmpStream = fopen("php://temp", "r+");
		if( $tmpStream === false ) {
			throw new \RuntimeException('Unable to open temporary stream');
		}

		$this->tmpStream = $tmpStream;
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
	 * @param array<int|string, mixed> $row An array of scalars.
	 * @throws InvalidDataTypeException
	 */
	public function addRow( array $row ) : void {
		foreach( $row as &$col ) {
			if( !is_scalar($col) && $col !== null ) {
				throw new InvalidDataTypeException;
			}

			$col = (string)$col;
		}

		$jsonRow = json_encode($row);
		if( $jsonRow === false ) {
			throw new InvalidDataTypeException('Unable to encode row');
		}

		fwrite($this->tmpStream, $jsonRow . "\n");
	}

	/**
	 * Append multiple rows of data to the end of the Worksheet.
	 *
	 * @param iterable<array<int|string, mixed>> $dataSet An iterable of arrays of scalars.
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
			$this->currentValue = $this->decodeRow($string);
			$this->rowIndex++;
		}
	}

	/**
	 * @return array<int|string, string>
	 */
	private function decodeRow( string $row ) : array {
		$decodedRow = json_decode($row, true);
		if( !is_array($decodedRow) ) {
			throw new \UnexpectedValueException('Unable to decode row');
		}

		$normalizedRow = [];
		foreach( $decodedRow as $key => $value ) {
			if( !is_string($value) ) {
				throw new \UnexpectedValueException('Decoded row contains a non-string value');
			}

			$normalizedRow[$key] = $value;
		}

		return $normalizedRow;
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
