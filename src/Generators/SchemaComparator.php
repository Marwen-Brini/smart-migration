<?php

namespace Flux\Generators;

class SchemaComparator
{
    /**
     * Compare two schemas and return differences
     */
    public function compare(array $sourceSchema, array $targetSchema): array
    {
        return [
            'tables_to_create' => $this->findTablesToCreate($sourceSchema, $targetSchema),
            'tables_to_drop' => $this->findTablesToDrop($sourceSchema, $targetSchema),
            'tables_to_modify' => $this->findTablesToModify($sourceSchema, $targetSchema),
        ];
    }

    /**
     * Find tables that exist in target but not in source (need to be created)
     */
    protected function findTablesToCreate(array $sourceSchema, array $targetSchema): array
    {
        $sourceTables = array_keys($sourceSchema['tables'] ?? []);
        $targetTables = array_keys($targetSchema['tables'] ?? []);

        $newTables = array_diff($targetTables, $sourceTables);

        $tables = [];
        foreach ($newTables as $tableName) {
            $tables[$tableName] = $targetSchema['tables'][$tableName];
        }

        return $tables;
    }

    /**
     * Find tables that exist in source but not in target (need to be dropped)
     */
    protected function findTablesToDrop(array $sourceSchema, array $targetSchema): array
    {
        $sourceTables = array_keys($sourceSchema['tables'] ?? []);
        $targetTables = array_keys($targetSchema['tables'] ?? []);

        return array_values(array_diff($sourceTables, $targetTables));
    }

    /**
     * Find tables that exist in both but have modifications
     */
    protected function findTablesToModify(array $sourceSchema, array $targetSchema): array
    {
        $sourceTables = array_keys($sourceSchema['tables'] ?? []);
        $targetTables = array_keys($targetSchema['tables'] ?? []);

        $commonTables = array_intersect($sourceTables, $targetTables);

        $modifications = [];

        foreach ($commonTables as $tableName) {
            $sourceTable = $sourceSchema['tables'][$tableName];
            $targetTable = $targetSchema['tables'][$tableName];

            $columnsToAdd = $this->findColumnsToAdd($sourceTable, $targetTable);
            $columnsToDrop = $this->findColumnsToDrop($sourceTable, $targetTable);

            // Detect potential column renames
            $renames = $this->detectColumnRenames($columnsToDrop, $columnsToAdd, $sourceTable, $targetTable);

            // Remove renamed columns from add/drop lists
            foreach ($renames as $rename) {
                $columnsToAdd = array_filter($columnsToAdd, fn($col) => $col['name'] !== $rename['to']);
                $columnsToDrop = array_filter($columnsToDrop, fn($col) => $col !== $rename['from']);
            }

            $tableModifications = [
                'columns_to_add' => array_values($columnsToAdd),
                'columns_to_drop' => array_values($columnsToDrop),
                'columns_to_rename' => $renames,
                'columns_to_modify' => $this->findColumnsToModify($sourceTable, $targetTable),
                'indexes_to_add' => $this->findIndexesToAdd($sourceTable, $targetTable),
                'indexes_to_drop' => $this->findIndexesToDrop($sourceTable, $targetTable),
                'foreign_keys_to_add' => $this->findForeignKeysToAdd($sourceTable, $targetTable),
                'foreign_keys_to_drop' => $this->findForeignKeysToDrop($sourceTable, $targetTable),
            ];

            // Only include table if it has modifications
            if ($this->hasModifications($tableModifications)) {
                $modifications[$tableName] = $tableModifications;
            }
        }

        return $modifications;
    }

