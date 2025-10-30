<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate Master API Key
        $masterApiKey = 'mk_' . Str::random(64);

        $this->command->newLine();
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('🔐 SUPER ADMIN CREDENTIALS');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->newLine();
        $this->command->line('Master API Key (untuk create tenant):');
        $this->command->warn($masterApiKey);
        $this->command->newLine();
        $this->command->info('Simpan Master API Key ini ke file .env:');
        $this->command->line('MASTER_API_KEY=' . $masterApiKey);
        $this->command->newLine();
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->warn('⚠️  PENTING: Copy Master API Key diatas dan simpan dengan aman!');
        $this->command->warn('⚠️  Anda akan memerlukan ini untuk membuat tenant baru.');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->newLine();

        // Informasi endpoint
        $this->command->line('📡 ENDPOINT MANAGEMENT:');
        $this->command->line('Base URL: http://localhost:8000/api/central');
        $this->command->line('Create Tenant: POST /tenants');
        $this->command->line('List Tenants: GET /tenants');
        $this->command->newLine();
    }
}
