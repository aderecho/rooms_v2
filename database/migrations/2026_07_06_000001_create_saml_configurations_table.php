<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saml_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('mode', ['idp', 'sp'])->default('idp');
            $table->string('entity_id')->unique();
            $table->string('sso_url')->nullable();
            $table->string('acs_url')->nullable();
            $table->string('slo_url')->nullable();
            $table->longText('x509_cert')->nullable();
            $table->string('signing_algo')->default('rsa-sha256');
            $table->string('default_relay_state')->nullable();
            $table->json('attribute_release')->nullable();
            $table->boolean('require_signed_requests')->default(false);
            $table->boolean('sign_responses')->default(true);
            $table->boolean('sign_assertions')->default(true);
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->boolean('is_active')->default(false);
            $table->longText('metadata_xml')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['mode', 'is_active']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saml_configurations');
    }
};
