<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFileVersion;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportReviewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! User::where('email', 'admin@bbwsnt2.go.id')->exists()) {
            $this->seed();
        } else {
            $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function uploadVersion(Work $work, string $fileId, string $name)
    {
        return Volt::test('admin.works.documents', ['work' => $work])
            ->call('startUpload', $fileId)
            ->set('uploadFile', UploadedFile::fake()->create($name, 200, 'application/pdf'))
            ->call('saveUpload')
            ->assertHasNoErrors();
    }

    public function test_report_revision_cycle(): void
    {
        $work = Work::create(['code' => 'LAP-01', 'name' => 'SID Bendungan Uji', 'fiscal_year' => 2026]);
        $penyusun = $this->userWithRole('Admin Arsip');
        $reviewer = $this->userWithRole('Pimpinan');

        // Penyusun membuat dokumen kategori laporan + slot berkas + unggah v1.
        $this->actingAs($penyusun);
        Volt::test('admin.works.documents', ['work' => $work])
            ->call('createDocument')
            ->set('title', 'Laporan Pendahuluan SID')
            ->set('category', 'laporan')
            ->set('reportType', 'pendahuluan')
            ->call('saveDocument')
            ->assertHasNoErrors();

        $document = Document::where('title', 'Laporan Pendahuluan SID')->firstOrFail();
        $this->assertTrue($document->isReport());
        $file = $document->files()->create(['label' => 'Laporan Pendahuluan']);

        $this->uploadVersion($work, $file->id, 'lap-v1.pdf');
        $v1 = $file->fresh()->currentVersion;
        $this->assertSame(DocumentFileVersion::REVIEW_DRAFT, $v1->review_status);

        // Penyusun tidak boleh mereview.
        Volt::test('admin.reports.index')->call('submitForReview', $v1->id);
        $this->assertSame(DocumentFileVersion::REVIEW_PENDING, $v1->fresh()->review_status);
        Volt::test('admin.reports.index')->call('startReview', $v1->id, 'disetujui')->assertForbidden();

        // Reviewer meminta revisi: catatan wajib.
        $this->actingAs($reviewer);
        $this->get(route('admin.reports.index'))->assertOk()->assertSee('Laporan Pendahuluan SID');
        Volt::test('admin.reports.index')
            ->call('startReview', $v1->id, 'revisi')
            ->call('saveReview')
            ->assertHasErrors('reviewNotes')
            ->set('reviewNotes', 'Lengkapi analisis hidrologi bab 3')
            ->call('saveReview')
            ->assertHasNoErrors();
        $this->assertSame(DocumentFileVersion::REVIEW_REVISION, $v1->fresh()->review_status);

        // Penyusun mengunggah perbaikan (v2 = draf), mengajukan lagi.
        $this->actingAs($penyusun);
        $this->get(route('admin.works.documents', $work))->assertOk()->assertSee('Lengkapi analisis hidrologi bab 3');
        $this->uploadVersion($work, $file->id, 'lap-v2.pdf');
        $v2 = $file->fresh()->currentVersion;
        $this->assertSame(2, $v2->version_number);
        $this->assertSame(DocumentFileVersion::REVIEW_DRAFT, $v2->review_status);
        Volt::test('admin.works.documents', ['work' => $work])->call('submitForReview', $v2->id);

        // Reviewer menyetujui dari halaman Dokumen pekerjaan.
        $this->actingAs($reviewer);
        Volt::test('admin.works.documents', ['work' => $work])
            ->call('startReview', $v2->id, 'disetujui')
            ->call('saveReview')
            ->assertHasNoErrors();

        $v2->refresh();
        $this->assertSame(DocumentFileVersion::REVIEW_APPROVED, $v2->review_status);
        $this->assertSame($reviewer->id, $v2->reviewed_by);
        $this->assertSame(DocumentFileVersion::REVIEW_REVISION, $v1->fresh()->review_status);

        // Riwayat: v1 diajukan+revisi, v2 diajukan+disetujui.
        $this->assertSame(['diajukan', 'revisi'], $v1->reviews()->pluck('action')->all());
        $this->assertSame(['diajukan', 'disetujui'], $v2->reviews()->pluck('action')->all());

        Volt::test('admin.reports.index')->set('statusFilter', 'disetujui')->assertSee('Laporan Pendahuluan SID');

        // Versi yang sudah disetujui tidak bisa direview ulang.
        Volt::test('admin.reports.index')->call('startReview', $v2->id, 'revisi')->assertStatus(422);
    }

    public function test_general_documents_have_no_review_status(): void
    {
        $work = Work::create(['code' => 'UMM-01', 'name' => 'Pekerjaan Umum', 'fiscal_year' => 2026]);
        $this->actingAs(User::where('email', 'admin@bbwsnt2.go.id')->first());

        $document = $work->documents()->create(['title' => 'Kontrak', 'visibility' => 'internal', 'category' => 'umum']);
        $file = $document->files()->create(['label' => 'Kontrak']);
        $this->uploadVersion($work, $file->id, 'kontrak.pdf');

        $version = $file->fresh()->currentVersion;
        $this->assertNull($version->review_status);

        // Versi dokumen umum tidak bisa diajukan review.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Volt::test('admin.reports.index')->call('submitForReview', $version->id);
    }
}
