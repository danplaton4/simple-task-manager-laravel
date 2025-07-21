<?php

namespace Tests\Unit\DTOs\Auth;

use App\DTOs\Auth\AuthResultDTO;
use App\DTOs\User\UserDTO;
use App\Models\User;
use Tests\TestCase;
use Carbon\Carbon;
class AuthResultDTOTest extends TestCase
{
    private function createTestUser(): User
    {
        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'preferred_language' => 'en',
            'timezone' => 'UTC',
        ]);
        $user->id = 1;
        $user->created_at = Carbon::now();
        $user->updated_at = Carbon::now();
        
        return $user;
    }

    public function test_can_create_from_user_and_token()
    {
        $user = $this->createTestUser();
        $token = 'test-token-123';
        $expiresAt = Carbon::now()->addHours(24);

        $dto = AuthResultDTO::fromUserAndToken($user, $token, $expiresAt, 'Test message');

        $this->assertInstanceOf(UserDTO::class, $dto->user);
        $this->assertEquals($token, $dto->token);
        $this->assertEquals('Bearer', $dto->tokenType);
        $this->assertEquals($expiresAt, $dto->expiresAt);
        $this->assertEquals('Test message', $dto->message);
    }

    public function test_can_create_registration_success()
    {
        $user = $this->createTestUser();
        $token = 'registration-token-123';
        $expiresAt = Carbon::now()->addDays(30);

        $dto = AuthResultDTO::registrationSuccess($user, $token, $expiresAt);

        $this->assertEquals($token, $dto->token);
        $this->assertEquals('Bearer', $dto->tokenType);
        $this->assertEquals($expiresAt, $dto->expiresAt);
        $this->assertEquals('Registration successful', $dto->message);
        $this->assertEquals($user->id, $dto->user->id);
    }

    public function test_can_create_login_success()
    {
        $user = $this->createTestUser();
        $token = 'login-token-123';
        $expiresAt = Carbon::now()->addHours(24);

        $dto = AuthResultDTO::loginSuccess($user, $token, $expiresAt);

        $this->assertEquals($token, $dto->token);
        $this->assertEquals('Bearer', $dto->tokenType);
        $this->assertEquals($expiresAt, $dto->expiresAt);
        $this->assertEquals('Login successful', $dto->message);
        $this->assertEquals($user->id, $dto->user->id);
    }

    public function test_to_api_response_with_all_fields()
    {
        $user = $this->createTestUser();
        $token = 'test-token-123';
        $expiresAt = Carbon::parse('2024-01-01 12:00:00');

        $dto = AuthResultDTO::fromUserAndToken($user, $token, $expiresAt, 'Success message');
        $response = $dto->toApiResponse();

        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('token_type', $response);
        $this->assertArrayHasKey('expires_at', $response);
        $this->assertArrayHasKey('message', $response);

        $this->assertEquals($token, $response['token']);
        $this->assertEquals('Bearer', $response['token_type']);
        $this->assertEquals($expiresAt->toISOString(), $response['expires_at']);
        $this->assertEquals('Success message', $response['message']);
    }

    public function test_to_api_response_without_optional_fields()
    {
        $user = $this->createTestUser();
        $token = 'test-token-123';

        $dto = AuthResultDTO::fromUserAndToken($user, $token);
        $response = $dto->toApiResponse();

        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('token_type', $response);
        $this->assertArrayNotHasKey('expires_at', $response);
        $this->assertArrayNotHasKey('message', $response);
    }

    public function test_to_array_returns_correct_structure()
    {
        $user = $this->createTestUser();
        $token = 'test-token-123';
        $expiresAt = Carbon::now()->addHours(24);

        $dto = AuthResultDTO::fromUserAndToken($user, $token, $expiresAt, 'Test message');
        $array = $dto->toArray();

        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('token', $array);
        $this->assertArrayHasKey('tokenType', $array);
        $this->assertArrayHasKey('expiresAt', $array);
        $this->assertArrayHasKey('message', $array);

        $this->assertEquals($token, $array['token']);
        $this->assertEquals('Bearer', $array['tokenType']);
        $this->assertEquals('Test message', $array['message']);
    }
}