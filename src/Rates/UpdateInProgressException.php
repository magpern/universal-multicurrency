<?php
/**
 * Rate update already in progress.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Thrown when the update lock cannot be acquired.
 */
final class UpdateInProgressException extends \RuntimeException {
}
