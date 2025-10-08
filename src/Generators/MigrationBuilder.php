<?php

namespace Flux\Generators;

use Illuminate\Support\Str;

class MigrationBuilder
{
    protected const INDENT = '            ';

    protected const DOUBLE_INDENT = '                ';

    /**
     * Build migration file content from differences
     */
    public function build(array $differences, string $migrationName): string
    {
        $className = $this->getClassName($migrationName);
        $upMethod = $this->generateUpMethod($differences);
        $downMethod = $this->generateDownMethod($differences);

        return $this->getStub($className, $upMethod, $downMethod);
    }

    /**
     * Generate the up() method content
     */
    protected function generateUpMethod(array $differences): string
    {
        $operations = [];

        // Create new tables
        foreach ($differences['tables_to_create'] ?? [] as $tableName => $tableStructure) {
            $operations[] = $this->generateCreateTable($tableName, $tableStructure);
        }

        // Modify existing tables
        foreach ($differences['tables_to_modify'] ?? [] as $tableName => $modifications) {
            $operations[] = $this->generateModifyTable($tableName, $modifications);
        }

        // Drop tables
        foreach ($differences['tables_to_drop'] ?? [] as $tableName) {
            $operations[] = $this->generateDropTable($tableName);
        }

        if (empty($operations)) {
            return self::INDENT.'// No changes detected';
        }

        return implode("\n\n", $operations);
    }

    /**
     * Generate the down() method content
     */
    protected function generateDownMethod(array $differences): string
    {
        $operations = [];

        // Reverse drop tables (create them)
        foreach (array_reverse($differences['tables_to_drop'] ?? []) as $tableName) {
            $operations[] = $this->generateDropTableRollback($tableName);
        }

        // Reverse modify tables
        foreach (array_reverse($differences['tables_to_modify'] ?? []) as $tableName => $modifications) {
            $operations[] = $this->generateModifyTableRollback($tableName, $modifications);
        }

        // Reverse create tables (drop them)
        foreach (array_reverse($differences['tables_to_create'] ?? []) as $tableName => $tableStructure) {
            $operations[] = $this->generateDropTable($tableName);
        }

        if (empty($operations)) {
            return self::INDENT.'// No rollback needed';
        }

        return implode("\n\n", $operations);
    }

    /**
     * Generate Schema::create() for a table
     */
    protected function generateCreateTable(string $tableName, array $tableStructure): string
    {
        $columns = [];
        $columnsList = $tableStructure['columns'] ?? [];

        // Detect timestamps pattern
        $hasTimestamps = $this->detectTimestamps($columnsList);
        $hasSoftDeletes = $this->detectSoftDeletes($columnsList);
        $processedColumns = [];

        foreach ($columnsList as $column) {
            // Skip created_at and updated_at if we're using timestamps()
            if ($hasTimestamps && in_array($column['name'], ['created_at', 'updated_at'])) {
                if (!in_array('timestamps', $processedColumns)) {
                    $columns[] = self::DOUBLE_INDENT . '$table->timestamps();';
                    $processedColumns[] = 'timestamps';
                }
                continue;
            }

            // Skip deleted_at if we're using softDeletes()
            if ($hasSoftDeletes && $column['name'] === 'deleted_at') {
                if (!in_array('softDeletes', $processedColumns)) {
                    $columns[] = self::DOUBLE_INDENT . '$table->softDeletes();';
                    $processedColumns[] = 'softDeletes';
                }
                continue;
            }

            $columns[] = $this->generateColumnDefinition($column);
        }

        $columnDefinitions = implode("\n", $columns);
        $indent = self::INDENT;

        return <<<PHP
{$indent}Schema::create('{$tableName}', function (Blueprint \$table) {
{$columnDefinitions}
{$indent}});
PHP;
    }

