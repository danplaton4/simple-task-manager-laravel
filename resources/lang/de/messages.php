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
    'email' => [
        'task_created' => [
            'subject' => 'Neue Aufgabe erstellt: :task_name',
            'greeting' => 'Hallo :user_name,',
            'intro' => 'Eine neue Aufgabe wurde in Ihrem Aufgabenverwaltungssystem erstellt.',
            'task_details' => 'Aufgabendetails:',
            'name' => 'Name: :name',
            'description' => 'Beschreibung: :description',
            'status' => 'Status: :status',
            'priority' => 'Priorität: :priority',
            'due_date' => 'Fälligkeitsdatum: :due_date',
            'parent_task' => 'Übergeordnete Aufgabe: :parent_name',
            'view_task' => 'Aufgabe anzeigen',
            'footer' => 'Vielen Dank für die Nutzung unseres Aufgabenverwaltungssystems!',
        ],
        'task_updated' => [
            'subject' => 'Aufgabe aktualisiert: :task_name',
            'greeting' => 'Hallo :user_name,',
            'intro' => 'Eine Ihrer Aufgaben wurde aktualisiert.',
            'task_details' => 'Aktualisierte Aufgabendetails:',
            'name' => 'Name: :name',
            'description' => 'Beschreibung: :description',
            'status' => 'Status: :status',
            'priority' => 'Priorität: :priority',
            'due_date' => 'Fälligkeitsdatum: :due_date',
            'parent_task' => 'Übergeordnete Aufgabe: :parent_name',
            'view_task' => 'Aufgabe anzeigen',
            'footer' => 'Vielen Dank für die Nutzung unseres Aufgabenverwaltungssystems!',
        ],
        'task_completed' => [
            'subject' => 'Aufgabe abgeschlossen: :task_name',
            'greeting' => 'Hallo :user_name,',
            'intro' => 'Herzlichen Glückwunsch! Sie haben eine Aufgabe abgeschlossen.',
            'task_details' => 'Abgeschlossene Aufgabendetails:',
            'name' => 'Name: :name',
            'description' => 'Beschreibung: :description',
            'completed_at' => 'Abgeschlossen am: :completed_at',
            'priority' => 'Priorität: :priority',
            'due_date' => 'Fälligkeitsdatum: :due_date',
            'parent_task' => 'Übergeordnete Aufgabe: :parent_name',
            'view_task' => 'Aufgabe anzeigen',
            'footer' => 'Machen Sie weiter so!',
        ],
        'task_deleted' => [
            'subject' => 'Aufgabe gelöscht: :task_name',
            'content' => 'Hallo :user_name,

Ihre Aufgabe ":task_name" wurde aus Ihrem Aufgabenverwaltungssystem gelöscht.

Falls dies versehentlich geschehen ist, wenden Sie sich bitte an den Support.

Mit freundlichen Grüßen,
Aufgabenverwaltungs-Team',
        ],
        'task_due_soon' => [
            'subject' => 'Aufgabe bald fällig: :task_name',
            'content' => 'Hallo :user_name,

Dies ist eine Erinnerung, dass Ihre Aufgabe ":task_name" bald fällig ist.

Fälligkeitsdatum: :due_date

Bitte stellen Sie sicher, dass Sie sie rechtzeitig abschließen.

Mit freundlichen Grüßen,
Aufgabenverwaltungs-Team',
        ],
        'task_overdue' => [
            'subject' => 'Aufgabe überfällig: :task_name',
            'content' => 'Hallo :user_name,

Ihre Aufgabe ":task_name" ist jetzt überfällig.

Ursprüngliches Fälligkeitsdatum: :due_date
Tage überfällig: :days_overdue

Bitte schließen Sie diese Aufgabe so schnell wie möglich ab.

Mit freundlichen Grüßen,
Aufgabenverwaltungs-Team',
        ],
        'daily_digest' => [
            'subject' => 'Tägliche Aufgabenzusammenfassung - :date',
            'greeting' => 'Hallo :user_name,',
            'intro' => 'Hier ist Ihre tägliche Aufgabenzusammenfassung für :period:',
            'summary' => 'Zusammenfassung:',
            'created_tasks' => ':count neue Aufgaben erstellt',
            'completed_tasks' => ':count Aufgaben abgeschlossen',
            'total_active' => ':count aktive Aufgaben verbleibend',
            'due_soon_section' => 'Bald fällige Aufgaben:',
            'overdue_section' => 'Überfällige Aufgaben (Handlung erforderlich):',
            'completed_section' => 'Kürzlich abgeschlossene Aufgaben:',
            'footer' => 'Alle Ihre Aufgaben anzeigen unter:',
            'unsubscribe' => 'Um sich von täglichen Zusammenfassungen abzumelden, aktualisieren Sie Ihre Benachrichtigungseinstellungen in Ihren Kontoeinstellungen.',
        ],
        'weekly_digest' => [
            'subject' => 'Wöchentliche Aufgabenzusammenfassung - :date',
            'greeting' => 'Hallo :user_name,',
            'intro' => 'Hier ist Ihre wöchentliche Aufgabenzusammenfassung für :period:',
            'summary' => 'Zusammenfassung:',
            'created_tasks' => ':count neue Aufgaben erstellt',
            'completed_tasks' => ':count Aufgaben abgeschlossen',
            'total_active' => ':count aktive Aufgaben verbleibend',
            'due_soon_section' => 'Bald fällige Aufgaben:',
            'overdue_section' => 'Überfällige Aufgaben (Handlung erforderlich):',
            'completed_section' => 'Kürzlich abgeschlossene Aufgaben:',
            'footer' => 'Alle Ihre Aufgaben anzeigen unter:',
            'unsubscribe' => 'Um sich von wöchentlichen Zusammenfassungen abzumelden, aktualisieren Sie Ihre Benachrichtigungseinstellungen in Ihren Kontoeinstellungen.',
        ],
        'common' => [
            'no_description' => 'Keine Beschreibung angegeben',
            'no_due_date' => 'Kein Fälligkeitsdatum festgelegt',
            'no_parent_task' => 'Dies ist eine Hauptaufgabe',
        ],
    ],
];