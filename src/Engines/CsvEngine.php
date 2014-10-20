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

	/**
	 * @var string
	 * @var string
	 */
	protected $outputEncoding, $inputEncoding;

	/**
	 * @var string
	 * @var string
	 */
	protected $delimiter, $enclosure;

	protected $multiSheetStrategy = self::STRATEGY_CONCAT;

	/**
	 * @var bool
	 */
	protected $disableBom = false;

	/**
	 * @var int
	 */
	protected $autoIndex = 1;

	/**
	 * @var string
	 */
	protected $tmpDir, $tmpPrefix = 'csv-export-';

	function __construct( $outputEncoding = 'UTF-16LE', $delimiter = null, $enclosure = '"', $inputEncoding = 'UTF-8' ) {
		$this->setDelimiter($delimiter);
		$this->setEnclosure($enclosure);
		$this->setOutputEncoding($outputEncoding);
		$this->setInputEncoding($inputEncoding);
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

	/**
	 * @param string $tmpDir
	 */
	public function setTmpDir( $tmpDir ) {
		$this->tmpDir = $tmpDir;
	}

	/**
	 * @return string
	 */
	public function getMultiSheetStrategy() {
		return $this->multiSheetStrategy;
	}

	/**
	 * @param string $multiSheetStrategy
	 */
	public function setMultiSheetStrategy( $multiSheetStrategy ) {
		if( !in_array($multiSheetStrategy, [ self::STRATEGY_ZIP, self::STRATEGY_CONCAT ]) ) {
			throw new \InvalidArgumentException('Invalid MultiSheet Strategy');
		}
		$this->multiSheetStrategy = $multiSheetStrategy;
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

	/**
	 * @param resource $outputStream
	 * @throws \Exception
	 */
	public function outputToStream( $outputStream ) {


		switch( $this->multiSheetStrategy ) {
			case self::STRATEGY_ZIP:
				$tmpDir = rtrim($this->tmpDir ?: sys_get_temp_dir(), '/');
				if( !is_dir($tmpDir) ) {
					throw new \RuntimeException("Temporary Directory Not Found");
				}

				$tmpName = tempnam($tmpDir, $this->tmpPrefix);

				$zip = new \ZipArchive;
				if( !$zip->open($tmpName, \ZipArchive::CREATE) ) {
					throw new ExportException('Error creating zip');
				}

				$x = 0;
				foreach( $this->streams as $stream ) {
					rewind($stream);
					$zip->addFromString('Sheet' . ($this->autoIndex++) . '.csv', stream_get_contents($stream));
				}

				$zip->close();

				$tmpStream = fopen($tmpName, 'r');
				stream_copy_to_stream($tmpStream, $outputStream);
				fclose($tmpStream);

				register_shutdown_function(function () use ( $tmpName ) {
					if( file_exists($tmpName) ) {
						unlink($tmpName);
					}
				});

				break;
			case self::STRATEGY_CONCAT:
				fwrite($outputStream, $this->getBom());
				foreach( $this->streams as $stream ) {
					rewind($stream);
					stream_copy_to_stream($stream, $outputStream);
				}

				break;
			default:
				throw new \Exception('Invalid MultiSheet Strategy');
		}
	}

	/**
	 * @return mixed
	 */
	public function getDelimiter() {
		if( $this->delimiter === null ) {
			if( stripos($this->outputEncoding, 'UTF-16') === 0 || stripos($this->outputEncoding, 'UTF-32') === 0 ) {
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

	protected function getBom() {
		if( $this->disableBom ) {
			return '';
		}

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

	/**
	 * @param bool $disable
	 */
	public function disableBom( $disable = true ) {
		$this->disableBom = $disable;
	}

}
