<?php

namespace App\Http\Controllers;

use App\Models\SamlAuditEvent;
use App\Models\SamlConfiguration;
use App\Models\SamlReplayRecord;
use App\Models\UserAccount;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response as SamlResponse;
use OneLogin\Saml2\Settings as SamlSettings;

class SamlSpController extends Controller
{
    public function redirectToIdp(Request $request): RedirectResponse
    {
        $configuration = SamlConfiguration::query()
            ->where('mode', 'idp')
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();

        if (! $configuration || blank($configuration->sso_url)) {
            return redirect()->route('login')->withErrors([
                'sso' => 'SAML login is not configured.',
            ]);
        }

        $requestId = '_' . Str::uuid()->toString();
        $relayState = $request->query('RelayState', $configuration->default_relay_state ?: route('main.dashboard'));

        SamlReplayRecord::create([
            'request_id' => $requestId,
            'issuer' => $configuration->entity_id,
            'expires_at' => now()->addSeconds((int) config('services.saml.assertion_ttl_seconds', 300)),
        ]);

        SamlAuditEvent::create([
            'saml_configuration_id' => $configuration->id,
            'event_name' => 'saml.sp.request.issued',
            'entity_id' => $configuration->entity_id,
            'request_id' => $requestId,
            'ip_address' => $request->ip(),
            'outcome' => 'success',
        ]);

        $configuration->forceFill(['last_used_at' => now()])->save();

        $query = http_build_query([
            'SAMLRequest' => $this->buildAuthnRequest($requestId, $configuration),
            'RelayState' => $relayState,
        ]);

        return redirect()->away($configuration->sso_url . (str_contains($configuration->sso_url, '?') ? '&' : '?') . $query);
    }

