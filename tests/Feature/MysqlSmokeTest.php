<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Loan;
use App\Models\PhysicalLocation;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Uji asap (smoke test) untuk memastikan semua halaman & query utama jalan
 * di MySQL/MariaDB setelah porting dari PostgreSQL. Jalankan pada database
 * yang sudah di-migrate + di-seed:  php artisan test --filter=MysqlSmokeTest
 * Semua data uji dibuat di dalam transaksi dan otomatis di-rollback.
 */
class MysqlSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Tes Breeze bawaan memakai RefreshDatabase yang mengosongkan data
        // seed; isi ulang di dalam transaksi supaya tes ini mandiri.
        if (! User::where('email', 'admin@bbwsnt2.go.id')->exists()) {
            $this->seed();
        }
    }

    private function admin(): User
    {
        return User::where('email', 'admin@bbwsnt2.go.id')->firstOrFail();
    }

    private function sampleData(): array
    {
        $work = Work::create(['code' => 'SMK-001', 'name' => 'Bendungan Uji Smoke', 'fiscal_year' => 2026]);
        $document = Document::create([
            'work_id' => $work->id, 'title' => 'Laporan Uji Smoke', 'visibility' => 'public', 'status' => 'active',
        ]);
        $location = WorkLocation::createFromLatLng(
            ['work_id' => $work->id, 'name' => 'As Bendungan', 'type' => 'bendungan'], -10.1772, 123.6070
        );
        $physical = PhysicalLocation::create(['type' => 'gedung', 'code' => 'SMK-G', 'name' => 'Gedung Uji', 'is_active' => true]);

        return compact('work', 'document', 'location', 'physical');
    }

    public function test_admin_pages_render_on_mysql(): void
    {
        $this->withoutVite();
        ['work' => $work, 'physical' => $physical] = $this->sampleData();

        $pages = [
            '/dashboard', '/profile', '/admin/organizational-units', '/admin/work-types', '/admin/phases',
            '/admin/work-statuses', '/admin/tags', '/admin/works', '/admin/works?search=uji',
            '/admin/works/create', "/admin/works/{$work->id}/edit", "/admin/works/{$work->id}/documents",
            "/admin/works/{$work->id}/locations", '/admin/works-import', '/admin/physical-locations',
            "/admin/physical-locations/{$physical->id}", '/admin/archive', '/admin/loans', '/admin/audit-logs',
        ];

        $this->actingAs($this->admin());
        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_public_pages_and_like_search_on_mysql(): void
    {
        $this->withoutVite();
        ['document' => $document] = $this->sampleData();

        $this->get('/')->assertOk();
        $this->get('/pustaka')->assertOk()->assertSee('Laporan Uji Smoke');
        $this->get("/pustaka/{$document->id}")->assertOk();

        // Pencarian case-insensitive: dulu 'ilike' (PostgreSQL), sekarang 'like' + collation *_ci.
        $this->assertSame(1, Work::where('name', 'like', '%BENDUNGAN uji%')->count());
    }

    public function test_work_location_helpers_on_mysql(): void
    {
        ['work' => $work, 'location' => $location] = $this->sampleData();

        $this->assertEqualsWithDelta(-10.1772, $location->latLng()['lat'], 0.0000001);
        $location->updateLatLng(-10.2, 123.6);
        $this->assertEqualsWithDelta(123.6, $location->fresh()->longitude, 0.0000001);

        $row = $work->locations()->withLatLng()->first();
        $this->assertEqualsWithDelta(-10.2, (float) $row->lat, 0.0000001);

        // ~2,5 km dari titik: masuk radius 5 km, tidak masuk radius 1 km.
        $this->assertSame(1, WorkLocation::withinRadius(-10.18, 123.61, 5000)->count());
        $this->assertSame(0, WorkLocation::withinRadius(-10.18, 123.61, 1000)->count());
    }

    public function test_api_on_mysql(): void
    {
        $this->sampleData();
        $token = $this->admin()->createToken('smoke')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/works?search=smoke')
            ->assertOk()->assertJsonPath('data.0.code', 'SMK-001');
    }

    public function test_loan_with_soft_deleted_document_on_mysql(): void
    {
        $this->withoutVite();
        ['document' => $document] = $this->sampleData();
        Loan::create([
            'document_id' => $document->id, 'borrower_name' => 'Uji', 'borrower_contact' => '0800', 'purpose' => 'Smoke', 'status' => 'returned',
        ]);
        $document->delete();

        $this->actingAs($this->admin())->get('/admin/loans')->assertOk();
    }
}
