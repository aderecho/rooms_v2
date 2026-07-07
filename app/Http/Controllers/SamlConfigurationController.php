<?php

namespace App\Http\Controllers;

use App\Models\SamlConfiguration;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SamlConfigurationController extends Controller
{
    private const DEFAULT_ATTRIBUTES = [
        'email',
        'first_name',
        'last_name',
        'display_name',
        'role',
        'department',
    ];

    public function index(Request $request)
    {
        if (! $this->canManageSaml($request)) {
            return redirect()->route('main.dashboard')
                ->with('error', 'You do not have permission to manage SAML integrations.');
        }

        $configurations = SamlConfiguration::query()
            ->latest()
            ->get()
            ->map(fn (SamlConfiguration $configuration) => $this->transform($configuration));

        return Inertia::render('SamlIntegration', [
            'configurations' => $configurations,
            'defaultAttributes' => self::DEFAULT_ATTRIBUTES,
            'metadataUrl' => url('/saml2/metadata'),
            'acsUrl' => url('/saml2/acs'),
            'sloUrl' => url('/saml2/logout'),
            'stats' => [
                'total' => $configurations->count(),
                'active' => $configurations->where('is_active', true)->count(),
                'idp' => $configurations->where('mode', 'idp')->count(),
                'sp' => $configurations->where('mode', 'sp')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($resp = $this->ensureCanManageSaml($request)) {
            return $resp;
        }

        $validated = $this->validatedPayload($request);
        $validated = $this->mergeMetadata($validated);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        $configuration = DB::transaction(function () use ($validated) {
            $configuration = SamlConfiguration::create($validated);
            $this->deactivateOtherProviders($configuration);

            return $configuration;
        });

        return response()->json([
            'success' => true,
            'message' => 'SAML configuration created successfully.',
            'configuration' => $this->transform($configuration),
        ], 201);
    }

    public function update(Request $request, SamlConfiguration $samlConfiguration)
    {
        if ($resp = $this->ensureCanManageSaml($request)) {
            return $resp;
        }

        $validated = $this->validatedPayload($request, $samlConfiguration);
        $validated = $this->mergeMetadata($validated);

        DB::transaction(function () use ($samlConfiguration, $validated) {
            $samlConfiguration->update($validated);
            $this->deactivateOtherProviders($samlConfiguration);
        });

        return response()->json([
            'success' => true,
            'message' => 'SAML configuration updated successfully.',
            'configuration' => $this->transform($samlConfiguration->fresh()),
        ]);
    }

    public function destroy(Request $request, SamlConfiguration $samlConfiguration)
    {
        if ($resp = $this->ensureCanManageSaml($request)) {
            return $resp;
        }

        $samlConfiguration->delete();

        return response()->json([
            'success' => true,
            'message' => 'SAML configuration deleted successfully.',
        ]);
    }

    private function validatedPayload(Request $request, ?SamlConfiguration $configuration = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in(['idp', 'sp'])],
            'entity_id' => [
                'nullable',
                'string',
                'max:2048',
                Rule::unique('saml_configurations', 'entity_id')->ignore($configuration?->id),
            ],
            'sso_url' => ['nullable', 'url', 'max:2048'],
            'acs_url' => ['nullable', 'url', 'max:2048'],
            'slo_url' => ['nullable', 'url', 'max:2048'],
            'x509_cert' => ['nullable', 'string'],
            'signing_algo' => ['required', Rule::in(['rsa-sha256', 'rsa-sha384', 'rsa-sha512'])],
            'default_relay_state' => ['nullable', 'string', 'max:2048'],
            'attribute_release' => ['nullable', 'array'],
            'attribute_release.*' => ['string', Rule::in(self::DEFAULT_ATTRIBUTES)],
            'require_signed_requests' => ['boolean'],
            'sign_responses' => ['boolean'],
            'sign_assertions' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'is_active' => ['boolean'],
            'metadata_xml' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function mergeMetadata(array $validated): array
    {
        $metadataXml = trim((string) ($validated['metadata_xml'] ?? ''));

        if ($metadataXml === '') {
            if (blank($validated['entity_id'] ?? null)) {
                abort(422, 'Entity ID is required when metadata XML is not provided.');
            }

            return $validated;
        }

        $parsed = $this->parseMetadata($metadataXml);

        foreach (['entity_id', 'sso_url', 'slo_url', 'x509_cert'] as $key) {
            if (blank($validated[$key] ?? null) && filled($parsed[$key] ?? null)) {
                $validated[$key] = $parsed[$key];
            }
        }

        if (blank($validated['entity_id'] ?? null)) {
            abort(422, 'Metadata XML did not include an entityID.');
        }

        return $validated;
    }

    private function parseMetadata(string $metadataXml): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($metadataXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            abort(422, 'Metadata XML could not be parsed.');
        }

        $xpath = new DOMXPath($document);

        return [
            'entity_id' => $xpath->evaluate('string(/*[local-name()="EntityDescriptor"]/@entityID)') ?: null,
            'sso_url' => $xpath->evaluate('string(//*[local-name()="SingleSignOnService"][1]/@Location)') ?: null,
            'slo_url' => $xpath->evaluate('string(//*[local-name()="SingleLogoutService"][1]/@Location)') ?: null,
            'x509_cert' => $xpath->evaluate('string(//*[local-name()="X509Certificate"][1])') ?: null,
        ];
    }

    private function transform(SamlConfiguration $configuration): array
    {
        return [
            'id' => $configuration->id,
            'name' => $configuration->name,
            'slug' => $configuration->slug,
            'mode' => $configuration->mode,
            'entity_id' => $configuration->entity_id,
            'sso_url' => $configuration->sso_url,
            'acs_url' => $configuration->acs_url,
            'slo_url' => $configuration->slo_url,
            'x509_cert' => $configuration->x509_cert,
            'signing_algo' => $configuration->signing_algo,
            'default_relay_state' => $configuration->default_relay_state,
            'attribute_release' => $configuration->attribute_release ?? self::DEFAULT_ATTRIBUTES,
            'require_signed_requests' => $configuration->require_signed_requests,
            'sign_responses' => $configuration->sign_responses,
            'sign_assertions' => $configuration->sign_assertions,
            'status' => $configuration->status,
            'is_active' => $configuration->is_active,
            'metadata_xml' => $configuration->metadata_xml,
            'notes' => $configuration->notes,
            'last_used_at' => optional($configuration->last_used_at)->toDateTimeString(),
            'created_at' => optional($configuration->created_at)->toDateTimeString(),
            'updated_at' => optional($configuration->updated_at)->toDateTimeString(),
        ];
    }

    private function deactivateOtherProviders(SamlConfiguration $configuration): void
    {
        if (! $configuration->is_active || $configuration->status !== 'active') {
            return;
        }

        SamlConfiguration::query()
            ->where('mode', $configuration->mode)
            ->whereKeyNot($configuration->id)
            ->update(['is_active' => false]);
    }

    private function canManageSaml(Request $request): bool
    {
        $role = strtolower((string) data_get($request->session()->get('user'), 'role', ''));

        return in_array($role, ['admin', 'sysadmin'], true);
    }

    private function ensureCanManageSaml(Request $request)
    {
        if ($this->canManageSaml($request)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only admins can manage SAML integrations.',
        ], 403);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'saml-provider';
        $slug = $base;
        $counter = 2;

        while (SamlConfiguration::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
