<?php

namespace Tests\Unit\DTOs\Auth;

use App\DTOs\Auth\RegisterUserDTO;
use App\Http\Requests\RegisterRequest;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegisterUserDTOTest extends TestCase
{
    public function test_can_create_from_array()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'JOHN@EXAMPLE.COM',
            'password' => 'password123',
            'preferred_language' => 'en',
            'timezone' => 'UTC'
        ];

        $dto = RegisterUserDTO::fromArray($data);

        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals('john@example.com', $dto->email); // Should be lowercased
        $this->assertEquals('password123', $dto->password);
        $this->assertEquals('en', $dto->preferredLanguage);
        $this->assertEquals('UTC', $dto->timezone);
    }

    public function test_can_create_from_array_with_defaults()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        $dto = RegisterUserDTO::fromArray($data);

        $this->assertEquals('en', $dto->preferredLanguage);
        $this->assertEquals('UTC', $dto->timezone);
    }

    public function test_trims_name_and_email()
    {
        $data = [
            'name' => '  John Doe  ',
            'email' => '  JOHN@EXAMPLE.COM  ',
            'password' => 'password123'
        ];

        $dto = RegisterUserDTO::fromArray($data);

        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals('john@example.com', $dto->email);
    }

    public function test_to_model_data_returns_correct_format()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $modelData = $dto->toModelData();

        $expected = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'preferred_language' => 'en',
            'timezone' => 'UTC',
        ];

        $this->assertEquals($expected, $modelData);
    }

    public function test_validation_passes_with_valid_data()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->validate());
    }

    public function test_validation_fails_with_empty_name()
    {
        $dto = new RegisterUserDTO(
            name: '',
            email: 'john@example.com',
            password: 'password123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals('Name is required', $errors['name']);
    }

    public function test_validation_fails_with_invalid_email()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'invalid-email',
            password: 'password123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Valid email is required', $errors['email']);
    }

    public function test_validation_fails_with_short_password()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'john@example.com',
            password: '123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('Password must be at least 8 characters', $errors['password']);
    }

    public function test_validation_fails_with_invalid_language()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password123',
            preferredLanguage: 'invalid',
            timezone: 'UTC'
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('preferred_language', $errors);
        $this->assertEquals('Invalid preferred language', $errors['preferred_language']);
    }

    public function test_to_array_returns_correct_structure()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $array = $dto->toArray();

        $expected = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'preferredLanguage' => 'en',
            'timezone' => 'UTC',
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_json_returns_valid_json()
    {
        $dto = new RegisterUserDTO(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password123',
            preferredLanguage: 'en',
            timezone: 'UTC'
        );

        $json = $dto->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('John Doe', $decoded['name']);
        $this->assertEquals('john@example.com', $decoded['email']);
    }
}