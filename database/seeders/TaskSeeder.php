<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get existing users
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Create comprehensive task scenarios for testing
        $this->createProjectManagementTasks($users->first());
        $this->createDevelopmentTasks($users->skip(1)->first());
        $this->createTestingTasks($users->skip(2)->first());
        
        // Create random tasks for remaining users
        foreach ($users->skip(3) as $user) {
            $this->createRandomTasksForUser($user);
        }

        $this->command->info('Task seeding completed successfully!');
    }

    /**
     * Create project management related tasks
     */
    private function createProjectManagementTasks(User $user): void
    {
        $projectTask = Task::create([
            'user_id' => $user->id,
            'name' => [
                'en' => 'Project Planning Phase',
                'de' => 'Projektplanungsphase',
                'fr' => 'Phase de planification du projet'
            ],
            'description' => [
                'en' => 'Complete all planning activities for the new project',
                'de' => 'Alle Planungsaktivitäten für das neue Projekt abschließen',
                'fr' => 'Terminer toutes les activités de planification pour le nouveau projet'
            ],
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(10),
        ]);

        // Create subtasks for project planning
        Task::create([
            'user_id' => $user->id,
            'parent_id' => $projectTask->id,
            'name' => [
                'en' => 'Requirements Gathering',
                'de' => 'Anforderungssammlung',
                'fr' => 'Collecte des exigences'
            ],
            'description' => [
                'en' => 'Gather and document all project requirements',
                'de' => 'Alle Projektanforderungen sammeln und dokumentieren',
                'fr' => 'Rassembler et documenter toutes les exigences du projet'
            ],
            'status' => 'completed',
            'priority' => 'high',
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $projectTask->id,
            'name' => [
                'en' => 'Technical Architecture Design',
                'de' => 'Technische Architektur Design',
                'fr' => 'Conception de l\'architecture technique'
            ],
            'description' => [
                'en' => 'Design the technical architecture and system components',
                'de' => 'Technische Architektur und Systemkomponenten entwerfen',
                'fr' => 'Concevoir l\'architecture technique et les composants du système'
            ],
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $projectTask->id,
            'name' => [
                'en' => 'Resource Allocation',
                'de' => 'Ressourcenzuteilung',
                'fr' => 'Allocation des ressources'
            ],
            'description' => [
                'en' => 'Allocate team members and resources to project tasks',
                'de' => 'Teammitglieder und Ressourcen zu Projektaufgaben zuweisen',
                'fr' => 'Allouer les membres de l\'équipe et les ressources aux tâches du projet'
            ],
            'status' => 'pending',
            'priority' => 'medium',
        ]);
    }

    /**
     * Create development related tasks
     */
    private function createDevelopmentTasks(User $user): void
    {
        $devTask = Task::create([
            'user_id' => $user->id,
            'name' => [
                'en' => 'Backend Development',
                'de' => 'Backend-Entwicklung',
                'fr' => 'Développement Backend'
            ],
            'description' => [
                'en' => 'Implement all backend functionality and APIs',
                'de' => 'Alle Backend-Funktionalitäten und APIs implementieren',
                'fr' => 'Implémenter toutes les fonctionnalités backend et les API'
            ],
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(21),
        ]);

        // Backend subtasks
        Task::create([
            'user_id' => $user->id,
            'parent_id' => $devTask->id,
            'name' => [
                'en' => 'Database Schema Implementation',
                'de' => 'Datenbankschema-Implementierung',
                'fr' => 'Implémentation du schéma de base de données'
            ],
            'description' => [
                'en' => 'Create and implement database migrations and models',
                'de' => 'Datenbankmigrationen und Modelle erstellen und implementieren',
                'fr' => 'Créer et implémenter les migrations et modèles de base de données'
            ],
            'status' => 'completed',
            'priority' => 'high',
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $devTask->id,
            'name' => [
                'en' => 'API Endpoints Development',
                'de' => 'API-Endpunkte Entwicklung',
                'fr' => 'Développement des points de terminaison API'
            ],
            'description' => [
                'en' => 'Develop RESTful API endpoints for all features',
                'de' => 'RESTful API-Endpunkte für alle Features entwickeln',
                'fr' => 'Développer les points de terminaison API RESTful pour toutes les fonctionnalités'
            ],
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $devTask->id,
            'name' => [
                'en' => 'Authentication System',
                'de' => 'Authentifizierungssystem',
                'fr' => 'Système d\'authentification'
            ],
            'description' => [
                'en' => 'Implement secure user authentication and authorization',
                'de' => 'Sichere Benutzerauthentifizierung und -autorisierung implementieren',
                'fr' => 'Implémenter l\'authentification et l\'autorisation sécurisées des utilisateurs'
            ],
            'status' => 'pending',
            'priority' => 'urgent',
        ]);

        // Frontend task
        $frontendTask = Task::create([
            'user_id' => $user->id,
            'name' => [
                'en' => 'Frontend Development',
                'de' => 'Frontend-Entwicklung',
                'fr' => 'Développement Frontend'
            ],
            'description' => [
                'en' => 'Build responsive React application with TypeScript',
                'de' => 'Responsive React-Anwendung mit TypeScript erstellen',
                'fr' => 'Construire une application React responsive avec TypeScript'
            ],
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDays(28),
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $frontendTask->id,
            'name' => [
                'en' => 'Component Library Setup',
                'de' => 'Komponentenbibliothek-Setup',
                'fr' => 'Configuration de la bibliothèque de composants'
            ],
            'description' => [
                'en' => 'Set up reusable component library with Tailwind CSS',
                'de' => 'Wiederverwendbare Komponentenbibliothek mit Tailwind CSS einrichten',
                'fr' => 'Configurer une bibliothèque de composants réutilisables avec Tailwind CSS'
            ],
            'status' => 'pending',
            'priority' => 'medium',
        ]);
    }

    /**
     * Create testing related tasks
     */
    private function createTestingTasks(User $user): void
    {
        $testingTask = Task::create([
            'user_id' => $user->id,
            'name' => [
                'en' => 'Quality Assurance & Testing',
                'de' => 'Qualitätssicherung & Tests',
                'fr' => 'Assurance qualité et tests'
            ],
            'description' => [
                'en' => 'Comprehensive testing strategy implementation',
                'de' => 'Umfassende Teststrategie-Implementierung',
                'fr' => 'Implémentation d\'une stratégie de test complète'
            ],
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDays(35),
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $testingTask->id,
            'name' => [
                'en' => 'Unit Tests Implementation',
                'de' => 'Unit-Tests Implementierung',
                'fr' => 'Implémentation des tests unitaires'
            ],
            'description' => [
                'en' => 'Write comprehensive unit tests using Pest PHP',
                'de' => 'Umfassende Unit-Tests mit Pest PHP schreiben',
                'fr' => 'Écrire des tests unitaires complets en utilisant Pest PHP'
            ],
            'status' => 'pending',
            'priority' => 'high',
        ]);

        Task::create([
            'user_id' => $user->id,
            'parent_id' => $testingTask->id,
            'name' => [
                'en' => 'Integration Testing',
                'de' => 'Integrationstests',
                'fr' => 'Tests d\'intégration'
            ],
            'description' => [
                'en' => 'Test API endpoints and database interactions',
                'de' => 'API-Endpunkte und Datenbankinteraktionen testen',
                'fr' => 'Tester les points de terminaison API et les interactions avec la base de données'
            ],
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        // Overdue task for testing
        Task::create([
            'user_id' => $user->id,
            'name' => [
                'en' => 'Security Audit',
                'de' => 'Sicherheitsaudit',
                'fr' => 'Audit de sécurité'
            ],
            'description' => [
                'en' => 'Conduct comprehensive security audit of the application',
                'de' => 'Umfassendes Sicherheitsaudit der Anwendung durchführen',
                'fr' => 'Effectuer un audit de sécurité complet de l\'application'
            ],
            'status' => 'pending',
            'priority' => 'urgent',
            'due_date' => now()->subDays(3), // Overdue task
        ]);
    }

    /**
     * Create random tasks for a user
     */
    private function createRandomTasksForUser(User $user): void
    {
        // Create 3-7 random tasks per user
        $taskCount = rand(3, 7);
        $tasks = Task::factory($taskCount)->create([
            'user_id' => $user->id,
        ]);

        // For some tasks, create subtasks (30% chance)
        foreach ($tasks->take(2) as $task) {
            if (rand(1, 10) <= 3) { // 30% chance
                Task::factory(rand(1, 4))->create([
                    'user_id' => $user->id,
                    'parent_id' => $task->id,
                ]);
            }
        }
    }
}
