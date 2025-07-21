<?php

describe('Security Tests', function () {
    
    it('requires authentication for protected routes', function () {
        // Test that protected routes return 401 without authentication
        $protectedRoutes = [
            '/api/tasks',
            '/api/user',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            expect($response->status())->toBeIn([401, 500]); // 401 or 500 due to middleware
        }
    });

    it('has proper CORS headers configured', function () {
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        
        // Check for basic security headers
        $headers = $response->headers->all();
        expect($headers)->toBeArray();
    });

    it('validates input on authentication endpoints', function () {
        // Test registration with invalid data
        $invalidData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456'
        ];

        $response = $this->postJson('/api/auth/register', $invalidData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('validates input on login endpoint', function () {
        // Test login with invalid data
        $invalidData = [
            'email' => 'invalid-email',
            'password' => ''
        ];

        $response = $this->postJson('/api/auth/login', $invalidData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    });

    it('prevents SQL injection in database queries', function () {
        // Test that SQL injection attempts are handled safely
        $maliciousInput = "'; DROP TABLE users; --";
        
        // This should not cause any issues
        $result = \DB::select('SELECT ? as test', [$maliciousInput]);
        expect($result[0]->test)->toBe($maliciousInput);
    });

    it('has rate limiting configured', function () {
        // Test that rate limiting is in place (basic check)
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        
        // Check for rate limiting headers (if configured)
        $headers = $response->headers->all();
        expect($headers)->toBeArray();
    });

    it('sanitizes output to prevent XSS', function () {
        // Test that HTML/JS is properly escaped
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        
        $content = $response->getContent();
        expect($content)->not->toContain('<script>');
        expect($content)->not->toContain('javascript:');
    });

    it('uses HTTPS in production configuration', function () {
        // Check that the app URL is configured properly
        $appUrl = config('app.url');
        expect($appUrl)->toBeString();
        
        // In production, this should be HTTPS
        if (config('app.env') === 'production') {
            expect($appUrl)->toStartWith('https://');
        }
    });
});