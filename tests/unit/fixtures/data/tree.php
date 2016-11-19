<?php
return [
    '1' => [
        'id' => '1',
        'name' => "node 1",
        'path' => '{}',
        'position' => 0,
    ],
    '1.1' => [
        'id' => '2',
        'name' => "node 1.1",
        'path' => '{1}',
        'position' => 0,
    ],
    '1.1.1' => [
        'id' => '3',
        'name' => "node 1.1.1",
        'path' => '{1,2}',
        'position' => 0,
    ],
    '1.1.2' => [
        'id' => '4',
        'name' => "node 1.1.2",
        'path' => '{1,2}',
        'position' => 100,
    ],
    '1.2' => [
        'id' => '5',
        'name' => "node 1.2",
        'path' => '{1}',
        'position' => 100,
    ],
    '1.2.1' => [
        'id' => '6',
        'name' => "node 1.2.1",
        'path' => '{1,5}',
        'position' => 0,
    ],
    '1.2.2' => [
        'id' => '7',
        'name' => "node 1.2.2",
        'path' => '{1,5}',
        'position' => 100,
    ],
    '2' => [
        'id' => '8',
        'name' => "node 2",
        'path' => '{}',
        'position' => 100,
    ],
    '2.1' => [
        'id' => '9',
        'name' => "node 2.1",
        'path' => '{8}',
        'position' => 0,
    ],
    '2.1.1' => [
        'id' => '10',
        'name' => "node 2.1.1",
        'path' => '{8,9}',
        'position' => 0,
    ],
    '2.1.2' => [
        'id' => '11',
        'name' => "node 2.1.2",
        'path' => '{8,9}',
        'position' => 100,
    ],
    '2.2' => [
        'id' => '12',
        'name' => "node 2.2",
        'path' => '{8}',
        'position' => 100,
    ],
    '2.2.1' => [
        'id' => '13',
        'name' => "node 2.2.1",
        'path' => '{8,12}',
        'position' => 0,
    ],
    '2.2.2' => [
        'id' => '14',
        'name' => "node 2.2.2",
        'path' => '{8,12}',
        'position' => 100,
    ],
];