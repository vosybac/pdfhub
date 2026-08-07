<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('normalized_name')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('affiliation')->nullable();
            $table->string('slug')->nullable()->index();
            $table->timestamps();

            $table->index(['normalized_name', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
