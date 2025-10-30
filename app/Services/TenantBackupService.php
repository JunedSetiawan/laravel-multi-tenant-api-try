<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantBackupService
{
    /**
     * Create database backup using PHP native
     */
    public function createBackup(string $connection, string $outputPath): bool
    {
        try {
            $pdo = DB::connection($connection)->getPdo();
            $database = DB::connection($connection)->getDatabaseName();

            $dump = "-- MySQL Dump\n";
            $dump .= "-- Database: {$database}\n";
            $dump .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";

            $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            // Get all tables
            $tables = DB::connection($connection)
                ->select("SHOW TABLES");

            $tableKey = "Tables_in_{$database}";

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;

                // Get table structure
                $createTable = DB::connection($connection)
                    ->select("SHOW CREATE TABLE `{$tableName}`");

                $dump .= "-- Table: {$tableName}\n";
                $dump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $dump .= $createTable[0]->{'Create Table'} . ";\n\n";

                // Get table data
                $rows = DB::connection($connection)
                    ->table($tableName)
                    ->get();

                if ($rows->count() > 0) {
                    $dump .= "-- Data for table: {$tableName}\n";

                    foreach ($rows as $row) {
                        $rowArray = (array) $row;
                        $columns = array_keys($rowArray);
                        $values = array_values($rowArray);

                        // Escape values
                        $escapedValues = array_map(function ($value) use ($pdo) {
                            if (is_null($value)) {
                                return 'NULL';
                            }
                            return $pdo->quote($value);
                        }, $values);

                        $columnsStr = '`' . implode('`, `', $columns) . '`';
                        $valuesStr = implode(', ', $escapedValues);

                        $dump .= "INSERT INTO `{$tableName}` ({$columnsStr}) VALUES ({$valuesStr});\n";
                    }

                    $dump .= "\n";
                }
            }

            $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

            // Write to file
            file_put_contents($outputPath, $dump);

            return true;
        } catch (\Exception $e) {
            Log::error('Native backup failed', [
                'connection' => $connection,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(string $connection, string $backupPath): bool
    {
        try {
            $sql = file_get_contents($backupPath);

            if ($sql === false) {
                throw new \Exception('Failed to read backup file');
            }

            // Split into individual statements
            $statements = array_filter(
                array_map('trim', explode(";\n", $sql)),
                function ($stmt) {
                    return !empty($stmt) && !str_starts_with($stmt, '--');
                }
            );

            DB::connection($connection)->beginTransaction();

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    DB::connection($connection)->statement($statement);
                }
            }

            DB::connection($connection)->commit();

            return true;
        } catch (\Exception $e) {
            DB::connection($connection)->rollBack();

            Log::error('Native restore failed', [
                'connection' => $connection,
                'backup_path' => $backupPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
