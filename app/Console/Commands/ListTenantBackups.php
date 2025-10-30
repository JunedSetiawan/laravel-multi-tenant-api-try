<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ListTenantBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:backups 
                            {tenant_id? : Specific tenant ID (optional, shows all if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenant database backups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                $this->error("Tenant with ID '{$tenantId}' not found!");
                return 1;
            }

            $this->listTenantBackups($tenant);
        } else {
            $tenants = Tenant::all();

            if ($tenants->isEmpty()) {
                $this->warn('No tenants found.');
                return 0;
            }

            foreach ($tenants as $tenant) {
                $this->listTenantBackups($tenant);
                $this->newLine();
            }
        }

        return 0;
    }

    /**
     * List backups for a specific tenant
     */
    protected function listTenantBackups(Tenant $tenant): void
    {
        $this->info("Tenant: {$tenant->name} ({$tenant->id})");

        $backupPath = "backups/{$tenant->id}";

        if (!Storage::disk('local')->exists($backupPath)) {
            $this->warn('  No backups found.');
            return;
        }

        $files = Storage::disk('local')->allFiles($backupPath);

        if (empty($files)) {
            $this->warn('  No backups found.');
            return;
        }

        // Sort by modification time, latest first
        usort($files, function ($a, $b) {
            return Storage::disk('local')->lastModified($b) <=> Storage::disk('local')->lastModified($a);
        });

        $backups = [];
        foreach ($files as $file) {
            $fileTime = Storage::disk('local')->lastModified($file);
            $fileSize = Storage::disk('local')->size($file);

            $backups[] = [
                'File' => basename($file),
                'Date' => Carbon::createFromTimestamp($fileTime)->format('Y-m-d H:i:s'),
                'Age' => Carbon::createFromTimestamp($fileTime)->diffForHumans(),
                'Size' => $this->formatBytes($fileSize),
                'Path' => $file,
            ];
        }

        $this->table(
            ['File', 'Date', 'Age', 'Size'],
            array_map(function ($backup) {
                return [
                    $backup['File'],
                    $backup['Date'],
                    $backup['Age'],
                    $backup['Size'],
                ];
            }, $backups)
        );

        $this->info("  Total: " . count($backups) . " backup(s)");
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
