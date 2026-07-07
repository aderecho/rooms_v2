<?php

namespace Database\Seeders;

use App\Models\SamlConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AmsSamlConfigurationSeeder extends Seeder
{
    private const DEFAULT_ATTRIBUTES = [
        'email',
        'first_name',
        'last_name',
        'display_name',
        'role',
        'department',
    ];

    private const ENTITY_ID = 'https://ams.upcebu.edu.ph/saml2/metadata';
    private const SSO_URL = 'https://ams.upcebu.edu.ph/saml2/sso';

    public function run(): void
    {
        $certificate = $this->certificate();

        DB::transaction(function () use ($certificate) {
            SamlConfiguration::query()
                ->where('mode', 'idp')
                ->where('slug', '!=', 'ams-upcebu-idp')
                ->update(['is_active' => false]);

            SamlConfiguration::updateOrCreate(
                ['slug' => 'ams-upcebu-idp'],
                [
                    'name' => 'UP Cebu AMS IdP',
                    'mode' => 'idp',
                    'entity_id' => self::ENTITY_ID,
                    'sso_url' => self::SSO_URL,
                    'acs_url' => null,
                    'slo_url' => null,
                    'x509_cert' => $certificate,
                    'signing_algo' => 'rsa-sha256',
                    'default_relay_state' => '/MainDashboard',
                    'attribute_release' => self::DEFAULT_ATTRIBUTES,
                    'require_signed_requests' => false,
                    'sign_responses' => true,
                    'sign_assertions' => true,
                    'status' => 'active',
                    'is_active' => true,
                    'metadata_xml' => $this->metadataXml($certificate),
                    'notes' => 'Seeded from UP Cebu AMS SAML metadata.',
                ]
            );
        });

        $this->command?->info('AMS SAML IdP configuration seeded and activated.');
    }

    private function certificate(): string
    {
        $certificate = trim((string) config('services.saml.idp_public_cert'));

        if ($certificate === '') {
            throw new RuntimeException('Set SAML_IDP_PUBLIC_CERT in .env before running AmsSamlConfigurationSeeder.');
        }

        return $certificate;
    }

    private function metadataXml(string $certificate): string
    {
        $certificate = $this->certificateBody($certificate);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="https://ams.upcebu.edu.ph/saml2/metadata">
  <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
    <md:KeyDescriptor use="signing">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
          <ds:X509Certificate>{$certificate}</ds:X509Certificate>
        </ds:X509Data>
      </ds:KeyInfo>
    </md:KeyDescriptor>
    <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
    <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://ams.upcebu.edu.ph/saml2/sso" />
  </md:IDPSSODescriptor>
</md:EntityDescriptor>
XML;
    }

    private function certificateBody(string $certificate): string
    {
        return str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n", ' '],
            '',
            $certificate
        );
    }
}
