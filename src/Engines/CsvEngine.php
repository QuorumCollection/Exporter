<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;
use Quorum\Exporter\Exceptions\ExportException;

class CsvEngine implements EngineInterface {

	const STRATEGY_CONCAT = 'stat-concat';
	const STRATEGY_ZIP    = 'stat-zip';
	/**
	 * @var resource[]
	 */
	protected $streams = [ ];
	protected $outputEncoding;
	protected $inputEncoding;
	protected $delimiter;
	protected $enclosure;
	protected $multiSheetStrategy = self::STRATEGY_CONCAT;

	function __construct( $outputEncoding = 'UTF-16LE', $delimiter = null, $enclosure = '"', $inputEncoding = 'UTF-8' ) {
		$this->setDelimiter($delimiter);
		$this->setEnclosure($enclosure);
		$this->setOutputEncoding($outputEncoding);
		$this->setInputEncoding($inputEncoding);
	}

	/**
	 * @param string $outputEncoding
	 */
	protected function setOutputEncoding( $outputEncoding ) {
		if( !in_array($outputEncoding, mb_list_encodings()) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}
		$this->outputEncoding = $outputEncoding;
	}

	/**
	 * @param string $inputEncoding
	 */
	protected function setInputEncoding( $inputEncoding ) {
		if( !in_array($inputEncoding, mb_list_encodings()) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}
		$this->inputEncoding = $inputEncoding;
	}
	}

	public function processSheet( DataSheet $sheet ) {
		$stream = $sheet->getTmpStream();
		rewind($stream);

		$outputStream = fopen("php://temp", "r+");

		while( ($buffer = fgets($stream)) !== false ) {
			$data = json_decode($buffer, true);

			$mem = fopen('php://memory', 'w+');
			if( ($length = @fputcsv($mem, $data, $this->getDelimiter(), $this->getEnclosure())) === false ) {
				throw new ExportException('fputcsv failed');
			}
			rewind($mem);
			$line = fread($mem, $length);
			fclose($mem);

			$line = mb_convert_encoding($line, $this->outputEncoding, $this->inputEncoding);
			fputs($outputStream, $line);
		}

		$this->streams[] = $outputStream;
	}

	public function getFinalStreams() {
		return $this->streams;
	}

	/**
	 * @return mixed
	 */
	public function getDelimiter() {
		if( $this->delimiter === null ) {
			if( stripos($this->outputEncoding, 'UTF-16') === 0 ) {
				return "\t";
			} else {
				return ",";
			}
		}

		return $this->delimiter;
	}

	/**
	 * @param mixed $delimiter
	 */
	public function setDelimiter( $delimiter ) {
		if( $delimiter !== null && strlen($delimiter) !== 1 ) {
			throw new \InvalidArgumentException('Delimiter must be exactly one byte');
		}
		$this->delimiter = $delimiter;
	}

	/**
	 * @return mixed
	 */
	public function getEnclosure() {
		return $this->enclosure;
	}

	/**
	 * @param mixed $enclosure
	 */
	public function setEnclosure( $enclosure ) {
		if( strlen($enclosure) !== 1 ) {
			throw new \InvalidArgumentException('Enclosure must be exactly one byte');
		}
		$this->enclosure = $enclosure;
	}

	protected function getBom() {
		$encoding = $this->outputEncoding;

		switch( $encoding ) {
			case 'UTF-16':
			case 'UTF-32':
				$encoding .= $this->isLittleEndian() ? 'LE' : 'BE';
				break;
		}

		switch( $encoding ) {
//			case 'UTF-8':
//				return "\xEF\xBB\xBF";
			case 'UTF-16BE':
				return "\xFE\xFF";
			case 'UTF-16LE':
				return "\xFF\xFE";
			case 'UTF-32BE':
				return "\0\0\xFE\xFF";
			case 'UTF-32LE':
				return "\xFF\xFE\0\0";
		}

		return '';
	}

	/**
	 * @return bool
	 */
	protected final function isLittleEndian() {
		return unpack('S', "\x01\x00")[1] === 1;
	}

}
