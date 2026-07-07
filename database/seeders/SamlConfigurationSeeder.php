<?php

namespace Database\Seeders;

use App\Models\SamlConfiguration;
use Illuminate\Database\Seeder;

class SamlConfigurationSeeder extends Seeder
{
    private const DEFAULT_ATTRIBUTES = [
        'email',
        'first_name',
        'last_name',
        'display_name',
        'role',
        'department',
    ];

    private const ONEPORTAL_CERT = 'MIIC3TCCAcWgAwIBAgIJAK/pW0BA52+YMA0GCSqGSIb3DQEBCwUAMB4xHDAaBgNVBAMME09uZVBvcnRhbCBMb2NhbCBJZFAwHhcNMjYwNjI4MTAyMTQ5WhcNMjcwNjI4MTAyMTQ5WjAeMRwwGgYDVQQDDBNPbmVQb3J0YWwgTG9jYWwgSWRQMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApvRhKYxGOoLxbgLBY3Vpc2YO9cXgf1T1LYAqKPjaGRgRANjzrMlO56iK7p9tQBIGp5Iamvyfqhlve7Nz1fwY72eMEuXfEu2MX6uPjr6IGbSl184zzyQIuANpxyUlIXKz4jRe1fQBJg7GFR8P4Nazttp3MXgFLwv4wADKIdij/9mzBMR/6wBDrq0W/zUf4o2qAEkJtIetBdXxIxX0/ynvmXwV8unupBVZsKFOVagkdJoQDA6HcZimWYTFBfoT3ZlU8uF3hCL68L9TsinJZEYmuX2qO4iGlBh7womia18EJ8oVHD/WRmgFkIt2ZUZ0F/63XRev6ePkv4IT+gIpACW9gQIDAQABox4wHDAaBgNVHREEEzARgglsb2NhbGhvc3SHBH8AAAEwDQYJKoZIhvcNAQELBQADggEBAAxK99/K7r/zrHIfnb1eVV3k9yqEMqNzyeNpNQhKjC3uMeLz2p7c42lV08CtcbpJKP2qRG4fxEBk0aqLoGkK+NyNjJm2erN9yF1RuKVgQLdOYTCLBcadw5rX8WDHTifwiNub8TOWQPCTblwXt1AeHBHrW08fls3/a2y+qh7ZsYHqYYij2v4AHFhsDWHJEfvHsu7WQyONwpWdcqeAK8e1l2NZYaPYhYZcL48hKOaFaicEg3JLnf5uA43Ohyt3JV+Rob5Ron5qlMe1NKmyNP4a89bJFiJ1U3+/U04S0SQOEGM8Sqt8GzTMoQqQVHcbwdZw2v2jAVL2IU5DY8rQaDtNSG0=';

    public function run(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $spEntityId = config('services.saml.sp_entity_id') ?: "{$appUrl}/saml2/metadata";

        SamlConfiguration::updateOrCreate(
            ['slug' => 'oneportal-local-idp'],
            [
                'name' => 'OnePortal Local IdP',
                'mode' => 'idp',
                'entity_id' => 'http://127.0.0.1:8012/saml2/metadata',
                'sso_url' => 'http://127.0.0.1:8012/saml2/sso',
                'acs_url' => null,
                'slo_url' => 'http://127.0.0.1:8012/saml2/slo',
                'x509_cert' => self::ONEPORTAL_CERT,
                'signing_algo' => 'rsa-sha256',
                'default_relay_state' => '/MainDashboard',
                'attribute_release' => self::DEFAULT_ATTRIBUTES,
                'require_signed_requests' => false,
                'sign_responses' => true,
                'sign_assertions' => true,
                'status' => 'active',
                'is_active' => true,
                'metadata_xml' => $this->onePortalMetadataXml(),
                'notes' => 'Seeded from the OnePortal SAML integration metadata sample.',
            ]
        );

        SamlConfiguration::updateOrCreate(
            ['slug' => 'cebu-rooms-local-sp'],
            [
                'name' => 'Cebu Rooms Local SP',
                'mode' => 'sp',
                'entity_id' => $spEntityId,
                'sso_url' => null,
                'acs_url' => "{$appUrl}/saml2/acs",
                'slo_url' => "{$appUrl}/saml2/logout",
                'x509_cert' => config('services.saml.idp_public_cert'),
                'signing_algo' => 'rsa-sha256',
                'default_relay_state' => '/MainDashboard',
                'attribute_release' => self::DEFAULT_ATTRIBUTES,
                'require_signed_requests' => false,
                'sign_responses' => true,
                'sign_assertions' => true,
                'status' => 'active',
                'is_active' => true,
                'metadata_xml' => null,
                'notes' => 'Local service provider settings to register in OnePortal.',
            ]
        );

        $this->command?->info('SAML configurations seeded: 2 records.');
    }

    private function onePortalMetadataXml(): string
    {
        $certificate = self::ONEPORTAL_CERT;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="http://127.0.0.1:8012/saml2/metadata">
  <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
    <md:KeyDescriptor use="signing">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
          <ds:X509Certificate>{$certificate}</ds:X509Certificate>
        </ds:X509Data>
      </ds:KeyInfo>
    </md:KeyDescriptor>
    <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
    <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="http://127.0.0.1:8012/saml2/sso" />
  </md:IDPSSODescriptor>
</md:EntityDescriptor>
XML;
    }
}
