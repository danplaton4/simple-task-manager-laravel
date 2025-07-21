<?php

namespace Tests\Unit\DTOs\Task;

use App\DTOs\Task\UpdateTaskDTO;
use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class UpdateTaskDTOTest extends TestCase
{
    public function test_can_create_from_array()
    {
        $data = [
            'name' => ['en' => 'Updated Task', 'fr' => 'Tâche mise à jour'],
            'description' => ['en' => 'Updated description'],
            'status' => Task::STATUS_COMPLETED,
            'priority' => Task::PRIORITY_HIGH,
            'due_date' => '2024-12-31 23:59:59',
            'parent_id' => 456
        ];

        $dto = UpdateTaskDTO::fromArray($data);

        $this->assertEquals(['en' => 'Updated Task', 'fr' => 'Tâche mise à jour'], $dto->name);
        $this->assertEquals(['en' => 'Updated description'], $dto->description);
        $this->assertEquals(Task::STATUS_COMPLETED, $dto->status);
        $this->assertEquals(Task::PRIORITY_HIGH, $dto->priority);
        $this->assertInstanceOf(Carbon::class, $dto->dueDate);
        $this->assertEquals(456, $dto->parentId);
    }

    public function test_can_create_with_null_values_to_clear_fields()
    {
        $data = [
            'name' => ['en' => 'Updated Task'],
            'due_date' => null,
            'parent_id' => null
        ];

        $dto = UpdateTaskDTO::fromArray($data);

        $this->assertEquals(['en' => 'Updated Task'], $dto->name);
        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->parentId);
        $this->assertTrue($dto->clearDueDate);
        $this->assertTrue($dto->clearParent);
    }

    public function test_to_model_data_returns_only_updated_fields()
    {
        $dto = new UpdateTaskDTO(
            name: ['en' => 'Updated Task'],
            description: null,
            status: Task::STATUS_COMPLETED,
            priority: null,
            dueDate: null,
            parentId: null,
            clearDueDate: false,
            clearParent: false
        );

        $modelData = $dto->toModelData();

        $expected = [
            'name' => ['en' => 'Updated Task'],
            'status' => Task::STATUS_COMPLETED,
        ];

        $this->assertEquals($expected, $modelData);
    }

    public function test_to_model_data_includes_cleared_fields()
    {
        $dto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: null,
            priority: null,
            dueDate: null,
            parentId: null,
            clearDueDate: true,
            clearParent: true
        );

        $modelData = $dto->toModelData();

        $expected = [
            'due_date' => null,
            'parent_id' => null,
        ];

        $this->assertEquals($expected, $modelData);
    }

    public function test_validation_passes_with_valid_data()
    {
        $dto = new UpdateTaskDTO(
            name: ['en' => 'Updated Task'],
            description: null,
            status: Task::STATUS_COMPLETED,
            priority: Task::PRIORITY_HIGH,
            dueDate: Carbon::tomorrow(),
            parentId: 123
        );

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->validate());
    }

    public function test_validation_fails_with_invalid_status()
    {
        $dto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: 'invalid_status',
            priority: null,
            dueDate: null,
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('status', $errors);
    }

    public function test_validation_fails_with_invalid_priority()
    {
        $dto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: null,
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
        $dto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: null,
            priority: null,
            dueDate: Carbon::yesterday(),
            parentId: null
        );

        $this->assertFalse($dto->isValid());
        $errors = $dto->validate();
        $this->assertArrayHasKey('due_date', $errors);
    }

    public function test_has_updates_returns_correct_value()
    {
        $dtoWithUpdates = new UpdateTaskDTO(
            name: ['en' => 'Updated Task'],
            description: null,
            status: null,
            priority: null,
            dueDate: null,
            parentId: null
        );

        $dtoWithoutUpdates = new UpdateTaskDTO();

        $this->assertTrue($dtoWithUpdates->hasUpdates());
        $this->assertFalse($dtoWithoutUpdates->hasUpdates());
    }

    public function test_get_updated_fields_returns_correct_list()
    {
        $dto = new UpdateTaskDTO(
            name: ['en' => 'Updated Task'],
            description: null,
            status: Task::STATUS_COMPLETED,
            priority: null,
            dueDate: null,
            parentId: null,
            clearDueDate: true,
            clearParent: false
        );

        $updatedFields = $dto->getUpdatedFields();

        $this->assertContains('name', $updatedFields);
        $this->assertContains('status', $updatedFields);
        $this->assertContains('due_date', $updatedFields);
        $this->assertNotContains('description', $updatedFields);
        $this->assertNotContains('priority', $updatedFields);
        $this->assertNotContains('parent_id', $updatedFields);
    }

    public function test_get_localized_name_returns_correct_translation()
    {
        $dto = new UpdateTaskDTO(
            name: ['en' => 'Updated Task', 'fr' => 'Tâche mise à jour'],
            description: null,
            status: null,
            priority: null,
            dueDate: null,
            parentId: null
        );

        $this->assertEquals('Updated Task', $dto->getLocalizedName('en'));
        $this->assertEquals('Tâche mise à jour', $dto->getLocalizedName('fr'));
        $this->assertEquals('Updated Task', $dto->getLocalizedName('de')); // Falls back to English
    }

    public function test_get_localized_name_returns_null_when_no_name()
    {
        $dto = new UpdateTaskDTO();

        $this->assertNull($dto->getLocalizedName('en'));
    }

    public function test_is_being_completed_returns_correct_value()
    {
        $completingDto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: Task::STATUS_COMPLETED,
            priority: null,
            dueDate: null,
            parentId: null
        );

        $notCompletingDto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: Task::STATUS_IN_PROGRESS,
            priority: null,
            dueDate: null,
            parentId: null
        );

        $this->assertTrue($completingDto->isBeingCompleted());
        $this->assertFalse($notCompletingDto->isBeingCompleted());
    }

    public function test_is_parent_changing_returns_correct_value()
    {
        $changingParentDto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: null,
            priority: null,
            dueDate: null,
            parentId: 123
        );

        $clearingParentDto = new UpdateTaskDTO(
            name: null,
            description: null,
            status: null,
            priority: null,
            dueDate: null,
            parentId: null,
            clearDueDate: false,
            clearParent: true
        );

        $notChangingParentDto = new UpdateTaskDTO();

        $this->assertTrue($changingParentDto->isParentChanging());
        $this->assertTrue($clearingParentDto->isParentChanging());
        $this->assertFalse($notChangingParentDto->isParentChanging());
    }
}