<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bride_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('bride_fullname');
            $table->string('bride_father');
            $table->string('bride_mother');
            $table->string('bride_instagram');
            $table->string('bride_photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bride_infos');
    }
};
