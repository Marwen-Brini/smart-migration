<?php

use Flux\Generators\MigrationBuilder;

beforeEach(function () {
    $this->builder = new MigrationBuilder;
});

it('generates migration for new table', function () {
    $differences = [
        'tables_to_create' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
                    ['name' => 'title', 'type' => 'varchar(255)', 'nullable' => false],
                    ['name' => 'content', 'type' => 'text', 'nullable' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_posts_table');

    expect($migration)->toContain('Schema::create(\'posts\'');
    expect($migration)->toContain('id(\'id\')');
    expect($migration)->toContain('string(\'title\', 255)');
    expect($migration)->toContain('text(\'content\')');
    expect($migration)->toContain('->nullable()');
});

it('generates migration for dropped table', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => ['old_posts'],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'drop_old_posts_table');

    expect($migration)->toContain('Schema::dropIfExists(\'old_posts\')');
});

it('generates migration for added columns', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [
                    ['name' => 'phone', 'type' => 'varchar(20)', 'nullable' => true],
                ],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_phone_to_users');

    expect($migration)->toContain('Schema::table(\'users\'');
    expect($migration)->toContain('string(\'phone\', 20)');
    expect($migration)->toContain('->nullable()');
});

it('generates migration for dropped columns', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => ['old_field'],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'remove_old_field_from_users');

    expect($migration)->toContain('Schema::table(\'users\'');
    expect($migration)->toContain('dropColumn(\'old_field\')');
});

it('generates migration for renamed columns', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [
                    ['from' => 'old_name', 'to' => 'new_name'],
                ],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'rename_column_in_users');

    expect($migration)->toContain('renameColumn(\'old_name\', \'new_name\')');
});

