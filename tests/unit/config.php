<?php

return [
    'id' => 'grnrbt/yii2-materialized-path-postgres-array',
    'basePath' => __DIR__ . '/../..',
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'pgsql:host=localhost;dbname=grass',
            'username' => 'postgres',
            'password' => '',
            'charset' => 'utf8',
        ],
    ],
];