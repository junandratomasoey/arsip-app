<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Surat Menyurat: klasifikasi arsip, agenda surat masuk/keluar,
     * dan disposisi berjenjang.
     *
     * - letter_classifications: kode klasifikasi arsip (mis. HK.02.01),
     *   master data CRUD supaya mengikuti pedoman yang berlaku tanpa migrasi.
     * - letters: satu tabel untuk surat masuk & keluar (kolom `direction`),
     *   nomor agenda berurut per arah per tahun. Satu berkas pindaian utama
     *   per surat (disimpan di disk dokumen privat, unduh lewat cek akses).
     * - letter_dispositions: pohon disposisi (parent_id) dari pimpinan ke
     *   bawahan, masing-masing dengan status & tindak lanjut penerimanya.
     */
    public function up(): void
    {
        Schema::create('letter_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('direction', 10);
            // direction: incoming (surat masuk) | outgoing (surat keluar)

            $table->unsignedSmallInteger('agenda_year');
            $table->unsignedInteger('agenda_number');

            $table->string('letter_number')->nullable();
            $table->date('letter_date');
            $table->date('received_date')->nullable();
            $table->string('correspondent');
            // correspondent: pengirim (surat masuk) atau tujuan (surat keluar)
            $table->string('subject', 500);
            $table->text('summary')->nullable();

            $table->foreignId('letter_classification_id')->nullable()->constrained('letter_classifications')->nullOnDelete();
            $table->string('nature', 20)->default('biasa');
            // nature (sifat): biasa | penting | segera | rahasia
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignUuid('work_id')->nullable()->constrained('works')->nullOnDelete();

            $table->string('status', 20);
            // masuk: baru | didisposisi | selesai ; keluar: draf | terkirim

            $table->string('file_disk', 30)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_checksum', 64)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['direction', 'agenda_year', 'agenda_number']);
            $table->index(['direction', 'letter_date']);
            $table->index('letter_number');
            $table->index('status');
        });

        Schema::create('letter_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('letter_id')->constrained('letters')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('letter_dispositions')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('instruction');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('baru');
            // status: baru | dibaca | diproses | selesai
            $table->timestamp('read_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_dispositions');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_classifications');
    }
};
