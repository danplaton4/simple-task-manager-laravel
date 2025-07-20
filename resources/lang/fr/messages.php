<?php

return [
    'task' => [
        'created' => 'Tâche créée avec succès',
        'updated' => 'Tâche mise à jour avec succès',
        'deleted' => 'Tâche supprimée avec succès',
        'restored' => 'Tâche restaurée avec succès',
        'not_found' => 'Tâche non trouvée',
        'unauthorized' => 'Vous n\'êtes pas autorisé à accéder à cette tâche',
        'status' => [
            'pending' => 'En attente',
            'in_progress' => 'En cours',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
        ],
        'priority' => [
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'urgent' => 'Urgente',
        ],
        'validation' => [
            'name_required' => 'Le nom de la tâche est requis',
            'name_max' => 'Le nom de la tâche ne peut pas dépasser 255 caractères',
            'description_max' => 'La description de la tâche ne peut pas dépasser 1000 caractères',
            'status_invalid' => 'Statut de tâche invalide',
            'priority_invalid' => 'Priorité de tâche invalide',
            'due_date_future' => 'La date d\'échéance doit être dans le futur',
            'parent_exists' => 'La tâche parent n\'existe pas',
            'circular_reference' => 'Impossible de créer des références circulaires de tâches',
        ],
    ],
    'auth' => [
        'login_success' => 'Connexion réussie',
        'login_failed' => 'Identifiants invalides',
        'logout_success' => 'Déconnexion réussie',
        'register_success' => 'Inscription réussie',
        'token_refresh_success' => 'Token actualisé avec succès',
        'unauthorized' => 'Accès non autorisé',
        'validation' => [
            'email_required' => 'L\'email est requis',
            'email_invalid' => 'Format d\'email invalide',
            'password_required' => 'Le mot de passe est requis',
            'password_min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'name_required' => 'Le nom est requis',
        ],
    ],
    'general' => [
        'success' => 'Opération terminée avec succès',
        'error' => 'Une erreur s\'est produite',
        'validation_failed' => 'Échec de la validation',
        'server_error' => 'Erreur interne du serveur',
        'not_found' => 'Ressource non trouvée',
    ],
    'email' => [
        'task_created' => [
            'subject' => 'Nouvelle tâche créée : :task_name',
            'greeting' => 'Bonjour :user_name,',
            'intro' => 'Une nouvelle tâche a été créée dans votre système de gestion des tâches.',
            'task_details' => 'Détails de la tâche :',
            'name' => 'Nom : :name',
            'description' => 'Description : :description',
            'status' => 'Statut : :status',
            'priority' => 'Priorité : :priority',
            'due_date' => 'Date d\'échéance : :due_date',
            'parent_task' => 'Tâche parent : :parent_name',
            'view_task' => 'Voir la tâche',
            'footer' => 'Merci d\'utiliser notre système de gestion des tâches !',
        ],
        'task_updated' => [
            'subject' => 'Tâche mise à jour : :task_name',
            'greeting' => 'Bonjour :user_name,',
            'intro' => 'Une de vos tâches a été mise à jour.',
            'task_details' => 'Détails de la tâche mise à jour :',
            'name' => 'Nom : :name',
            'description' => 'Description : :description',
            'status' => 'Statut : :status',
            'priority' => 'Priorité : :priority',
            'due_date' => 'Date d\'échéance : :due_date',
            'parent_task' => 'Tâche parent : :parent_name',
            'view_task' => 'Voir la tâche',
            'footer' => 'Merci d\'utiliser notre système de gestion des tâches !',
        ],
        'task_completed' => [
            'subject' => 'Tâche terminée : :task_name',
            'greeting' => 'Bonjour :user_name,',
            'intro' => 'Félicitations ! Vous avez terminé une tâche.',
            'task_details' => 'Détails de la tâche terminée :',
            'name' => 'Nom : :name',
            'description' => 'Description : :description',
            'completed_at' => 'Terminée le : :completed_at',
            'priority' => 'Priorité : :priority',
            'due_date' => 'Date d\'échéance : :due_date',
            'parent_task' => 'Tâche parent : :parent_name',
            'view_task' => 'Voir la tâche',
            'footer' => 'Continuez votre excellent travail !',
        ],
        'task_deleted' => [
            'subject' => 'Tâche supprimée : :task_name',
            'content' => 'Bonjour :user_name,

Votre tâche ":task_name" a été supprimée de votre système de gestion des tâches.

Si cela a été fait par erreur, veuillez contacter le support.

Cordialement,
Équipe de gestion des tâches',
        ],
        'task_due_soon' => [
            'subject' => 'Tâche bientôt due : :task_name',
            'content' => 'Bonjour :user_name,

Ceci est un rappel que votre tâche ":task_name" est bientôt due.

Date d\'échéance : :due_date

Veuillez vous assurer de la terminer à temps.

Cordialement,
Équipe de gestion des tâches',
        ],
        'task_overdue' => [
            'subject' => 'Tâche en retard : :task_name',
            'content' => 'Bonjour :user_name,

Votre tâche ":task_name" est maintenant en retard.

Date d\'échéance originale : :due_date
Jours de retard : :days_overdue

Veuillez terminer cette tâche dès que possible.

Cordialement,
Équipe de gestion des tâches',
        ],
        'daily_digest' => [
            'subject' => 'Résumé quotidien des tâches - :date',
            'greeting' => 'Bonjour :user_name,',
            'intro' => 'Voici votre résumé quotidien des tâches pour :period :',
            'summary' => 'Résumé :',
            'created_tasks' => ':count nouvelles tâches créées',
            'completed_tasks' => ':count tâches terminées',
            'total_active' => ':count tâches actives restantes',
            'due_soon_section' => 'Tâches bientôt dues :',
            'overdue_section' => 'Tâches en retard (action requise) :',
            'completed_section' => 'Tâches récemment terminées :',
            'footer' => 'Voir toutes vos tâches à :',
            'unsubscribe' => 'Pour vous désabonner des résumés quotidiens, mettez à jour vos préférences de notification dans les paramètres de votre compte.',
        ],
        'weekly_digest' => [
            'subject' => 'Résumé hebdomadaire des tâches - :date',
            'greeting' => 'Bonjour :user_name,',
            'intro' => 'Voici votre résumé hebdomadaire des tâches pour :period :',
            'summary' => 'Résumé :',
            'created_tasks' => ':count nouvelles tâches créées',
            'completed_tasks' => ':count tâches terminées',
            'total_active' => ':count tâches actives restantes',
            'due_soon_section' => 'Tâches bientôt dues :',
            'overdue_section' => 'Tâches en retard (action requise) :',
            'completed_section' => 'Tâches récemment terminées :',
            'footer' => 'Voir toutes vos tâches à :',
            'unsubscribe' => 'Pour vous désabonner des résumés hebdomadaires, mettez à jour vos préférences de notification dans les paramètres de votre compte.',
        ],
        'common' => [
            'no_description' => 'Aucune description fournie',
            'no_due_date' => 'Aucune date d\'échéance définie',
            'no_parent_task' => 'Ceci est une tâche racine',
        ],
    ],
];