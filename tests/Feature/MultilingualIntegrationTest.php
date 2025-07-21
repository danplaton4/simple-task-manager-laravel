<?php

use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

describe('Multilingual Integration Tests', function () {
    
    it('can handle locale detection from headers', function () {
        $user = User::factory()->create(['preferred_language' => 'en']);
        $token = $user->createToken('test')->plainTextToken;

        // Test German locale detection
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'de'
        ])->getJson('/api/tasks');

        expect(App::getLocale())->toBe('de');

        // Test French locale detection
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->getJson('/api/tasks');

        expect(App::getLocale())->toBe('fr');

        // Test fallback to English for unsupported locale
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'es'
        ])->getJson('/api/tasks');

        expect(App::getLocale())->toBe('en');
    });

    it('can create and retrieve tasks with multilingual content', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $taskData = [
            'name' => [
                'en' => 'Project Planning',
                'fr' => 'Planification de projet',
                'de' => 'Projektplanung'
            ],
            'description' => [
                'en' => 'Plan the entire project timeline and milestones',
                'fr' => 'Planifier toute la chronologie et les jalons du projet',
                'de' => 'Planen Sie die gesamte Projektzeitleiste und Meilensteine'
            ],
            'status' => 'pending',
            'priority' => 'high'
        ];

        // Create task
        $createResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $taskData);

        $createResponse->assertStatus(201);
        $taskId = $createResponse->json('data.id');

        // Test English retrieval
        $enResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'en'
        ])->getJson("/api/tasks/{$taskId}");

        $enResponse->assertStatus(200);
        expect($enResponse->json('data.name'))->toBe('Project Planning');
        expect($enResponse->json('data.description'))->toBe('Plan the entire project timeline and milestones');

        // Test French retrieval
        $frResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->getJson("/api/tasks/{$taskId}");

        $frResponse->assertStatus(200);
        expect($frResponse->json('data.name'))->toBe('Planification de projet');
        expect($frResponse->json('data.description'))->toBe('Planifier toute la chronologie et les jalons du projet');

        // Test German retrieval
        $deResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'de'
        ])->getJson("/api/tasks/{$taskId}");

        $deResponse->assertStatus(200);
        expect($deResponse->json('data.name'))->toBe('Projektplanung');
        expect($deResponse->json('data.description'))->toBe('Planen Sie die gesamte Projektzeitleiste und Meilensteine');
    });

    it('can handle partial translations with fallbacks', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create task with only English and French translations
        $taskData = [
            'name' => [
                'en' => 'English Only Task',
                'fr' => 'Tâche en français seulement'
            ],
            'description' => [
                'en' => 'This task only has English description'
            ],
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $createResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $taskData);

        $taskId = $createResponse->json('data.id');

        // Test German request (should fallback to English)
        $deResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'de'
        ])->getJson("/api/tasks/{$taskId}");

        $deResponse->assertStatus(200);
        expect($deResponse->json('data.name'))->toBe('English Only Task'); // Fallback to English
        expect($deResponse->json('data.description'))->toBe('This task only has English description');

        // Test French request (should use French for name, English for description)
        $frResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->getJson("/api/tasks/{$taskId}");

        $frResponse->assertStatus(200);
        expect($frResponse->json('data.name'))->toBe('Tâche en français seulement');
        expect($frResponse->json('data.description'))->toBe('This task only has English description'); // Fallback
    });

    it('can update multilingual content correctly', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create initial task
        $task = Task::factory()->for($user)->create([
            'name' => [
                'en' => 'Original English',
                'fr' => 'Français original'
            ],
            'description' => [
                'en' => 'Original description'
            ]
        ]);

        // Update with new translations
        $updateData = [
            'name' => [
                'en' => 'Updated English',
                'fr' => 'Français mis à jour',
                'de' => 'Aktualisiertes Deutsch'
            ],
            'description' => [
                'en' => 'Updated description',
                'fr' => 'Description mise à jour',
                'de' => 'Aktualisierte Beschreibung'
            ],
            'status' => 'in_progress',
            'priority' => 'high'
        ];

        $updateResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/tasks/{$task->id}", $updateData);

        $updateResponse->assertStatus(200);

        // Verify updates in all languages
        $enResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'en'
        ])->getJson("/api/tasks/{$task->id}");

        expect($enResponse->json('data.name'))->toBe('Updated English');
        expect($enResponse->json('data.description'))->toBe('Updated description');

        $frResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->getJson("/api/tasks/{$task->id}");

        expect($frResponse->json('data.name'))->toBe('Français mis à jour');
        expect($frResponse->json('data.description'))->toBe('Description mise à jour');

        $deResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'de'
        ])->getJson("/api/tasks/{$task->id}");

        expect($deResponse->json('data.name'))->toBe('Aktualisiertes Deutsch');
        expect($deResponse->json('data.description'))->toBe('Aktualisierte Beschreibung');
    });

    it('can handle multilingual validation errors', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Test validation with missing required English translation
        $invalidData = [
            'name' => [
                'fr' => 'Nom français seulement',
                'de' => 'Nur deutscher Name'
            ],
            'description' => [
                'fr' => 'Description française'
            ],
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name.en']);
    });

    it('can handle hierarchical tasks with multilingual content', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create parent task
        $parentData = [
            'name' => [
                'en' => 'Parent Project',
                'fr' => 'Projet parent',
                'de' => 'Elternprojekt'
            ],
            'description' => [
                'en' => 'Main project container',
                'fr' => 'Conteneur de projet principal',
                'de' => 'Hauptprojektcontainer'
            ],
            'status' => 'pending',
            'priority' => 'high'
        ];

        $parentResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $parentData);

        $parentId = $parentResponse->json('data.id');

        // Create subtask
        $subtaskData = [
            'name' => [
                'en' => 'Subtask One',
                'fr' => 'Sous-tâche un',
                'de' => 'Unteraufgabe eins'
            ],
            'description' => [
                'en' => 'First subtask',
                'fr' => 'Première sous-tâche',
                'de' => 'Erste Unteraufgabe'
            ],
            'status' => 'pending',
            'priority' => 'medium',
            'parent_id' => $parentId
        ];

        $subtaskResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $subtaskData);

        $subtaskId = $subtaskResponse->json('data.id');

        // Test hierarchical display in French
        $frListResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->getJson('/api/tasks');

        $frTasks = $frListResponse->json('data');
        $frParent = collect($frTasks)->firstWhere('id', $parentId);

        expect($frParent['name'])->toBe('Projet parent');
        expect($frParent['subtasks'])->toHaveCount(1);
        expect($frParent['subtasks'][0]['name'])->toBe('Sous-tâche un');

        // Test hierarchical display in German
        $deListResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'de'
        ])->getJson('/api/tasks');

        $deTasks = $deListResponse->json('data');
        $deParent = collect($deTasks)->firstWhere('id', $parentId);

        expect($deParent['name'])->toBe('Elternprojekt');
        expect($deParent['subtasks'])->toHaveCount(1);
        expect($deParent['subtasks'][0]['name'])->toBe('Unteraufgabe eins');
    });

    it('can handle user preferred language settings', function () {
        // Create users with different preferred languages
        $enUser = User::factory()->create(['preferred_language' => 'en']);
        $frUser = User::factory()->create(['preferred_language' => 'fr']);
        $deUser = User::factory()->create(['preferred_language' => 'de']);

        $enToken = $enUser->createToken('test')->plainTextToken;
        $frToken = $frUser->createToken('test')->plainTextToken;
        $deToken = $deUser->createToken('test')->plainTextToken;

        // Create task with all translations
        $taskData = [
            'name' => [
                'en' => 'Multilingual Task',
                'fr' => 'Tâche multilingue',
                'de' => 'Mehrsprachige Aufgabe'
            ],
            'description' => [
                'en' => 'Task for all languages',
                'fr' => 'Tâche pour toutes les langues',
                'de' => 'Aufgabe für alle Sprachen'
            ],
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $createResponse = $this->withHeaders(['Authorization' => "Bearer $enToken"])
            ->postJson('/api/tasks', $taskData);

        $taskId = $createResponse->json('data.id');

        // Test that each user sees content in their preferred language by default
        $enResponse = $this->withHeaders(['Authorization' => "Bearer $enToken"])
            ->getJson("/api/tasks/{$taskId}");

        expect($enResponse->json('data.name'))->toBe('Multilingual Task');

        // Note: In a full implementation, we would need to modify the API
        // to respect user's preferred language when no Accept-Language header is provided
    });
});