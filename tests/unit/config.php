<?php
return [
    'id' => 'id',
    'basePath' => __DIR__,
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'pgsql:host=localhost;dbname=grass_test',
            'username' => 'grass_test',
            'password' => 'pass',
            'charset' => 'utf8',
        ],
    ],
];