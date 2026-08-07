<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_author', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('pdf_documents')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->unsignedInteger('author_order')->default(0);
            $table->string('affiliation')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_author');
    }
};
