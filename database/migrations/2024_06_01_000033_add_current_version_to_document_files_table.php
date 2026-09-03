<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->foreignUuid('current_version_id')->nullable()->after('label')
                ->constrained('document_file_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
        });
    }
};
