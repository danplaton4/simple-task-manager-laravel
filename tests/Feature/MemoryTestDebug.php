<?php

describe('Memory Test Debug', function () {
    
    it('can run without database', function () {
        expect(true)->toBeTrue();
    });

    it('can access config', function () {
        $name = config('app.name');
        expect($name)->toBe('Task Management App');
    });
});