    /**
     * Generate Schema::table() for table modifications
     */
    protected function generateModifyTable(string $tableName, array $modifications): string
    {
        $statements = [];

        // Rename columns
        foreach ($modifications['columns_to_rename'] ?? [] as $rename) {
            $statements[] = self::DOUBLE_INDENT."\$table->renameColumn('{$rename['from']}', '{$rename['to']}');";
        }

        // Add columns
        foreach ($modifications['columns_to_add'] ?? [] as $column) {
            $statements[] = $this->generateColumnDefinition($column);
        }

        // Modify columns
        foreach ($modifications['columns_to_modify'] ?? [] as $columnChange) {
            $statements[] = $this->generateColumnModification($columnChange);
        }

        // Drop columns
        foreach ($modifications['columns_to_drop'] ?? [] as $columnName) {
            $statements[] = self::DOUBLE_INDENT."\$table->dropColumn('{$columnName}');";
        }

        // Add indexes
        foreach ($modifications['indexes_to_add'] ?? [] as $index) {
            $statements[] = $this->generateIndexDefinition($index);
        }

        // Drop indexes
        foreach ($modifications['indexes_to_drop'] ?? [] as $indexName) {
            $statements[] = self::DOUBLE_INDENT."\$table->dropIndex('{$indexName}');";
        }

        // Add foreign keys
        foreach ($modifications['foreign_keys_to_add'] ?? [] as $foreignKey) {
            $statements[] = $this->generateForeignKeyDefinition($foreignKey);
        }

        // Drop foreign keys
        foreach ($modifications['foreign_keys_to_drop'] ?? [] as $fkName) {
            $statements[] = self::DOUBLE_INDENT."\$table->dropForeign('{$fkName}');";
        }

        $statementBlock = implode("\n", $statements);
        $indent = self::INDENT;

        return <<<PHP
{$indent}Schema::table('{$tableName}', function (Blueprint \$table) {
{$statementBlock}
{$indent}});
PHP;
    }

    /**
     * Generate Schema::dropIfExists() for a table
     */
    protected function generateDropTable(string $tableName): string
    {
        return self::INDENT."Schema::dropIfExists('{$tableName}');";
    }

    /**
     * Generate rollback for dropped table
     */
    protected function generateDropTableRollback(string $tableName): string
    {
        return self::INDENT."// TODO: Restore table '{$tableName}' - requires manual implementation";
    }

    /**
     * Generate rollback for modified table
     */
    protected function generateModifyTableRollback(string $tableName, array $modifications): string
    {
        $statements = [];

        // Reverse column drops (add them back)
        foreach ($modifications['columns_to_drop'] ?? [] as $columnName) {
            $statements[] = self::DOUBLE_INDENT."// TODO: Restore column '{$columnName}'";
        }

        // Reverse column modifications
        foreach ($modifications['columns_to_modify'] ?? [] as $columnChange) {
            $statements[] = $this->generateColumnModification([
                'name' => $columnChange['name'],
                'from' => $columnChange['to'], // Reverse: to becomes from
                'to' => $columnChange['from'],  // Reverse: from becomes to
            ]);
        }

        // Reverse column additions (drop them)
        foreach ($modifications['columns_to_add'] ?? [] as $column) {
            $statements[] = self::DOUBLE_INDENT."\$table->dropColumn('{$column['name']}');";
        }

        // Reverse column renames
        foreach ($modifications['columns_to_rename'] ?? [] as $rename) {
            $statements[] = self::DOUBLE_INDENT."\$table->renameColumn('{$rename['to']}', '{$rename['from']}');";
        }

        // Reverse index additions (drop them)
        foreach ($modifications['indexes_to_add'] ?? [] as $index) {
            $statements[] = self::DOUBLE_INDENT."\$table->dropIndex('{$index['name']}');";
        }

        // Reverse index drops (add them back)
        foreach ($modifications['indexes_to_drop'] ?? [] as $indexName) {
            $statements[] = self::DOUBLE_INDENT."// TODO: Restore index '{$indexName}'";
        }

        if (empty($statements)) {
            return self::INDENT."// No rollback needed for table '{$tableName}'";
        }

        $statementBlock = implode("\n", $statements);
        $indent = self::INDENT;

        return <<<PHP
{$indent}Schema::table('{$tableName}', function (Blueprint \$table) {
{$statementBlock}
{$indent}});
PHP;
    }

    /**
     * Generate column definition
     */
    protected function generateColumnDefinition(array $column): string
    {
        $name = $column['name'];
        $blueprintMethod = $this->mapTypeToBlueprint($column);

        $definition = "\$table->{$blueprintMethod}";

        // Add modifiers
        if (($column['nullable'] ?? false) === true) {
            $definition .= '->nullable()';
        }

        if (isset($column['default']) && $column['default'] !== null) {
            $default = $this->formatDefaultValue($column['default']);
            $definition .= "->default({$default})";
        }

        $definition .= ';';

        return self::DOUBLE_INDENT.$definition;
    }

