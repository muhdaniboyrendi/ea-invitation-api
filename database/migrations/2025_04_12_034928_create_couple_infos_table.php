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
        Schema::create('couple_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('groom_fullname');
            $table->string('groom_callname');
            $table->string('groom_father');
            $table->string('groom_mother');
            $table->string('groom_instagram');
            $table->string('groom_photo');
            $table->string('bride_fullname');
            $table->string('bride_callname');
            $table->string('bride_father');
            $table->string('bride_mother');
            $table->string('bride_instagram');
            $table->string('bride_photo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_infos');
    }
};
