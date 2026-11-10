<?php

namespace Quorum\Exporter\Engines\Csv;

/**
 * Character encodings supported by the CSV engine. Generic UTF-16 and UTF-32 output uses native byte order.
 */
enum Encoding: string {

	case UTF8    = 'UTF-8';
	case UTF16   = 'UTF-16';
	case UTF16BE = 'UTF-16BE';
	case UTF16LE = 'UTF-16LE';
	case UTF32   = 'UTF-32';
	case UTF32BE = 'UTF-32BE';
	case UTF32LE = 'UTF-32LE';

}
