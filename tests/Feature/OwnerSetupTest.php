<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_owner_setup_opens_when_no_users_and_closes_permanently_after_registration(): void
    {
        $this->assertEquals(0, User::withTrashed()->count());

        // Ketika belum ada user, /login diarahkan ke /setup
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk();

        // Daftar Owner pertama kali
        $this->post('/setup', [
            'branch_code' => 'CAB01',
            'branch_name' => 'Toko Utama',
            'branch_address' => 'Jl. Merdeka No. 1',
            'branch_phone' => '021-555-1234',
            'tax_rate' => 11,
            'name' => 'Owner Utama',
            'email' => 'owner@toko.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'pin' => '654321',
        ])->assertRedirect('/pos');

        $this->assertAuthenticated();
        $this->assertEquals(1, User::withTrashed()->count());
        $this->assertEquals('owner', User::first()->role);
        $this->assertTrue(Branch::query()->where('code', 'CAB01')->exists());

        // Logout lalu coba akses /setup lagi -> harus selalu diarahkan ke /login dan tidak bisa daftar lagi
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->get('/setup')->assertRedirect('/login');

        $this->post('/setup', [
            'branch_code' => 'CAB02',
            'branch_name' => 'Toko Hacker',
            'tax_rate' => 11,
            'name' => 'Second Owner',
            'email' => 'hacker@toko.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'pin' => '111111',
        ])->assertRedirect('/login');

        $this->assertEquals(1, User::withTrashed()->count());
    }
}
