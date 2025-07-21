<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Database Connection Test', function () {
    
    it('can connect to test database', function () {
        // Test basic database connection
        $result = DB::select('SELECT 1 as test');
        expect($result[0]->test)->toBe(1);
    });

    it('uses in-memory sqlite for testing', function () {
        $connection = DB::connection()->getName();
        expect($connection)->toBe('sqlite');
        
        $database = DB::connection()->getDatabaseName();
        expect($database)->toBe(':memory:');
    });
});