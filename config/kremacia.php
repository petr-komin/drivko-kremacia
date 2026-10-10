<?php

return [
    // Kam chodí upozornění na nové zakázky a zprávy od kremace.
    // Prázdné = všem aktivním administrátorům.
    'notify_admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NOTIFY_ADMIN_EMAILS', ''))
    ))),

    // Jazyky prostředí – dílna česky, kremace slovensky; každý si přepne sám.
    // Výchozí je APP_LOCALE (cs). Čeština je zároveň klíč překladů (lang/sk.json).
    'locales' => [
        'cs' => 'Čeština',
        'sk' => 'Slovenčina',
    ],
];
