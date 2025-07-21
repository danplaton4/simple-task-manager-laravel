<?php

namespace Tests\Unit\DTOs\Task;

use App\DTOs\Task\CreateTaskDTO;
use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class CreateTaskDTOTest extends TestCase
{
    public function test_can_create_from_array()
    {
        $data = [
            'name' => ['en' => 'Test Task', 'fr' => 'Tâche de test'],
            'description' => ['en' => 'Test description', 'fr' => 'Description de test'],
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_MEDIUM,
            'due_date' => '2024-12-31 23:59:59',
            'parent_id' => 123
        ];

        $dto = CreateTaskDTO::fromArray($data);

        $this->assertEquals(['en' => 'Test Task', 'fr' => 'Tâche de test'], $dto->name);
        $this->assertEquals(['en' => 'Test description', 'fr' => 'Description de test'], $dto->description);
        $this->assertEquals(Task::STATUS_PENDING, $dto->status);
        $this->assertEquals(Task::PRIORITY_MEDIUM, $dto->priority);
        $this->assertInstanceOf(Carbon::class, $dto->dueDate);
        $this->assertEquals(123, $dto->parentId);
    }

    public function test_can_create_from_array_with_minimal_data()
    {
        $data = [
            'name' => ['en' => 'Test Task'],
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_MEDIUM
        ];

        $dto = CreateTaskDTO::fromArray($data);

        $this->assertEquals(['en' => 'Test Task'], $dto->name);
        $this->assertNull($dto->description);
        $this->assertEquals(Task::STATUS_PENDING, $dto->status);
        $this->assertEquals(Task::PRIORITY_MEDIUM, $dto->priority);
        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->parentId);
    }

    public function test_to_model_data_returns_correct_format()
    {
        $dueDate = Carbon::parse('2024-12-31 23:59:59');
        
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: ['en' => 'Test description'],
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: $dueDate,
            parentId: 123
        );

        $modelData = $dto->toModelData(456);

        $expected = [
            'name' => ['en' => 'Test Task'],
            'description' => ['en' => 'Test description'],
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_MEDIUM,
            'due_date' => $dueDate,
            'parent_id' => 123,
            'user_id' => 456,
        ];

        $this->assertEquals($expected, $modelData);
    }

    public function test_validation_passes_with_valid_data()
    {
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: Carbon::tomorrow(),
            parentId: null
        );

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->validate());
    }

    public function test_validation_fails_with_empty_name()
    {
        $dto = new CreateTaskDTO(
            name: [],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('name.en', $errors);
    }

    public function test_validation_fails_with_missing_english_name()
    {
        $dto = new CreateTaskDTO(
            name: ['fr' => 'Tâche de test'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('name.en', $errors);
    }

    public function test_validation_fails_with_invalid_status()
    {
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: null,
            status: 'invalid_status',
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('status', $errors);
    }

    public function test_validation_fails_with_invalid_priority()
    {
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: 'invalid_priority',
            dueDate: null,
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('priority', $errors);
    }

    public function test_validation_fails_with_past_due_date()
    {
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: Carbon::yesterday(),
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('due_date', $errors);
    }

    public function test_get_localized_name_returns_correct_translation()
    {
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task', 'fr' => 'Tâche de test'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertEquals('Test Task', $dto->getLocalizedName('en'));
        $this->assertEquals('Tâche de test', $dto->getLocalizedName('fr'));
        $this->assertEquals('Test Task', $dto->getLocalizedName('de')); // Falls back to English
    }

    public function test_get_localized_description_returns_correct_translation()
    {
        $dto = new CreateTaskDTO(
            name: ['en' => 'Test Task'],
            description: ['en' => 'Test description', 'fr' => 'Description de test'],
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertEquals('Test description', $dto->getLocalizedDescription('en'));
        $this->assertEquals('Description de test', $dto->getLocalizedDescription('fr'));
        $this->assertEquals('Test description', $dto->getLocalizedDescription('de')); // Falls back to English
    }

    public function test_is_subtask_returns_correct_value()
    {
        $subtaskDto = new CreateTaskDTO(
            name: ['en' => 'Subtask'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: 123
        );

        $rootTaskDto = new CreateTaskDTO(
            name: ['en' => 'Root Task'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertTrue($subtaskDto->isSubtask());
        $this->assertFalse($rootTaskDto->isSubtask());
    }

    public function test_has_due_date_returns_correct_value()
    {
        $withDueDateDto = new CreateTaskDTO(
            name: ['en' => 'Task with due date'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: Carbon::tomorrow(),
            parentId: null
        );

        $withoutDueDateDto = new CreateTaskDTO(
            name: ['en' => 'Task without due date'],
            description: null,
            status: Task::STATUS_PENDING,
            priority: Task::PRIORITY_MEDIUM,
            dueDate: null,
            parentId: null
        );

        $this->assertTrue($withDueDateDto->hasDueDate());
        $this->assertFalse($withoutDueDateDto->hasDueDate());
    }
}