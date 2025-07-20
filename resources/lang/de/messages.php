<?php

return [
    'task' => [
        'created' => 'Aufgabe erfolgreich erstellt',
        'updated' => 'Aufgabe erfolgreich aktualisiert',
        'deleted' => 'Aufgabe erfolgreich gelöscht',
        'restored' => 'Aufgabe erfolgreich wiederhergestellt',
        'not_found' => 'Aufgabe nicht gefunden',
        'unauthorized' => 'Sie sind nicht berechtigt, auf diese Aufgabe zuzugreifen',
        'status' => [
            'pending' => 'Ausstehend',
            'in_progress' => 'In Bearbeitung',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgebrochen',
        ],
        'priority' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'urgent' => 'Dringend',
        ],
        'validation' => [
            'name_required' => 'Aufgabenname ist erforderlich',
            'name_max' => 'Aufgabenname darf nicht länger als 255 Zeichen sein',
            'description_max' => 'Aufgabenbeschreibung darf nicht länger als 1000 Zeichen sein',
            'status_invalid' => 'Ungültiger Aufgabenstatus',
            'priority_invalid' => 'Ungültige Aufgabenpriorität',
            'due_date_future' => 'Fälligkeitsdatum muss in der Zukunft liegen',
            'parent_exists' => 'Übergeordnete Aufgabe existiert nicht',
            'circular_reference' => 'Zirkuläre Aufgabenreferenzen können nicht erstellt werden',
        ],
    ],
    'auth' => [
        'login_success' => 'Anmeldung erfolgreich',
        'login_failed' => 'Ungültige Anmeldedaten',
        'logout_success' => 'Abmeldung erfolgreich',
        'register_success' => 'Registrierung erfolgreich',
        'token_refresh_success' => 'Token erfolgreich aktualisiert',
        'unauthorized' => 'Unbefugter Zugriff',
        'validation' => [
            'email_required' => 'E-Mail ist erforderlich',
            'email_invalid' => 'Ungültiges E-Mail-Format',
            'password_required' => 'Passwort ist erforderlich',
            'password_min' => 'Passwort muss mindestens 8 Zeichen lang sein',
            'name_required' => 'Name ist erforderlich',
        ],
    ],
    'general' => [
        'success' => 'Vorgang erfolgreich abgeschlossen',
        'error' => 'Ein Fehler ist aufgetreten',
        'validation_failed' => 'Validierung fehlgeschlagen',
        'server_error' => 'Interner Serverfehler',
        'not_found' => 'Ressource nicht gefunden',
    ],
];