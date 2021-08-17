<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;
use Quorum\Exporter\Exceptions\ExportException;
use Quorum\Exporter\Exceptions\OutputException;
use ZipStream\Exception\OverflowException;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class CsvEngine implements EngineInterface {

	public const STRATEGY_CONCAT = 'stat-concat';
	public const STRATEGY_ZIP    = 'stat-zip';

	public const UTF8 = 'UTF-8';

	public const UTF16   = 'UTF-16';
	public const UTF16BE = 'UTF-16BE';
	public const UTF16LE = 'UTF-16LE';

	public const UTF32   = 'UTF-32';
	public const UTF32BE = 'UTF-32BE';
	public const UTF32LE = 'UTF-32LE';

	/** @var resource[] */
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

	/** @var bool */
	protected $disableBom = false;

	/** @var int */
	protected $autoIndex = 1;

	/** @var string */
	protected $tmpDir, $tmpPrefix = 'csv-export-';

	/**
	 * The default and highly recommended export format for CSV tab delimited UTF-16LE with leading Byte Order Mark.
	 * While this may seem like an odd choice, the reason for this is cross platform Microsoft Excel compatibility.
	 *
	 * You can read more on the topic here:
	 *
	 * - https://donatstudios.com/CSV-An-Encoding-Nightmare
	 *
	 * @see http://php.net/manual/en/function.mb-list-encodings.php for list of encoding strings.
	 *
	 * @param string      $outputEncoding The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
	 * @param string|null $delimiter      Character to use as Delimiter. Default varies based on encoding.
	 * @param string      $enclosure      Character to use as Enclosure.
	 * @param string      $inputEncoding  The encoding of the input going into the CSVs.
	 */
	public function __construct(
		string $outputEncoding = self::UTF16LE,
		?string $delimiter = null,
		string $enclosure = '"',
		string $inputEncoding = self::UTF8
	) {
		$this->setDelimiter($delimiter);
		$this->setEnclosure($enclosure);
		$this->setOutputEncoding($outputEncoding);
		$this->setInputEncoding($inputEncoding);
	}

	public function setEnclosure( string $enclosure ) : void {
		if( strlen($enclosure) !== 1 ) {
			throw new \InvalidArgumentException('Enclosure must be exactly one byte');
		}

		$this->enclosure = $enclosure;
	}

	protected function setOutputEncoding( string $outputEncoding ) : void {
		if( !in_array($outputEncoding, mb_list_encodings()) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}

		$this->outputEncoding = $outputEncoding;
	}

	protected function setInputEncoding( string $inputEncoding ) : void {
		if( !in_array($inputEncoding, mb_list_encodings()) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}

		$this->inputEncoding = $inputEncoding;
	}

	public function setTmpDir( string $tmpDir ) : void {
		$this->tmpDir = $tmpDir;
	}

	public function getMultiSheetStrategy() : string {
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
	public function setMultiSheetStrategy( string $multiSheetStrategy ) : void {
		if( !in_array($multiSheetStrategy, [ self::STRATEGY_ZIP, self::STRATEGY_CONCAT ]) ) {
			throw new \InvalidArgumentException('Invalid MultiSheet Strategy');
		}

		$this->multiSheetStrategy = $multiSheetStrategy;
	}

	public function processSheet( DataSheet $sheet ) : void {
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
	 * @throws OutputException
	 */
	public function outputToStream( $outputStream ) : void {

		switch( $this->multiSheetStrategy ) {
			case self::STRATEGY_ZIP:
				$tmpDir = rtrim($this->tmpDir ?: sys_get_temp_dir(), '/');
				if( !is_dir($tmpDir) ) {
					throw new \RuntimeException("Temporary Directory Not Found");
				}

				$opt = new Archive;
				$opt->setOutputStream($outputStream);
				$zip = new ZipStream('foo.zip', $opt);

				foreach( $this->streams as $name => $stream ) {
					rewind($stream);
					$tmpStream = fopen("php://temp", "r+");
					fwrite($tmpStream, $this->getBom());
					stream_copy_to_stream($stream, $tmpStream);
					rewind($tmpStream);

					$zip->addFileFromStream($name . '.csv', $tmpStream);
					fclose($tmpStream);
				}

				try {
					$zip->finish();
				}catch(OverflowException $ex) {
					throw new OutputException('Zip Overflow', $ex->getCode(), $ex);
				}

				return;
			case self::STRATEGY_CONCAT:
				fwrite($outputStream, $this->getBom());
				foreach( $this->streams as $stream ) {
					rewind($stream);
					stream_copy_to_stream($stream, $outputStream);
				}

				return;
		}

		throw new OutputException('Unsupported MultiSheet Strategy');
	}

	/**
	 * Gets delimiter.  If unset, UTF-16 and UTF-32 default to TAB "\t", everything else to COMMA ","
	 */
	public function getDelimiter() : string {
		if( $this->delimiter === null ) {
			if( stripos($this->outputEncoding, self::UTF16) === 0 || stripos($this->outputEncoding, self::UTF32) === 0 ) {
				return "\t";
			}

			return ",";
		}

		return $this->delimiter;
	}

	/**
	 * Sets delimiter. Setting to NULL triggers automatic delimiter decision based on recommended encoding rules.
	 *
	 * @param string|null $delimiter Delimiter Character. Must be a single byte.
	 */
	public function setDelimiter( ?string $delimiter ) : void {
		if( $delimiter !== null && strlen($delimiter) !== 1 ) {
			throw new \InvalidArgumentException('Delimiter must be exactly one byte');
		}

		$this->delimiter = $delimiter;
	}

	public function getEnclosure() : string {
		return $this->enclosure;
	}

	protected function getBom() : string {
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

	final protected function isLittleEndian() : bool {
		return unpack('S', "\x01\x00")[1] === 1;
	}

	/**
	 * Whether to disable the leading Byte Order Mark for the given encoding from being output.
	 */
	public function disableBom( bool $disable = true ) : void {
		$this->disableBom = $disable;
	}

}
