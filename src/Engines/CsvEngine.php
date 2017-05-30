<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;
use Quorum\Exporter\Exceptions\ExportException;
use ZipStream\ZipStream;

class CsvEngine implements EngineInterface {

	const STRATEGY_CONCAT = 'stat-concat';
	const STRATEGY_ZIP    = 'stat-zip';

	const UTF8 = 'UTF-8';

	const UTF16   = 'UTF-16';
	const UTF16BE = 'UTF-16BE';
	const UTF16LE = 'UTF-16LE';

	const UTF32   = 'UTF-32';
	const UTF32BE = 'UTF-32BE';
	const UTF32LE = 'UTF-32LE';

	/**
	 * @var resource[]
	 */
	protected $streams = [];

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

	/**
	 * @see http://php.net/manual/en/function.mb-list-encodings.php for list of encoding strings.
	 *
	 * @param string      $outputEncoding The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
	 * @param string|null $delimiter Character to use as Delimiter. Default varies based on encoding.
	 * @param string      $enclosure Character to use as Enclosure.
	 * @param string      $inputEncoding The encoding of the input going into the CSVs.
	 */
	public function __construct( $outputEncoding = self::UTF16LE, $delimiter = null, $enclosure = '"', $inputEncoding = self::UTF8 ) {
		$this->setDelimiter($delimiter);
		$this->setEnclosure($enclosure);
		$this->setOutputEncoding($outputEncoding);
		$this->setInputEncoding($inputEncoding);
	}

	/**
	 * @param string $enclosure
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
	 * Set the strategy for allowing multiple sheets.
	 *
	 * Supported strategies are `CsvEngine::STRATEGY_ZIP` and `CsvEngine::STRATEGY_CONCAT`
	 *
	 * - `CsvEngine::STRATEGY_ZIP` will output a single zipfile containing every sheet as a seperate CSV file.
	 * - `CsvEngine::STRATEGY_CONCAT` will output a single CSV file with every sheet one after the next.
	 *
	 * @param string $multiSheetStrategy Use the constant `CsvEngine::STRATEGY_ZIP` or `CsvEngine::STRATEGY_CONCAT`
	 */
	public function setMultiSheetStrategy( $multiSheetStrategy ) {
		if( !in_array($multiSheetStrategy, [ self::STRATEGY_ZIP, self::STRATEGY_CONCAT ]) ) {
			throw new \InvalidArgumentException('Invalid MultiSheet Strategy');
		}
		$this->multiSheetStrategy = $multiSheetStrategy;
	}

	/**
	 * @inheritdoc
	 */
	public function processSheet( DataSheet $sheet ) {
		$outputStream = fopen("php://temp", "r+");

		foreach( $sheet as $data ) {
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

		if( !$name = $sheet->getName() ) {
			$name = sprintf("Sheet%d", $this->autoIndex++);
		}
		$this->streams[$name] = $outputStream;
	}

	/**
	 * @inheritdoc
	 */
	public function outputToStream( $outputStream ) {

		switch( $this->multiSheetStrategy ) {
			case self::STRATEGY_ZIP:
				$tmpDir = rtrim($this->tmpDir ?: sys_get_temp_dir(), '/');
				if( !is_dir($tmpDir) ) {
					throw new \RuntimeException("Temporary Directory Not Found");
				}

				$zip = new ZipStream(null, [ ZipStream::OPTION_OUTPUT_STREAM => $outputStream ]);

				foreach( $this->streams as $name => $stream ) {
					rewind($stream);
					$tmpStream = fopen("php://temp", "r+");
					fwrite($tmpStream, $this->getBom());
					stream_copy_to_stream($stream, $tmpStream);

					$zip->addFileFromStream($name . '.csv', $tmpStream);
				}

				$zip->finish();

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
	 * Gets delimiter.  If unset, UTF-16 and UTF-32 default to TAB "\t", everything else to COMMA ","
	 *
	 * @return string
	 */
	public function getDelimiter() {
		if( $this->delimiter === null ) {
			if( stripos($this->outputEncoding, self::UTF16) === 0 || stripos($this->outputEncoding, self::UTF32) === 0 ) {
				return "\t";
			} else {
				return ",";
			}
		}

		return $this->delimiter;
	}

	/**
	 * Sets delimiter. Setting to NULL triggers automatic delimiter decision based on recommended encoding rules.
	 *
	 * @param string|null $delimiter Delimiter Character. Must be a single byte.
	 */
	public function setDelimiter( $delimiter ) {
		if( $delimiter !== null && strlen($delimiter) !== 1 ) {
			throw new \InvalidArgumentException('Delimiter must be exactly one byte');
		}
		$this->delimiter = $delimiter;
	}

	/**
	 * @return string
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
			case self::UTF16:
			case self::UTF32:
				$encoding .= $this->isLittleEndian() ? 'LE' : 'BE';
				break;
		}

		switch( $encoding ) {
			// commented out for the time being. There's almost NO case where you would want a UTF-8 BOM
			//case 'UTF-8':
			//	return "\xEF\xBB\xBF";
			case self::UTF16BE:
				return "\xFE\xFF";
			case self::UTF16LE:
				return "\xFF\xFE";
			case self::UTF32BE:
				return "\0\0\xFE\xFF";
			case self::UTF32LE:
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
