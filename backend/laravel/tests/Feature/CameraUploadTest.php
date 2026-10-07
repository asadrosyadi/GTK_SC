<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Modules\Dashboard\Models\SensorIot;
use Tests\TestCase;

class CameraUploadTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0fake-jpeg-body\xFF\xD9";

    protected function setUp(): void
    {
        parent::setUp();
        User::forceCreate([
            'name' => 'Petani Uji', 'email' => 'petani@example.test', 'email_verified_at' => now(),
            'password' => bcrypt('secret123'), 'image' => 'default.png',
            'iot_id' => 'jTZids5M', 'iot_token' => 'token-uji',
        ]);
    }

    private function upload(string $body, array $headers = [])
    {
        $headers = array_merge([
            'X-IOT-ID' => 'jTZids5M', 'X-IOT-TOKEN' => 'token-uji', 'X-TITIK' => '2',
            'CONTENT_TYPE' => 'image/jpeg', 'Accept' => 'application/json',
        ], $headers);
        $server = [];
        foreach ($headers as $k => $v) {
            $server[$k === 'CONTENT_TYPE' ? $k : 'HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('POST', '/api/kirim_gambar', [], [], [], $server, $body);
    }

    public function test_image_is_forwarded_to_detection_service_with_plot_and_device(): void
    {
        Http::fake(['*/detect*' => Http::response([
            'titik' => 2, 'detected' => true, 'saved' => true, 'penyakit' => 'Penyakit blas (jamur)', 'probabilitas' => 88.1,
        ], 201)]);

        $this->upload(self::JPEG)->assertCreated()->assertJson(['titik' => 2, 'saved' => true]);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/detect?titik=2&iot_id=jTZids5M')
            && $r->body() === self::JPEG && $r->hasHeader('Content-Type', 'image/jpeg'));
    }

    public function test_no_leaf_detected_is_reported_without_saving(): void
    {
        Http::fake(['*/detect*' => Http::response(['detected' => false, 'saved' => false,
            'reason' => 'tidak ada daun terdeteksi pada gambar'], 200)]);

        $this->upload(self::JPEG)->assertOk()->assertJson(['saved' => false]);
    }

    public function test_rejects_wrong_token_unknown_plot_and_non_jpeg(): void
    {
        Http::fake();

        $this->upload(self::JPEG, ['X-IOT-TOKEN' => 'salah'])->assertUnauthorized();
        $this->upload(self::JPEG, ['X-TITIK' => '9'])->assertStatus(422);
        $this->upload('bukan gambar')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_detection_service_offline_returns_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->upload(self::JPEG)->assertStatus(503);
    }

    public function test_gateway_payload_for_plot_2_stores_soil_only(): void
    {
        $this->postJson('/api/kirim_sensor', [
            'iot_id' => 'jTZids5M', 'iot_token' => 'token-uji', 'titik' => 2,
            'ph' => 6.1, 'soil_moisture' => 41.5, 'ec' => 820, 'tds' => 524.8, 'soil_temp' => 27.3,
            'Nitrogen_Level' => 28, 'Phosphorus_Level' => null, 'Potassium_Level' => 140,
            'battery_level' => 76, 'signal_strength' => 58,
        ])->assertCreated();

        $row = SensorIot::where('titik', 2)->first();
        $this->assertEquals(6.1, (float) $row->ph);
        $this->assertNull($row->Phosphorus_Level);
        $this->assertNull($row->temperature);
        $this->assertNull($row->rainfall);
    }
}
