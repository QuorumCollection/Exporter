<?php

namespace Quorum\Exporter\Engines\Csv;

/**
 * Byte order marks used by CSV output encodings.
 */
enum ByteOrderMark: string {

	/**
	 * UTF-8 byte order mark: EF BB BF.
	 */
	case UTF8    = "\xEF\xBB\xBF";
	case UTF16BE = "\xFE\xFF";
	case UTF16LE = "\xFF\xFE";
	case UTF32BE = "\0\0\xFE\xFF";
	case UTF32LE = "\xFF\xFE\0\0";

}
