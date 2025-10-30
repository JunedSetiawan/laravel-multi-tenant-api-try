<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TenantBackupController extends Controller
{
    /**
     * Trigger manual backup for a tenant
     */
    public function backup(Request $request, $tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        try {
            $compress = $request->input('compress', true);

            // Run backup command
            Artisan::call('tenant:backup', [
                'tenant_id' => $tenantId,
                '--compress' => $compress,
            ]);

            return response()->json([
                'message' => 'Backup created successfully',
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->name,
                'compressed' => $compress,
            ]);
        } catch (\Exception $e) {
            Log::error('Backup API failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create backup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all backups for a tenant
     */
    public function list($tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        $backupPath = "backups/{$tenantId}";

        if (!Storage::disk('local')->exists($backupPath)) {
            return response()->json([
                'data' => [],
                'total' => 0
            ]);
        }

        $files = Storage::disk('local')->allFiles($backupPath);

        if (empty($files)) {
            return response()->json([
                'data' => [],
                'total' => 0
            ]);
        }

        // Sort by modification time, latest first
        usort($files, function ($a, $b) {
            return Storage::disk('local')->lastModified($b) <=> Storage::disk('local')->lastModified($a);
        });

        $backups = array_map(function ($file) {
            $fileTime = Storage::disk('local')->lastModified($file);
            $fileSize = Storage::disk('local')->size($file);

            return [
                'filename' => basename($file),
                'path' => $file,
                'date' => Carbon::createFromTimestamp($fileTime)->toISOString(),
                'age' => Carbon::createFromTimestamp($fileTime)->diffForHumans(),
                'size' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'compressed' => str_ends_with($file, '.gz'),
            ];
        }, $files);

        return response()->json([
            'data' => $backups,
            'total' => count($backups)
        ]);
    }

    /**
     * Download a backup file
     */
    public function download($tenantId, Request $request)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        $filename = $request->input('file');

        if (!$filename) {
            return response()->json([
                'error' => 'Backup filename is required'
            ], 400);
        }

        $backupPath = "backups/{$tenantId}";
        $files = Storage::disk('local')->allFiles($backupPath);

        // Find the file
        $targetFile = null;
        foreach ($files as $file) {
            if (basename($file) === $filename) {
                $targetFile = $file;
                break;
            }
        }

        if (!$targetFile) {
            return response()->json([
                'error' => 'Backup file not found'
            ], 404);
        }

        $fullPath = storage_path("app/{$targetFile}");

        if (!file_exists($fullPath)) {
            return response()->json([
                'error' => 'Backup file does not exist'
            ], 404);
        }

        return response()->download($fullPath, $filename);
    }

    /**
     * Delete a backup file
     */
    public function delete($tenantId, Request $request)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        $filename = $request->input('file');

        if (!$filename) {
            return response()->json([
                'error' => 'Backup filename is required'
            ], 400);
        }

        $backupPath = "backups/{$tenantId}";
        $files = Storage::disk('local')->allFiles($backupPath);

        // Find the file
        $targetFile = null;
        foreach ($files as $file) {
            if (basename($file) === $filename) {
                $targetFile = $file;
                break;
            }
        }

        if (!$targetFile) {
            return response()->json([
                'error' => 'Backup file not found'
            ], 404);
        }

        try {
            Storage::disk('local')->delete($targetFile);

            Log::info('Backup deleted via API', [
                'tenant_id' => $tenantId,
                'file' => $targetFile,
            ]);

            return response()->json([
                'message' => 'Backup deleted successfully',
                'file' => $filename
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete backup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore tenant database from backup
     */
    public function restore($tenantId, Request $request)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        $filename = $request->input('file');

        if (!$filename) {
            // Use latest backup if not specified
            $backupPath = "backups/{$tenantId}";

            if (!Storage::disk('local')->exists($backupPath)) {
                return response()->json([
                    'error' => 'No backups found for this tenant'
                ], 404);
            }

            $files = Storage::disk('local')->allFiles($backupPath);

            if (empty($files)) {
                return response()->json([
                    'error' => 'No backups found for this tenant'
                ], 404);
            }

            usort($files, function ($a, $b) {
                return Storage::disk('local')->lastModified($b) <=> Storage::disk('local')->lastModified($a);
            });

            $filename = basename($files[0]);
        }

        try {
            // Find full path
            $backupPath = "backups/{$tenantId}";
            $files = Storage::disk('local')->allFiles($backupPath);

            $targetFile = null;
            foreach ($files as $file) {
                if (basename($file) === $filename) {
                    $targetFile = $file;
                    break;
                }
            }

            if (!$targetFile) {
                return response()->json([
                    'error' => 'Backup file not found'
                ], 404);
            }

            // Run restore command
            Artisan::call('tenant:restore', [
                'tenant_id' => $tenantId,
                'backup_file' => $targetFile,
            ]);

            return response()->json([
                'message' => 'Database restored successfully',
                'tenant_id' => $tenantId,
                'backup_file' => $filename,
            ]);
        } catch (\Exception $e) {
            Log::error('Restore API failed', [
                'tenant_id' => $tenantId,
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to restore database',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get backup statistics
     */
    public function stats()
    {
        $tenants = Tenant::all();
        $stats = [];

        foreach ($tenants as $tenant) {
            $backupPath = "backups/{$tenant->id}";

            if (!Storage::disk('local')->exists($backupPath)) {
                $stats[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'backup_count' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 B',
                    'latest_backup' => null,
                ];
                continue;
            }

            $files = Storage::disk('local')->allFiles($backupPath);
            $totalSize = 0;
            $latestBackup = null;
            $latestTime = 0;

            foreach ($files as $file) {
                $totalSize += Storage::disk('local')->size($file);
                $fileTime = Storage::disk('local')->lastModified($file);

                if ($fileTime > $latestTime) {
                    $latestTime = $fileTime;
                    $latestBackup = basename($file);
                }
            }

            $stats[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'backup_count' => count($files),
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'latest_backup' => $latestBackup,
                'latest_backup_date' => $latestTime > 0 ? Carbon::createFromTimestamp($latestTime)->toISOString() : null,
                'latest_backup_age' => $latestTime > 0 ? Carbon::createFromTimestamp($latestTime)->diffForHumans() : null,
            ];
        }

        return response()->json([
            'data' => $stats,
            'total_tenants' => count($stats),
        ]);
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