    /**
     * Map database column type to Laravel Blueprint method
     */
    protected function mapTypeToBlueprint(array $column): string
    {
        $typeString = strtolower($column['type']);
        $name = $column['name'];
        $unsigned = $column['unsigned'] ?? false;
        $autoIncrement = $column['auto_increment'] ?? false;

        // Extract type and length from type string (e.g., "varchar(80)" -> type="varchar", length=80)
        $type = $typeString;
        $length = $column['length'] ?? null;
        $precision = null;
        $scale = null;
        $enumValues = [];

        // Match enum/set types: enum('value1','value2','value3')
        if (preg_match('/^(enum|set)\((.*)\)$/i', $typeString, $matches)) {
            $type = strtolower($matches[1]);
            // Extract values from quoted strings
            preg_match_all("/'([^']+)'/", $matches[2], $valueMatches);
            $enumValues = $valueMatches[1] ?? [];
        }

        // Match single parameter types: varchar(80), char(10)
        if (preg_match('/^(\w+)\((\d+)\)$/', $typeString, $matches)) {
            $type = $matches[1];
            $length = (int) $matches[2];
        }

        // Match decimal/numeric types with precision and scale: decimal(10,2), numeric(8,2)
        if (preg_match('/^(decimal|numeric|double|float)\((\d+),\s*(\d+)\)$/i', $typeString, $matches)) {
            $type = $matches[1];
            $precision = (int) $matches[2];
            $scale = (int) $matches[3];
        }

        // Handle auto-increment columns
        if ($autoIncrement) {
            return match ($type) {
                'tinyint' => "tinyIncrements('{$name}')",
                'smallint' => "smallIncrements('{$name}')",
                'mediumint' => "mediumIncrements('{$name}')",
                'int', 'integer' => "increments('{$name}')",
                'bigint' => "id('{$name}')", // or bigIncrements
                default => "increments('{$name}')",
            };
        }

        // Handle unsigned integers
        if ($unsigned) {
            $method = match ($type) {
                'tinyint' => "unsignedTinyInteger('{$name}')",
                'smallint' => "unsignedSmallInteger('{$name}')",
                'mediumint' => "unsignedMediumInteger('{$name}')",
                'int', 'integer' => "unsignedInteger('{$name}')",
                'bigint' => "unsignedBigInteger('{$name}')",
                // @codeCoverageIgnoreStart
                default => null,
                // @codeCoverageIgnoreEnd
            };

            if ($method) {
                return $method;
            }
        }

        // Map standard types
        return match ($type) {
            // String types
            'varchar' => $length ? "string('{$name}', {$length})" : "string('{$name}')",
            'char' => $length ? "char('{$name}', {$length})" : "char('{$name}')",
            'text' => "text('{$name}')",
            'mediumtext' => "mediumText('{$name}')",
            'longtext' => "longText('{$name}')",
            'tinytext' => "tinyText('{$name}')",

            // @codeCoverageIgnoreStart
            // Integer types
            'tinyint' => "tinyInteger('{$name}')",
            'smallint' => "smallInteger('{$name}')",
            'mediumint' => "mediumInteger('{$name}')",
            // @codeCoverageIgnoreEnd
            'int', 'integer' => "integer('{$name}')",
            // @codeCoverageIgnoreStart
            'bigint' => "bigInteger('{$name}')",
            // @codeCoverageIgnoreEnd

            // Decimal types
            'decimal' => $precision && $scale !== null
                // @codeCoverageIgnoreStart
                ? "decimal('{$name}', {$precision}, {$scale})"
                // @codeCoverageIgnoreEnd
                : "decimal('{$name}')",
            'numeric' => $precision && $scale !== null
                // @codeCoverageIgnoreStart
                ? "decimal('{$name}', {$precision}, {$scale})"
                // @codeCoverageIgnoreEnd
                : "decimal('{$name}')",
            'double' => $precision && $scale !== null
                // @codeCoverageIgnoreStart
                ? "double('{$name}', {$precision}, {$scale})"
                // @codeCoverageIgnoreEnd
                : "double('{$name}')",
            'float' => $precision && $scale !== null
                // @codeCoverageIgnoreStart
                ? "float('{$name}', {$precision}, {$scale})"
                // @codeCoverageIgnoreEnd
                : "float('{$name}')",

            // Date/Time types
            'date' => "date('{$name}')",
            'datetime' => "dateTime('{$name}')",
            'timestamp' => "timestamp('{$name}')",
            'time' => "time('{$name}')",
            'year' => "year('{$name}')",

            // Binary types
            'binary' => "binary('{$name}')",
            'varbinary' => "binary('{$name}')",
            'blob' => "binary('{$name}')",

            // Boolean
            'boolean', 'bool', 'tinyint(1)' => "boolean('{$name}')",

            // JSON
            'json' => "json('{$name}')",
            'jsonb' => "jsonb('{$name}')",

            // UUID
            'uuid' => "uuid('{$name}')",

            // Enum and Set
            'enum' => ! empty($enumValues)
                ? "enum('{$name}', ['" . implode("', '", $enumValues) . "'])"
                : "string('{$name}')",
            'set' => ! empty($enumValues)
                ? "set('{$name}', ['" . implode("', '", $enumValues) . "'])"
                : "string('{$name}')",

            // Default fallback
            default => "string('{$name}')",
        };
    }

