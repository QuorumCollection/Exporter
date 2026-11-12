<?php

namespace Quorum\Exporter\Engines\Csv;

/**
 * Character encodings supported by the CSV engine.
 */
enum Encoding: string {

	case UTF8    = 'UTF-8';
	case UTF16BE = 'UTF-16BE';
	case UTF16LE = 'UTF-16LE';
	case UTF32BE = 'UTF-32BE';
	case UTF32LE = 'UTF-32LE';

}
