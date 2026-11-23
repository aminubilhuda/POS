<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited(): void
    {
        $this->seed(DemoSeeder::class);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'brute@nusapos.id',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        // Percobaan ke-6 dalam 1 menit harus diblokir dengan 429 Too Many Requests
        $this->post('/login', [
            'email' => 'brute@nusapos.id',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_admin_pin_void_endpoint_is_rate_limited(): void
    {
        $this->seed(DemoSeeder::class);
        $kasir = User::query()->where('email', 'kasir@nusapos.id')->firstOrFail();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($kasir)->post('/pos/void', [
                'invoice_number' => 'INV/UNKNOWN/0001',
                'pin' => '000000',
                'reason' => 'Test throttle',
            ]);
        }

        $this->actingAs($kasir)->post('/pos/void', [
            'invoice_number' => 'INV/UNKNOWN/0001',
            'pin' => '000000',
            'reason' => 'Test throttle',
        ])->assertStatus(429);
    }

    public function test_backup_command_runs_successfully(): void
    {
        $this->artisan('pos:backup')->assertSuccessful();
    }

    public function test_owner_can_create_download_restore_and_delete_backup_while_non_owner_is_forbidden(): void
    {
        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'owner@nusapos.id')->firstOrFail();
        $admin = User::query()->where('email', 'admin@nusapos.id')->firstOrFail();

        // Admin bukan Owner -> 403
        $this->actingAs($admin)->post('/settings/backups')->assertForbidden();

        // Owner buat backup
        $this->actingAs($owner)->post('/settings/backups')->assertRedirect();
        $backups = \App\Http\Controllers\Settings\BackupController::listBackups();
        $this->assertNotEmpty($backups);
        $latestName = $backups[0]['name'];

        // Unduh backup
        $this->actingAs($owner)->get("/settings/backups/{$latestName}/download")->assertOk();

        // Restore dengan PIN salah ditolak
        $this->actingAs($owner)->post("/settings/backups/{$latestName}/restore", ['pin' => '000000'])
            ->assertSessionHasErrors('pin');

        // Restore dengan PIN Owner valid berhasil
        $this->actingAs($owner)->post("/settings/backups/{$latestName}/restore", ['pin' => '123456'])
            ->assertRedirect();

        // Hapus backup
        $this->actingAs($owner)->delete("/settings/backups/{$latestName}")->assertRedirect();
    }
}
