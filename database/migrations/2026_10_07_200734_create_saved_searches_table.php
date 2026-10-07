<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();

            // A null criterion means unconstrained. Alert generation scans every
            // saved search to find matches, so the columns that narrow that
            // scan are indexed.
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->string('property_type')->nullable();
            $table->string('region')->nullable();
            $table->timestamps();

            $table->index('user_id');

            // Alert generation looks for saved searches that could match, which
            // is a scan over these columns rather than a point lookup.
            $table->index(['max_price', 'min_bedrooms']);
            $table->index('property_type');
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
