<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Dashboard\Models\SensorIot;
use Modules\Dashboard\Models\SensorKamera;
use Tests\TestCase;

class DummyDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::forceCreate([
            'name' => 'Petani', 'email' => 'petani@example.test', 'email_verified_at' => now(),
            'password' => bcrypt('secret123'), 'image' => 'x.png', 'iot_id' => 'jTZids5M', 'iot_token' => 't',
        ]);
    }

    public function test_generates_flagged_history_for_both_plots(): void
    {
        $this->artisan('taniverse:dummy', ['--hari' => 1])->assertSuccessful();

        $this->assertSame(194, SensorIot::where('is_dummy', true)->count());
        $this->assertSame(0, SensorIot::where('is_dummy', false)->count());
        $this->assertSame(0, SensorIot::where('titik', 2)->whereNotNull('temperature')->count());
        $p1 = SensorIot::where('titik', 1)->get();
        $this->assertTrue($p1->every(fn ($r) => $r->temperature >= 20 && $r->temperature <= 36));
        $this->assertTrue($p1->every(fn ($r) => $r->humidity >= 50 && $r->humidity <= 99));
        $this->assertTrue(SensorIot::all()->every(fn ($r) => $r->soil_moisture >= 30 && $r->soil_moisture <= 48));
    }

    public function test_refuses_to_duplicate_existing_dummy_history(): void
    {
        $this->artisan('taniverse:dummy', ['--hari' => 1])->assertSuccessful();
        $this->artisan('taniverse:dummy', ['--hari' => 1])
            ->expectsOutputToContain('sudah ada')
            ->assertFailed();
        $this->assertSame(194, SensorIot::count());
    }

    public function test_unknown_device_is_rejected(): void
    {
        $this->artisan('taniverse:dummy', ['--iot' => 'TIDAKADA'])->assertFailed();
        $this->assertSame(0, SensorIot::count());
    }

    public function test_snapshot_and_api_mark_dummy_data(): void
    {
        $this->artisan('taniverse:dummy', ['--hari' => 1])->assertSuccessful();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/snapshot')->assertOk()->assertJsonPath('data.dummy', true);
        $this->getJson('/api/v1/sensor-readings/latest')->assertOk()->assertJsonPath('data.0.is_dummy', true);
    }

    public function test_hapus_removes_only_dummy_rows(): void
    {
        $this->artisan('taniverse:dummy', ['--hari' => 1])->assertSuccessful();
        SensorIot::create(['iot_id' => 'jTZids5M', 'titik' => 1, 'ph' => 6.9]);
        SensorKamera::create(['iot_id' => 'jTZids5M', 'titik' => 1, 'penyakit' => 'Normal / Sehat', 'probabilitas' => '90', 'image' => 'asli.jpg']);
        SensorKamera::create(['iot_id' => 'jTZids5M', 'titik' => 2, 'penyakit' => 'Bercak coklat', 'probabilitas' => '95', 'image' => 'x.jpg', 'is_dummy' => true]);

        $this->artisan('taniverse:dummy', ['--hapus' => true])
            ->expectsOutputToContain('194 baris sensor, 1 deteksi kamera')
            ->assertSuccessful();

        $this->assertSame(1, SensorIot::count());
        $this->assertEquals(6.9, (float) SensorIot::first()->ph);
        $this->assertSame(['asli.jpg'], SensorKamera::pluck('image')->all());
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/snapshot')->assertJsonPath('data.dummy', false);
    }

    public function test_camera_option_reports_when_detection_service_is_offline(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->artisan('taniverse:dummy', ['--hari' => 1, '--kamera' => true, '--gambar' => base_path('tests')])
            ->expectsOutputToContain('Foto dummy dilewati')
            ->assertSuccessful();
        $this->assertSame(0, DB::table('sensor_kameras')->count());
    }
}
