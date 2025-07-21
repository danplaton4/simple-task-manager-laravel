<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use App\DTOs\BaseDTO;
use App\Services\BaseService;
use App\Exceptions\DomainException;
use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Eloquent\BaseEloquentRepository;
use App\Providers\RepositoryServiceProvider;

class FoundationalStructureTest extends TestCase
{
    /** @test */
    public function it_can_load_all_base_interfaces_and_classes()
    {
        // Test that all foundational classes can be instantiated or reflected
        $this->assertTrue(interface_exists(BaseRepositoryInterface::class));
        $this->assertTrue(interface_exists(UserRepositoryInterface::class));
        $this->assertTrue(interface_exists(TaskRepositoryInterface::class));
        
        $this->assertTrue(class_exists(BaseDTO::class));
        $this->assertTrue(class_exists(BaseService::class));
        $this->assertTrue(class_exists(DomainException::class));
        $this->assertTrue(class_exists(BaseEloquentRepository::class));
        $this->assertTrue(class_exists(RepositoryServiceProvider::class));
    }

    /** @test */
    public function base_dto_provides_array_conversion()
    {
        // Create a simple DTO for testing
        $dto = new class('test', 123) extends BaseDTO {
            public function __construct(
                public readonly string $name,
                public readonly int $value
            ) {}
        };

        $array = $dto->toArray();
        
        $this->assertEquals([
            'name' => 'test',
            'value' => 123
        ], $array);
    }

    /** @test */
    public function repository_service_provider_is_registered()
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(RepositoryServiceProvider::class, $providers);
    }

    /** @test */
    public function directory_structure_exists()
    {
        $this->assertDirectoryExists(app_path('DTOs'));
        $this->assertDirectoryExists(app_path('DTOs/Auth'));
        $this->assertDirectoryExists(app_path('DTOs/Task'));
        $this->assertDirectoryExists(app_path('DTOs/User'));
        
        $this->assertDirectoryExists(app_path('Repositories'));
        $this->assertDirectoryExists(app_path('Repositories/Contracts'));
        $this->assertDirectoryExists(app_path('Repositories/Eloquent'));
        
        $this->assertDirectoryExists(app_path('Services/Auth'));
        $this->assertDirectoryExists(app_path('Services/Task'));
        $this->assertDirectoryExists(app_path('Services/User'));
        
        $this->assertDirectoryExists(app_path('Exceptions'));
    }
}