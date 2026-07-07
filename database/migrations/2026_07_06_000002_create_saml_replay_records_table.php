<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saml_replay_records', function (Blueprint $table) {
            $table->id();
            $table->string('assertion_id')->nullable();
            $table->string('response_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('issuer')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('assertion_id');
            $table->unique('response_id');
            $table->unique('request_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saml_replay_records');
    }
};