    /**
     * Generate column modification
     */
    protected function generateColumnModification(array $columnChange): string
    {
        $targetColumn = $columnChange['to'];

        return $this->generateColumnDefinition($targetColumn).' // Modified from '.$columnChange['from']['type'];
    }

    /**
     * Generate index definition
     */
    protected function generateIndexDefinition(array $index): string
    {
        $columns = $index['columns'] ?? [];
        $columnsStr = "['".implode("', '", $columns)."']";

        $indexType = strtoupper($index['type'] ?? 'BTREE');
        $isPrimary = $index['primary'] ?? false;
        $isUnique = $index['unique'] ?? false;

        // Handle PRIMARY key
        if ($isPrimary) {
            return self::DOUBLE_INDENT."\$table->primary({$columnsStr});";
        }

        // Handle FULLTEXT index
        if ($indexType === 'FULLTEXT') {
            return self::DOUBLE_INDENT."\$table->fullText({$columnsStr});";
        }

        // Handle SPATIAL index
        if ($indexType === 'SPATIAL') {
            return self::DOUBLE_INDENT."\$table->spatialIndex({$columnsStr});";
        }

        // Handle UNIQUE index
        if ($isUnique) {
            return self::DOUBLE_INDENT."\$table->unique({$columnsStr});";
        }

        // Default to regular index
        return self::DOUBLE_INDENT."\$table->index({$columnsStr});";
    }

    /**
     * Generate foreign key definition
     */
    protected function generateForeignKeyDefinition(array $foreignKey): string
    {
        $column = $foreignKey['column'] ?? 'id';
        // Support both naming conventions
        $referencedTable = $foreignKey['referenced_table'] ?? $foreignKey['foreign_table'] ?? 'table';
        $referencedColumn = $foreignKey['referenced_column'] ?? $foreignKey['foreign_column'] ?? 'id';

        $definition = "\$table->foreign('{$column}')";
        $definition .= "->references('{$referencedColumn}')";
        $definition .= "->on('{$referencedTable}')";

        // Add onDelete clause if present
        if (isset($foreignKey['on_delete'])) {
            $onDelete = strtolower($foreignKey['on_delete']);
            $definition .= match ($onDelete) {
                'cascade' => "->cascadeOnDelete()",
                'set null' => "->nullOnDelete()",
                'restrict' => "->restrictOnDelete()",
                'no action' => "->noActionOnDelete()",
                // @codeCoverageIgnoreStart
                default => "->onDelete('{$onDelete}')",
                // @codeCoverageIgnoreEnd
            };
        }

        // Add onUpdate clause if present
        if (isset($foreignKey['on_update'])) {
            $onUpdate = strtolower($foreignKey['on_update']);
            $definition .= match ($onUpdate) {
                'cascade' => "->cascadeOnUpdate()",
                'restrict' => "->restrictOnUpdate()",
                'no action' => "->noActionOnUpdate()",
                // @codeCoverageIgnoreStart
                default => "->onUpdate('{$onUpdate}')",
                // @codeCoverageIgnoreEnd
            };
        }

        $definition .= ';';

        return self::DOUBLE_INDENT.$definition;
    }

    /**
     * Format default value for code generation
     */
    protected function formatDefaultValue(mixed $value): string
    {
        if (is_null($value)) {
            // @codeCoverageIgnoreStart
            return 'null';
            // @codeCoverageIgnoreEnd
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // String value - escape quotes
        return "'".addslashes($value)."'";
    }

    /**
     * Detect if columns include standard Laravel timestamps
     */
    protected function detectTimestamps(array $columns): bool
    {
        $hasCreatedAt = false;
        $hasUpdatedAt = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'created_at' &&
                (stripos($column['type'], 'timestamp') !== false || stripos($column['type'], 'datetime') !== false)) {
                $hasCreatedAt = true;
            }
            if ($column['name'] === 'updated_at' &&
                (stripos($column['type'], 'timestamp') !== false || stripos($column['type'], 'datetime') !== false)) {
                $hasUpdatedAt = true;
            }
        }

        return $hasCreatedAt && $hasUpdatedAt;
    }

    /**
     * Detect if columns include soft deletes
     */
    protected function detectSoftDeletes(array $columns): bool
    {
        foreach ($columns as $column) {
            if ($column['name'] === 'deleted_at' &&
                (stripos($column['type'], 'timestamp') !== false || stripos($column['type'], 'datetime') !== false) &&
                ($column['nullable'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get class name from migration name
     */
    protected function getClassName(string $migrationName): string
    {
        return Str::studly($migrationName);
    }

    /**
     * Get migration stub with replacements
     */
    protected function getStub(string $className, string $upMethod, string $downMethod): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upMethod}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downMethod}
    }
};

PHP;
    }
}
