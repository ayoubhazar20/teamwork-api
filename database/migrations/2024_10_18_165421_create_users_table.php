<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // Unique ID for each user
            $table->string('email');
            $table->bigInteger('user_id'); // Unique ID for each user in HubSpot
            $table->string('first_name');
            $table->string('last_name');
            $table->foreignId('portal_id')->constrained()->onDelete('cascade'); // Link to companies
            $table->boolean('super_admin')->default(false); // Track super admin status
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};