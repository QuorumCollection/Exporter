<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;
use Quorum\Exporter\Engines\Csv\ByteOrderMark;
use Quorum\Exporter\Engines\Csv\MultiSheetStrategy;
use Quorum\Exporter\Exceptions\ExportException;
use Quorum\Exporter\Exceptions\OutputException;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream;

class CsvEngine implements EngineInterface {

	public const UTF8 = 'UTF-8';

	public const UTF16   = 'UTF-16';
	public const UTF16BE = 'UTF-16BE';
	public const UTF16LE = 'UTF-16LE';

	public const UTF32   = 'UTF-32';
	public const UTF32BE = 'UTF-32BE';
	public const UTF32LE = 'UTF-32LE';

	/** @var array<int|string, resource> */
	protected array $streams = [];

	protected string $outputEncoding;
	protected string $inputEncoding;

	protected ?string $delimiter;
	protected string $enclosure;

	protected MultiSheetStrategy $multiSheetStrategy = MultiSheetStrategy::CONCAT;

	protected bool $disableBom = false;

	protected int $autoIndex = 1;

	protected ?string $tmpDir = null;

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
	 * @param string             $outputEncoding     The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
	 * @param string|null        $delimiter          Character to use as Delimiter. Default varies based on encoding.
	 * @param string             $enclosure          Character to use as Enclosure.
	 * @param string             $inputEncoding      The encoding of the input going into the CSVs.
	 * @param bool|null          $bomEnabled         Whether to include a byte order mark. Null disables it for UTF-8 and enables it for other encodings.
	 * @param MultiSheetStrategy $multiSheetStrategy How to export multiple sheets. Defaults to concatenation.
	 */
	public function __construct(
		string $outputEncoding = self::UTF16LE,
		?string $delimiter = null,
		string $enclosure = '"',
		string $inputEncoding = self::UTF8,
		?bool $bomEnabled = null,
		MultiSheetStrategy $multiSheetStrategy = MultiSheetStrategy::CONCAT,
	) {
		$this->setDelimiter($delimiter);
		$this->setEnclosure($enclosure);
		$this->setOutputEncoding($outputEncoding);
		$this->setInputEncoding($inputEncoding);
		$this->disableBom = !($bomEnabled ?? ($outputEncoding !== self::UTF8));
		$this->multiSheetStrategy = $multiSheetStrategy;
	}

	/**
	 * Character to use as CSV value enclosure. Commonly this will be `"`
	 */
	public function setEnclosure( string $enclosure ): void {
		if( strlen($enclosure) !== 1 ) {
			throw new \InvalidArgumentException('Enclosure must be exactly one byte');
		}

		$this->enclosure = $enclosure;
	}

	protected function setOutputEncoding( string $outputEncoding ): void {
		if( !in_array($outputEncoding, mb_list_encodings(), true) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}

		$this->outputEncoding = $outputEncoding;
	}

	protected function setInputEncoding( string $inputEncoding ): void {
		if( !in_array($inputEncoding, mb_list_encodings()) ) {
			throw new \InvalidArgumentException('Invalid Encoding');
		}

		$this->inputEncoding = $inputEncoding;
	}

	/**
	 * Set the directory to check before creating a ZIP export. Defaults to `sys_get_temp_dir`.
	 */
	public function setTmpDir( string $tmpDir ): void {
		$this->tmpDir = $tmpDir;
	}

	/**
	 * Get the current multi-sheet strategy.
	 */
	public function getMultiSheetStrategy(): MultiSheetStrategy {
		return $this->multiSheetStrategy;
	}

	/**
	 * Set the multi-sheet strategy. ZIP creates an archive. CONCAT writes all sheets to one CSV file.
	 */
	public function setMultiSheetStrategy( MultiSheetStrategy $multiSheetStrategy ): void {
		$this->multiSheetStrategy = $multiSheetStrategy;
	}

