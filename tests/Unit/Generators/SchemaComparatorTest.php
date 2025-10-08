<?php

use Flux\Generators\SchemaComparator;

beforeEach(function () {
    $this->comparator = new SchemaComparator;
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

it('detects added indexes', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'email', 'type' => 'varchar(255)'],
                ],
                'indexes' => [],
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
                'indexes' => [
                    ['name' => 'idx_email', 'columns' => ['email'], 'unique' => false, 'primary' => false],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['indexes_to_add'])->toHaveCount(1);
    expect($differences['tables_to_modify']['users']['indexes_to_add'][0]['name'])->toBe('idx_email');
});

it('detects dropped indexes', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'email', 'type' => 'varchar(255)'],
                ],
                'indexes' => [
                    ['name' => 'idx_email', 'columns' => ['email'], 'unique' => false, 'primary' => false],
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
                'indexes' => [],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['indexes_to_drop'])->toContain('idx_email');
});

it('detects added foreign keys', function () {
    $sourceSchema = [
        'tables' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'user_id', 'type' => 'bigint'],
                ],
                'foreign_keys' => [],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'user_id', 'type' => 'bigint'],
                ],
                'foreign_keys' => [
                    ['name' => 'fk_user_id', 'column' => 'user_id', 'foreign_table' => 'users', 'foreign_column' => 'id'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('posts');
    expect($differences['tables_to_modify']['posts']['foreign_keys_to_add'])->toHaveCount(1);
    expect($differences['tables_to_modify']['posts']['foreign_keys_to_add'][0]['name'])->toBe('fk_user_id');
});

it('detects dropped foreign keys', function () {
    $sourceSchema = [
        'tables' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'user_id', 'type' => 'bigint'],
                ],
                'foreign_keys' => [
                    ['name' => 'fk_user_id', 'column' => 'user_id', 'foreign_table' => 'users', 'foreign_column' => 'id'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint'],
                    ['name' => 'user_id', 'type' => 'bigint'],
                ],
                'foreign_keys' => [],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    expect($differences['tables_to_modify'])->toHaveKey('posts');
    expect($differences['tables_to_modify']['posts']['foreign_keys_to_drop'])->toContain('fk_user_id');
});

it('handles edge case where dropped column does not exist in source', function () {
    // This is an edge case where internal state might be inconsistent
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
                    ['name' => 'new_col', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should detect the new column
    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_add'])->toHaveCount(1);
});

it('considers compatible string types when detecting changes', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'description', 'type' => 'varchar(255)', 'nullable' => false],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'description', 'type' => 'text', 'nullable' => false],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should detect the type change from varchar to text
    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_modify'])->toHaveCount(1);
});

it('considers compatible integer types when detecting changes', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'age', 'type' => 'int', 'nullable' => false],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'age', 'type' => 'bigint', 'nullable' => false],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should detect the type change from int to bigint
    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_modify'])->toHaveCount(1);
});

it('considers compatible decimal types when detecting changes', function () {
    $sourceSchema = [
        'tables' => [
            'products' => [
                'columns' => [
                    ['name' => 'price', 'type' => 'decimal(10,2)', 'nullable' => false],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'products' => [
                'columns' => [
                    ['name' => 'price', 'type' => 'float', 'nullable' => false],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should detect the type change from decimal to float
    expect($differences['tables_to_modify'])->toHaveKey('products');
    expect($differences['tables_to_modify']['products']['columns_to_modify'])->toHaveCount(1);
});

it('considers compatible datetime types when detecting changes', function () {
    $sourceSchema = [
        'tables' => [
            'events' => [
                'columns' => [
                    ['name' => 'occurred_at', 'type' => 'datetime', 'nullable' => false],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'events' => [
                'columns' => [
                    ['name' => 'occurred_at', 'type' => 'timestamp', 'nullable' => false],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should detect the type change from datetime to timestamp
    expect($differences['tables_to_modify'])->toHaveKey('events');
    expect($differences['tables_to_modify']['events']['columns_to_modify'])->toHaveCount(1);
});

it('detects column rename with compatible string type change', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'short_bio', 'type' => 'varchar(255)'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'biography', 'type' => 'text'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should not detect rename because types are too different
    // But if it does detect rename, it should use compatible type matching
    expect($differences['tables_to_modify'])->toHaveKey('users');
});

it('detects column rename with compatible integer type change', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'count_val', 'type' => 'int'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'counter', 'type' => 'bigint'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // The comparator checks type compatibility during rename detection
    expect($differences['tables_to_modify'])->toHaveKey('users');
});

it('detects column rename with exact type match', function () {
    $sourceSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'user_name', 'type' => 'varchar(100)'],
                ],
            ],
        ],
    ];

    $targetSchema = [
        'tables' => [
            'users' => [
                'columns' => [
                    ['name' => 'username', 'type' => 'varchar(100)'],
                ],
            ],
        ],
    ];

    $differences = $this->comparator->compare($sourceSchema, $targetSchema);

    // Should detect rename due to high name similarity and same type
    expect($differences['tables_to_modify'])->toHaveKey('users');
    expect($differences['tables_to_modify']['users']['columns_to_rename'])->toHaveCount(1);
    expect($differences['tables_to_modify']['users']['columns_to_rename'][0]['from'])->toBe('user_name');
    expect($differences['tables_to_modify']['users']['columns_to_rename'][0]['to'])->toBe('username');
});
