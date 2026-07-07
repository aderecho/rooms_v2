<?php

use App\Models\SamlConfiguration;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('metadata endpoint returns saml metadata without private key material', function () {
    config()->set('services.saml.sp_entity_id', 'https://rooms.example.test/saml2/metadata');
    config()->set('services.saml.idp_public_cert', '-----BEGIN CERTIFICATE----- public-cert -----END CERTIFICATE-----');
    config()->set('services.saml.idp_private_key', 'private-key-secret');

    $this->get(route('saml.metadata'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/samlmetadata+xml')
        ->assertSee('https://rooms.example.test/saml2/metadata', false)
        ->assertDontSee('private-key-secret', false);
});

test('admin can save a saml configuration from metadata xml', function () {
    $admin = UserAccount::factory()->create([
        'user_type' => 'admin',
        'account_status' => 'active',
    ]);

    $metadata = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="http://127.0.0.1:8012/saml2/metadata">
  <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
    <md:KeyDescriptor use="signing">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data><ds:X509Certificate>abc123</ds:X509Certificate></ds:X509Data>
      </ds:KeyInfo>
    </md:KeyDescriptor>
    <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="http://127.0.0.1:8012/saml2/sso" />
  </md:IDPSSODescriptor>
</md:EntityDescriptor>
XML;

    $this->actingAs($admin)
        ->withSession(['user' => [
            'id' => $admin->id,
            'role' => 'admin',
        ]])
        ->postJson(route('saml-configurations.store'), [
            'name' => 'OnePortal Local IdP',
            'mode' => 'idp',
            'metadata_xml' => $metadata,
            'signing_algo' => 'rsa-sha256',
            'attribute_release' => ['email', 'first_name'],
            'status' => 'active',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('configuration.entity_id', 'http://127.0.0.1:8012/saml2/metadata')
        ->assertJsonPath('configuration.sso_url', 'http://127.0.0.1:8012/saml2/sso')
        ->assertJsonPath('configuration.x509_cert', 'abc123');

    $this->assertDatabaseHas('saml_configurations', [
        'name' => 'OnePortal Local IdP',
        'entity_id' => 'http://127.0.0.1:8012/saml2/metadata',
        'sso_url' => 'http://127.0.0.1:8012/saml2/sso',
        'is_active' => true,
    ]);
});

test('only admins can manage saml configurations', function () {
    $faculty = UserAccount::factory()->create([
        'user_type' => 'faculty',
        'account_status' => 'active',
    ]);

    $this->actingAs($faculty)
        ->withSession(['user' => [
            'id' => $faculty->id,
            'role' => 'faculty',
        ]])
        ->postJson(route('saml-configurations.store'), [
            'name' => 'Blocked',
            'mode' => 'idp',
            'entity_id' => 'https://blocked.example.test',
            'signing_algo' => 'rsa-sha256',
            'status' => 'draft',
        ])
        ->assertForbidden();
});

test('activating a provider deactivates the previous active provider for the same mode', function () {
    $admin = UserAccount::factory()->create([
        'user_type' => 'admin',
        'account_status' => 'active',
    ]);

    $old = SamlConfiguration::create([
        'name' => 'Old IdP',
        'slug' => 'old-idp',
        'mode' => 'idp',
        'entity_id' => 'https://old.example.test',
        'signing_algo' => 'rsa-sha256',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->withSession(['user' => [
            'id' => $admin->id,
            'role' => 'admin',
        ]])
        ->postJson(route('saml-configurations.store'), [
            'name' => 'New IdP',
            'mode' => 'idp',
            'entity_id' => 'https://new.example.test',
            'signing_algo' => 'rsa-sha256',
            'status' => 'active',
            'is_active' => true,
        ])
        ->assertCreated();

    expect($old->fresh()->is_active)->toBeFalse();
});

test('acs accepts oneportal saml response for an existing rooms user', function () {
    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.saml.sp_entity_id', 'http://localhost:8000/saml2/metadata');

    seedOnePortalSamlIdp();
    $user = UserAccount::factory()->create([
        'email' => 'standard.user@oneportal.test',
        'account_status' => 'active',
    ]);

    $this->post(route('saml.acs'), [
        'SAMLResponse' => base64_encode(onePortalSamlResponse('standard.user@oneportal.test')),
        'RelayState' => '/MainDashboard',
    ])
        ->assertRedirect('/MainDashboard')
        ->assertSessionHas('user.email', 'standard.user@oneportal.test');

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('user_accounts', [
        'email' => 'standard.user@oneportal.test',
    ]);
});

test('acs redirects to user not found page when saml email is not registered in rooms', function () {
    config()->set('app.url', 'http://localhost:8000');
    config()->set('services.saml.sp_entity_id', 'http://localhost:8000/saml2/metadata');

    seedOnePortalSamlIdp();

    $this->post(route('saml.acs'), [
        'SAMLResponse' => base64_encode(onePortalSamlResponse('unknown.user@oneportal.test', '_response2', '_assertion2')),
        'RelayState' => '/MainDashboard',
    ])
        ->assertRedirect(route('saml.user-not-found'))
        ->assertSessionHas('saml_email', 'unknown.user@oneportal.test');

    $this->assertGuest();
    $this->assertDatabaseMissing('user_accounts', [
        'email' => 'unknown.user@oneportal.test',
    ]);
    $this->assertDatabaseHas('saml_audit_events', [
        'event_name' => 'saml.sp.assertion.rejected',
        'outcome' => 'rejected',
        'response_id' => '_response2',
    ]);
});

function seedOnePortalSamlIdp(): void
{
    SamlConfiguration::create([
        'name' => 'OnePortal Local IdP',
        'slug' => 'oneportal-local-idp',
        'mode' => 'idp',
        'entity_id' => 'http://127.0.0.1:8012/saml2/metadata',
        'sso_url' => 'http://127.0.0.1:8012/saml2/sso',
        'signing_algo' => 'rsa-sha256',
        'status' => 'active',
        'is_active' => true,
    ]);
}

function onePortalSamlResponse(string $email, string $responseId = '_response1', string $assertionId = '_assertion1'): string
{
    $now = now()->utc();
    $expires = $now->copy()->addMinutes(5);

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="{$responseId}" Version="2.0" IssueInstant="{$now->toIso8601ZuluString()}" Destination="http://localhost:8000/saml2/acs">
  <saml:Issuer>http://127.0.0.1:8012/saml2/metadata</saml:Issuer>
  <samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>
  <saml:Assertion ID="{$assertionId}" Version="2.0" IssueInstant="{$now->toIso8601ZuluString()}">
    <saml:Issuer>http://127.0.0.1:8012/saml2/metadata</saml:Issuer>
    <saml:Subject>
      <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">{$email}</saml:NameID>
      <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
        <saml:SubjectConfirmationData Recipient="http://localhost:8000/saml2/acs" NotOnOrAfter="{$expires->toIso8601ZuluString()}"/>
      </saml:SubjectConfirmation>
    </saml:Subject>
    <saml:Conditions NotBefore="{$now->copy()->subMinute()->toIso8601ZuluString()}" NotOnOrAfter="{$expires->toIso8601ZuluString()}">
      <saml:AudienceRestriction><saml:Audience>http://localhost:8000/saml2/metadata</saml:Audience></saml:AudienceRestriction>
    </saml:Conditions>
    <saml:AttributeStatement>
      <saml:Attribute Name="name"><saml:AttributeValue>Standard User</saml:AttributeValue></saml:Attribute>
      <saml:Attribute Name="role"><saml:AttributeValue>user</saml:AttributeValue></saml:Attribute>
    </saml:AttributeStatement>
  </saml:Assertion>
</samlp:Response>
XML;
}
