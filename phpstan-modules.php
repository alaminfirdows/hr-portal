<?php

// Larastan reads table columns from every module's migrations.
return [
    'parameters' => [
        'databaseMigrationsPath' => [
            __DIR__.'/database/migrations',
            ...glob(__DIR__.'/modules/*/Database/Migrations', GLOB_ONLYDIR) ?: [],
        ],
    ],
];
