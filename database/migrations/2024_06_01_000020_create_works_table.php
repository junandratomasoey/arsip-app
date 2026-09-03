<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data paket pekerjaan. Field mengikuti Bab IV.4 dokumen perancangan
     * (Identitas, Kontrak, Masa Pelaksanaan, Penyedia, Internal).
     * Primary key UUID karena entitas ini akan diekspos lewat URL publik
     * (Public Digital Library) - ID tidak boleh mudah ditebak/diurutkan.
     */
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identitas
            $table->string('code')->unique();
            $table->string('name');
            $table->string('package_name')->nullable();
            $table->foreignId('work_type_id')->nullable()->constrained('work_types')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->string('funding_source')->nullable();
            $table->string('province')->nullable();
            $table->string('regency')->nullable();
            $table->string('district')->nullable();
            $table->string('village')->nullable();
            $table->text('location_description')->nullable();

            // Kontrak
            $table->string('contract_number')->nullable();
            $table->date('contract_date')->nullable();
            $table->decimal('contract_value', 18, 2)->nullable();
            $table->string('spmk_number')->nullable();
            $table->date('spmk_date')->nullable();
            $table->string('bast_number')->nullable();
            $table->date('bast_date')->nullable();

            // Masa pelaksanaan
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Penyedia jasa
            $table->string('provider_name')->nullable();
            $table->text('provider_address')->nullable();
            $table->string('provider_npwp')->nullable();
            $table->string('provider_leader_name')->nullable();
            $table->string('provider_role', 30)->nullable();
            // provider_role: konsultan | kontraktor | konsultan_dan_kontraktor

            // Internal
            $table->foreignId('satker_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('ppk_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('bidang_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('person_in_charge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_status_id')->nullable()->constrained('work_statuses')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fiscal_year']);
            $table->index(['satker_unit_id']);
            $table->index(['ppk_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
