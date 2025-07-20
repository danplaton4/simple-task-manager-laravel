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
];