    public function acs(Request $request): RedirectResponse
    {
        $configuration = SamlConfiguration::query()
            ->where('mode', 'idp')
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();

        if (! $configuration) {
            $this->auditRejected($request, null, 'No active IdP configuration.');

            return redirect()->route('login')->withErrors([
                'sso' => 'SAML login is not configured.',
            ]);
        }

        try {
            $assertion = $this->parseSamlResponse($request, $configuration);
        } catch (\Throwable $exception) {
            $this->auditRejected($request, $configuration, $exception->getMessage());

            return redirect()->route('login')->withErrors([
                'sso' => $exception->getMessage(),
            ]);
        }

        $user = $this->findUser($assertion['email']);

        if (! $user) {
            $this->auditRejected($request, $configuration, 'SAML user was not found in Rooms user accounts.', $assertion);

            return redirect()
                ->route('saml.user-not-found')
                ->with('saml_email', $assertion['email']);
        }

        if ($user->account_status !== 'active') {
            $this->auditRejected($request, $configuration, 'SAML user account is not active.', $assertion);

            return redirect()
                ->route('saml.user-not-found')
                ->with('saml_email', $assertion['email'])
                ->with('saml_reason', 'inactive');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('user', LoginController::sessionPayload($user));

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        SamlAuditEvent::create([
            'saml_configuration_id' => $configuration->id,
            'event_name' => 'saml.sp.assertion.accepted',
            'entity_id' => $configuration->entity_id,
            'user_account_id' => $user->id,
            'response_id' => $assertion['response_id'],
            'ip_address' => $request->ip(),
            'outcome' => 'success',
            'metadata' => [
                'assertion_id' => $assertion['assertion_id'],
                'email' => $assertion['email'],
            ],
        ]);

        $relayState = (string) $request->input('RelayState', '');

        return redirect($relayState !== '' && str_starts_with($relayState, '/') ? $relayState : route('main.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        SamlAuditEvent::create([
            'event_name' => 'saml.logout.completed',
            'user_account_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'outcome' => 'success',
        ]);

        return redirect()->route('login');
    }

    private function buildAuthnRequest(string $requestId, SamlConfiguration $configuration): string
    {
        $issueInstant = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $issuer = e(config('services.saml.sp_entity_id', url('/saml2/metadata')));
        $destination = e($configuration->sso_url);
        $acsUrl = e(url('/saml2/acs'));

        $xml = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="{$requestId}" Version="2.0" IssueInstant="{$issueInstant}" Destination="{$destination}" AssertionConsumerServiceURL="{$acsUrl}" ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
  <saml:Issuer xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">{$issuer}</saml:Issuer>
  <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        return base64_encode(gzdeflate($xml));
    }

    private function parseSamlResponse(Request $request, SamlConfiguration $configuration): array
    {
        $encoded = (string) $request->input('SAMLResponse', '');
        throw_if($encoded === '', new \RuntimeException('Missing SAMLResponse.'));
        throw_if(blank($configuration->x509_cert), new \RuntimeException('SAML IdP signing certificate is not configured.'));

        $this->syncSamlServerUrl($request);

        $xml = base64_decode($encoded, true);
        throw_if($xml === false, new \RuntimeException('Invalid SAMLResponse encoding.'));

        $document = new DOMDocument();
        $loaded = @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        throw_unless($loaded, new \RuntimeException('Invalid SAMLResponse XML.'));

        $xpath = new DOMXPath($document);
        $inResponseTo = $this->attribute($xpath, '/*[local-name()="Response"]', 'InResponseTo');
        if ($inResponseTo !== '') {
            throw_unless(
                SamlReplayRecord::where('request_id', $inResponseTo)->where('expires_at', '>', now())->exists(),
                new \RuntimeException('SAML response does not match an active login request.')
            );
        }

        $response = new SamlResponse($this->samlSettings($configuration), $encoded);

        throw_unless($response->isValid($inResponseTo ?: null), new \RuntimeException($response->getError(false) ?: 'Invalid SAMLResponse signature or assertion.'));

        $responseId = $this->attribute($xpath, '/*[local-name()="Response"]', 'ID');
        $assertionId = $this->attribute($xpath, '//*[local-name()="Assertion"]', 'ID');
        $issuer = $this->value($xpath, 'string(/*[local-name()="Response"]/*[local-name()="Issuer"])');
        $destination = $this->attribute($xpath, '/*[local-name()="Response"]', 'Destination');
        $status = $this->attribute($xpath, '/*[local-name()="Response"]/*[local-name()="Status"]/*[local-name()="StatusCode"]', 'Value');
        $audience = $this->value($xpath, 'string(//*[local-name()="Audience"])');
        $recipient = $this->attribute($xpath, '//*[local-name()="SubjectConfirmationData"]', 'Recipient');
        $notBefore = $this->attribute($xpath, '//*[local-name()="Conditions"]', 'NotBefore');
        $notOnOrAfter = $this->attribute($xpath, '//*[local-name()="Conditions"]', 'NotOnOrAfter');
        $email = trim((string) $response->getNameId());
        $attributes = $this->flattenAttributes($response->getAttributes());
        if ($email === '') {
            $email = $this->emailFromAttributes($attributes);
        }

        throw_if($status !== 'urn:oasis:names:tc:SAML:2.0:status:Success', new \RuntimeException('SAML response was not successful.'));
        throw_if($issuer !== $configuration->entity_id, new \RuntimeException('SAML issuer does not match the active IdP.'));
        throw_unless(in_array($destination, $this->acsUrls(), true), new \RuntimeException('SAML destination does not match this ACS endpoint.'));
        throw_unless(in_array($recipient, $this->acsUrls(), true), new \RuntimeException('SAML recipient does not match this ACS endpoint.'));
        throw_if($audience !== config('services.saml.sp_entity_id', url('/saml2/metadata')), new \RuntimeException('SAML audience does not match this service provider.'));
        throw_if($email === '', new \RuntimeException('SAML assertion did not include an email NameID.'));

        $now = now()->utc();
        if ($notBefore !== '') {
            throw_if($now->lt(\Carbon\Carbon::parse($notBefore)->subSeconds((int) config('services.saml.clock_skew_seconds', 60))), new \RuntimeException('SAML assertion is not valid yet.'));
        }
        if ($notOnOrAfter !== '') {
            throw_if($now->gte(\Carbon\Carbon::parse($notOnOrAfter)->addSeconds((int) config('services.saml.clock_skew_seconds', 60))), new \RuntimeException('SAML assertion has expired.'));
        }

        throw_if(SamlReplayRecord::where('response_id', $responseId)->orWhere('assertion_id', $assertionId)->exists(), new \RuntimeException('SAML response was already used.'));

        SamlReplayRecord::create([
            'assertion_id' => $assertionId,
            'response_id' => $responseId,
            'issuer' => $issuer,
            'expires_at' => now()->addSeconds((int) config('services.saml.assertion_ttl_seconds', 300)),
        ]);

        return [
            'response_id' => $responseId,
            'assertion_id' => $assertionId,
            'email' => $email,
            'attributes' => $attributes ?: $this->attributes($xpath),
        ];
    }

    private function samlSettings(SamlConfiguration $configuration): SamlSettings
    {
        return new SamlSettings([
            'strict' => true,
            'debug' => config('app.debug'),
            'baseurl' => rtrim((string) config('app.url'), '/'),
            'sp' => [
                'entityId' => config('services.saml.sp_entity_id', url('/saml2/metadata')),
                'assertionConsumerService' => [
                    'url' => url('/saml2/acs'),
                    'binding' => Constants::BINDING_HTTP_POST,
                ],
                'singleLogoutService' => [
                    'url' => url('/saml2/logout'),
                    'binding' => Constants::BINDING_HTTP_REDIRECT,
                ],
            ],
            'idp' => [
                'entityId' => $configuration->entity_id,
                'singleSignOnService' => [
                    'url' => $configuration->sso_url ?: $configuration->entity_id,
                    'binding' => Constants::BINDING_HTTP_REDIRECT,
                ],
                'singleLogoutService' => [
                    'url' => $configuration->slo_url ?: $configuration->sso_url ?: $configuration->entity_id,
                    'binding' => Constants::BINDING_HTTP_REDIRECT,
                ],
                'x509cert' => $configuration->x509_cert,
            ],
            'security' => [
                'wantXMLValidation' => true,
                'wantMessagesSigned' => false,
                'wantAssertionsSigned' => false,
                'wantNameId' => true,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                'destinationStrictlyMatches' => true,
            ],
        ]);
    }

    private function syncSamlServerUrl(Request $request): void
    {
        $acsPath = parse_url(url('/saml2/acs'), PHP_URL_PATH) ?: '/saml2/acs';

        $_SERVER['REQUEST_URI'] = $acsPath;
        $_SERVER['SCRIPT_NAME'] = '';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['HTTPS'] = $request->isSecure() ? 'on' : 'off';
    }

    private function flattenAttributes(array $attributes): array
    {
        return collect($attributes)
            ->map(fn ($value) => is_array($value) ? implode(', ', array_filter(array_map('strval', $value))) : (string) $value)
            ->all();
    }

    private function emailFromAttributes(array $attributes): string
    {
        foreach (['email', 'mail', 'EmailAddress', 'emailAddress', 'urn:oid:0.9.2342.19200300.100.1.3'] as $key) {
            if (! blank($attributes[$key] ?? null)) {
                return trim((string) $attributes[$key]);
            }
        }

        return '';
    }

    private function findUser(string $email): ?UserAccount
    {
        return UserAccount::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    private function attributes(DOMXPath $xpath): array
    {
        $attributes = [];

        foreach ($xpath->query('//*[local-name()="Attribute"]') as $attribute) {
            $name = $attribute->attributes?->getNamedItem('Name')?->nodeValue;
            if (! $name) {
                continue;
            }

            $value = (new DOMXPath($attribute->ownerDocument))->evaluate('string(*[local-name()="AttributeValue"])', $attribute);
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    private function value(DOMXPath $xpath, string $query): string
    {
        return trim((string) $xpath->evaluate($query));
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): string
    {
        $node = $xpath->query($query)->item(0);

        return $node?->attributes?->getNamedItem($attribute)?->nodeValue ?? '';
    }

    private function auditRejected(Request $request, ?SamlConfiguration $configuration, string $reason, ?array $assertion = null): void
    {
        SamlAuditEvent::create([
            'saml_configuration_id' => $configuration?->id,
            'event_name' => 'saml.sp.assertion.rejected',
            'entity_id' => $configuration?->entity_id,
            'response_id' => $assertion['response_id'] ?? null,
            'ip_address' => $request->ip(),
            'outcome' => 'rejected',
            'metadata' => [
                'reason' => $reason,
                'has_response' => $request->filled('SAMLResponse'),
                'assertion_id' => $assertion['assertion_id'] ?? null,
                'email' => $assertion['email'] ?? null,
            ],
        ]);
    }

    private function acsUrls(): array
    {
        return array_values(array_unique([
            url('/saml2/acs'),
            rtrim((string) config('app.url'), '/').'/saml2/acs',
        ]));
    }
}
