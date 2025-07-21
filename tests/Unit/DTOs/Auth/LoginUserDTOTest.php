<?php

namespace Tests\Unit\DTOs\Auth;

use App\DTOs\Auth\LoginUserDTO;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class LoginUserDTOTest extends TestCase
{
    public function test_can_create_from_array()
    {
        $data = [
            'email' => 'JOHN@EXAMPLE.COM',
            'password' => 'password123',
            'remember' => true
        ];

        $dto = LoginUserDTO::fromArray($data);

        $this->assertEquals('john@example.com', $dto->email); // Should be lowercased
        $this->assertEquals('password123', $dto->password);
        $this->assertTrue($dto->remember);
    }

    public function test_can_create_from_array_with_defaults()
    {
        $data = [
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        $dto = LoginUserDTO::fromArray($data);

        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals('password123', $dto->password);
        $this->assertFalse($dto->remember); // Default should be false
    }

    public function test_trims_email()
    {
        $data = [
            'email' => '  JOHN@EXAMPLE.COM  ',
            'password' => 'password123'
        ];

        $dto = LoginUserDTO::fromArray($data);

        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_validation_passes_with_valid_data()
    {
        $dto = new LoginUserDTO(
            email: 'john@example.com',
            password: 'password123',
            remember: false
        );

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->validate());
    }

    public function test_validation_fails_with_invalid_email()
    {
        $dto = new LoginUserDTO(
            email: 'invalid-email',
            password: 'password123',
            remember: false
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Valid email is required', $errors['email']);
    }

    public function test_validation_fails_with_empty_email()
    {
        $dto = new LoginUserDTO(
            email: '',
            password: 'password123',
            remember: false
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Valid email is required', $errors['email']);
    }

    public function test_validation_fails_with_empty_password()
    {
        $dto = new LoginUserDTO(
            email: 'john@example.com',
            password: '',
            remember: false
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('Password is required', $errors['password']);
    }

    public function test_get_token_expiration_with_remember_true()
    {
        $dto = new LoginUserDTO(
            email: 'john@example.com',
            password: 'password123',
            remember: true
        );

        $expiration = $dto->getTokenExpiration();
        $expected = now()->addDays(30);

        // Allow for small time differences in test execution
        $this->assertTrue($expiration->diffInSeconds($expected) < 2);
    }

    public function test_get_token_expiration_with_remember_false()
    {
        $dto = new LoginUserDTO(
            email: 'john@example.com',
            password: 'password123',
            remember: false
        );

        $expiration = $dto->getTokenExpiration();
        $expected = now()->addHours(24);

        // Allow for small time differences in test execution
        $this->assertTrue($expiration->diffInSeconds($expected) < 2);
    }

    public function test_to_array_returns_correct_structure()
    {
        $dto = new LoginUserDTO(
            email: 'john@example.com',
            password: 'password123',
            remember: true
        );

        $array = $dto->toArray();

        $expected = [
            'email' => 'john@example.com',
            'password' => 'password123',
            'remember' => true,
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_json_returns_valid_json()
    {
        $dto = new LoginUserDTO(
            email: 'john@example.com',
            password: 'password123',
            remember: false
        );

        $json = $dto->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('john@example.com', $decoded['email']);
        $this->assertEquals('password123', $decoded['password']);
        $this->assertFalse($decoded['remember']);
    }
}