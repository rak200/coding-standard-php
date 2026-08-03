<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp;

use RuntimeException;

/**
 * A condition the caller should report as a coverage-floor failure.
 *
 * The twin of `FloorError` in rak200/coding-standard-ts, named the way PHP names an
 * exception rather than the way JavaScript names one. Nothing in src/ knows about exit
 * codes: `bin/coverage-floor` catches this and is the only place that does.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class FloorException extends RuntimeException {}
