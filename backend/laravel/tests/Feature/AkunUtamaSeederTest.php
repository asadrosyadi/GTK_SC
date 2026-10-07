<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AkunUtamaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AkunUtamaSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.admin' => [
            'name' => 'Pi',
            'email' => 'pi@pi.com',
            'password' => null,
            'iot_id' => 'jTZids5M',
            'iot_token' => 'lOsx0KMJSAfJpxEe',
        ]]);
    }

    public function test_creates_main_superadmin_with_roles_and_permissions(): void
    {
        config(['monitoring.admin.password' => 'rahasia-uji-123']);

        $this->seed(AkunUtamaSeeder::class);

        $u = User::where('email', 'pi@pi.com')->firstOrFail();
        $this->assertTrue(Hash::check('rahasia-uji-123', $u->password));
        $this->assertSame('jTZids5M', $u->iot_id);
        $this->assertSame('lOsx0KMJSAfJpxEe', $u->iot_token);
        $this->assertNotNull($u->email_verified_at);
        $this->assertTrue($u->hasRole('SuperAdmin'));
        $this->assertSame(8, Permission::count());
        $this->assertTrue($u->can('view_user') && $u->can('delete_role'));
        $this->assertTrue(Role::where('name', 'User')->exists());
    }

    public function test_generates_random_password_when_none_configured(): void
    {
        config(['monitoring.admin.password' => null]);

        $this->artisan('db:seed', ['--class' => AkunUtamaSeeder::class])
            ->expectsOutputToContain('Password awal akun utama:')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'pi@pi.com')->exists());
    }

    public function test_is_idempotent_and_keeps_existing_password_and_other_users(): void
    {
        config(['monitoring.admin.password' => 'rahasia-uji-123']);
        $this->seed(AkunUtamaSeeder::class);
        $lain = User::forceCreate([
            'name' => 'Petani', 'email' => 'petani@example.test', 'password' => 'x', 'image' => 'a.png',
            'iot_id' => 'jTZids5M', 'iot_token' => 't', 'email_verified_at' => now(),
        ]);

        config(['monitoring.admin.password' => null]);
        $this->seed(AkunUtamaSeeder::class);
        $this->seed();

        $this->assertSame(1, User::where('email', 'pi@pi.com')->count());
        $this->assertTrue(Hash::check('rahasia-uji-123', User::where('email', 'pi@pi.com')->value('password')));
        $this->assertTrue(User::whereKey($lain->id)->exists());
        $this->assertSame(2, Role::count());
        $this->assertSame(8, Permission::count());
    }
}
