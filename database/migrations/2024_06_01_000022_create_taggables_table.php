<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relasi many-to-many polymorphic: satu tag bisa dipasang ke pekerjaan
     * maupun dokumen. taggable_id bertipe uuid karena kedua model tersebut
     * (Work, Document) memakai primary key uuid.
     */
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->uuidMorphs('taggable');
            $table->primary(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
