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
        Schema::create('invitation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('male_couple_fullname');
            $table->string('male_couple_callname');
            $table->string('male_couple_father_name');
            $table->string('male_couple_mother_name');
            $table->string('male_couple_instagram');
            $table->string('male_couple_photo');
            $table->string('female_couple_fullname');
            $table->string('female_couple_callname');
            $table->string('female_couple_father_name');
            $table->string('female_couple_mother_name');
            $table->string('female_couple_instagram');
            $table->string('female_couple_photo');
            $table->date('akad_date');
            $table->string('akad_time');
            $table->string('akad_place');
            $table->string('akad_address');
            $table->date('reception_date');
            $table->string('reception_time');
            $table->string('reception_place');
            $table->string('reception_address');
            $table->string('address_link');
            $table->string('gallery');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_details');
    }
};
