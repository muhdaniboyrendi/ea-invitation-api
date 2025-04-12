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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained()->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'canceled', 'expired'])->default('pending');
            $table->string('payment_id', 100)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_url')->nullable();
            $table->string('payment_token')->nullable();
            $table->timestamp('payment_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
