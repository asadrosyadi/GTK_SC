<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Dashboard\Models\HybridInference;
use Modules\Dashboard\Models\SensorIot;
use Modules\Dashboard\Models\SensorKamera;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeUser('petani@example.test', 'jTZids5M');
    }

    private function makeUser(string $email, string $iotId, bool $verified = true): User
    {
        return User::forceCreate([
            'name' => 'Pengguna ' . $iotId, 'email' => $email, 'email_verified_at' => $verified ? now() : null,
            'password' => bcrypt('secret123'), 'image' => 'default.png',
            'iot_id' => $iotId, 'iot_token' => 'token-' . $iotId,
        ]);
    }

    private function reading(string $iotId, int $titik, array $extra = []): SensorIot
    {
        return SensorIot::create(array_merge([
            'iot_id' => $iotId, 'titik' => $titik,
            'temperature' => $titik === 1 ? 30.0 : null, 'humidity' => $titik === 1 ? 70.0 : null,
            'ph' => 6.5, 'soil_moisture' => 40.0, 'ec' => 900, 'tds' => 576, 'soil_temp' => 27.0,
            'Nitrogen_Level' => 30, 'Phosphorus_Level' => null, 'Potassium_Level' => 120,
        ], $extra));
    }

    public function test_login_returns_bearer_token_and_hides_device_secret(): void
    {
        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'petani@example.test', 'password' => 'secret123', 'device_name' => 'android',
        ])->assertCreated()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('user.iot_id', 'jTZids5M');

        $this->assertNotEmpty($res->json('expires_at'));
        $this->withToken($res->json('token'))->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.email', 'petani@example.test')->assertJsonMissingPath('data.iot_token');
    }

    public function test_login_rejects_wrong_password_and_unverified_email(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'petani@example.test', 'password' => 'salah', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->makeUser('baru@example.test', 'BARU0001', verified: false);
        $this->postJson('/api/v1/auth/login', ['email' => 'baru@example.test', 'password' => 'secret123', 'device_name' => 'x'])
            ->assertForbidden();
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'petani@example.test', 'password' => 'secret123', 'device_name' => 'android',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_endpoints_require_token_and_answer_json(): void
    {
        $this->get('/api/v1/sensor-readings')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_sensor_readings_are_scoped_paginated_and_filtered(): void
    {
        $this->reading('jTZids5M', 1);
        $this->reading('jTZids5M', 2);
        $this->reading('LAIN0001', 1);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/sensor-readings')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);

        $res = $this->getJson('/api/v1/sensor-readings?titik=1&per_page=1')->assertOk()->assertJsonCount(1, 'data');
        $row = $res->json('data.0');
        $this->assertSame(1, $row['titik']);
        $this->assertEqualsWithDelta(1.273, $row['weather']['vpd'], 0.0005);
        $this->assertNull($row['soil']['phosphorus']);

        $this->getJson('/api/v1/sensor-readings?titik=9')->assertNotFound();
        $this->getJson('/api/v1/sensor-readings?from=2026-10-05&to=2026-10-01')->assertStatus(422);
    }

    public function test_latest_returns_one_reading_per_plot_and_show_is_scoped(): void
    {
        $this->reading('jTZids5M', 1, ['ph' => 6.0]);
        $this->reading('jTZids5M', 1, ['ph' => 6.2]);
        $this->reading('jTZids5M', 2);
        $other = $this->reading('LAIN0001', 1);
        Sanctum::actingAs($this->user);

        $res = $this->getJson('/api/v1/sensor-readings/latest')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEquals(6.2, $res->json('data.0.soil.ph'));
        $this->assertNull($res->json('data.1.weather.temperature'));
        $this->getJson("/api/v1/sensor-readings/{$other->id}")->assertNotFound();
    }

    public function test_other_device_requires_superadmin(): void
    {
        $this->reading('LAIN0001', 1);
        $admin = $this->makeUser('admin@example.test', 'ADMIN001');
        $admin->assignRole(Role::create(['name' => 'SuperAdmin', 'guard_name' => 'web']));

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/sensor-readings?iot_id=LAIN0001')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/sensor-readings?iot_id=LAIN0001')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_detections_include_image_url(): void
    {
        SensorKamera::create(['iot_id' => 'jTZids5M', 'titik' => 2, 'penyakit' => 'Penyakit blas (jamur)', 'probabilitas' => '88.5', 'image' => 'abc.jpg']);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/detections/latest')->assertOk()
            ->assertJsonPath('data.0.titik', 2)
            ->assertJsonPath('data.0.probabilitas', 88.5)
            ->assertJsonPath('data.0.image_url', asset('img/hama_padi/abc.jpg'));
    }

    public function test_inferences_expose_actuation(): void
    {
        HybridInference::create([
            'iot_id' => 'jTZids5M', 'titik' => 1, 'pest_label' => 'blast', 'pest_class' => 3, 'pest_confidence' => 0.9,
            'health_status' => 'Healthy', 'health_class' => 0, 'health_confidence' => 0.8, 'vpd_kpa' => 1.1,
            'actuation_action' => 'irrigation_notification', 'actuators' => ['irrigation' => true],
        ]);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/inferences?titik=1')->assertOk()
            ->assertJsonPath('data.0.actuation.action', 'irrigation_notification')
            ->assertJsonPath('data.0.actuation.actuators.irrigation', true);
    }

    public function test_plots_list_and_plot_2_uses_area_weather_of_point_1(): void
    {
        $this->reading('jTZids5M', 1);
        $this->reading('jTZids5M', 2, ['ph' => 5.1]);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/plots')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.has_weather_station', true)->assertJsonPath('data.1.has_weather_station', false);

        $this->getJson('/api/v1/plots/2')->assertOk()
            ->assertJsonPath('data.latest_reading.soil.ph', 5.1)
            ->assertJsonPath('data.area_weather.temperature', 30)
            ->assertJsonPath('data.area_weather.source_titik', 1);
        $this->getJson('/api/v1/plots/9')->assertNotFound();
    }

    public function test_forecast_proxies_python_service_or_reports_unavailable(): void
    {
        Sanctum::actingAs($this->user);
        Http::fake(['*/sensor-forecast/*' => Http::response(['iot_id' => 'jTZids5M', 'titik' => 1, 'forecast' => [['date' => '2026-10-05 12:00']]])]);
        $this->getJson('/api/v1/plots/1/forecast?days=7')->assertOk()->assertJsonPath('data.titik', 1);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/sensor-forecast/1') && str_contains($r->url(), 'days=7'));

        $this->getJson('/api/v1/plots/1/forecast?days=5')->assertOk();
        $this->getJson('/api/v1/plots/1/forecast?days=0')->assertStatus(422);
        $this->getJson('/api/v1/plots/1/forecast?days=31')->assertStatus(422);

        Http::fake(fn () => throw new ConnectionException('offline'));
        $this->getJson('/api/v1/plots/2/forecast')->assertStatus(503);
    }

    public function test_recommendations_fall_back_to_rules_when_model_offline(): void
    {
        $this->reading('jTZids5M', 1);
        Http::fake(fn () => throw new ConnectionException('offline'));
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/recommendations')->assertOk()
            ->assertJsonStructure(['data' => ['generated_at', 'model' => ['source', 'label'], 'overall', 'items', 'plots']])
            ->assertJsonPath('data.model.label', 'Aturan cadangan');
        $this->getJson('/api/v1/snapshot')->assertOk()->assertJsonPath('data.iot_id', 'jTZids5M');
    }

    public function test_device_alias_accepts_sensor_payload(): void
    {
        $this->postJson('/api/v1/device/sensor-readings', [
            'iot_id' => 'jTZids5M', 'iot_token' => 'token-jTZids5M', 'titik' => 2, 'ph' => 6.3,
        ])->assertCreated();
        $this->postJson('/api/v1/device/sensor-readings', [
            'iot_id' => 'jTZids5M', 'iot_token' => 'salah', 'titik' => 2,
        ])->assertUnauthorized();
    }
}
