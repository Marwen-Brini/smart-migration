<?php

namespace Flux\Tests\Unit\Generators;

use Flux\Generators\MigrationBuilder;
use PHPUnit\Framework\TestCase;

class MigrationBuilderTest extends TestCase
{
    protected MigrationBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new MigrationBuilder();
    }

    /** @test */
    public function it_generates_migration_for_new_table()
    {
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

        $this->assertStringContainsString('Schema::create(\'posts\'', $migration);
        $this->assertStringContainsString('id(\'id\')', $migration);
        $this->assertStringContainsString('string(\'title\', 255)', $migration);
        $this->assertStringContainsString('text(\'content\')', $migration);
        $this->assertStringContainsString('->nullable()', $migration);
    }

    /** @test */
    public function it_generates_migration_for_dropped_table()
    {
        $differences = [
            'tables_to_create' => [],
            'tables_to_drop' => ['old_posts'],
            'tables_to_modify' => [],
        ];

        $migration = $this->builder->build($differences, 'drop_old_posts_table');

        $this->assertStringContainsString('Schema::dropIfExists(\'old_posts\')', $migration);
    }

    /** @test */
    public function it_generates_migration_for_added_columns()
    {
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

        $this->assertStringContainsString('Schema::table(\'users\'', $migration);
        $this->assertStringContainsString('string(\'phone\', 20)', $migration);
        $this->assertStringContainsString('->nullable()', $migration);
    }

    /** @test */
    public function it_generates_migration_for_dropped_columns()
    {
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

        $this->assertStringContainsString('Schema::table(\'users\'', $migration);
        $this->assertStringContainsString('dropColumn(\'old_field\')', $migration);
    }

    /** @test */
    public function it_generates_migration_for_renamed_columns()
    {
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

        $this->assertStringContainsString('renameColumn(\'old_name\', \'new_name\')', $migration);
    }

    /** @test */
    public function it_detects_and_uses_timestamps_shorthand()
    {
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

        $this->assertStringContainsString('$table->timestamps()', $migration);
        $this->assertStringNotContainsString('created_at', $migration);
        $this->assertStringNotContainsString('updated_at', $migration);
    }

    /** @test */
    public function it_detects_and_uses_soft_deletes_shorthand()
    {
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

        $this->assertStringContainsString('$table->softDeletes()', $migration);
    }

    /** @test */
    public function it_generates_decimal_columns_with_precision_and_scale()
    {
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

        $this->assertStringContainsString('decimal(\'price\', 10, 2)', $migration);
    }

    /** @test */
    public function it_generates_enum_columns_with_values()
    {
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

        $this->assertStringContainsString('enum(\'status\', [\'active\', \'inactive\', \'pending\'])', $migration);
    }

    /** @test */
    public function it_generates_foreign_keys_with_cascade()
    {
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

        $this->assertStringContainsString('foreign(\'user_id\')', $migration);
        $this->assertStringContainsString('references(\'id\')', $migration);
        $this->assertStringContainsString('on(\'users\')', $migration);
        $this->assertStringContainsString('cascadeOnDelete()', $migration);
    }

    /** @test */
    public function it_generates_fulltext_indexes()
    {
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

        $this->assertStringContainsString('fullText([\'title\', \'content\'])', $migration);
    }

    /** @test */
    public function it_generates_spatial_indexes()
    {
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

        $this->assertStringContainsString('spatialIndex([\'coordinates\'])', $migration);
    }

    /** @test */
    public function it_generates_rollback_for_created_tables()
    {
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

        // Check down() method drops the created table
        $this->assertMatchesRegularExpression('/public function down.*Schema::dropIfExists\(\'posts\'\)/s', $migration);
    }

    /** @test */
    public function it_generates_rollback_for_column_renames()
    {
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

        // Check down() method reverses the rename
        $this->assertMatchesRegularExpression('/public function down.*renameColumn\(\'new_name\', \'old_name\'\)/s', $migration);
    }

    /** @test */
    public function it_handles_column_defaults()
    {
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

        $this->assertStringContainsString('->default(true)', $migration);
        $this->assertStringContainsString('->default(0)', $migration);
        $this->assertStringContainsString('->default(\'default_name\')', $migration);
    }

    /** @test */
    public function it_generates_complete_migration_structure()
    {
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

        // Check structure
        $this->assertStringContainsString('<?php', $migration);
        $this->assertStringContainsString('use Illuminate\Database\Migrations\Migration', $migration);
        $this->assertStringContainsString('use Illuminate\Database\Schema\Blueprint', $migration);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Schema', $migration);
        $this->assertStringContainsString('return new class extends Migration', $migration);
        $this->assertStringContainsString('public function up(): void', $migration);
        $this->assertStringContainsString('public function down(): void', $migration);
    }
}