    /**
     * Find columns that exist in target but not in source
     */
    protected function findColumnsToAdd(array $sourceTable, array $targetTable): array
    {
        $sourceColumns = $this->getColumnNames($sourceTable['columns'] ?? []);
        $targetColumns = $this->getColumnNames($targetTable['columns'] ?? []);

        $newColumns = array_diff($targetColumns, $sourceColumns);

        $columns = [];
        foreach ($targetTable['columns'] ?? [] as $column) {
            if (in_array($column['name'], $newColumns)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Find columns that exist in source but not in target
     */
    protected function findColumnsToDrop(array $sourceTable, array $targetTable): array
    {
        $sourceColumns = $this->getColumnNames($sourceTable['columns'] ?? []);
        $targetColumns = $this->getColumnNames($targetTable['columns'] ?? []);

        return array_values(array_diff($sourceColumns, $targetColumns));
    }

    /**
     * Find columns that exist in both but have been modified
     */
    protected function findColumnsToModify(array $sourceTable, array $targetTable): array
    {
        $sourceColumns = $this->indexColumnsByName($sourceTable['columns'] ?? []);
        $targetColumns = $this->indexColumnsByName($targetTable['columns'] ?? []);

        $commonColumns = array_intersect(array_keys($sourceColumns), array_keys($targetColumns));

        $modifiedColumns = [];

        foreach ($commonColumns as $columnName) {
            $sourceColumn = $sourceColumns[$columnName];
            $targetColumn = $targetColumns[$columnName];

            if ($this->columnHasChanged($sourceColumn, $targetColumn)) {
                $modifiedColumns[] = [
                    'name' => $columnName,
                    'from' => $sourceColumn,
                    'to' => $targetColumn,
                ];
            }
        }

        return $modifiedColumns;
    }

    /**
     * Find indexes that exist in target but not in source
     */
    protected function findIndexesToAdd(array $sourceTable, array $targetTable): array
    {
        $sourceIndexes = $this->getIndexNames($sourceTable['indexes'] ?? []);
        $targetIndexes = $this->getIndexNames($targetTable['indexes'] ?? []);

        $newIndexes = array_diff($targetIndexes, $sourceIndexes);

        $indexes = [];
        foreach ($targetTable['indexes'] ?? [] as $index) {
            if (in_array($index['name'], $newIndexes)) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Find indexes that exist in source but not in target
     */
    protected function findIndexesToDrop(array $sourceTable, array $targetTable): array
    {
        $sourceIndexes = $this->getIndexNames($sourceTable['indexes'] ?? []);
        $targetIndexes = $this->getIndexNames($targetTable['indexes'] ?? []);

        return array_values(array_diff($sourceIndexes, $targetIndexes));
    }

    /**
     * Find foreign keys that exist in target but not in source
     */
    protected function findForeignKeysToAdd(array $sourceTable, array $targetTable): array
    {
        $sourceFKs = $this->getForeignKeyNames($sourceTable['foreign_keys'] ?? []);
        $targetFKs = $this->getForeignKeyNames($targetTable['foreign_keys'] ?? []);

        $newFKs = array_diff($targetFKs, $sourceFKs);

        $foreignKeys = [];
        foreach ($targetTable['foreign_keys'] ?? [] as $fk) {
            if (in_array($fk['name'], $newFKs)) {
                $foreignKeys[] = $fk;
            }
        }

        return $foreignKeys;
    }

    /**
     * Find foreign keys that exist in source but not in target
     */
    protected function findForeignKeysToDrop(array $sourceTable, array $targetTable): array
    {
        $sourceFKs = $this->getForeignKeyNames($sourceTable['foreign_keys'] ?? []);
        $targetFKs = $this->getForeignKeyNames($targetTable['foreign_keys'] ?? []);

        return array_values(array_diff($sourceFKs, $targetFKs));
    }

    /**
     * Check if a column has changed between source and target
     */
    protected function columnHasChanged(array $sourceColumn, array $targetColumn): bool
    {
        // Compare relevant properties
        $relevantKeys = ['type', 'length', 'nullable', 'default', 'unsigned', 'auto_increment'];

        foreach ($relevantKeys as $key) {
            $sourceValue = $sourceColumn[$key] ?? null;
            $targetValue = $targetColumn[$key] ?? null;

            if ($sourceValue !== $targetValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect potential column renames based on similarity
     */
    protected function detectColumnRenames(array $droppedColumns, array $addedColumns, array $sourceTable, array $targetTable): array
    {
        $renames = [];
        $sourceColumns = $this->indexColumnsByName($sourceTable['columns'] ?? []);
        $targetColumns = $this->indexColumnsByName($targetTable['columns'] ?? []);

        foreach ($droppedColumns as $droppedName) {
            $droppedColumn = $sourceColumns[$droppedName] ?? null;
            // @codeCoverageIgnoreStart
            if (!$droppedColumn) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $bestMatch = null;
            $bestScore = 0;

            foreach ($addedColumns as $addedColumn) {
                $addedName = $addedColumn['name'];

                // Calculate similarity score
                $score = $this->calculateRenameSimilarity($droppedName, $addedName, $droppedColumn, $addedColumn);

                // Only consider if similarity is above threshold (70%)
                if ($score > 0.7 && $score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $addedName;
                }
            }

            if ($bestMatch) {
                $renames[] = [
                    'from' => $droppedName,
                    'to' => $bestMatch,
                ];
            }
        }

        return $renames;
    }

    /**
     * Calculate similarity score between two columns for rename detection
     */
    protected function calculateRenameSimilarity(string $oldName, string $newName, array $oldColumn, array $newColumn): float
    {
        // Name similarity (Levenshtein distance)
        $maxLen = max(strlen($oldName), strlen($newName));
        $nameSimilarity = 1 - (levenshtein($oldName, $newName) / $maxLen);

        // Type similarity (exact match or compatible types)
        $typeSimilarity = $this->areTypesCompatible($oldColumn['type'], $newColumn['type']) ? 1.0 : 0.0;

        // Weighted score: 60% name similarity + 40% type compatibility
        return ($nameSimilarity * 0.6) + ($typeSimilarity * 0.4);
    }

    /**
     * Check if two column types are compatible for rename
     */
    protected function areTypesCompatible(string $type1, string $type2): bool
    {
        // Normalize types for comparison
        $normalizedType1 = strtolower(preg_replace('/\(.*?\)/', '', $type1));
        $normalizedType2 = strtolower(preg_replace('/\(.*?\)/', '', $type2));

        // Exact match
        if ($normalizedType1 === $normalizedType2) {
            return true;
        }

        // Compatible type groups
        $compatibleGroups = [
            ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'tinytext'],
            ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'],
            ['decimal', 'numeric', 'float', 'double'],
            ['timestamp', 'datetime', 'date'],
        ];

        foreach ($compatibleGroups as $group) {
            if (in_array($normalizedType1, $group) && in_array($normalizedType2, $group)) {
                return true;
            }
        }

        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check if table modifications exist
     */
    protected function hasModifications(array $modifications): bool
    {
        return ! empty($modifications['columns_to_add'])
            || ! empty($modifications['columns_to_drop'])
            || ! empty($modifications['columns_to_rename'])
            || ! empty($modifications['columns_to_modify'])
            || ! empty($modifications['indexes_to_add'])
            || ! empty($modifications['indexes_to_drop'])
            || ! empty($modifications['foreign_keys_to_add'])
            || ! empty($modifications['foreign_keys_to_drop']);
    }

    /**
     * Get column names from columns array
     */
    protected function getColumnNames(array $columns): array
    {
        return array_map(fn ($col) => $col['name'], $columns);
    }

    /**
     * Index columns by name
     */
    protected function indexColumnsByName(array $columns): array
    {
        $indexed = [];
        foreach ($columns as $column) {
            $indexed[$column['name']] = $column;
        }

        return $indexed;
    }

    /**
     * Get index names from indexes array
     */
    protected function getIndexNames(array $indexes): array
    {
        return array_map(fn ($idx) => $idx['name'], $indexes);
    }

    /**
     * Get foreign key names from foreign keys array
     */
    protected function getForeignKeyNames(array $foreignKeys): array
    {
        return array_map(fn ($fk) => $fk['name'], $foreignKeys);
    }
}
