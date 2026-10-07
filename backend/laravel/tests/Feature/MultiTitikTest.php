<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Dashboard\Models\SensorIot;
use Modules\Dashboard\Models\SensorKamera;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MultiTitikTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::forceCreate([
            'name' => 'Petani Uji',
            'email' => 'petani@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
            'image' => 'default.png',
            'iot_id' => 'jTZids5M',
            'iot_token' => 'token-uji',
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'iot_id' => 'jTZids5M',
            'iot_token' => 'token-uji',
            'temperature' => 29.3, 'humidity' => 72, 'windspeed' => 1.4, 'rainfall' => 0,
            'light_intensity' => 750, 'pressure' => 1008.3,
            'ph' => 6.8, 'soil_moisture' => 76, 'ec' => 1100, 'tds' => 480, 'soil_temp' => 26.7,
            'battery_level' => 85, 'signal_strength' => 65,
            'Nitrogen_Level' => 30, 'Phosphorus_Level' => 15, 'Potassium_Level' => 120,
        ], $extra);
    }

    private function seedTwoPlots(): void
    {
        $this->postJson('/api/kirim_sensor', $this->payload(['titik' => 1]))->assertCreated();
        $this->postJson('/api/kirim_sensor', $this->payload([
            'titik' => 2, 'ph' => 5.1, 'soil_moisture' => 58, 'Nitrogen_Level' => 15,
        ]))->assertCreated();

        SensorKamera::create(['iot_id' => 'jTZids5M', 'titik' => 1, 'penyakit' => 'Penyakit blas (jamur)', 'probabilitas' => '86.5', 'image' => 'a.jpg']);
        SensorKamera::create(['iot_id' => 'jTZids5M', 'titik' => 2, 'penyakit' => 'Normal / Sehat', 'probabilitas' => '91', 'image' => 'b.jpg']);
    }

    public function test_sensor_node_stores_titik_and_titik2_ignores_weather(): void
    {
        $this->seedTwoPlots();

        $t1 = SensorIot::where('titik', 1)->first();
        $t2 = SensorIot::where('titik', 2)->first();

        $this->assertEquals(29.3, (float) $t1->temperature);
        $this->assertEquals(1008.3, (float) $t1->pressure);
        $this->assertNull($t2->temperature);
        $this->assertNull($t2->humidity);
        $this->assertEquals(5.1, (float) $t2->ph);
    }

    public function test_default_titik_is_1_and_unknown_titik_is_rejected(): void
    {
        $this->postJson('/api/kirim_sensor', $this->payload())->assertCreated();
        $this->assertEquals(1, SensorIot::first()->titik);

        $this->postJson('/api/kirim_sensor', $this->payload(['titik' => 3]))->assertStatus(422);
        $this->postJson('/api/kirim_sensor', $this->payload(['iot_token' => 'salah']))->assertStatus(401);
    }

    public function test_weather_rainfall_is_24_hour_total_for_bmkg_categories(): void
    {
        $old = SensorIot::create(array_merge($this->payload(['titik' => 1, 'rainfall' => 99]), ['iot_id' => 'jTZids5M']));
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->postJson('/api/kirim_sensor', $this->payload(['titik' => 1, 'rainfall' => 30]))->assertCreated();
        $this->postJson('/api/kirim_sensor', $this->payload(['titik' => 1, 'rainfall' => 25]))->assertCreated();

        $this->actingAs($this->user)->getJson('/datasensor')->assertOk()
            ->assertJsonPath('weather.rainfall', 55)
            ->assertJsonPath('weather.rainfall_last', 25);
    }

    public function test_storage_limit_drops_only_oldest_rows(): void
    {
        $controller = new \App\Http\Controllers\SensorController();
        $limit = (new \ReflectionClass($controller))->getConstant('MAX_SENSOR_ROWS');

        $rows = [];
        for ($i = 1; $i <= $limit + 3; $i++) {
            $rows[] = ['iot_id' => 'jTZids5M', 'titik' => 1, 'temperature' => 25, 'created_at' => now(), 'updated_at' => now()];
            if (count($rows) === 5000) { \DB::table('sensor_iots')->insert($rows); $rows = []; }
        }
        if ($rows) { \DB::table('sensor_iots')->insert($rows); }

        $controller->cekDanBersihkanDataSensor();

        $this->assertSame($limit, \DB::table('sensor_iots')->count());
        $this->assertSame(4, (int) \DB::table('sensor_iots')->min('id'));
    }

    public function test_history_api_can_filter_by_titik(): void
    {
        $this->seedTwoPlots();

        $this->getJson('/api/sensor?iot_id=jTZids5M')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/sensor?iot_id=jTZids5M&titik=2')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.titik', 2);
    }

    public function test_dashboard_sensor_and_camera_endpoints_return_both_plots(): void
    {
        $this->seedTwoPlots();

        $this->actingAs($this->user)->getJson('/datasensor')->assertOk()
            ->assertJsonPath('weather.temperature', 29.3)
            ->assertJsonPath('titik.1.soil.ph', 6.8)
            ->assertJsonPath('titik.2.soil.ph', 5.1)
            ->assertJsonPath('titik.2.label', 'Petak 2');

        $this->actingAs($this->user)->getJson('/datayolo')->assertOk()
            ->assertJsonPath('titik.1.penyakit', 'Penyakit blas (jamur)')
            ->assertJsonPath('titik.2.penyakit', 'Normal / Sehat');
    }

    public function test_recommendation_uses_offline_model_with_full_snapshot(): void
    {
        $this->seedTwoPlots();

        Http::fake([
            '*/recommendation' => Http::response([
                'model' => ['source' => 'random_forest', 'f1_micro' => 0.953],
                'petak' => [
                    '1' => ['label' => 'Petak 1', 'actuation' => ['action' => 'recommend_selective_insecticide_spraying']],
                    '2' => ['label' => 'Petak 2', 'actuation' => ['action' => 'irrigation_notification']],
                ],
                'summary' => [[
                    'action' => 'fungisida', 'title' => 'Aplikasi fungisida', 'message' => 'x', 'type' => 'danger',
                    'icon' => 'bx', 'probability' => 1.0, 'petak' => ['Petak 1'],
                ]],
            ]),
        ]);

        $this->actingAs($this->user)->getJson('/getRecommendations')->assertOk()
            ->assertJsonPath('model.source', 'random_forest')
            ->assertJsonPath('recommendations.0.title', 'Aplikasi fungisida')
            ->assertJsonPath('petak.2.actuation.action', 'irrigation_notification');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/recommendation')
                && $request['weather']['temperature'] == 29.3
                && $request['titik']['2']['soil']['ph'] == 5.1
                && $request['titik']['1']['camera']['penyakit'] === 'Penyakit blas (jamur)';
        });
    }

    public function test_recommendation_falls_back_to_php_rules_when_model_offline(): void
    {
        $this->seedTwoPlots();

        Http::fake(fn () => throw new ConnectionException('offline'));

        $response = $this->actingAs($this->user)->getJson('/getRecommendations')->assertOk()
            ->assertJsonPath('model.source', 'php_rules');

        $this->assertArrayHasKey('1', $response->json('petak'));
        $this->assertArrayHasKey('2', $response->json('petak'));
        $this->assertNotEmpty($response->json('recommendations'));
    }

    public function test_dashboard_page_renders_for_each_plot(): void
    {
        $this->seedTwoPlots();
        Http::fake(['*' => Http::response(['error' => 'offline'], 500)]);

        $this->actingAs($this->user)->get('/dashboard')->assertOk()
            ->assertSee('Petak 1')->assertSee('Petak 2')
            ->assertSee('soil-ph-2', false)
            ->assertSee('Belum ada ramalan sensor')
            ->assertDontSee('0.00 °C');
        $this->actingAs($this->user)->get('/dashboard?titik=2&days=7')->assertOk();
    }

    private function sampleForecast(string $iotId): array
    {
        $vars = [];
        foreach (['temperature' => '°C', 'humidity' => '%', 'vpd' => 'kPa', 'windspeed' => 'm/s', 'rainfall' => 'mm',
            'light_intensity' => 'lux', 'pressure' => 'Pa', 'ph' => '', 'soil_moisture' => '%', 'ec' => 'µS/cm',
            'tds' => 'ppm', 'soil_temp' => '°C', 'Nitrogen_Level' => 'mg/kg', 'Phosphorus_Level' => 'mg/kg',
            'Potassium_Level' => 'mg/kg'] as $key => $unit) {
            $vars[] = ['key' => $key, 'label' => $key, 'unit' => $unit, 'method' => 'holt_winters',
                'method_label' => 'Holt-Winters aditif (tren teredam)', 'mae' => 0.5, 'mase' => 0.8];
        }
        $row = fn (string $date, float $t) => ['date' => $date, 'temperature' => $t, 'humidity' => 91.0, 'vpd' => 0.3,
            'windspeed' => 1.2, 'rainfall' => 0.0, 'light_intensity' => 800.0, 'pressure' => 100800.0, 'ph' => 6.5,
            'soil_moisture' => 40.0, 'ec' => 1100.0, 'tds' => 715.0, 'soil_temp' => 27.0, 'Nitrogen_Level' => 30.0,
            'Phosphorus_Level' => 15.0, 'Potassium_Level' => 120.0];

        return [
            'iot_id' => $iotId, 'titik' => 1, 'resolution_hours' => 4, 'days' => 3,
            'last_observation' => '2026-10-05 08:00', 'variables' => $vars,
            'history' => [$row('2026-10-05 04:00', 26.1), $row('2026-10-05 08:00', 27.4)],
            'forecast' => [
                $row('2026-10-05 12:00', 31.37) + ['rainfall_24h' => 0.0, 'status' => []],
                $row('2026-10-05 16:00', 24.5) + ['rainfall_24h' => 0.0, 'status' => [['code' => 'blas', 'label' => 'Cuaca mendukung blas']]],
            ],
        ];
    }

    public function test_dashboard_shows_15_sensor_forecast(): void
    {
        $this->seedTwoPlots();
        Http::fake(['*/sensor-forecast/*' => Http::response($this->sampleForecast('jTZids5M')),
            '*' => Http::response(['error' => 'offline'], 500)]);

        $this->actingAs($this->user)->get('/dashboard?days=3')->assertOk()
            ->assertSee('sensorTrendChart', false)
            ->assertSee('31.37')
            ->assertSee('Potassium_Level')
            ->assertSee('Rawan penyakit blas')
            ->assertDontSee('Belum ada ramalan sensor');

        Http::assertSent(fn ($req) => str_contains($req->url(), '/sensor-forecast/1') && str_contains($req->url(), 'days=3'));
    }

    public function test_sensor_forecast_for_other_device_is_ignored(): void
    {
        $this->seedTwoPlots();
        Http::fake(['*/sensor-forecast/*' => Http::response($this->sampleForecast('PERANGKAT-LAIN')),
            '*' => Http::response(['error' => 'offline'], 500)]);

        $this->actingAs($this->user)->get('/dashboard')->assertOk()
            ->assertSee('Belum ada ramalan sensor')
            ->assertDontSee('31.37');
    }

    public function test_admin_monitoring_page_shows_sensor_forecast(): void
    {
        $this->seedTwoPlots();
        $this->grantDataAccess();
        Http::fake(['*/sensor-forecast/*' => Http::response($this->sampleForecast('jTZids5M')),
            '*' => Http::response(['error' => 'offline'], 500)]);

        $this->actingAs($this->user)->get('/data/monitoring/jTZids5M?days=7')->assertOk()
            ->assertSee('adminSensorTrendChart', false)
            ->assertSee('31.37')
            ->assertSee('Rawan penyakit blas');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sensor-forecast/1') && str_contains($req->url(), 'days=7'));
    }

    public function test_admin_monitoring_page_renders_when_services_offline(): void
    {
        $this->seedTwoPlots();
        $this->grantDataAccess();
        Http::fake(['*' => Http::response(['error' => 'offline'], 500)]);

        $this->actingAs($this->user)->get('/data/monitoring/jTZids5M')->assertOk()
            ->assertSee('Belum ada ramalan sensor')
            ->assertDontSee('0.00 °C');
        $this->actingAs($this->user)->get('/data/monitoring/TIDAK-ADA')->assertNotFound();
    }

    public function test_data_pages_require_view_user_permission(): void
    {
        $this->seedTwoPlots();
        Http::fake(['*' => Http::response(['error' => 'offline'], 500)]);

        foreach (['/data', '/data/monitoring/jTZids5M', '/data/historyiot/jTZids5M', '/data/historyyolo/jTZids5M',
            '/monitor_iot?iot_id=jTZids5M', '/monitor_kamera?iot_id=jTZids5M', '/export-data-user/jTZids5M',
            '/export-data-yolo-user/jTZids5M'] as $url) {
            $this->actingAs($this->user)->get($url)->assertForbidden();
        }
    }

    private function grantDataAccess(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'view_user', 'guard_name' => 'web']));
    }
}
