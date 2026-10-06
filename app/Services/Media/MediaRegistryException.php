<?php

namespace App\Services\Media;

use DomainException;

/** An expected, user-facing refusal from the media registry. Nothing was written. */
class MediaRegistryException extends DomainException
{
}
