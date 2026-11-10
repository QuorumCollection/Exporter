<?php

namespace Quorum\Exporter\Engines\Csv;

/**
 * Strategies for exporting multiple CSV sheets.
 */
enum MultiSheetStrategy {

	/**
	 * Write all sheets to a single CSV file.
	 */
	case CONCAT;

	/**
	 * Write each sheet to a separate CSV file inside a ZIP archive.
	 */
	case ZIP;

}
