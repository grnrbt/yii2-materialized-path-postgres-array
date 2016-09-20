<?php
return [
    'id' => 'id',
    'basePath' => __DIR__,
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'pgsql:host=localhost;dbname=mp_test',
            'username' => 'mp_test',
            'password' => 'pass',
            'charset' => 'utf8',
        ],
    ],
];