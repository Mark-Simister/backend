<?php

namespace Tests\Feature\AdminContent;

use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\VideoAuthority;
use App\Support\Reviews\VideoAuthorityConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The fail-closed video-authority resolver (design record section 8): unset -> Mode P,
 * exactly "payload" -> Mode P, exactly "admin" -> Mode A, anything else invalid with NO
 * active scheme. Nothing silently defaults to payload.
 */
class VideoAuthorityTest extends TestCase
{
    public function test_unset_resolves_to_payload_authority(): void
    {
        config(['reviews.video_authority' => null]);

        $authority = VideoAuthority::resolve();
        $this->assertTrue($authority->isValid());
        $this->assertSame(VideoAuthority::MODE_PAYLOAD, $authority->mode);
        $this->assertSame(FingerprintScheme::P1, $authority->contentScheme());
    }

    public function test_exact_payload_resolves_to_p1(): void
    {
        config(['reviews.video_authority' => 'payload']);

        $this->assertSame(FingerprintScheme::P1, VideoAuthority::resolve()->contentSchemeOrFail());
    }

    public function test_exact_admin_resolves_to_a1(): void
    {
        config(['reviews.video_authority' => 'admin']);

        $authority = VideoAuthority::resolve();
        $this->assertSame(VideoAuthority::MODE_ADMIN, $authority->mode);
        $this->assertSame(FingerprintScheme::A1, $authority->contentSchemeOrFail());
    }

    public static function invalidValues(): array
    {
        return [
            'typo' => ['admn'],
            'other case' => ['Admin'],
            'upper case payload' => ['PAYLOAD'],
            'trailing space' => ['payload '],
            'empty string' => [''],
            'boolean true' => [true],
            'boolean false' => [false],
            'number' => [1],
            'array' => [['payload']],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_any_other_value_is_invalid_and_has_no_scheme(mixed $value): void
    {
        config(['reviews.video_authority' => $value]);

        $authority = VideoAuthority::resolve();
        $this->assertFalse($authority->isValid());
        $this->assertNull($authority->mode);
        $this->assertNull($authority->contentScheme());

        $this->expectException(VideoAuthorityConfigurationException::class);
        $authority->contentSchemeOrFail();
    }

    public function test_the_configuration_key_reads_the_documented_environment_variable(): void
    {
        $source = file_get_contents(config_path('reviews.php'));

        $this->assertStringContainsString("'video_authority' => env('PUBLIC_REVIEW_VIDEO_AUTHORITY')", $source,
            'the key must have no default: unset must stay null so the resolver, not config, decides');
    }

    public function test_scheme_identifiers(): void
    {
        $this->assertSame('br-render-canonical-v1:P1', FingerprintScheme::P1);
        $this->assertSame('br-render-canonical-v1:A1', FingerprintScheme::A1);
        $this->assertSame('br-render-canonical-v1:M1', FingerprintScheme::M1);
        $this->assertTrue(FingerprintScheme::isContentScheme(FingerprintScheme::P1));
        $this->assertFalse(FingerprintScheme::isContentScheme(FingerprintScheme::M1));
        $this->assertFalse(FingerprintScheme::isContentScheme(null));
    }
}
