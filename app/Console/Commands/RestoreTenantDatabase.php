<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\TenantBackupService;

class RestoreTenantDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:restore 
                            {tenant_id : Tenant ID to restore}
                            {backup_file? : Specific backup file to restore (optional, uses latest if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore tenant database from backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $backupFile = $this->argument('backup_file');

        // Find tenant
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant with ID '{$tenantId}' not found!");
            return 1;
        }

        // Get backup file
        if (!$backupFile) {
            $backupFile = $this->getLatestBackup($tenant);

            if (!$backupFile) {
                $this->error("No backup found for tenant '{$tenantId}'!");
                return 1;
            }

            $this->info("Using latest backup: {$backupFile}");
        }

        // Confirm restoration
        if (!$this->confirm("This will restore tenant '{$tenant->name}' database. Continue?", false)) {
            $this->warn('Restoration cancelled.');
            return 0;
        }

        // Perform restoration
        return $this->restoreTenant($tenant, $backupFile);
    }

    /**
     * Get latest backup file for tenant
     */
    protected function getLatestBackup(Tenant $tenant): ?string
    {
        $backupPath = "backups/{$tenant->id}";

        if (!Storage::disk('local')->exists($backupPath)) {
            return null;
        }

        $files = Storage::disk('local')->allFiles($backupPath);

        if (empty($files)) {
            return null;
        }

        // Sort by modification time, latest first
        usort($files, function ($a, $b) {
            return Storage::disk('local')->lastModified($b) <=> Storage::disk('local')->lastModified($a);
        });

        return $files[0];
    }

    /**
     * Restore tenant database from backup file
     */
    protected function restoreTenant(Tenant $tenant, string $backupFile): int
    {
        try {
            $backupPath = storage_path("app/{$backupFile}");

            if (!file_exists($backupPath)) {
                $this->error("Backup file not found: {$backupPath}");
                return 1;
            }

            // Initialize tenancy
            tenancy()->initialize($tenant);

            // Get database credentials
            $dbName = $tenant->database()->getName();
            $dbHost = $tenant->getInternal('db_host') ?? config('database.connections.tenant.host');
            $dbPort = $tenant->getInternal('db_port') ?? config('database.connections.tenant.port');
            $dbUsername = $tenant->getInternal('db_username') ?? config('database.connections.tenant.username');

            // Decrypt password
            $dbPassword = $tenant->getInternal('db_password');
            if ($dbPassword) {
                try {
                    $dbPassword = decrypt($dbPassword);
                } catch (\Exception $e) {
                    $dbPassword = $dbPassword;
                }
            } else {
                $dbPassword = config('database.connections.tenant.password');
            }

            // Set tenant connection config
            config([
                'database.connections.tenant.database' => $dbName,
                'database.connections.tenant.host' => $dbHost,
                'database.connections.tenant.port' => $dbPort,
                'database.connections.tenant.username' => $dbUsername,
                'database.connections.tenant.password' => $dbPassword,
            ]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $this->info('Restoring database...');

            // Determine if file is compressed
            $isCompressed = str_ends_with($backupFile, '.gz');

            // Decompress if needed
            $tempPath = $backupPath;
            if ($isCompressed) {
                $this->info('Decompressing backup...');
                $tempPath = storage_path('app/temp_restore_' . uniqid() . '.sql');
                $compressed = file_get_contents($backupPath);
                $decompressed = gzdecode($compressed);
                file_put_contents($tempPath, $decompressed);
            }

            // Restore using native service
            $backupService = new TenantBackupService();
            $success = $backupService->restoreBackup('tenant', $tempPath);

            // Cleanup temp file
            if ($isCompressed && file_exists($tempPath)) {
                unlink($tempPath);
            }

            // End tenancy
            tenancy()->end();

            if (!$success) {
                throw new \Exception('Native restore failed');
            }

            // Log success
            Log::info('Tenant database restored', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'database' => $dbName,
                'backup_file' => $backupFile,
            ]);

            $this->info('Database restored successfully!');
            return 0;
        } catch (\Exception $e) {
            tenancy()->end();

            Log::error('Failed to restore tenant database', [
                'tenant_id' => $tenant->id,
                'backup_file' => $backupFile,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("Restoration failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Find mysql executable on system
     */
    protected function findMysql(): string
    {
        // Check if mysql is in PATH
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where mysql 2>nul', $output, $returnVar);
        } else {
            exec('which mysql 2>/dev/null', $output, $returnVar);
        }

        if ($returnVar === 0 && !empty($output)) {
            return trim($output[0]);
        }

        // Common paths on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $commonPaths = [
                'C:\xampp\mysql\bin\mysql.exe',
                'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe',
                'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe',
                'C:\Program Files\MySQL\MySQL Server 5.7\bin\mysql.exe',
                'C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe',
            ];

            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Check Herd MySQL path
            $herdPath = getenv('USERPROFILE') . '\\.config\\herd\\bin\\mysql.exe';
            if (file_exists($herdPath)) {
                return $herdPath;
            }

            // Try to find in Herd's library path
            $herdLibPath = getenv('USERPROFILE') . '\\.config\\herd-lite\\bin\\mysql.exe';
            if (file_exists($herdLibPath)) {
                return $herdLibPath;
            }
        }

        // Fallback to just 'mysql' and hope it's in PATH
        return 'mysql';
    }
}
