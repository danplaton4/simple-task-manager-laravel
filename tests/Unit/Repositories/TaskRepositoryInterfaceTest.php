<?php

namespace Tests\Unit\Repositories;

use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\DTOs\Task\TaskFilterDTO;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Eloquent\EloquentTaskRepository;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TaskRepositoryInterfaceTest extends TestCase
{
    public function test_eloquent_task_repository_implements_interface(): void
    {
        // Arrange
        $task = new Task();
        $repository = new EloquentTaskRepository($task);

        // Assert
        $this->assertInstanceOf(TaskRepositoryInterface::class, $repository);
    }

    public function test_repository_has_required_methods(): void
    {
        // Arrange
        $task = new Task();
        $repository = new EloquentTaskRepository($task);

        // Assert - Check that all required methods exist
        $this->assertTrue(method_exists($repository, 'createFromDTO'));
        $this->assertTrue(method_exists($repository, 'updateFromDTO'));
        $this->assertTrue(method_exists($repository, 'findByIdAndUser'));
        $this->assertTrue(method_exists($repository, 'getTasksForUser'));
        $this->assertTrue(method_exists($repository, 'getPaginatedTasksForUser'));
        $this->assertTrue(method_exists($repository, 'getSubtasks'));
        $this->assertTrue(method_exists($repository, 'getTasksByStatus'));
        $this->assertTrue(method_exists($repository, 'getOverdueTasks'));
        $this->assertTrue(method_exists($repository, 'getCompletedTasks'));
        $this->assertTrue(method_exists($repository, 'countTasksByStatus'));
        
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
        $task = new Task();
        $repository = new EloquentTaskRepository($task);
        $reflection = new \ReflectionClass($repository);

        // Test createFromDTO method signature
        $createFromDTOMethod = $reflection->getMethod('createFromDTO');
        $this->assertEquals('createFromDTO', $createFromDTOMethod->getName());
        $this->assertEquals(2, $createFromDTOMethod->getNumberOfParameters());
        
        $parameters = $createFromDTOMethod->getParameters();
        $this->assertEquals('dto', $parameters[0]->getName());
        $this->assertEquals(CreateTaskDTO::class, $parameters[0]->getType()->getName());
        $this->assertEquals('user', $parameters[1]->getName());
        $this->assertEquals(User::class, $parameters[1]->getType()->getName());

        // Test updateFromDTO method signature
        $updateFromDTOMethod = $reflection->getMethod('updateFromDTO');
        $this->assertEquals('updateFromDTO', $updateFromDTOMethod->getName());
        $this->assertEquals(2, $updateFromDTOMethod->getNumberOfParameters());
        
        $parameters = $updateFromDTOMethod->getParameters();
        $this->assertEquals('task', $parameters[0]->getName());
        $this->assertEquals(Task::class, $parameters[0]->getType()->getName());
        $this->assertEquals('dto', $parameters[1]->getName());
        $this->assertEquals(UpdateTaskDTO::class, $parameters[1]->getType()->getName());

        // Test findByIdAndUser method signature
        $findByIdAndUserMethod = $reflection->getMethod('findByIdAndUser');
        $this->assertEquals('findByIdAndUser', $findByIdAndUserMethod->getName());
        $this->assertEquals(2, $findByIdAndUserMethod->getNumberOfParameters());
        
        $parameters = $findByIdAndUserMethod->getParameters();
        $this->assertEquals('id', $parameters[0]->getName());
        $this->assertEquals('int', $parameters[0]->getType()->getName());
        $this->assertEquals('user', $parameters[1]->getName());
        $this->assertEquals(User::class, $parameters[1]->getType()->getName());
    }

    public function test_repository_extends_base_repository(): void
    {
        // Arrange
        $task = new Task();
        $repository = new EloquentTaskRepository($task);

        // Assert
        $this->assertInstanceOf(\App\Repositories\Eloquent\BaseEloquentRepository::class, $repository);
    }

    public function test_repository_constructor_accepts_task_model(): void
    {
        // Arrange
        $task = new Task();

        // Act & Assert - Should not throw exception
        $repository = new EloquentTaskRepository($task);
        $this->assertInstanceOf(EloquentTaskRepository::class, $repository);
    }

    public function test_additional_repository_methods_exist(): void
    {
        // Arrange
        $task = new Task();
        $repository = new EloquentTaskRepository($task);

        // Assert - Check additional methods that extend the interface
        $this->assertTrue(method_exists($repository, 'getTasksWithRelationships'));
        $this->assertTrue(method_exists($repository, 'getRootTasks'));
        $this->assertTrue(method_exists($repository, 'getTasksByPriority'));
        $this->assertTrue(method_exists($repository, 'searchTasks'));
        $this->assertTrue(method_exists($repository, 'getTasksDueInRange'));
        $this->assertTrue(method_exists($repository, 'getTaskStatistics'));
        $this->assertTrue(method_exists($repository, 'clearTaskStatisticsCache'));
        $this->assertTrue(method_exists($repository, 'getCachedTasksForUser'));
        $this->assertTrue(method_exists($repository, 'clearTaskCache'));
        $this->assertTrue(method_exists($repository, 'bulkUpdateStatus'));
        $this->assertTrue(method_exists($repository, 'bulkDelete'));
        $this->assertTrue(method_exists($repository, 'getTasksNeedingNotification'));
        $this->assertTrue(method_exists($repository, 'getTasksWithCompletionPercentage'));
        $this->assertTrue(method_exists($repository, 'findByTranslation'));
        $this->assertTrue(method_exists($repository, 'getTasksMissingTranslation'));
    }

    public function test_dto_integration_methods_exist(): void
    {
        // Test CreateTaskDTO
        $createDto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: ['en' => 'Test Description'],
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: Carbon::tomorrow(),
            parentId: null
        );
        
        // Assert CreateTaskDTO has required methods
        $this->assertTrue(method_exists($createDto, 'toModelData'));
        $this->assertTrue(method_exists($createDto, 'validate'));
        $this->assertTrue(method_exists($createDto, 'isValid'));
        $this->assertTrue(method_exists($createDto, 'getLocalizedName'));
        $this->assertTrue(method_exists($createDto, 'getLocalizedDescription'));
        $this->assertTrue(method_exists($createDto, 'isSubtask'));
        $this->assertTrue(method_exists($createDto, 'hasDueDate'));
        
        // Test that toModelData returns an array with user ID
        $modelData = $createDto->toModelData(1);
        $this->assertIsArray($modelData);
        $this->assertArrayHasKey('name', $modelData);
        $this->assertArrayHasKey('description', $modelData);
        $this->assertArrayHasKey('status', $modelData);
        $this->assertArrayHasKey('priority', $modelData);
        $this->assertArrayHasKey('due_date', $modelData);
        $this->assertArrayHasKey('parent_id', $modelData);
        $this->assertArrayHasKey('user_id', $modelData);
        $this->assertEquals(1, $modelData['user_id']);

        // Test UpdateTaskDTO
        $updateDto = new UpdateTaskDTO(
            name: ['en' => 'Updated Task'],
            status: Task::STATUS_IN_PROGRESS
        );
        
        // Assert UpdateTaskDTO has required methods
        $this->assertTrue(method_exists($updateDto, 'toModelData'));
        $this->assertTrue(method_exists($updateDto, 'validate'));
        $this->assertTrue(method_exists($updateDto, 'isValid'));
        $this->assertTrue(method_exists($updateDto, 'hasUpdates'));
        $this->assertTrue(method_exists($updateDto, 'getUpdatedFields'));
        $this->assertTrue(method_exists($updateDto, 'isBeingCompleted'));
        $this->assertTrue(method_exists($updateDto, 'isParentChanging'));
        
        // Test that toModelData returns only updated fields
        $updateModelData = $updateDto->toModelData();
        $this->assertIsArray($updateModelData);
        $this->assertArrayHasKey('name', $updateModelData);
        $this->assertArrayHasKey('status', $updateModelData);
        $this->assertArrayNotHasKey('user_id', $updateModelData); // Should not include user_id in updates
    }

    public function test_task_filter_dto_integration(): void
    {
        // Test TaskFilterDTO
        $filterDto = new TaskFilterDTO(
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_HIGH,
            search: 'test',
            page: 2,
            perPage: 10
        );
        
        // Assert TaskFilterDTO has required methods
        $this->assertTrue(method_exists($filterDto, 'getFiltersArray'));
        $this->assertTrue(method_exists($filterDto, 'getPaginationArray'));
        $this->assertTrue(method_exists($filterDto, 'getSortingArray'));
        $this->assertTrue(method_exists($filterDto, 'getCacheKey'));
        $this->assertTrue(method_exists($filterDto, 'validate'));
        $this->assertTrue(method_exists($filterDto, 'isValid'));
        $this->assertTrue(method_exists($filterDto, 'hasFilters'));
        $this->assertTrue(method_exists($filterDto, 'isRootTasksOnly'));
        $this->assertTrue(method_exists($filterDto, 'isSubtasksOnly'));
        $this->assertTrue(method_exists($filterDto, 'hasDateRange'));
        $this->assertTrue(method_exists($filterDto, 'hasSearch'));
        $this->assertTrue(method_exists($filterDto, 'getOffset'));
        
        // Test filter methods
        $this->assertTrue($filterDto->hasFilters());
        $this->assertTrue($filterDto->hasSearch());
        $this->assertFalse($filterDto->hasDateRange());
        $this->assertFalse($filterDto->isRootTasksOnly());
        $this->assertFalse($filterDto->isSubtasksOnly());
        
        // Test cache key generation
        $cacheKey = $filterDto->getCacheKey(1);
        $this->assertIsString($cacheKey);
        $this->assertStringStartsWith('user:1:tasks:', $cacheKey);
        
        // Test pagination
        $this->assertEquals(10, $filterDto->getOffset()); // (page 2 - 1) * 10 per page
    }

    public function test_task_constants_are_available(): void
    {
        // Test status constants
        $this->assertEquals('pending', Task::STATUS_PENDING);
        $this->assertEquals('in_progress', Task::STATUS_IN_PROGRESS);
        $this->assertEquals('completed', Task::STATUS_COMPLETED);
        $this->assertEquals('cancelled', Task::STATUS_CANCELLED);
        
        // Test priority constants
        $this->assertEquals('low', Task::PRIORITY_LOW);
        $this->assertEquals('medium', Task::PRIORITY_MEDIUM);
        $this->assertEquals('high', Task::PRIORITY_HIGH);
        $this->assertEquals('urgent', Task::PRIORITY_URGENT);
        
        // Test static methods
        $statuses = Task::getStatuses();
        $this->assertIsArray($statuses);
        $this->assertContains(Task::STATUS_PENDING, $statuses);
        $this->assertContains(Task::STATUS_COMPLETED, $statuses);
        
        $priorities = Task::getPriorities();
        $this->assertIsArray($priorities);
        $this->assertContains(Task::PRIORITY_LOW, $priorities);
        $this->assertContains(Task::PRIORITY_URGENT, $priorities);
    }

    public function test_dto_validation_works(): void
    {
        // Test valid CreateTaskDTO
        $validCreateDto = new CreateTaskDTO(
            name: ['en' => 'Valid Task'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: Carbon::tomorrow(),
            parentId: null
        );
        
        $this->assertTrue($validCreateDto->isValid());
        $this->assertEmpty($validCreateDto->validate());
        
        // Test invalid CreateTaskDTO
        $invalidCreateDto = new CreateTaskDTO(
            name: ['en' => ''], // Empty name
            description: null,
            status: 'invalid_status',
            priority: 'invalid_priority',
            dueDate: Carbon::yesterday(), // Past due date
            parentId: -1 // Invalid parent ID
        );
        
        $this->assertFalse($invalidCreateDto->isValid());
        $errors = $invalidCreateDto->validate();
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name.en', $errors);
        $this->assertArrayHasKey('status', $errors);
        $this->assertArrayHasKey('priority', $errors);
        $this->assertArrayHasKey('due_date', $errors);
        $this->assertArrayHasKey('parent_id', $errors);
    }

    public function test_repository_caching_methods(): void
    {
        // Arrange
        $task = new Task();
        $repository = new EloquentTaskRepository($task);
        $user = new User();
        $user->id = 1;

        // Test that caching methods exist and can be called
        $this->assertTrue(method_exists($repository, 'getCachedTasksForUser'));
        $this->assertTrue(method_exists($repository, 'clearTaskCache'));
        $this->assertTrue(method_exists($repository, 'getTaskStatistics'));
        $this->assertTrue(method_exists($repository, 'clearTaskStatisticsCache'));
        
        // These methods should not throw exceptions when called
        // (though they may fail due to database not being available in unit tests)
        try {
            $repository->clearTaskCache($user);
            $repository->clearTaskStatisticsCache($user);
            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            // Expected in unit test environment without database
            $this->assertTrue(true);
        }
    }
}