	public function processSheet( DataSheet $sheet ): void {
		$outputStream = fopen("php://temp", "r+");
		if( $outputStream === false ) {
			throw new OutputException('Unable to open temporary output stream');
		}

		$outputEncoding = match( $this->outputEncoding ) {
			self::UTF16 => $this->isLittleEndian() ? self::UTF16LE : self::UTF16BE,
			self::UTF32 => $this->isLittleEndian() ? self::UTF32LE : self::UTF32BE,
			default     => $this->outputEncoding,
		};

		foreach( $sheet as $data ) {
			$mem = fopen('php://memory', 'w+');
			if( $mem === false ) {
				throw new OutputException('Unable to open temporary memory stream');
			}

			if( ($length = @fputcsv($mem, $data, $this->getDelimiter(), $this->getEnclosure())) === false || $length === 0 ) {
				fclose($mem);

				throw new ExportException('fputcsv failed');
			}

			rewind($mem);
			$line = fread($mem, $length);
			fclose($mem);
			if( $line === false ) {
				throw new ExportException('Unable to read CSV data');
			}

			$line = mb_convert_encoding($line, $outputEncoding, $this->inputEncoding);
			if( $line === false ) {
				throw new ExportException('Unable to convert CSV data');
			}

			fputs($outputStream, $line);
		}

		if( !$name = $sheet->getName() ) {
			$name = sprintf("Sheet%d", $this->autoIndex++);
		}

		$this->streams[$name] = $outputStream;
	}

	/**
	 * @param resource $outputStream A writable stream resource.
	 * @throws OutputException
	 */
	public function outputToStream( mixed $outputStream ): void {

		switch( $this->multiSheetStrategy ) {
			case MultiSheetStrategy::ZIP:
				$tmpDir = rtrim($this->tmpDir ?: sys_get_temp_dir(), '/');
				if( !is_dir($tmpDir) ) {
					throw new \RuntimeException("Temporary Directory Not Found");
				}

				$zip = new ZipStream(outputStream: $outputStream, sendHttpHeaders: false);

				foreach( $this->streams as $name => $stream ) {
					rewind($stream);
					$tmpStream = fopen("php://temp", "r+");
					if( $tmpStream === false ) {
						throw new OutputException('Unable to open temporary output stream');
					}

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
			case MultiSheetStrategy::CONCAT:
				fwrite($outputStream, $this->getBom());
				foreach( $this->streams as $stream ) {
					rewind($stream);
					stream_copy_to_stream($stream, $outputStream);
				}

				return;
		}
	}

	/**
	 * Gets delimiter.  If unset, UTF-16 and UTF-32 default to TAB "\t", everything else to COMMA ","
	 */
	public function getDelimiter(): string {
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
	public function setDelimiter( ?string $delimiter ): void {
		if( $delimiter !== null && strlen($delimiter) !== 1 ) {
			throw new \InvalidArgumentException('Delimiter must be exactly one byte');
		}

		$this->delimiter = $delimiter;
	}

	/**
	 * Get the current character used for enclosure.
	 */
	public function getEnclosure(): string {
		return $this->enclosure;
	}

	protected function getBom(): string {
		if( $this->disableBom ) {
			return '';
		}

		$bom = match( $this->outputEncoding ) {
			self::UTF8    => ByteOrderMark::UTF8,
			self::UTF16   => $this->isLittleEndian() ? ByteOrderMark::UTF16LE : ByteOrderMark::UTF16BE,
			self::UTF16BE => ByteOrderMark::UTF16BE,
			self::UTF16LE => ByteOrderMark::UTF16LE,
			self::UTF32   => $this->isLittleEndian() ? ByteOrderMark::UTF32LE : ByteOrderMark::UTF32BE,
			self::UTF32BE => ByteOrderMark::UTF32BE,
			self::UTF32LE => ByteOrderMark::UTF32LE,
			default       => null,
		};

		return $bom === null ? '' : $bom->value;
	}

	final protected function isLittleEndian(): bool {
		return unpack('S', "\x01\x00") === [ 1 => 1 ];
	}

	/**
	 * Enable or disable the leading byte order mark. By default it is disabled for UTF-8 and enabled for other encodings.
	 */
	public function disableBom( bool $disable = true ): void {
		$this->disableBom = $disable;
	}

}
