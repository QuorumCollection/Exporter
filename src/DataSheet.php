<?php

namespace Quorum\Exporter;

use Quorum\Exporter\Engines\Csv\Encoding;
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
	private string $inputEncoding;

	/**
	 * DataSheet represents a worksheet. String values are converted to UTF-8 when rows are added.
	 *
	 * @param string|null     $name          The name to give the sheet. The use is Engine implementation specific but is likely
	 *                                       filename or Sheet name
	 * @param Encoding|string $inputEncoding The encoding of string values, as an enum case or an mbstring encoding name. Defaults to UTF-8.
	 * @throws \InvalidArgumentException If the input encoding is not supported.
	 */
	public function __construct( protected ?string $name = null, Encoding|string $inputEncoding = Encoding::UTF8 ) {
		$inputEncoding = $inputEncoding instanceof Encoding ? $inputEncoding->value : $inputEncoding;
		if( !in_array($inputEncoding, mb_list_encodings(), true) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}

		$this->inputEncoding = $inputEncoding;
	}

	/**
	 * Get the name of the sheet. Use thereof is Engine Specific
	 */
	public function getName(): ?string {
		return $this->name;
	}

	/**
	 * Append a row. String values use the configured input encoding. Array keys must be valid UTF-8. Values are converted to UTF-8 strings.
	 *
	 * @param array<int|string, scalar|null> $row An array of scalar values or null.
	 * @throws InvalidDataTypeException
	 */
	public function addRow( array $row ): void {
		foreach( $row as &$col ) {
			$col = $this->normalizeValue($col);
		}

		$jsonRow = json_encode($row);
		if( $jsonRow === false ) {
			throw new InvalidDataTypeException('Unable to encode row');
		}

		if( $this->tmpStream === null ) {
			$tmpStream = fopen("php://temp", "r+");
			if( $tmpStream === false ) {
				throw new \RuntimeException('Unable to open temporary stream');
			}

			$this->tmpStream = $tmpStream;
		}

		fwrite($this->tmpStream, $jsonRow . "\n");
	}

	private function normalizeValue( mixed $value ): string {
		if( !is_scalar($value) && $value !== null ) {
			throw new InvalidDataTypeException;
		}

		if( is_string($value) && $this->inputEncoding !== 'UTF-8' ) {
			$converted = mb_convert_encoding($value, 'UTF-8', $this->inputEncoding);
			if( $converted === false ) {
				throw new InvalidDataTypeException('Unable to convert row value');
			}

			return $converted;
		}

		return (string)$value;
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
	 * Move forward to next element
	 */
	public function next(): void {
		if( $this->tmpStream === null ) {
			$this->currentValue = null;

			return;
		}

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
	private function decodeRow( string $row ): array {
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
