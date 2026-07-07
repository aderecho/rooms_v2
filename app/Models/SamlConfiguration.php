<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SamlConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'mode',
        'entity_id',
        'sso_url',
        'acs_url',
        'slo_url',
        'x509_cert',
        'signing_algo',
        'default_relay_state',
        'attribute_release',
        'require_signed_requests',
        'sign_responses',
        'sign_assertions',
        'status',
        'is_active',
        'metadata_xml',
        'notes',
        'last_used_at',
    ];

    protected $casts = [
        'attribute_release' => 'array',
        'require_signed_requests' => 'boolean',
        'sign_responses' => 'boolean',
        'sign_assertions' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function auditEvents()
    {
        return $this->hasMany(SamlAuditEvent::class);
    }
}
