<?php

namespace App\Support\Reviews;

use RuntimeException;

/**
 * A render projection could not be built (invalid stored JSON, a partially loaded row,
 * an unknown scheme). Callers treat it as "no fingerprint": fail closed.
 */
class ProjectionException extends RuntimeException
{
}