it('detects and uses timestamps shorthand', function () {
    $differences = [
        'tables_to_create' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                    ['name' => 'title', 'type' => 'varchar(255)'],
                    ['name' => 'created_at', 'type' => 'timestamp', 'nullable' => true],
                    ['name' => 'updated_at', 'type' => 'timestamp', 'nullable' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_posts_with_timestamps');

    expect($migration)->toContain('$table->timestamps()');
    expect($migration)->not->toContain('created_at');
    expect($migration)->not->toContain('updated_at');
});

it('detects and uses soft deletes shorthand', function () {
    $differences = [
        'tables_to_create' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                    ['name' => 'title', 'type' => 'varchar(255)'],
                    ['name' => 'deleted_at', 'type' => 'timestamp', 'nullable' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_posts_with_soft_deletes');

    expect($migration)->toContain('$table->softDeletes()');
});

it('generates decimal columns with precision and scale', function () {
    $differences = [
        'tables_to_create' => [
            'products' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                    ['name' => 'price', 'type' => 'decimal(10,2)'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_products_table');

    expect($migration)->toContain('decimal(\'price\', 10, 2)');
});

it('generates enum columns with values', function () {
    $differences = [
        'tables_to_create' => [
            'users' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                    ['name' => 'status', 'type' => 'enum(\'active\',\'inactive\',\'pending\')'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_users_table');

    expect($migration)->toContain('enum(\'status\', [\'active\', \'inactive\', \'pending\'])');
});

it('generates foreign keys with cascade', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [
                    [
                        'column' => 'user_id',
                        'foreign_table' => 'users',
                        'foreign_column' => 'id',
                        'on_delete' => 'cascade',
                    ],
                ],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_foreign_key_to_posts');

    expect($migration)->toContain('foreign(\'user_id\')');
    expect($migration)->toContain('references(\'id\')');
    expect($migration)->toContain('on(\'users\')');
    expect($migration)->toContain('cascadeOnDelete()');
});

it('generates fulltext indexes', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [
                    [
                        'name' => 'idx_fulltext_title_content',
                        'columns' => ['title', 'content'],
                        'type' => 'FULLTEXT',
                        'unique' => false,
                        'primary' => false,
                    ],
                ],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_fulltext_index_to_posts');

    expect($migration)->toContain('fullText([\'title\', \'content\'])');
});

it('generates spatial indexes', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'locations' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [
                    [
                        'name' => 'idx_spatial_coordinates',
                        'columns' => ['coordinates'],
                        'type' => 'SPATIAL',
                        'unique' => false,
                        'primary' => false,
                    ],
                ],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_spatial_index_to_locations');

    expect($migration)->toContain('spatialIndex([\'coordinates\'])');
});

it('generates rollback for created tables', function () {
    $differences = [
        'tables_to_create' => [
            'posts' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_posts_table');

    expect($migration)->toMatch('/public function down.*Schema::dropIfExists\(\'posts\'\)/s');
});

it('generates rollback for column renames', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [
                    ['from' => 'old_name', 'to' => 'new_name'],
                ],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'rename_column');

    expect($migration)->toMatch('/public function down.*renameColumn\(\'new_name\', \'old_name\'\)/s');
});

it('handles column defaults', function () {
    $differences = [
        'tables_to_create' => [
            'settings' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                    ['name' => 'is_active', 'type' => 'boolean', 'default' => true],
                    ['name' => 'count', 'type' => 'int', 'default' => 0],
                    ['name' => 'name', 'type' => 'varchar(255)', 'default' => 'default_name'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_settings_table');

    expect($migration)->toContain('->default(true)');
    expect($migration)->toContain('->default(0)');
    expect($migration)->toContain('->default(\'default_name\')');
});

it('generates complete migration structure', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'auto_increment' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_test_table');

    expect($migration)->toContain('<?php');
    expect($migration)->toContain('use Illuminate\Database\Migrations\Migration');
    expect($migration)->toContain('use Illuminate\Database\Schema\Blueprint');
    expect($migration)->toContain('use Illuminate\Support\Facades\Schema');
    expect($migration)->toContain('return new class extends Migration');
    expect($migration)->toContain('public function up(): void');
    expect($migration)->toContain('public function down(): void');
});

it('generates empty migration when no changes', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'empty_migration');

    expect($migration)->toContain('// No changes detected');
    expect($migration)->toContain('// No rollback needed');
});

it('generates column modifications', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [
                    [
                        'name' => 'email',
                        'from' => ['name' => 'email', 'type' => 'varchar(80)', 'nullable' => false],
                        'to' => ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => true],
                    ],
                ],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'modify_email_column');

    expect($migration)->toContain('string(\'email\', 255)');
    expect($migration)->toContain('// Modified from varchar(80)');
});

it('generates drop index', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => ['idx_email'],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'drop_email_index');

    expect($migration)->toContain('dropIndex(\'idx_email\')');
});

it('generates drop foreign key', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => ['fk_user_id'],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'drop_user_fk');

    expect($migration)->toContain('dropForeign(\'fk_user_id\')');
});

it('generates primary key index', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'composite_keys' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [
                    [
                        'name' => 'PRIMARY',
                        'columns' => ['user_id', 'post_id'],
                        'type' => 'BTREE',
                        'unique' => true,
                        'primary' => true,
                    ],
                ],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_composite_primary');

    expect($migration)->toContain('primary([\'user_id\', \'post_id\'])');
});

it('generates unique index', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [
                    [
                        'name' => 'idx_email_unique',
                        'columns' => ['email'],
                        'type' => 'BTREE',
                        'unique' => true,
                        'primary' => false,
                    ],
                ],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_unique_email');

    expect($migration)->toContain('unique([\'email\'])');
});

it('generates regular index', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [
                    [
                        'name' => 'idx_created_at',
                        'columns' => ['created_at'],
                        'type' => 'BTREE',
                        'unique' => false,
                        'primary' => false,
                    ],
                ],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_created_at_index');

    expect($migration)->toContain('index([\'created_at\'])');
});

