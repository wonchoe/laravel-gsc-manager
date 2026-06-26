<?php

namespace Wonchoe\GscManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wonchoe\GscManager\Support\GscSafeJson;

class GscSafeJsonTest extends TestCase
{
    public function test_redacts_top_level_and_nested_secrets(): void
    {
        $payload = [
            'client_email' => 'sa@project.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY-----abc-----END PRIVATE KEY-----',
            'private_key_id' => 'kid123',
            'nested' => ['refresh_token' => 'rt', 'access_token' => 'at', 'client_secret' => 'cs'],
        ];

        $out = GscSafeJson::redact($payload);

        $this->assertSame('[redacted]', $out['private_key']);
        $this->assertSame('[redacted]', $out['private_key_id']);
        $this->assertSame('[redacted]', $out['nested']['refresh_token']);
        $this->assertSame('[redacted]', $out['nested']['access_token']);
        $this->assertSame('[redacted]', $out['nested']['client_secret']);
        // non-secret fields are preserved
        $this->assertSame('sa@project.iam.gserviceaccount.com', $out['client_email']);
    }
}
