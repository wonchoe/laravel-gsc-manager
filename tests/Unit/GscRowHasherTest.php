<?php

namespace Wonchoe\GscManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wonchoe\GscManager\Support\GscRowHasher;

class GscRowHasherTest extends TestCase
{
    public function test_data_state_changes_the_hash(): void
    {
        // Regression: a 'preliminary' (fresh) row and the later 'final' row for the same
        // dimensions must NOT collide, or one overwrites the other on upsert.
        $base = ['site_id' => 1, 'type' => 'web', 'aggregation_type' => 'auto', 'date' => '2026-06-24', 'query' => 'corn'];

        $final = GscRowHasher::make($base + ['data_state' => 'final']);
        $fresh = GscRowHasher::make($base + ['data_state' => 'all']);

        $this->assertNotSame($final, $fresh);
    }

    public function test_url_like_keys_are_case_sensitive_others_lowercased(): void
    {
        // page/query/url keep case (URLs are case-sensitive); everything else is normalised.
        $this->assertSame(
            GscRowHasher::make(['country' => 'USA']),
            GscRowHasher::make(['country' => 'usa']),
        );
        $this->assertNotSame(
            GscRowHasher::make(['page' => 'https://x/AB']),
            GscRowHasher::make(['page' => 'https://x/ab']),
        );
    }

    public function test_is_deterministic(): void
    {
        $parts = ['site_id' => 2, 'type' => 'news', 'date' => '2026-06-24', 'data_state' => 'final'];
        $this->assertSame(GscRowHasher::make($parts), GscRowHasher::make($parts));
    }
}
