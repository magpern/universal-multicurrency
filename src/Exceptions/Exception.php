<?php

/**
 * Marker interface for all domain exceptions.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Exceptions;

/**
 * Implemented by every exception this plugin's domain layer throws, so callers
 * can catch all of them with a single `catch ( \UMC\Exceptions\Exception )`
 * while each concrete class still extends the most fitting SPL exception type.
 */
interface Exception {

}
