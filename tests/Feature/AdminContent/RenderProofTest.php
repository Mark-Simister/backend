<?php

namespace Tests\Feature\AdminContent;

use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\ReviewFingerprints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\Feature\AdminContent\Concerns\ReleasesPublicReviews;
use Tests\TestCase;

/**
 * Render proof (design record section 6.6): stored {} vs [] variants must produce
 * byte-identical page HTML and JSON-LD through the public renderer - the collapse the
 * canonical form relies on is real at the output. Since Sprint B the renderer receives
 * only the verified projection, so this also proves the {} variant verifies against a
 * binding made for the [] variant. Rendering is enabled for this test only.
 */
class RenderProofTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;
    use ReleasesPublicReviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        config(['reviews.rendering_enabled' => true, 'reviews.cache_ttl' => 0, 'reviews.indexable' => false]);
    }

    public function test_empty_object_and_empty_list_variants_render_byte_identically(): void
    {
        $payload = $this->ingest($this->reviewRow('pr_render_proof'));

        $page = json_decode(DB::table('published_review_payloads')->where('id', $payload->id)->value('review_page_json'), true);
        $page['buyer_fit'] = [];
        $page['pros_cons']['notes'] = [];
        $schema = ['@context' => 'https://schema.org', '@graph' => [['@type' => 'Product', 'name' => 'Test Feeder', 'additionalProperty' => []]]];

        $listVariant = [
            'review_page_json' => json_encode($page),
            'schema_json_ld' => json_encode($schema),
        ];
        $objectVariant = [
            'review_page_json' => str_replace(['"buyer_fit":[]', '"notes":[]'], ['"buyer_fit":{}', '"notes":{}'], $listVariant['review_page_json']),
            'schema_json_ld' => str_replace('"additionalProperty":[]', '"additionalProperty":{}', $listVariant['schema_json_ld']),
        ];
        $this->assertNotSame($listVariant, $objectVariant, 'the stored text really differs');
        $this->assertStringContainsString('"buyer_fit":{}', $objectVariant['review_page_json']);

        // Release the [] variant (Sprint B: only a verified release renders). The {} variant
        // written afterwards must then verify against THAT binding, because it decodes to
        // the identical projection - the render proof and the fingerprint proof together.
        DB::table('published_review_payloads')->where('id', $payload->id)->update($listVariant);
        $this->releaseForPublic($payload);
        $listHtml = $this->get('http://example.test/review/' . $payload->review_slug)->assertOk()->getContent();
        $listFp = ReviewFingerprints::content($payload->fresh(), FingerprintScheme::P1);

        DB::table('published_review_payloads')->where('id', $payload->id)->update($objectVariant);
        $objectHtml = $this->get('http://example.test/review/' . $payload->review_slug)->assertOk()->getContent();
        $objectFp = ReviewFingerprints::content($payload->fresh(), FingerprintScheme::P1);

        $this->assertSame($listHtml, $objectHtml, 'page HTML must be byte-identical');
        $this->assertSame($this->jsonLd($listHtml), $this->jsonLd($objectHtml), 'JSON-LD must be byte-identical');
        $this->assertNotSame('', $this->jsonLd($listHtml));
        $this->assertSame($listFp, $objectFp, 'and the fingerprint agrees with the output');
    }

    private function jsonLd(string $html): string
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return implode("\n", $m[1]);
    }
}
