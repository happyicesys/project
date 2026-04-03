<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultEntry extends Model
{
    protected $fillable = [
        'service',
        'key_name',
        'encrypted_value',
        'environment',
        'is_active',
        'notes',
    ];

    protected $hidden = [
        'encrypted_value',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Store a value with Laravel's built-in encryption.
     */
    public function setValueAttribute(string $plaintext): void
    {
        $this->attributes['encrypted_value'] = encrypt($plaintext);
    }

    /**
     * Retrieve the decrypted value.
     */
    public function getValueAttribute(): ?string
    {
        return $this->encrypted_value ? decrypt($this->encrypted_value) : null;
    }

    /**
     * Scope: active entries for a given service and environment.
     */
    public function scopeForService($query, string $service, string $environment = 'production')
    {
        return $query->where('service', $service)
                     ->where('environment', $environment)
                     ->where('is_active', true);
    }
}
