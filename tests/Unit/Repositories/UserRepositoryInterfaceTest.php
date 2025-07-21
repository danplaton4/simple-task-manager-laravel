<?php

namespace Tests\Unit\Repositories;

use App\DTOs\Auth\RegisterUserDTO;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentUserRepository;
use PHPUnit\Framework\TestCase;

class UserRepositoryInterfaceTest extends TestCase
{
    public function test_eloquent_user_repository_implements_interface(): void
    {
        // Arrange
        $user = new User();
        $repository = new EloquentUserRepository($user);

        // Assert
        $this->assertInstanceOf(UserRepositoryInterface::class, $repository);
    }

    public function test_repository_has_required_methods(): void
    {
        // Arrange
        $user = new User();
        $repository = new EloquentUserRepository($user);

        // Assert - Check that all required methods exist
        $this->assertTrue(method_exists($repository, 'createFromDTO'));
        $this->assertTrue(method_exists($repository, 'findByEmail'));
        $this->assertTrue(method_exists($repository, 'existsByEmail'));
        $this->assertTrue(method_exists($repository, 'findById'));
        $this->assertTrue(method_exists($repository, 'findWithTaskStats'));
        
        // Base repository methods
        $this->assertTrue(method_exists($repository, 'find'));
        $this->assertTrue(method_exists($repository, 'findOrFail'));
        $this->assertTrue(method_exists($repository, 'all'));
        $this->assertTrue(method_exists($repository, 'create'));
        $this->assertTrue(method_exists($repository, 'update'));
        $this->assertTrue(method_exists($repository, 'delete'));
        $this->assertTrue(method_exists($repository, 'save'));
    }

    public function test_repository_method_signatures_are_correct(): void
    {
        // Arrange
        $user = new User();
        $repository = new EloquentUserRepository($user);
        $reflection = new \ReflectionClass($repository);

        // Test createFromDTO method signature
        $createFromDTOMethod = $reflection->getMethod('createFromDTO');
        $this->assertEquals('createFromDTO', $createFromDTOMethod->getName());
        $this->assertEquals(1, $createFromDTOMethod->getNumberOfParameters());
        
        $parameters = $createFromDTOMethod->getParameters();
        $this->assertEquals('dto', $parameters[0]->getName());
        $this->assertEquals(RegisterUserDTO::class, $parameters[0]->getType()->getName());

        // Test findByEmail method signature
        $findByEmailMethod = $reflection->getMethod('findByEmail');
        $this->assertEquals('findByEmail', $findByEmailMethod->getName());
        $this->assertEquals(1, $findByEmailMethod->getNumberOfParameters());
        
        $parameters = $findByEmailMethod->getParameters();
        $this->assertEquals('email', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());

        // Test existsByEmail method signature
        $existsByEmailMethod = $reflection->getMethod('existsByEmail');
        $this->assertEquals('existsByEmail', $existsByEmailMethod->getName());
        $this->assertEquals(1, $existsByEmailMethod->getNumberOfParameters());
        
        $parameters = $existsByEmailMethod->getParameters();
        $this->assertEquals('email', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());
    }

    public function test_repository_extends_base_repository(): void
    {
        // Arrange
        $user = new User();
        $repository = new EloquentUserRepository($user);

        // Assert
        $this->assertInstanceOf(\App\Repositories\Eloquent\BaseEloquentRepository::class, $repository);
    }

    public function test_repository_constructor_accepts_user_model(): void
    {
        // Arrange
        $user = new User();

        // Act & Assert - Should not throw exception
        $repository = new EloquentUserRepository($user);
        $this->assertInstanceOf(EloquentUserRepository::class, $repository);
    }

    public function test_additional_repository_methods_exist(): void
    {
        // Arrange
        $user = new User();
        $repository = new EloquentUserRepository($user);

        // Assert - Check additional methods that extend the interface
        $this->assertTrue(method_exists($repository, 'findByIds'));
        $this->assertTrue(method_exists($repository, 'search'));
        $this->assertTrue(method_exists($repository, 'paginate'));
        $this->assertTrue(method_exists($repository, 'updateNotificationPreferences'));
        $this->assertTrue(method_exists($repository, 'getUsersWantingNotification'));
        $this->assertTrue(method_exists($repository, 'softDelete'));
        $this->assertTrue(method_exists($repository, 'restore'));
        $this->assertTrue(method_exists($repository, 'getTrashed'));
    }

    public function test_dto_integration_methods_exist(): void
    {
        // Test that the DTO classes have the required methods for repository integration
        $dto = new RegisterUserDTO('Test', 'test@example.com', 'password', 'en', 'UTC');
        
        // Assert DTO has required methods
        $this->assertTrue(method_exists($dto, 'toModelData'));
        $this->assertTrue(method_exists($dto, 'validate'));
        $this->assertTrue(method_exists($dto, 'isValid'));
        
        // Test that toModelData returns an array
        $modelData = $dto->toModelData();
        $this->assertIsArray($modelData);
        $this->assertArrayHasKey('name', $modelData);
        $this->assertArrayHasKey('email', $modelData);
        $this->assertArrayHasKey('password', $modelData);
        $this->assertArrayHasKey('preferred_language', $modelData);
        $this->assertArrayHasKey('timezone', $modelData);
    }
}