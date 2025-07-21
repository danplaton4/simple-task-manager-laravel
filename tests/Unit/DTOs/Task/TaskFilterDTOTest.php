<?php

namespace Tests\Unit\DTOs\Task;

use App\DTOs\Task\TaskFilterDTO;
use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class TaskFilterDTOTest extends TestCase
{
    public function test_can_create_from_array()
    {
        $data = [
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_HIGH,
            'parent_id' => 123,
            'due_date_from' => '2024-01-01',
            'due_date_to' => '2024-12-31',
            'search' => 'test search',
            'page' => 2,
            'per_page' => 25,
            'sort_by' => 'due_date',
            'sort_direction' => 'asc',
            'include_subtasks' => false,
            'include_completed' => false,
            'include_deleted' => true,
            'hierarchy_level' => 'root'
        ];

        $dto = TaskFilterDTO::fromArray($data);

        $this->assertEquals(Task::STATUS_PENDING, $dto->status);
        $this->assertEquals(Task::PRIORITY_HIGH, $dto->priority);
        $this->assertEquals(123, $dto->parentId);
        $this->assertInstanceOf(Carbon::class, $dto->dueDateFrom);
        $this->assertInstanceOf(Carbon::class, $dto->dueDateTo);
        $this->assertEquals('test search', $dto->search);
        $this->assertEquals(2, $dto->page);
        $this->assertEquals(25, $dto->perPage);
        $this->assertEquals('due_date', $dto->sortBy);
        $this->assertEquals('asc', $dto->sortDirection);
        $this->assertFalse($dto->includeSubtasks);
        $this->assertFalse($dto->includeCompleted);
        $this->assertTrue($dto->includeDeleted);
        $this->assertEquals('root', $dto->hierarchyLevel);
    }

    public function test_can_create_with_defaults()
    {
        $dto = TaskFilterDTO::fromArray([]);

        $this->assertNull($dto->status);
        $this->assertNull($dto->priority);
        $this->assertNull($dto->parentId);
        $this->assertNull($dto->dueDateFrom);
        $this->assertNull($dto->dueDateTo);
        $this->assertNull($dto->search);
        $this->assertEquals(1, $dto->page);
        $this->assertEquals(15, $dto->perPage);
        $this->assertEquals('created_at', $dto->sortBy);
        $this->assertEquals('desc', $dto->sortDirection);
        $this->assertTrue($dto->includeSubtasks);
        $this->assertTrue($dto->includeCompleted);
        $this->assertFalse($dto->includeDeleted);
        $this->assertEquals('all', $dto->hierarchyLevel);
    }

    public function test_get_filters_array_returns_correct_structure()
    {
        $dto = new TaskFilterDTO(
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_HIGH,
            parentId: 123,
            dueDateFrom: Carbon::parse('2024-01-01'),
            dueDateTo: Carbon::parse('2024-12-31'),
            search: 'test search',
            hierarchyLevel: 'root'
        );

        $filters = $dto->getFiltersArray();

        $expected = [
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_HIGH,
            'parent_id' => 123,
            'due_date_from' => '2024-01-01',
            'due_date_to' => '2024-12-31',
            'search' => 'test search',
            'include_subtasks' => true,
            'include_completed' => true,
            'include_deleted' => false,
            'hierarchy_level' => 'root',
        ];

        $this->assertEquals($expected, $filters);
    }

    public function test_get_pagination_array_returns_correct_structure()
    {
        $dto = new TaskFilterDTO(page: 3, perPage: 50);

        $pagination = $dto->getPaginationArray();

        $expected = [
            'page' => 3,
            'per_page' => 50,
        ];

        $this->assertEquals($expected, $pagination);
    }

    public function test_get_sorting_array_returns_correct_structure()
    {
        $dto = new TaskFilterDTO(sortBy: 'due_date', sortDirection: 'asc');

        $sorting = $dto->getSortingArray();

        $expected = [
            'sort_by' => 'due_date',
            'sort_direction' => 'asc',
        ];

        $this->assertEquals($expected, $sorting);
    }

    public function test_get_cache_key_generates_consistent_key()
    {
        $dto = new TaskFilterDTO(
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_HIGH,
            page: 1,
            perPage: 15
        );

        $key1 = $dto->getCacheKey(123);
        $key2 = $dto->getCacheKey(123);
        $key3 = $dto->getCacheKey(456);

        $this->assertEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertStringStartsWith('user:123:tasks:', $key1);
        $this->assertStringStartsWith('user:456:tasks:', $key3);
    }

    public function test_validation_passes_with_valid_data()
    {
        $dto = new TaskFilterDTO(
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_HIGH,
            parentId: 123,
            dueDateFrom: Carbon::parse('2024-01-01'),
            dueDateTo: Carbon::parse('2024-12-31'),
            page: 1,
            perPage: 15,
            sortBy: 'created_at',
            sortDirection: 'desc',
            hierarchyLevel: 'all'
        );

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->validate());
    }

    public function test_validation_fails_with_invalid_status()
    {
        $dto = new TaskFilterDTO(status: 'invalid_status');

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('status', $errors);
    }

    public function test_validation_fails_with_invalid_priority()
    {
        $dto = new TaskFilterDTO(priority: 'invalid_priority');

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('priority', $errors);
    }

    public function test_validation_fails_with_invalid_date_range()
    {
        $dto = new TaskFilterDTO(
            dueDateFrom: Carbon::parse('2024-12-31'),
            dueDateTo: Carbon::parse('2024-01-01')
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('due_date_range', $errors);
    }

    public function test_validation_fails_with_invalid_pagination()
    {
        $dto1 = new TaskFilterDTO(page: 0);
        $dto2 = new TaskFilterDTO(perPage: 0);
        $dto3 = new TaskFilterDTO(perPage: 101);

        $this->assertFalse($dto1->isValid());
        $this->assertFalse($dto2->isValid());
        $this->assertFalse($dto3->isValid());
    }

    public function test_has_filters_returns_correct_value()
    {
        $dtoWithFilters = new TaskFilterDTO(
            status: Task::STATUS_PENDING,
            includeCompleted: false
        );

        $dtoWithoutFilters = new TaskFilterDTO();

        $this->assertTrue($dtoWithFilters->hasFilters());
        $this->assertFalse($dtoWithoutFilters->hasFilters());
    }

    public function test_is_root_tasks_only_returns_correct_value()
    {
        $rootTasksDto = new TaskFilterDTO(hierarchyLevel: 'root');
        $allTasksDto = new TaskFilterDTO(hierarchyLevel: 'all');

        $this->assertTrue($rootTasksDto->isRootTasksOnly());
        $this->assertFalse($allTasksDto->isRootTasksOnly());
    }

    public function test_is_subtasks_only_returns_correct_value()
    {
        $subtasksDto = new TaskFilterDTO(hierarchyLevel: 'subtasks');
        $allTasksDto = new TaskFilterDTO(hierarchyLevel: 'all');

        $this->assertTrue($subtasksDto->isSubtasksOnly());
        $this->assertFalse($allTasksDto->isSubtasksOnly());
    }

    public function test_has_date_range_returns_correct_value()
    {
        $withDateRangeDto = new TaskFilterDTO(
            dueDateFrom: Carbon::parse('2024-01-01'),
            dueDateTo: Carbon::parse('2024-12-31')
        );

        $withoutDateRangeDto = new TaskFilterDTO();

        $this->assertTrue($withDateRangeDto->hasDateRange());
        $this->assertFalse($withoutDateRangeDto->hasDateRange());
    }

    public function test_has_search_returns_correct_value()
    {
        $withSearchDto = new TaskFilterDTO(search: 'test search');
        $withEmptySearchDto = new TaskFilterDTO(search: '   ');
        $withoutSearchDto = new TaskFilterDTO();

        $this->assertTrue($withSearchDto->hasSearch());
        $this->assertFalse($withEmptySearchDto->hasSearch());
        $this->assertFalse($withoutSearchDto->hasSearch());
    }

    public function test_get_offset_calculates_correctly()
    {
        $dto1 = new TaskFilterDTO(page: 1, perPage: 15);
        $dto2 = new TaskFilterDTO(page: 3, perPage: 20);

        $this->assertEquals(0, $dto1->getOffset());
        $this->assertEquals(40, $dto2->getOffset());
    }
}