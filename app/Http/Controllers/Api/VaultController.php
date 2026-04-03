<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultController extends Controller
{
    public function __construct(
        private readonly VaultService $vault,
    ) {}

    /**
     * Store a key in the vault (CEO-only operation in production).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'required|string|max:50',
            'key_name' => 'required|string|max:100',
            'value' => 'required|string',
            'environment' => 'nullable|string|in:production,testnet,paper',
            'notes' => 'nullable|string',
        ]);

        $entry = $this->vault->store(
            $validated['service'],
            $validated['key_name'],
            $validated['value'],
            $validated['environment'] ?? 'production',
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => "Vault entry stored for {$validated['service']}.{$validated['key_name']}",
            'id' => $entry->id,
        ], 201);
    }

    /**
     * Get credentials for a service (agent auth required).
     * Returns decrypted values — only accessible internally.
     */
    public function getForService(Request $request, string $service): JsonResponse
    {
        $environment = $request->query('environment', 'production');

        $credentials = $this->vault->getAllForService($service, $environment);

        if (empty($credentials)) {
            return response()->json([
                'error' => "No active vault entries for service: {$service}",
            ], 404);
        }

        return response()->json([
            'service' => $service,
            'environment' => $environment,
            'credentials' => $credentials,
        ]);
    }

    /**
     * List all services in the vault (no values exposed).
     */
    public function listServices(): JsonResponse
    {
        return response()->json([
            'services' => $this->vault->listServices(),
        ]);
    }
}
