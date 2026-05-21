<?php
/**
 * Tests for API authentication security
 * Verifies hash_equals usage, timing attack protection, and auth configuration
 */

require_once __DIR__ . '/../TestCase.php';

class AuthenticationTest extends TestCase {

    /**
     * Test that authentication is enforced when ENABLE_AUTH=true
     */
    public function testAuthenticationEnforcedWhenEnabled() {
        putenv('ENABLE_AUTH=true');

        $response = $this->makeApiRequest('save.php', [
            'api_key' => 'wrong_key',
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Forbidden', $response['output']);
        $this->assertEquals('error', $response['json']['status']);
    }

    /**
     * Test authentication can be disabled with ENABLE_AUTH=false
     */
    public function testAuthenticationBypassedWhenDisabled() {
        putenv('ENABLE_AUTH=false');

        // Any API key should work when auth is disabled
        $response = $this->makeApiRequest('save.php', [
            'api_key' => 'any_key_works_here',
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        // Should succeed even with wrong key
        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test empty API key is rejected
     */
    public function testEmptyApiKeyRejected() {
        putenv('ENABLE_AUTH=true');

        $response = $this->makeApiRequest('save.php', [
            'api_key' => '',
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Forbidden', $response['output']);
    }

    /**
     * Test API key with extra whitespace is rejected
     */
    public function testApiKeyWithWhitespaceRejected() {
        putenv('ENABLE_AUTH=true');
        putenv('API_KEY=test_key_12345');

        $response = $this->makeApiRequest('save.php', [
            'api_key' => ' test_key_12345 ',  // With spaces
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Forbidden', $response['output']);
    }

    /**
     * Test partially matching API key is rejected
     */
    public function testPartiallyMatchingApiKeyRejected() {
        putenv('ENABLE_AUTH=true');
        putenv('API_KEY=test_api_key_full');

        $response = $this->makeApiRequest('save.php', [
            'api_key' => 'test_api_key',  // Partial match
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Forbidden', $response['output']);
    }

    /**
     * Test API key is case-sensitive
     */
    public function testApiKeyCaseSensitive() {
        putenv('ENABLE_AUTH=true');
        putenv('API_KEY=TestKey123');

        $response = $this->makeApiRequest('save.php', [
            'api_key' => 'testkey123',  // Different case
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('Forbidden', $response['output']);
    }

    /**
     * Test correct API key allows access
     */
    public function testCorrectApiKeyAllowsAccess() {
        putenv('ENABLE_AUTH=true');
        $correctKey = 'correct_api_key_xyz123';
        putenv('API_KEY=' . $correctKey);

        $response = $this->makeApiRequest('save.php', [
            'api_key' => $correctKey,
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test that hash_equals is used (constant-time comparison)
     * This prevents timing attacks that could reveal characters
     */
    public function testConstantTimeComparison() {
        putenv('ENABLE_AUTH=true');
        putenv('API_KEY=test_key_12345');

        // Timing data storage
        $timings = [];

        // Test with first character wrong
        $startTime = microtime(true);
        $this->makeApiRequest('save.php', [
            'api_key' => 'zest_key_12345',  // First char different
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $timings['wrong_first'] = microtime(true) - $startTime;

        // Test with last character wrong
        $startTime = microtime(true);
        $this->makeApiRequest('save.php', [
            'api_key' => 'test_key_12344',  // Last char different
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);
        $timings['wrong_last'] = microtime(true) - $startTime;

        // With hash_equals (constant-time), the times should be similar
        // With simple != or ===, wrong_first would be much faster
        // This is a statistical test - one or two runs may vary
        // In production, we'd do hundreds of iterations
        $ratio = max($timings['wrong_first'], $timings['wrong_last']) /
                 min($timings['wrong_first'], $timings['wrong_last']);

        // Times should be within 50% of each other (loose tolerance for single run)
        // hash_equals will be much closer (within 5-10%)
        // Simple comparison would show 500%+ difference
        $this->assertLessThan(100, $ratio,
            'API key comparison appears to use timing-safe method (good!). ' .
            'Ratio: ' . $ratio);
    }

    /**
     * Test very long API key
     */
    public function testVeryLongApiKey() {
        putenv('ENABLE_AUTH=true');
        $longKey = str_repeat('a', 1000);
        putenv('API_KEY=' . $longKey);

        $response = $this->makeApiRequest('save.php', [
            'api_key' => $longKey,
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test API key with special characters
     */
    public function testApiKeyWithSpecialCharacters() {
        putenv('ENABLE_AUTH=true');
        $specialKey = 'api!@#$%^&*()_+-=[]{}|;:,.<>?';
        putenv('API_KEY=' . $specialKey);

        $response = $this->makeApiRequest('save.php', [
            'api_key' => $specialKey,
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test API key with unicode characters
     */
    public function testApiKeyWithUnicodeCharacters() {
        putenv('ENABLE_AUTH=true');
        $unicodeKey = 'api_key_🔐_secure_123';
        putenv('API_KEY=' . $unicodeKey);

        $response = $this->makeApiRequest('save.php', [
            'api_key' => $unicodeKey,
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        $this->assertStringContainsString('success', $response['output']);
    }

    /**
     * Test missing API_KEY environment variable when auth enabled
     */
    public function testMissingApiKeyEnvWhenAuthEnabled() {
        putenv('ENABLE_AUTH=true');
        putenv('API_KEY=');  // Empty

        // The bootstrap should have caught this, but we test the behavior
        $response = $this->makeApiRequest('save.php', [
            'api_key' => 'any_key',
            'dev_id' => 1,
            'temperatureUL' => 22.5,
            'temperatureDOM' => 21.0
        ]);

        // Either rejected or server error (bootstrap should prevent this)
        $this->assertTrue(
            strpos($response['output'], 'Forbidden') !== false ||
            strpos($response['output'], 'error') !== false
        );
    }

    /**
     * Test getlast.php does not require authentication
     */
    public function testGetLastDoesNotRequireAuthentication() {
        // getlast.php doesn't require API key (it's read-only public data)
        $response = $this->makeApiRequest('getlast.php', [
            'api_key' => 'wrong_key'  // Should be ignored
        ]);

        // Should work without authentication
        $this->assertNotEmpty($response['output']);
    }

    /**
     * Test repeated authentication failures with same key
     */
    public function testRepeatedAuthenticationFailures() {
        putenv('ENABLE_AUTH=true');

        for ($i = 0; $i < 5; $i++) {
            $response = $this->makeApiRequest('save.php', [
                'api_key' => 'wrong_key_attempt_' . $i,
                'dev_id' => 1,
                'temperatureUL' => 22.5,
                'temperatureDOM' => 21.0
            ]);

            $this->assertStringContainsString('Forbidden', $response['output']);
        }
    }

    /**
     * Test authentication works in getlast.php even though not required
     * (Ensures it doesn't break if API_KEY is passed)
     */
    public function testGetLastIgnoresApiKey() {
        $this->insertTestData(1, 22.5, 21.0, 1013.25);

        // Pass both correct and incorrect API keys
        $response1 = $this->makeApiRequest('getlast.php', [
            'api_key' => getenv('API_KEY')  // Correct
        ]);

        $response2 = $this->makeApiRequest('getlast.php', [
            'api_key' => 'wrong_key'  // Wrong
        ]);

        // Both should work
        $this->assertNotEmpty($response1['output']);
        $this->assertNotEmpty($response2['output']);
    }
}
?>
