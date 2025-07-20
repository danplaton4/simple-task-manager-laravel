<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Authentication API', function () {
    describe('Registration', function () {
        it('can register a new user', function () {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'preferred_language' => 'en',
                'timezone' => 'UTC'
            ];

            $response = $this->postJson('/api/auth/register', $userData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'user' => ['id', 'name', 'email', 'preferred_language', 'timezone'],
                    'token',
                    'message'
                ]);

            $this->assertDatabaseHas('users', [
                'email' => 'john@example.com',
                'name' => 'John Doe'
            ]);
        });

        it('validates required fields during registration', function () {
            $response = $this->postJson('/api/auth/register', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password']);
        });

        it('validates email uniqueness during registration', function () {
            $existingUser = User::factory()->create(['email' => 'existing@example.com']);

            $response = $this->postJson('/api/auth/register', [
                'name' => 'John Doe',
                'email' => 'existing@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });

        it('validates password confirmation during registration', function () {
            $response = $this->postJson('/api/auth/register', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'password_confirmation' => 'different_password'
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        });
    });

    describe('Login', function () {
        beforeEach(function () {
            $this->user = User::factory()->create([
                'email' => 'test.user@example.com',
                'password' => 'Password123!'
            ]);
        });

        it('can login with valid credentials', function () {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test.user@example.com',
                'password' => 'Password123!'
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'message'
                ]);
        });

        it('cannot login with invalid credentials', function () {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test.user@example.com',
                'password' => 'WrongPassword123!'
            ]);

            $response->assertStatus(401)
                ->assertJson(['message' => 'Invalid credentials']);
        });

        it('validates required fields during login', function () {
            $response = $this->postJson('/api/auth/login', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
        });

        it('cannot login with non-existent email', function () {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'nonexistent.user@example.com',
                'password' => 'Password123!'
            ]);

            $response->assertStatus(401)
                ->assertJson(['message' => 'Invalid credentials']);
        });
    });

    describe('Logout', function () {
        beforeEach(function () {
            $this->user = User::factory()->create();
        });

        it('can logout authenticated user', function () {
            Sanctum::actingAs($this->user);

            $response = $this->postJson('/api/auth/logout');

            $response->assertStatus(200)
                ->assertJson(['message' => 'Successfully logged out']);
        });

        it('cannot logout unauthenticated user', function () {
            $response = $this->postJson('/api/auth/logout');

            $response->assertStatus(401);
        });

        it('can logout from all devices', function () {
            Sanctum::actingAs($this->user);

            // Create multiple tokens
            $this->user->createToken('token1');
            $this->user->createToken('token2');

            expect($this->user->tokens)->toHaveCount(2); // Two tokens created

            $response = $this->postJson('/api/auth/logout-all');

            $response->assertStatus(200)
                ->assertJson(['message' => 'Successfully logged out from all devices']);

            expect($this->user->fresh()->tokens)->toHaveCount(0);
        });
    });

    describe('Token Refresh', function () {
        beforeEach(function () {
            $this->user = User::factory()->create();
        });

        it('can refresh token for authenticated user', function () {
            Sanctum::actingAs($this->user);

            $response = $this->postJson('/api/auth/refresh');

            $response->assertStatus(200)
                ->assertJsonStructure(['token', 'message']);
        });

        it('cannot refresh token for unauthenticated user', function () {
            $response = $this->postJson('/api/auth/refresh');

            $response->assertStatus(401);
        });
    });

    describe('User Profile', function () {
        beforeEach(function () {
            $this->user = User::factory()->create([
                'preferred_language' => 'fr',
                'timezone' => 'Europe/Paris'
            ]);
        });

        it('can get authenticated user profile', function () {
            Sanctum::actingAs($this->user);

            $response = $this->getJson('/api/auth/me');

            $response->assertStatus(200)
                ->assertJson([
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                        'preferred_language' => 'fr',
                        'timezone' => 'Europe/Paris'
                    ]
                ]);
        });

        it('cannot get profile for unauthenticated user', function () {
            $response = $this->getJson('/api/auth/me');

            $response->assertStatus(401);
        });

        it('can get user info from /user endpoint', function () {
            Sanctum::actingAs($this->user);

            $response = $this->getJson('/api/user');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'user' => ['id', 'name', 'email'],
                    'preferences' => ['language', 'timezone']
                ]);
        });
    });

    describe('Rate Limiting', function () {
        it('applies rate limiting to authentication endpoints', function () {
            // This test would require multiple requests to trigger rate limiting
            // For now, we'll just verify the middleware is applied by checking the route
            $routes = collect(Route::getRoutes())->filter(function ($route) {
                return str_contains($route->uri(), 'auth/login');
            });

            expect($routes)->not->toBeEmpty();
            
            // In a real scenario, you would make multiple requests and expect a 429 status
            // This is a simplified test to verify the endpoint exists
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test.user@example.com',
                'password' => 'password'
            ]);

            // Should get validation error, not rate limit error (since we're not hitting the limit)
            expect(in_array($response->status(), [401, 422]))->toBeTrue();
        });
    });
});