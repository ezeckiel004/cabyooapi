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
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'vehicle_type')) {
                $table->string('vehicle_type')->nullable()->after('dropoff_address');
            }
            if (!Schema::hasColumn('rides', 'passengers_count')) {
                $table->integer('passengers_count')->default(1)->after('vehicle_type');
            }
            if (!Schema::hasColumn('rides', 'pickup_date')) {
                $table->date('pickup_date')->nullable()->after('passengers_count');
            }
            if (!Schema::hasColumn('rides', 'pickup_time')) {
                $table->time('pickup_time')->nullable()->after('pickup_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'passengers_count', 'pickup_date', 'pickup_time']);
        });
    }
};
