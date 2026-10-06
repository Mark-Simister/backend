<?php

namespace App\Support\Reviews;

use RuntimeException;

/**
 * PUBLIC_REVIEW_VIDEO_AUTHORITY holds a value other than unset, "payload" or "admin".
 * There is no active render scheme: verification fails closed and supported ingestion
 * refuses to run, so a configuration typo can never cause mass demotion.
 */
class VideoAuthorityConfigurationException extends RuntimeException
{
}
