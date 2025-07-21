<?php

describe('Simple Integration Test', function () {
    
    it('can run basic test without database', function () {
        expect(true)->toBeTrue();
    });

    it('can access application config', function () {
        $appName = config('app.name');
        expect($appName)->toBe('Task Management App');
    });

    it('can make basic HTTP request', function () {
        $response = $this->get('/');
        $response->assertStatus(200);
    });
});