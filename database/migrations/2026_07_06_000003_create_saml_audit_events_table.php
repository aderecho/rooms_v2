<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saml_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saml_configuration_id')->nullable()->constrained('saml_configurations')->nullOnDelete();
            $table->string('event_name');
            $table->string('entity_id')->nullable();
            $table->foreignId('user_account_id')->nullable()->constrained('user_accounts')->nullOnDelete();
            $table->string('request_id')->nullable();
            $table->string('response_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('outcome')->default('success');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_name', 'outcome']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saml_audit_events');
    }
};
