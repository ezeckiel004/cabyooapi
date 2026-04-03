<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['client', 'chauffeur', 'admin'])->default('client');
            $table->enum('status', ['active', 'inactive', 'blocked', 'on_leave'])->default('active');
            $table->string('avatar')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->integer('ride_count')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
