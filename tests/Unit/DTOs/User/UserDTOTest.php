<?php

namespace Tests\Unit\DTOs\User;

use App\DTOs\User\UserDTO;
use App\Models\User;
use Tests\TestCase;
use Carbon\Carbon;
class UserDTOTest extends TestCase
{

    public function test_can_create_from_model()
    {
        // Create a mock user without database
        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'preferred_language' => 'en',
            'timezone' => 'UTC',
        ]);
        $user->id = 1;
        $user->created_at = Carbon::parse('2024-01-01 10:00:00');
        $user->updated_at = Carbon::parse('2024-01-02 15:30:00');

        $dto = UserDTO::fromModel($user);

        $this->assertEquals($user->id, $dto->id);
        $this->assertEquals($user->name, $dto->name);
        $this->assertEquals($user->email, $dto->email);
        $this->assertEquals($user->preferred_language, $dto->preferredLanguage);
        $this->assertEquals($user->timezone, $dto->timezone);
        $this->assertEquals($user->created_at, $dto->createdAt);
        $this->assertEquals($user->updated_at, $dto->updatedAt);
    }

    public function test_to_array_returns_correct_structure()
    {
        $createdAt = Carbon::parse('2024-01-01 10:00:00');
        $updatedAt = Carbon::parse('2024-01-02 15:30:00');

        $dto = new UserDTO(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            preferredLanguage: 'en',
            timezone: 'UTC',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $array = $dto->toArray();

        $expected = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'preferredLanguage' => 'en',
            'timezone' => 'UTC',
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_api_array_returns_snake_case_keys()
    {
        $createdAt = Carbon::parse('2024-01-01 10:00:00');
        $updatedAt = Carbon::parse('2024-01-02 15:30:00');

        $dto = new UserDTO(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            preferredLanguage: 'en',
            timezone: 'UTC',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $apiArray = $dto->toApiArray();

        $expected = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'preferred_language' => 'en',
            'timezone' => 'UTC',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        $this->assertEquals($expected, $apiArray);
    }

    public function test_to_api_array_handles_null_updated_at()
    {
        $createdAt = Carbon::parse('2024-01-01 10:00:00');

        $dto = new UserDTO(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            preferredLanguage: 'en',
            timezone: 'UTC',
            createdAt: $createdAt,
            updatedAt: null
        );

        $apiArray = $dto->toApiArray();

        $this->assertNull($apiArray['updated_at']);
    }

    public function test_to_json_returns_valid_json()
    {
        $createdAt = Carbon::parse('2024-01-01 10:00:00');
        $updatedAt = Carbon::parse('2024-01-02 15:30:00');

        $dto = new UserDTO(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            preferredLanguage: 'en',
            timezone: 'UTC',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $json = $dto->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals(1, $decoded['id']);
        $this->assertEquals('John Doe', $decoded['name']);
        $this->assertEquals('john@example.com', $decoded['email']);
        $this->assertEquals('en', $decoded['preferredLanguage']);
        $this->assertEquals('UTC', $decoded['timezone']);
    }
}