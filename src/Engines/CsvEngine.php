<?php

namespace Quorum\Exporter\Engines;

use Quorum\Exporter\DataSheet;
use Quorum\Exporter\EngineInterface;
use Quorum\Exporter\Engines\Csv\ByteOrderMark;
use Quorum\Exporter\Engines\Csv\Encoding;
use Quorum\Exporter\Engines\Csv\MultiSheetStrategy;
use Quorum\Exporter\Exceptions\ExportException;
use Quorum\Exporter\Exceptions\OutputException;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream;

class CsvEngine implements EngineInterface {

	/** @var array<int|string, resource> */
	protected array $streams = [];

	protected Encoding $outputEncoding;
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
	 * @param Encoding           $outputEncoding     The encoding to output. Defaults to UTF-16LE as it is by far the best supported by Excel
	 * @param string|null        $delimiter          A single ASCII byte. The default varies based on encoding.
	 * @param string             $enclosure          A single ASCII byte used to enclose fields.
	 * @param bool|null          $bomEnabled         Whether to include a byte order mark. Null disables it for UTF-8 and enables it for other encodings.
	 * @param MultiSheetStrategy $multiSheetStrategy How to export multiple sheets. Defaults to concatenation.
	 */
	public function __construct(
		Encoding $outputEncoding = Encoding::UTF16LE,
		?string $delimiter = null,
		string $enclosure = '"',
		?bool $bomEnabled = null,
		MultiSheetStrategy $multiSheetStrategy = MultiSheetStrategy::CONCAT,
	) {
		$this->setDelimiter($delimiter);
		$this->setEnclosure($enclosure);
		$this->setOutputEncoding($outputEncoding);
		$this->disableBom = !($bomEnabled ?? ($outputEncoding !== Encoding::UTF8));
		$this->multiSheetStrategy = $multiSheetStrategy;
	}

	/**
	 * Character to use as CSV value enclosure. Commonly this will be `"`
	 */
	public function setEnclosure( string $enclosure ): void {
		if( strlen($enclosure) !== 1 || ord($enclosure) > 0x7F ) {
			throw new \InvalidArgumentException('Enclosure must be a single ASCII byte');
		}

		$this->enclosure = $enclosure;
	}

	protected function setOutputEncoding( Encoding $outputEncoding ): void {
		$this->outputEncoding = $outputEncoding;
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
			Encoding::UTF16 => $this->isLittleEndian() ? Encoding::UTF16LE : Encoding::UTF16BE,
			Encoding::UTF32 => $this->isLittleEndian() ? Encoding::UTF32LE : Encoding::UTF32BE,
			default         => $this->outputEncoding,
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

			$line = mb_convert_encoding($line, $outputEncoding->value, 'UTF-8');
			// @phpstan-ignore identical.alwaysFalse (Handle the documented failure return.)
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
			if( str_starts_with($this->outputEncoding->value, Encoding::UTF16->value) || str_starts_with($this->outputEncoding->value, Encoding::UTF32->value) ) {
				return "\t";
			}

			return ",";
		}

		return $this->delimiter;
	}

	/**
	 * Sets delimiter. Setting to NULL triggers automatic delimiter decision based on recommended encoding rules.
	 *
	 * @param string|null $delimiter Delimiter character. Must be a single ASCII byte.
	 */
	public function setDelimiter( ?string $delimiter ): void {
		if( $delimiter !== null && (strlen($delimiter) !== 1 || ord($delimiter) > 0x7F) ) {
			throw new \InvalidArgumentException('Delimiter must be a single ASCII byte');
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
			Encoding::UTF8    => ByteOrderMark::UTF8,
			Encoding::UTF16   => $this->isLittleEndian() ? ByteOrderMark::UTF16LE : ByteOrderMark::UTF16BE,
			Encoding::UTF16BE => ByteOrderMark::UTF16BE,
			Encoding::UTF16LE => ByteOrderMark::UTF16LE,
			Encoding::UTF32   => $this->isLittleEndian() ? ByteOrderMark::UTF32LE : ByteOrderMark::UTF32BE,
			Encoding::UTF32BE => ByteOrderMark::UTF32BE,
			Encoding::UTF32LE => ByteOrderMark::UTF32LE,
		};

		return $bom->value;
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
