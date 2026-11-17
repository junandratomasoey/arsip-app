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
        $work = Work::create(['code' => 'SMK-001', 'name' => 'Bendungan Uji Smoke', 'fiscal_year' => 2026, 'visibility' => 'public']);
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
        // /pustaka menampilkan pekerjaan; dokumen publiknya ada di halaman pekerjaan.
        $this->get('/pustaka')->assertOk()->assertSee('Bendungan Uji Smoke');
        $this->get('/pustaka?q=Laporan+Uji')->assertOk()->assertSee('Bendungan Uji Smoke')->assertSee('Laporan Uji Smoke');
        $this->get("/pustaka/pekerjaan/{$document->work_id}")->assertOk()->assertSee('Laporan Uji Smoke');
        $this->get("/pustaka/{$document->id}")->assertOk();

        // Pekerjaan internal (default) tidak boleh muncul sama sekali -
        // termasuk dokumennya yang bervisibilitas publik - dan dokumen
        // non-publik tidak boleh bocor di halaman pekerjaan publik.
        $hidden = Work::create(['code' => 'SMK-002', 'name' => 'Embung Tertutup Smoke', 'fiscal_year' => 2026]);
        $this->assertSame('internal', $hidden->fresh()->visibility);
        $leak = Document::create(['work_id' => $hidden->id, 'title' => 'Dokumen Publik Smoke Tertutup', 'visibility' => 'public', 'status' => 'active']);
        Document::create(['work_id' => $document->work_id, 'title' => 'Nota Internal Smoke', 'visibility' => 'internal', 'status' => 'active']);

        $this->get('/pustaka')->assertOk()->assertDontSee('Embung Tertutup Smoke');
        $this->get('/pustaka?q=Smoke+Tertutup')->assertOk()->assertDontSee('Embung Tertutup Smoke');
        $this->get("/pustaka/pekerjaan/{$hidden->id}")->assertNotFound();
        $this->get("/pustaka/{$leak->id}")->assertNotFound();
        $this->get("/pustaka/pekerjaan/{$document->work_id}")->assertOk()->assertDontSee('Nota Internal Smoke');

        // Pekerjaan publik tanpa dokumen publik tetap tampil (kosong).
        $empty = Work::create(['code' => 'SMK-003', 'name' => 'Irigasi Kosong Smoke', 'visibility' => 'public']);
        $this->get('/pustaka')->assertOk()->assertSee('Irigasi Kosong Smoke');
        $this->get("/pustaka/pekerjaan/{$empty->id}")->assertOk()->assertSee('Belum ada dokumen yang dipublikasikan');

        // Filter publik: tahun, lokasi, dan pilihan filter tidak membocorkan
        // nilai milik pekerjaan internal.
        Work::create(['code' => 'SMK-004', 'name' => 'Pantai Publik Smoke', 'fiscal_year' => 2021, 'regency' => 'Kota Smoke', 'visibility' => 'public']);
        Work::create(['code' => 'SMK-005', 'name' => 'Pantai Internal Smoke', 'fiscal_year' => 1999, 'regency' => 'Kabupaten Rahasia Smoke']);
        $this->get('/pustaka?tahun=2021')->assertOk()->assertSee('Pantai Publik Smoke')->assertDontSee('Bendungan Uji Smoke');
        $this->get('/pustaka?lokasi=Kota+Smoke')->assertOk()->assertSee('Pantai Publik Smoke')->assertDontSee('Irigasi Kosong Smoke');
        $this->get('/pustaka?urut=nama')->assertOk()->assertSeeInOrder(['Bendungan Uji Smoke', 'Pantai Publik Smoke']);
        $this->get('/pustaka')->assertOk()->assertDontSee('Kabupaten Rahasia Smoke')->assertDontSee('1999');

        // Form pekerjaan menyimpan pilihan visibilitas.
        $this->actingAs($this->admin());
        \Livewire\Volt\Volt::test('admin.works.form', ['work' => $hidden])
            ->assertSet('visibility', 'internal')
            ->set('visibility', 'public')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('public', $hidden->fresh()->visibility);

        // Pencarian case-insensitive: dulu 'ilike' (PostgreSQL), sekarang 'like' + collation *_ci.
        $this->assertSame(1, Work::where('name', 'like', '%BENDUNGAN uji%')->count());
    }

    public function test_archive_completeness_on_works_list_and_dashboard(): void
    {
        $this->withoutVite();
        ['work' => $work, 'document' => $document, 'physical' => $physical] = $this->sampleData();

        // Dokumen contoh belum punya berkas maupun lokasi fisik.
        $this->assertSame(1, Document::where('work_id', $work->id)->withoutSoftfile()->withoutHardcopy()->count());

        $both = Document::create(['work_id' => $work->id, 'title' => 'Lengkap Smoke', 'visibility' => 'internal', 'status' => 'active', 'physical_location_id' => $physical->id]);
        $file = $both->files()->create(['label' => 'Berkas']);
        $file->addVersion(['disk' => 'local', 'path' => 'x/y.pdf', 'original_filename' => 'y.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'checksum_sha256' => str_repeat('a', 64)]);

        // Slot berkas tanpa unggahan tidak dihitung sebagai softfile.
        $document->files()->create(['label' => 'Slot kosong']);

        $this->assertSame(1, Document::where('work_id', $work->id)->withSoftfile()->count());
        $this->assertSame(1, Document::where('work_id', $work->id)->withHardcopy()->count());
        $this->assertSame(1, Document::where('work_id', $work->id)->withoutSoftfile()->count());

        $empty = Work::create(['code' => 'SMK-009', 'name' => 'Pekerjaan Tanpa Dokumen Smoke']);

        $this->actingAs($this->admin());
        $this->get('/admin/works')->assertOk()->assertSee('Softfile 1/2')->assertSee('Hardcopy 1/2')->assertSee('Belum ada dokumen');
        $this->get('/admin/works?kelengkapan=softfile')->assertOk()->assertSee('Bendungan Uji Smoke')->assertDontSee('Pekerjaan Tanpa Dokumen Smoke');
        $this->get('/admin/works?kelengkapan=hardcopy')->assertOk()->assertSee('Bendungan Uji Smoke');
        $this->get('/admin/works?kelengkapan=kosong')->assertOk()->assertSee('Pekerjaan Tanpa Dokumen Smoke')->assertDontSee('Bendungan Uji Smoke');
        $this->get('/dashboard')->assertOk()->assertSee('Kelengkapan Arsip')->assertSee('Tanpa softfile')->assertSee('Tanpa hardcopy');
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
