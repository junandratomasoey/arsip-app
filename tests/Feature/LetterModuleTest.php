<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterClassification;
use App\Models\LetterDisposition;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LetterModuleTest extends TestCase
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
    }

    private function admin(): User
    {
        return User::where('email', 'admin@bbwsnt2.go.id')->firstOrFail();
    }

    private function userWithRole(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);

        return $user;
    }

    private function incomingLetter(array $overrides = []): Letter
    {
        return Letter::createWithAgenda(array_merge([
            'direction' => Letter::DIRECTION_INCOMING,
            'letter_number' => 'UND-01/2026',
            'letter_date' => '2026-10-01',
            'correspondent' => 'Dinas PUPR Kab. Kupang',
            'subject' => 'Undangan rapat koordinasi',
            'nature' => 'biasa',
            'status' => Letter::STATUS_NEW,
            'created_by' => $this->admin()->id,
        ], $overrides));
    }

    public function test_incoming_letter_created_with_agenda_and_file(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        Volt::test('admin.letters.form')
            ->set('direction', Letter::DIRECTION_INCOMING)
            ->set('letterNumber', '005/123/DPUPR/2026')
            ->set('letterDate', '2026-10-01')
            ->set('correspondent', 'Dinas PUPR Kab. Kupang')
            ->set('subject', 'Permohonan data debit')
            ->set('status', Letter::STATUS_NEW)
            ->set('scanFile', UploadedFile::fake()->create('surat.pdf', 300, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $letter = Letter::where('subject', 'Permohonan data debit')->firstOrFail();
        $this->assertSame(1, $letter->agenda_number);
        $this->assertSame(2026, $letter->agenda_year);
        $this->assertNotNull($letter->file_checksum);
        Storage::disk('local')->assertExists($letter->file_path);

        $this->get(route('admin.letters.file', $letter))->assertOk();
        $this->get(route('admin.letters.show', $letter))->assertOk()->assertSee('Permohonan data debit');
        $this->get(route('admin.letters.index', ['arah' => 'masuk']))->assertOk()->assertSee('005/123/DPUPR/2026');
    }

    public function test_outgoing_number_generated_and_incremented(): void
    {
        $this->actingAs($this->admin());
        $classification = LetterClassification::create(['code' => 'HK.01', 'name' => 'Hukum', 'is_active' => true]);
        $unit = OrganizationalUnit::create(['type' => 'bidang', 'name' => 'Bidang Uji', 'code' => 'BU']);

        foreach (['Surat pertama', 'Surat kedua'] as $subject) {
            Volt::test('admin.letters.form')
                ->set('direction', Letter::DIRECTION_OUTGOING)
                ->set('autoNumber', true)
                ->set('letterDate', '2026-10-02')
                ->set('correspondent', 'Kepala Dinas')
                ->set('subject', $subject)
                ->set('classificationId', $classification->id)
                ->set('unitId', $unit->id)
                ->set('status', Letter::STATUS_DRAFT)
                ->call('save')
                ->assertHasNoErrors();
        }

        $this->assertSame('1/HK.01/BU/2026', Letter::where('subject', 'Surat pertama')->value('letter_number'));
        $this->assertSame('2/HK.01/BU/2026', Letter::where('subject', 'Surat kedua')->value('letter_number'));

        // Nomor manual yang kembar ditolak.
        Volt::test('admin.letters.form')
            ->set('direction', Letter::DIRECTION_OUTGOING)
            ->set('autoNumber', false)
            ->set('letterNumber', '1/HK.01/BU/2026')
            ->set('letterDate', '2026-10-02')
            ->set('correspondent', 'X')
            ->set('subject', 'Kembar')
            ->set('status', Letter::STATUS_DRAFT)
            ->call('save')
            ->assertHasErrors('letterNumber');

        // Segmen kosong dibuang dan unit tanpa kode memakai kode instansi.
        $this->assertSame('3/BBWSNT2/2026', Letter::formatOutgoingNumber(3, now()->setDate(2026, 5, 1), null, null));
    }

    public function test_tiered_disposition_flow(): void
    {
        $letter = $this->incomingLetter();
        $kepala = $this->userWithRole('Pimpinan', 'Kepala Balai');
        $kabid = $this->userWithRole('Staf', 'Kabid OP');
        $staf = $this->userWithRole('Staf', 'Staf OP');

        // Staf belum menerima disposisi: tidak boleh melihat surat.
        $this->actingAs($staf)->get(route('admin.letters.show', $letter))->assertForbidden();

        // Kepala Balai mendisposisikan ke Kabid.
        $this->actingAs($kepala);
        Volt::test('admin.letters.show', ['letter' => $letter])
            ->call('openDisposeForm')
            ->call('toggleRecipient', $kabid->id)
            ->set('instruction', 'Tindak lanjuti dan siapkan data')
            ->call('saveDisposition')
            ->assertHasNoErrors();

        $this->assertSame(Letter::STATUS_DISPOSED, $letter->fresh()->status);

        // Kabid (tanpa letter.view) melihat di kotak masuk, membuka, meneruskan ke staf.
        $this->actingAs($kabid);
        $this->get(route('admin.dispositions.index'))->assertOk()->assertSee('Undangan rapat koordinasi');
        Volt::test('admin.letters.show', ['letter' => $letter])
            ->call('openDisposeForm')
            ->call('toggleRecipient', $staf->id)
            ->set('instruction', 'Siapkan bahan rapat')
            ->call('saveDisposition')
            ->assertHasNoErrors();

        $kabidDisposition = LetterDisposition::where('to_user_id', $kabid->id)->firstOrFail();
        $stafDisposition = LetterDisposition::where('to_user_id', $staf->id)->firstOrFail();
        $this->assertSame($kabidDisposition->id, $stafDisposition->parent_id);
        $this->assertSame(LetterDisposition::STATUS_IN_PROGRESS, $kabidDisposition->status);

        // Staf membuka (otomatis dibaca) lalu menyelesaikan.
        $this->actingAs($staf);
        Volt::test('admin.letters.show', ['letter' => $letter])
            ->call('startRespond', $stafDisposition->id)
            ->set('responseText', 'Bahan rapat sudah disiapkan')
            ->set('responseStatus', 'selesai')
            ->call('saveResponse')
            ->assertHasNoErrors();
        $this->assertNotNull($stafDisposition->fresh()->read_at);
        $this->assertSame(Letter::STATUS_DISPOSED, $letter->fresh()->status);

        // Kabid menyelesaikan -> semua disposisi selesai -> surat selesai.
        $this->actingAs($kabid);
        Volt::test('admin.letters.show', ['letter' => $letter])
            ->call('startRespond', $kabidDisposition->id)
            ->set('responseText', 'Sudah dihadiri')
            ->call('saveResponse');
        $this->assertSame(Letter::STATUS_DONE, $letter->fresh()->status);

        // Orang lain tidak bisa mengisi tindak lanjut disposisi milik staf.
        $this->actingAs($kepala);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Volt::test('admin.letters.show', ['letter' => $letter])->call('startRespond', $stafDisposition->id);
    }

    public function test_confidential_letter_hidden_without_permission(): void
    {
        $letter = $this->incomingLetter(['nature' => 'rahasia', 'subject' => 'Surat sangat rahasia']);
        $petugas = $this->userWithRole('Petugas Arsip', 'Petugas');
        $pimpinan = $this->userWithRole('Pimpinan', 'Pimpinan');

        $this->actingAs($petugas)->get(route('admin.letters.index'))->assertOk()->assertDontSee('Surat sangat rahasia');
        $this->actingAs($petugas)->get(route('admin.letters.show', $letter))->assertForbidden();

        $this->actingAs($pimpinan)->get(route('admin.letters.index'))->assertOk()->assertSee('Surat sangat rahasia');
        $this->actingAs($pimpinan)->get(route('admin.letters.show', $letter))->assertOk();
    }

    public function test_pages_render_and_users_can_be_managed(): void
    {
        $this->actingAs($this->admin());
        $letter = $this->incomingLetter();
        $letter->delete();

        foreach (['/admin/letters?arah=keluar', '/admin/letters/create?arah=masuk', '/admin/letters/create?arah=keluar',
            "/admin/letters/{$letter->id}", '/admin/dispositions', '/admin/letter-classifications', '/admin/users'] as $url) {
            $this->get($url)->assertOk();
        }

        $unit = OrganizationalUnit::first();
        Volt::test('admin.users.index')
            ->call('createNew')
            ->set('name', 'Pegawai Baru')
            ->set('email', 'pegawai.baru@bbwsnt2.go.id')
            ->set('unitId', $unit->id)
            ->set('role', 'Staf')
            ->set('password', 'rahasia123')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'pegawai.baru@bbwsnt2.go.id')->firstOrFail();
        $this->assertTrue($user->hasRole('Staf'));
        $this->assertNotNull($user->email_verified_at);
    }
}
