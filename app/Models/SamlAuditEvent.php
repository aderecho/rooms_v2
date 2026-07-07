<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SamlAuditEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'saml_configuration_id',
        'event_name',
        'entity_id',
        'user_account_id',
        'request_id',
        'response_id',
        'ip_address',
        'outcome',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function configuration()
    {
        return $this->belongsTo(SamlConfiguration::class, 'saml_configuration_id');
    }
}
