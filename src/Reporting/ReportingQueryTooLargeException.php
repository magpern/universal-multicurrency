<?php
/**
 * Thrown when a reporting query exceeds safe bounds.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use RuntimeException;

/**
 * Thrown when a reporting query exceeds safe bounds.
 */
final class ReportingQueryTooLargeException extends RuntimeException {
}
