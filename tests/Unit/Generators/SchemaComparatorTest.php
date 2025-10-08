<?php

use Flux\Generators\SchemaComparator;

beforeEach(function () {
    $this->comparator = new SchemaComparator();
});

it('detects new tables', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'title', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_create'])->toHaveKey('posts');
    expect($differences['tables_to_create']['posts']['columns'])->toHaveCount(2);
});

it('detects dropped tables', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_drop'])->toContain('posts');
});

it('detects added columns', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'email', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_add'])->toHaveCount(1);
    expect($differences['tables_to_modify']['users']['columns_to_add'][0]['name'])->toBe('email');
});

it('detects dropped columns', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'email', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_drop'])->toContain('email');
});

it('detects modified columns', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'email', 'type' => 'varchar(80)', 'nullable' => false],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => true],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_modify'])->toHaveCount(1);
    expect($differences['tables_to_modify']['users']['columns_to_modify'][0]['name'])->toBe('email');
});

it('detects column renames', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'old_name', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'new_name', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_rename'])->toHaveCount(1);
    expect($differences['tables_to_modify']['users']['columns_to_rename'][0]['from'])->toBe('old_name');
    expect($differences['tables_to_modify']['users']['columns_to_rename'][0]['to'])->toBe('new_name');
});

it('does not detect rename for dissimilar columns', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'email', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'password', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_rename'])->toBeEmpty();
    expect($differences['tables_to_modify']['users']['columns_to_add'])->toHaveCount(1);
    expect($differences['tables_to_modify']['users']['columns_to_drop'])->toHaveCount(1);
});

it('returns empty differences for identical schemas', function () {
    $schema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'email', 'type' => 'varchar(255)'],
                ],
                'indexes' => [],
                'foreign_keys' => [],
            ],
        ],
    ];

    $differences = $this->comparator->compare($schema, $schema);

    expect($differences['tables_to_create'])->toBeEmpty();
    expect($differences['tables_to_drop'])->toBeEmpty();
    expect($differences['tables_to_modify'])->toBeEmpty();
});
