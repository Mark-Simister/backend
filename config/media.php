<?php

/*
|--------------------------------------------------------------------------
| Admin Content & Media Operations - media registry
|--------------------------------------------------------------------------
| Implements section 7 of BeastieRated-ADMIN-CONTENT-MEDIA-DESIGN-RECORD-2026-10-06.md.
|
| Videos are held by provider reference only; Laravel never hosts video binaries.
| Embed URLs are NEVER stored or accepted from input: they are built on the server
| from an allow-listed provider template plus the provider asset id.
|
| Managed images and audio live on the dedicated `media` disk (config/filesystems.php),
| never in the checkout's public/ directory.
*/

return [

    // The filesystem disk that holds BeastieRated-managed images and audio.
    'disk' => 'media',

    // External providers: the allowlist, the server-side embed template and the
    // accepted asset-id shape. A provider not listed here cannot be registered.
    'providers' => [
        'vimeo' => [
            'embed' => 'https://player.vimeo.com/video/{id}',
            'id_pattern' => '/^[0-9]{1,20}$/',
        ],
        'youtube' => [
            'embed' => 'https://www.youtube-nocookie.com/embed/{id}',
            'id_pattern' => '/^[A-Za-z0-9_-]{11}$/',
        ],
    ],

    // Managed uploads: accepted extensions and maximum size per kind.
    //
    // Audio is also checked by CONTENT: the type detected from the file's bytes by the
    // runtime's fileinfo extension must be one listed for the file's own extension, so
    // an extension/content mismatch is refused. Only the audio types each supported
    // format is detected as are listed (an MP4 container detected as video/mp4 is not
    // audio and is refused). The detected type is what the registry stores.
    'managed' => [
        'image' => ['mimes' => ['jpg', 'jpeg', 'png', 'webp'], 'max_kb' => 8192],
        'audio' => [
            'mimes' => ['mp3', 'm4a', 'ogg', 'wav'],
            'max_kb' => 20480,
            'content_types' => [
                'mp3' => ['audio/mpeg'],
                'm4a' => ['audio/x-m4a', 'audio/mp4'],
                'ogg' => ['audio/ogg'],
                'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'],
            ],
        ],
    ],

    // The role list, per owner type. `kinds` are the media kinds a role accepts;
    // `single` roles hold at most one active attachment (enforced by active_slot).
    'roles' => [
        'review' => [
            'video' => ['kinds' => ['video'], 'single' => true],
            'poster' => ['kinds' => ['image'], 'single' => true],
            'card_thumbnail' => ['kinds' => ['image'], 'single' => true],
        ],
        'channel' => [
            'intro_video' => ['kinds' => ['video'], 'single' => true],
            'hero_artwork' => ['kinds' => ['image'], 'single' => true],
            'card_image' => ['kinds' => ['image'], 'single' => true],
        ],
        'character' => [
            'intro_video' => ['kinds' => ['video'], 'single' => true],
            'portrait' => ['kinds' => ['image'], 'single' => true],
            'card_thumbnail' => ['kinds' => ['image'], 'single' => true],
            'dossier_image' => ['kinds' => ['image'], 'single' => true],
            'character_song' => ['kinds' => ['audio'], 'single' => true],
            'background_tune' => ['kinds' => ['audio'], 'single' => true],
            'voice_sample' => ['kinds' => ['audio'], 'single' => true],
            'signature_song' => ['kinds' => ['audio'], 'single' => false],
        ],
    ],

];