it('generates foreign key with on update', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [
                    [
                        'column' => 'user_id',
                        'foreign_table' => 'users',
                        'foreign_column' => 'id',
                        'on_delete' => 'set null',
                        'on_update' => 'cascade',
                    ],
                ],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_fk_with_update');

    expect($migration)->toContain('foreign(\'user_id\')');
    expect($migration)->toContain('nullOnDelete()');
    expect($migration)->toContain('cascadeOnUpdate()');
});

it('generates various auto increment types', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'tiny_id', 'type' => 'tinyint', 'auto_increment' => true],
                    ['name' => 'small_id', 'type' => 'smallint', 'auto_increment' => true],
                    ['name' => 'medium_id', 'type' => 'mediumint', 'auto_increment' => true],
                    ['name' => 'int_id', 'type' => 'int', 'auto_increment' => true],
                    ['name' => 'big_id', 'type' => 'bigint', 'auto_increment' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_auto_increments');

    expect($migration)->toContain('tinyIncrements(\'tiny_id\')');
    expect($migration)->toContain('smallIncrements(\'small_id\')');
    expect($migration)->toContain('mediumIncrements(\'medium_id\')');
    expect($migration)->toContain('increments(\'int_id\')');
    expect($migration)->toContain('id(\'big_id\')');
});

it('generates unsigned integer types', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'tiny_unsigned', 'type' => 'tinyint', 'unsigned' => true],
                    ['name' => 'small_unsigned', 'type' => 'smallint', 'unsigned' => true],
                    ['name' => 'medium_unsigned', 'type' => 'mediumint', 'unsigned' => true],
                    ['name' => 'int_unsigned', 'type' => 'int', 'unsigned' => true],
                    ['name' => 'big_unsigned', 'type' => 'bigint', 'unsigned' => true],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_unsigned_integers');

    expect($migration)->toContain('unsignedTinyInteger(\'tiny_unsigned\')');
    expect($migration)->toContain('unsignedSmallInteger(\'small_unsigned\')');
    expect($migration)->toContain('unsignedMediumInteger(\'medium_unsigned\')');
    expect($migration)->toContain('unsignedInteger(\'int_unsigned\')');
    expect($migration)->toContain('unsignedBigInteger(\'big_unsigned\')');
});

it('generates text column types', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'tiny_text', 'type' => 'tinytext'],
                    ['name' => 'text_field', 'type' => 'text'],
                    ['name' => 'medium_text', 'type' => 'mediumtext'],
                    ['name' => 'long_text', 'type' => 'longtext'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_text_fields');

    expect($migration)->toContain('tinyText(\'tiny_text\')');
    expect($migration)->toContain('text(\'text_field\')');
    expect($migration)->toContain('mediumText(\'medium_text\')');
    expect($migration)->toContain('longText(\'long_text\')');
});

it('generates date and time types', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'date_field', 'type' => 'date'],
                    ['name' => 'datetime_field', 'type' => 'datetime'],
                    ['name' => 'timestamp_field', 'type' => 'timestamp'],
                    ['name' => 'time_field', 'type' => 'time'],
                    ['name' => 'year_field', 'type' => 'year'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_datetime_fields');

    expect($migration)->toContain('date(\'date_field\')');
    expect($migration)->toContain('dateTime(\'datetime_field\')');
    expect($migration)->toContain('timestamp(\'timestamp_field\')');
    expect($migration)->toContain('time(\'time_field\')');
    expect($migration)->toContain('year(\'year_field\')');
});

it('generates json and uuid types', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'data', 'type' => 'json'],
                    ['name' => 'jsonb_data', 'type' => 'jsonb'],
                    ['name' => 'uuid_field', 'type' => 'uuid'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_json_uuid');

    expect($migration)->toContain('json(\'data\')');
    expect($migration)->toContain('jsonb(\'jsonb_data\')');
    expect($migration)->toContain('uuid(\'uuid_field\')');
});

