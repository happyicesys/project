<?php

namespace App\Services;

use App\Models\VaultEntry;
use Illuminate\Support\Collection;

class VaultService
{
    /**
     * Store a key-value pair in the vault (encrypted at rest).
     */
    public function store(
        string $service,
        string $keyName,
        string $value,
        string $environment = 'production',
        ?string $notes = null,
    ): VaultEntry {
        return VaultEntry::updateOrCreate(
            [
                'service' => $service,
                'key_name' => $keyName,
                'environment' => $environment,
            ],
            [
                'encrypted_value' => encrypt($value),
                'is_active' => true,
                'notes' => $notes,
            ],
        );
    }

    /**
     * Retrieve a single decrypted value from the vault.
     */
    public function get(string $service, string $keyName, string $environment = 'production'): ?string
    {
        $entry = VaultEntry::forService($service, $environment)
            ->where('key_name', $keyName)
            ->first();

        if (! $entry) {
            return null;
        }

        return decrypt($entry->encrypted_value);
    }

    /**
     * Retrieve all key-value pairs for a service as an associative array.
     */
    public function getAllForService(string $service, string $environment = 'production'): array
    {
        return VaultEntry::forService($service, $environment)
            ->get()
            ->mapWithKeys(fn (VaultEntry $entry) => [
                $entry->key_name => decrypt($entry->encrypted_value),
            ])
            ->toArray();
    }

    /**
     * Deactivate a vault entry (soft-disable, does not delete).
     */
    public function deactivate(string $service, string $keyName, string $environment = 'production'): bool
    {
        return VaultEntry::where('service', $service)
            ->where('key_name', $keyName)
            ->where('environment', $environment)
            ->update(['is_active' => false]) > 0;
    }

    /**
     * List all services that have active vault entries.
     */
    public function listServices(): Collection
    {
        return VaultEntry::where('is_active', true)
            ->distinct()
            ->pluck('service');
    }
}
