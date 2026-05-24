<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_charges', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('car_id');
            $table->integer('charged');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_charges');
    }
};