it('generates binary types', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'binary_field', 'type' => 'binary'],
                    ['name' => 'varbinary_field', 'type' => 'varbinary'],
                    ['name' => 'blob_field', 'type' => 'blob'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_binary_fields');

    expect($migration)->toContain('binary(\'binary_field\')');
    expect($migration)->toContain('binary(\'varbinary_field\')');
    expect($migration)->toContain('binary(\'blob_field\')');
});

it('generates set column type', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'permissions', 'type' => 'set(\'read\',\'write\',\'delete\')'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_set_field');

    expect($migration)->toContain('set(\'permissions\', [\'read\', \'write\', \'delete\'])');
});

it('generates rollback for modified columns', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [
                    [
                        'name' => 'email',
                        'from' => ['name' => 'email', 'type' => 'varchar(80)', 'nullable' => false],
                        'to' => ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => true],
                    ],
                ],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'modify_email');

    // Check down method reverses the modification
    expect($migration)->toMatch('/public function down.*Modified from varchar\(255\)/s');
});

it('generates rollback for dropped indexes', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'users' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => ['idx_email'],
                'foreign_keys_to_add' => [],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'drop_index');

    // Check down method has TODO comment
    expect($migration)->toContain('// TODO: Restore index \'idx_email\'');
});

it('generates char column without length', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'code', 'type' => 'char'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_test');

    expect($migration)->toContain('char(\'code\')');
});

it('generates decimal without precision and scale', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'amount', 'type' => 'decimal'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_test');

    expect($migration)->toContain('decimal(\'amount\')');
});

it('generates numeric without precision and scale', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'value', 'type' => 'numeric'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_test');

    expect($migration)->toContain('decimal(\'value\')');
});

it('generates double without precision', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'latitude', 'type' => 'double'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_test');

    expect($migration)->toContain('double(\'latitude\')');
});

it('generates float without precision', function () {
    $differences = [
        'tables_to_create' => [
            'test' => [
                'columns' => [
                    ['name' => 'rate', 'type' => 'float'],
                ],
            ],
        ],
        'tables_to_drop' => [],
        'tables_to_modify' => [],
    ];

    $migration = $this->builder->build($differences, 'create_test');

    expect($migration)->toContain('float(\'rate\')');
});

it('generates foreign key with restrict on delete', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [
                    [
                        'column' => 'user_id',
                        'foreign_table' => 'users',
                        'foreign_column' => 'id',
                        'on_delete' => 'restrict',
                    ],
                ],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_fk');

    expect($migration)->toContain('restrictOnDelete()');
});

it('generates foreign key with no action on delete', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [
                    [
                        'column' => 'user_id',
                        'foreign_table' => 'users',
                        'foreign_column' => 'id',
                        'on_delete' => 'no action',
                    ],
                ],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_fk');

    expect($migration)->toContain('noActionOnDelete()');
});

it('generates foreign key with restrict on update', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [
                    [
                        'column' => 'user_id',
                        'foreign_table' => 'users',
                        'foreign_column' => 'id',
                        'on_update' => 'restrict',
                    ],
                ],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_fk');

    expect($migration)->toContain('restrictOnUpdate()');
});

it('generates foreign key with no action on update', function () {
    $differences = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'tables_to_modify' => [
            'posts' => [
                'columns_to_add' => [],
                'columns_to_drop' => [],
                'columns_to_rename' => [],
                'columns_to_modify' => [],
                'indexes_to_add' => [],
                'indexes_to_drop' => [],
                'foreign_keys_to_add' => [
                    [
                        'column' => 'user_id',
                        'foreign_table' => 'users',
                        'foreign_column' => 'id',
                        'on_update' => 'no action',
                    ],
                ],
                'foreign_keys_to_drop' => [],
            ],
        ],
    ];

    $migration = $this->builder->build($differences, 'add_fk');

    expect($migration)->toContain('noActionOnUpdate()');
});
