<?php

use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

describe('User Model', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'preferred_language' => 'fr',
            'timezone' => 'Europe/Paris'
        ]);
    });

    describe('Relationships', function () {
        it('has many tasks', function () {
            $task1 = Task::factory()->for($this->user)->create();
            $task2 = Task::factory()->for($this->user)->create();
            
            expect($this->user->tasks)->toHaveCount(2);
            expect($this->user->tasks->pluck('id')->toArray())->toContain($task1->id, $task2->id);
        });

        it('has many root tasks', function () {
            $rootTask = Task::factory()->for($this->user)->create();
            $parentTask = Task::factory()->for($this->user)->create();
            $subtask = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            $rootTasks = $this->user->rootTasks;
            
            expect($rootTasks)->toHaveCount(2);
            expect($rootTasks->pluck('id')->toArray())->toContain($rootTask->id, $parentTask->id);
            expect($rootTasks->pluck('id')->toArray())->not->toContain($subtask->id);
        });
    });

    describe('Preferences', function () {
        it('returns preferred language', function () {
            expect($this->user->getPreferredLanguage())->toBe('fr');
        });

        it('defaults to english when no preferred language is set', function () {
            $user = User::factory()->make(['preferred_language' => null]);
            
            expect($user->getPreferredLanguage())->toBe('en');
        });

        it('returns timezone', function () {
            expect($this->user->getTimezone())->toBe('Europe/Paris');
        });

        it('defaults to UTC when no timezone is set', function () {
            $user = User::factory()->make(['timezone' => null]);
            
            expect($user->getTimezone())->toBe('UTC');
        });
    });

    describe('Authentication', function () {
        it('uses HasApiTokens trait', function () {
            expect(method_exists($this->user, 'createToken'))->toBeTrue();
            expect(method_exists($this->user, 'tokens'))->toBeTrue();
        });

        it('can create API tokens', function () {
            $token = $this->user->createToken('test-token');
            
            expect($token)->toBeInstanceOf(\Laravel\Sanctum\NewAccessToken::class);
            expect($token->accessToken)->toBeInstanceOf(PersonalAccessToken::class);
            expect($token->accessToken->tokenable_id)->toBe($this->user->id);
        });

        it('can have multiple tokens', function () {
            $this->user->createToken('token1');
            $this->user->createToken('token2');
            
            expect($this->user->tokens)->toHaveCount(2);
        });

        it('can revoke tokens', function () {
            $token = $this->user->createToken('test-token');
            
            $this->user->tokens()->delete();
            
            expect($this->user->fresh()->tokens)->toHaveCount(0);
        });
    });

    describe('Soft Deletes', function () {
        it('soft deletes users', function () {
            $this->user->delete();
            
            expect($this->user->trashed())->toBeTrue();
            expect(User::count())->toBe(0);
            expect(User::withTrashed()->count())->toBe(1);
        });

        it('can restore soft deleted users', function () {
            $this->user->delete();
            
            $this->user->restore();
            
            expect($this->user->trashed())->toBeFalse();
            expect(User::count())->toBe(1);
        });
    });

    describe('Mass Assignment', function () {
        it('allows mass assignment of fillable fields', function () {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'preferred_language' => 'de',
                'timezone' => 'Europe/Berlin'
            ];
            
            $user = User::create($userData);
            
            expect($user->name)->toBe('John Doe');
            expect($user->email)->toBe('john@example.com');
            expect($user->preferred_language)->toBe('de');
            expect($user->timezone)->toBe('Europe/Berlin');
        });

        it('hides sensitive fields in array representation', function () {
            $userArray = $this->user->toArray();
            
            expect($userArray)->not->toHaveKey('password');
            expect($userArray)->not->toHaveKey('remember_token');
        });
    });

    describe('Password Hashing', function () {
        it('automatically hashes passwords', function () {
            $user = User::factory()->create(['password' => 'plaintext']);
            
            expect($user->password)->not->toBe('plaintext');
            expect(strlen($user->password))->toBeGreaterThan(50); // Hashed passwords are longer
        });

        it('can verify passwords', function () {
            $user = User::factory()->create(['password' => 'secret123']);
            
            expect(Hash::check('secret123', $user->password))->toBeTrue();
            expect(Hash::check('wrongpassword', $user->password))->toBeFalse();
        });
    });

    describe('Notifications', function () {
        it('uses Notifiable trait', function () {
            expect(method_exists($this->user, 'notify'))->toBeTrue();
            expect(method_exists($this->user, 'notifications'))->toBeTrue();
        });

        it('can receive notifications', function () {
            // This is a basic test to ensure the trait is working
            expect(method_exists($this->user, 'routeNotificationFor'))->toBeTrue();
        });
    });

    describe('Factory', function () {
        it('can create users with factory', function () {
            $user = User::factory()->create();
            
            expect($user)->toBeInstanceOf(User::class);
            expect($user->name)->not->toBeEmpty();
            expect($user->email)->not->toBeEmpty();
            expect($user->email)->toContain('@');
        });

        it('can create multiple users with factory', function () {
            $users = User::factory()->count(3)->create();
            
            expect($users)->toHaveCount(3);
            expect($users->every(fn($user) => $user instanceof User))->toBeTrue();
        });

        it('can override factory attributes', function () {
            $user = User::factory()->create([
                'name' => 'Custom Name',
                'preferred_language' => 'es'
            ]);
            
            expect($user->name)->toBe('Custom Name');
            expect($user->preferred_language)->toBe('es');
        });
    });
});