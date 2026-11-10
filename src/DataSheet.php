<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Exceptions\InvalidDataTypeException;

/**
 * @implements \Iterator<int, array<int|string, string>>
 */
class DataSheet implements \Iterator {

	/** @var resource|null */
	protected $tmpStream;

	/** The row counter. */
	protected int $rowIndex = 0;
	/** @var array<int|string, string>|null The current iterator value */
	protected ?array $currentValue = null;

	/**
	 * DataSheet is the representation of a Worksheet
	 *
	 * @param string|null $name The name to give the sheet. The use is Engine implementation specific but is likely
	 *                          filename or Sheet name
	 */
	public function __construct( protected ?string $name = null ) {
	}

	/**
	 * Get the name of the sheet. Use thereof is Engine Specific
	 */
	public function getName(): ?string {
		return $this->name;
	}

	/**
	 * Append a row worth of data to the end of the Worksheet.
	 *
	 * @param array<int|string, scalar|null> $row An array of scalar values or null. Values are converted to strings.
	 * @throws InvalidDataTypeException
	 */
	public function addRow( array $row ): void {
		foreach( $row as $col ) {
			$this->validateValue($col);
		}

		if( $this->tmpStream === null ) {
			$tmpStream = fopen("php://temp", "r+");
			if( $tmpStream === false ) {
				throw new \RuntimeException('Unable to open temporary stream');
			}

			$this->tmpStream = $tmpStream;
		}

		fwrite($this->tmpStream, base64_encode(serialize($row)) . "\n");
	}

	private function validateValue( mixed $value ): void {
		if( !is_scalar($value) && $value !== null ) {
			throw new InvalidDataTypeException;
		}
	}

	/**
	 * Append rows from an iterable.
	 *
	 * @param iterable<array<int|string, scalar|null>> $dataSet An iterable of rows.
	 */
	public function addRows( iterable $dataSet ): void {
		foreach( $dataSet as $row ) {
			$this->addRow($row);
		}
	}

	/**
	 * Return the current value
	 *
	 * @return array<int|string, string>|null
	 */
	public function current(): ?array {
		return $this->currentValue;
	}

	/**
	 * Iterate over rows before values are converted to strings.
	 *
	 * @return \Generator<int, array<int|string, scalar|null>>
	 * @internal
	 */
	public function rawRows(): \Generator {
		$this->rowIndex = 0;
		if( $this->tmpStream !== null ) {
			rewind($this->tmpStream);
		}

		while( ($row = $this->readRow()) !== null ) {
			yield $this->rowIndex => $row;
		}
	}

	/**
	 * Move forward to next element
	 */
	public function next(): void {
		$this->readRow();
	}

	/**
	 * @return array<int|string, scalar|null>|null
	 */
	private function readRow(): ?array {
		if( $this->tmpStream === null ) {
			$this->currentValue = null;

			return null;
		}

		$string = fgets($this->tmpStream);

		if( $string === false ) {
			$this->currentValue = null;

			return null;
		}

		$row = $this->decodeRow($string);
		$this->currentValue = array_map('strval', $row);
		$this->rowIndex++;

		return $row;
	}

	/**
	 * @return array<int|string, scalar|null>
	 */
	private function decodeRow( string $row ): array {
		$serializedRow = base64_decode($row, true);
		if( $serializedRow === false ) {
			throw new \UnexpectedValueException('Unable to decode row');
		}

		$decodedRow = @unserialize($serializedRow, [ 'allowed_classes' => false ]);
		if( !is_array($decodedRow) ) {
			throw new \UnexpectedValueException('Unable to decode row');
		}

		$validatedRow = [];
		foreach( $decodedRow as $key => $value ) {
			if( !is_scalar($value) && $value !== null ) {
				throw new \UnexpectedValueException('Decoded row contains a non-scalar value');
			}

			$validatedRow[$key] = $value;
		}

		return $validatedRow;
	}

	/**
	 * Return the key of the current element
	 *
	 * @see http://php.net/manual/en/iterator.key.php
	 */
	public function key(): int {
		return $this->rowIndex;
	}

	/**
	 * Checks if current position is valid
	 */
	public function valid(): bool {
		return $this->currentValue !== null;
	}

	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind(): void {
		$this->rowIndex = 0;
		if( $this->tmpStream !== null ) {
			rewind($this->tmpStream);
		}

		$this->next();
	}

}
