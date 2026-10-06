<?php

namespace Tests\Unit\AdminContent;

use App\Support\Reviews\CanonicalJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * br-render-canonical-v1 fixed test vectors (design record section 6.6), executed against
 * real PHP json_decode/json_encode. Each input is decoded exactly as Laravel's `array`
 * cast decodes a stored JSON column (json_decode($text, true)), then canonicalised.
 *
 * These are the executable proof the design deferred to Sprint A (section 6.6, evidence
 * limit): if PHP behaved differently from the frozen assumptions, these would fail.
 */
class CanonicalJsonTest extends TestCase
{
    private static function fp(string $storedJson): ?string
    {
        return CanonicalJson::fingerprint(json_decode($storedJson, true, 512, JSON_THROW_ON_ERROR));
    }

    public static function collidingVectors(): array
    {
        return [
            'compact vs pretty-printed' => ['{"a":1,"b":[1,2],"c":{"d":"x"}}', "{\n  \"a\": 1,\n  \"b\": [ 1, 2 ],\n  \"c\": { \"d\": \"x\" }\n}"],
            'escaped \\u00e9 vs literal' => ['{"t":"café"}', '{"t":"café"}'],
            'escaped \\/ vs /' => ['{"u":"https:\/\/example.test\/a"}', '{"u":"https://example.test/a"}'],
            '4.70 vs 4.7' => ['{"s":4.70}', '{"s":4.7}'],
            'empty {} vs []' => ['{"p":{}}', '{"p":[]}'],
            'sequential numeric-key object vs list' => ['{"l":{"0":"a","1":"b"}}', '{"l":["a","b"]}'],
            'duplicate key vs last-wins value' => ['{"k":"first","k":"second"}', '{"k":"second"}'],
        ];
    }

    public static function differingVectors(): array
    {
        return [
            'swapped member order' => ['{"a":1,"b":2}', '{"b":2,"a":1}'],
            'swapped array order' => ['{"l":["a","b"]}', '{"l":["b","a"]}'],
            'null vs absent' => ['{"a":1,"b":null}', '{"a":1}'],
            '4 vs 4.0' => ['{"s":4}', '{"s":4.0}'],
            'NFC vs NFD text' => ['{"t":"café"}', '{"t":"café"}'],
            'string vs number' => ['{"s":"4.5"}', '{"s":4.5}'],
            'different rendered text' => ['{"t":"Quiet motor"}', '{"t":"Quiet  motor"}'],
        ];
    }

    #[DataProvider('collidingVectors')]
    public function test_vectors_that_must_collide(string $a, string $b): void
    {
        $this->assertNotNull(self::fp($a));
        $this->assertSame(self::fp($a), self::fp($b));
    }

    #[DataProvider('differingVectors')]
    public function test_vectors_that_must_differ(string $a, string $b): void
    {
        $this->assertNotNull(self::fp($a));
        $this->assertNotNull(self::fp($b));
        $this->assertNotSame(self::fp($a), self::fp($b));
    }

    public function test_the_canonical_text_is_unescaped_compact_and_keeps_the_zero_fraction(): void
    {
        $this->assertSame(
            '{"u":"https://example.test/a","t":"café","f":4.0,"i":4,"d":0.1,"e":[],"n":null}',
            CanonicalJson::encode(['u' => 'https://example.test/a', 't' => 'café', 'f' => 4.0, 'i' => 4, 'd' => 0.1, 'e' => [], 'n' => null])
        );
    }

    public function test_the_fingerprint_is_lowercase_hex_sha256_of_the_canonical_text(): void
    {
        $value = ['a' => [1, 2.5, 'x']];

        $this->assertSame(hash('sha256', '{"a":[1,2.5,"x"]}'), CanonicalJson::fingerprint($value));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', CanonicalJson::fingerprint($value));
    }

    public function test_serialize_precision_is_pinned_and_then_restored(): void
    {
        $previous = ini_get('serialize_precision');

        try {
            ini_set('serialize_precision', '17');
            $under17 = CanonicalJson::fingerprint(['x' => 0.1, 'y' => 4.7]);
            $this->assertSame('17', ini_get('serialize_precision'), 'the caller setting must be restored');

            ini_set('serialize_precision', '-1');
            $underDefault = CanonicalJson::fingerprint(['x' => 0.1, 'y' => 4.7]);
        } finally {
            ini_set('serialize_precision', (string) $previous);
        }

        $this->assertSame($underDefault, $under17);
        $this->assertSame(hash('sha256', '{"x":0.1,"y":4.7}'), $under17);
    }

    public function test_unencodable_values_produce_no_fingerprint(): void
    {
        $this->assertNull(CanonicalJson::fingerprint(['t' => "bad \xB1\x31 utf-8"]));
        $this->assertNull(CanonicalJson::fingerprint(['f' => NAN]));
        $this->assertNull(CanonicalJson::fingerprint(['f' => INF]));

        $deep = [];
        $cursor = &$deep;
        for ($i = 0; $i < 600; $i++) {
            $cursor['n'] = [];
            $cursor = &$cursor['n'];
        }
        unset($cursor);
        $this->assertNull(CanonicalJson::fingerprint($deep), 'exceeding the depth limit fails closed');
    }

    public function test_the_version_identifier(): void
    {
        $this->assertSame('br-render-canonical-v1', CanonicalJson::VERSION);
    }
}
