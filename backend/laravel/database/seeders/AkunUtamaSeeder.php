<?php

namespace Database\Seeders;

use App\Enum\PermissionAction;
use App\Enum\PermissionResource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AkunUtamaSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [];
        foreach (PermissionResource::cases() as $resource) {
            foreach (PermissionAction::cases() as $action) {
                $permissions[] = Permission::firstOrCreate(
                    ['name' => $action->value . '_' . $resource->value, 'guard_name' => 'web']
                );
            }
        }

        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        $cfg = config('monitoring.admin');
        $user = User::where('email', $cfg['email'])->first();
        $password = (string) ($cfg['password'] ?? '');
        $generated = null;

        if (! $user) {
            if ($password === '') {
                $password = $generated = Str::password(16, symbols: false);
            }
            $user = User::forceCreate([
                'name' => $cfg['name'],
                'email' => $cfg['email'],
                'password' => $password,
                'image' => 'default.jpg',
                'iot_id' => $cfg['iot_id'],
                'iot_token' => $cfg['iot_token'],
                'email_verified_at' => now(),
            ]);
            $this->command?->info("Akun utama dibuat: {$user->email} (iot_id {$user->iot_id})");
        } else {
            $user->forceFill([
                'iot_id' => $user->iot_id ?: $cfg['iot_id'],
                'iot_token' => $user->iot_token ?: $cfg['iot_token'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            if ($password !== '') {
                $user->password = $password;
            }
            $user->save();
            $this->command?->info("Akun utama sudah ada: {$user->email}" . ($password !== '' ? ' (password diperbarui dari ADMIN_PASSWORD)' : ' (password tidak diubah)'));
        }

        $user->assignRole($superAdmin);

        if ($generated) {
            $this->command?->warn("Password awal akun utama: {$generated}");
            $this->command?->warn('Simpan password ini lalu ganti lewat menu Akun > Profil. Password tidak akan ditampilkan lagi.');
        }
    }
}
