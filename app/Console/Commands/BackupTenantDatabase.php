<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\TenantBackupService;

class BackupTenantDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:backup 
                            {tenant_id? : Specific tenant ID to backup (optional, backs up all if omitted)}
                            {--keep-days=30 : Number of days to keep old backups}
                            {--compress : Compress backup with gzip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup tenant database(s) automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $keepDays = $this->option('keep-days');
        $compress = $this->option('compress');

        if ($tenantId) {
            // Backup specific tenant
            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                $this->error("Tenant with ID '{$tenantId}' not found!");
                return 1;
            }

            $this->info("Backing up tenant: {$tenant->name} ({$tenant->id})");
            $this->backupTenant($tenant, $compress);
        } else {
            // Backup all tenants
            $tenants = Tenant::all();

            if ($tenants->isEmpty()) {
                $this->warn('No tenants found to backup.');
                return 0;
            }

            $this->info("Backing up {$tenants->count()} tenant(s)...");

            $progressBar = $this->output->createProgressBar($tenants->count());
            $progressBar->start();

            foreach ($tenants as $tenant) {
                $this->backupTenant($tenant, $compress);
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
        }

        // Cleanup old backups
        $this->cleanupOldBackups($keepDays);

        $this->info('Backup completed successfully!');
        return 0;
    }

    /**
     * Backup a single tenant database
     */
    protected function backupTenant(Tenant $tenant, bool $compress = false): bool
    {
        try {
            // Create backup filename
            $timestamp = Carbon::now()->format('Y-m-d_His');
            $filename = "{$tenant->id}_{$timestamp}.sql";

            // Create backup directory structure: backups/tenant_id/YYYY/MM/
            $backupDir = "backups/{$tenant->id}/" . Carbon::now()->format('Y/m');

            // Ensure directory exists
            Storage::disk('local')->makeDirectory($backupDir);

            $backupPath = storage_path("app/{$backupDir}/{$filename}");

            // Initialize tenancy for this tenant
            tenancy()->initialize($tenant);

            // Get database credentials to configure connection
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
                    $dbPassword = $dbPassword; // Use as-is if decryption fails
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

            // Use native PHP backup
            $backupService = new TenantBackupService();
            $success = $backupService->createBackup('tenant', $backupPath);

            if (!$success) {
                throw new \Exception('Native backup failed');
            }

            // Compress if requested
            if ($compress && file_exists($backupPath)) {
                $compressed = gzencode(file_get_contents($backupPath), 9);
                file_put_contents($backupPath . '.gz', $compressed);
                unlink($backupPath);
                $backupPath .= '.gz';
                $filename .= '.gz';
            }

            // Verify backup file exists and has content
            if (!file_exists($backupPath) || filesize($backupPath) === 0) {
                throw new \Exception('Backup file is empty or was not created');
            }

            // End tenancy
            tenancy()->end();

            // Log success
            Log::info('Tenant database backup created', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'database' => $dbName,
                'backup_file' => $backupPath,
                'file_size' => $this->formatBytes(filesize($backupPath)),
                'compressed' => $compress,
            ]);

            return true;
        } catch (\Exception $e) {
            tenancy()->end();

            Log::error('Failed to backup tenant database', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("Failed to backup tenant {$tenant->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Find mysqldump executable on system
     */
    protected function findMysqldump(): string
    {
        // Check if mysqldump is in PATH
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where mysqldump 2>nul', $output, $returnVar);
        } else {
            exec('which mysqldump 2>/dev/null', $output, $returnVar);
        }

        if ($returnVar === 0 && !empty($output)) {
            return trim($output[0]);
        }

        // Common paths on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $commonPaths = [
                'C:\xampp\mysql\bin\mysqldump.exe',
                'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe',
                'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe',
                'C:\Program Files\MySQL\MySQL Server 5.7\bin\mysqldump.exe',
                'C:\wamp64\bin\mysql\mysql8.0.31\bin\mysqldump.exe',
            ];

            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Check Herd MySQL path
            $herdPath = getenv('USERPROFILE') . '\\.config\\herd\\bin\\mysqldump.exe';
            if (file_exists($herdPath)) {
                return $herdPath;
            }

            // Try to find in Herd's library path
            $herdLibPath = getenv('USERPROFILE') . '\\.config\\herd-lite\\bin\\mysqldump.exe';
            if (file_exists($herdLibPath)) {
                return $herdLibPath;
            }
        }

        // Fallback to just 'mysqldump' and hope it's in PATH
        return 'mysqldump';
    }

    /**
     * Cleanup old backups
     */
    protected function cleanupOldBackups(int $keepDays): void
    {
        $this->info("Cleaning up backups older than {$keepDays} days...");

        $cutoffDate = Carbon::now()->subDays($keepDays);
        $deletedCount = 0;

        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $backupPath = "backups/{$tenant->id}";

            if (!Storage::disk('local')->exists($backupPath)) {
                continue;
            }

            // Get all backup files recursively
            $files = Storage::disk('local')->allFiles($backupPath);

            foreach ($files as $file) {
                $fileTime = Storage::disk('local')->lastModified($file);

                if ($fileTime < $cutoffDate->timestamp) {
                    Storage::disk('local')->delete($file);
                    $deletedCount++;

                    Log::info('Old backup deleted', [
                        'file' => $file,
                        'age_days' => Carbon::createFromTimestamp($fileTime)->diffInDays(Carbon::now()),
                    ]);
                }
            }

            // Remove empty directories
            $this->removeEmptyDirectories($backupPath);
        }

        if ($deletedCount > 0) {
            $this->info("Deleted {$deletedCount} old backup file(s).");
        } else {
            $this->info('No old backups to delete.');
        }
    }

    /**
     * Remove empty directories recursively
     */
    protected function removeEmptyDirectories(string $path): void
    {
        $directories = Storage::disk('local')->directories($path);

        foreach ($directories as $directory) {
            $this->removeEmptyDirectories($directory);

            // Check if directory is empty after recursive cleanup
            $files = Storage::disk('local')->allFiles($directory);
            $subdirs = Storage::disk('local')->directories($directory);

            if (empty($files) && empty($subdirs)) {
                Storage::disk('local')->deleteDirectory($directory);
            }
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
