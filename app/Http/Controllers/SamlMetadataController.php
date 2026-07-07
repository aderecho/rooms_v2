<?php

namespace App\Http\Controllers;

use App\Models\SamlAuditEvent;
use DOMDocument;
use Illuminate\Http\Request;

class SamlMetadataController extends Controller
{
    public function __invoke(Request $request)
    {
        SamlAuditEvent::create([
            'event_name' => 'saml.metadata.viewed',
            'entity_id' => config('services.saml.sp_entity_id'),
            'ip_address' => $request->ip(),
            'outcome' => 'success',
        ]);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $entity = $document->createElementNS('urn:oasis:names:tc:SAML:2.0:metadata', 'md:EntityDescriptor');
        $entity->setAttribute('entityID', config('services.saml.sp_entity_id', url('/saml2/metadata')));
        $document->appendChild($entity);

        $sp = $document->createElement('md:SPSSODescriptor');
        $sp->setAttribute('protocolSupportEnumeration', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $sp->setAttribute('AuthnRequestsSigned', config('services.saml.require_signed_requests') ? 'true' : 'false');
        $sp->setAttribute('WantAssertionsSigned', config('services.saml.sign_assertions') ? 'true' : 'false');
        $entity->appendChild($sp);

        $certificate = trim((string) config('services.saml.idp_public_cert'));
        if ($certificate !== '') {
            $keyDescriptor = $document->createElement('md:KeyDescriptor');
            $keyDescriptor->setAttribute('use', 'signing');
            $keyInfo = $document->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:KeyInfo');
            $x509Data = $document->createElement('ds:X509Data');
            $x509Certificate = $document->createElement('ds:X509Certificate', $this->normalizeCertificate($certificate));
            $x509Data->appendChild($x509Certificate);
            $keyInfo->appendChild($x509Data);
            $keyDescriptor->appendChild($keyInfo);
            $sp->appendChild($keyDescriptor);
        }

        $nameId = $document->createElement('md:NameIDFormat', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress');
        $sp->appendChild($nameId);

        $acs = $document->createElement('md:AssertionConsumerService');
        $acs->setAttribute('Binding', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST');
        $acs->setAttribute('Location', url('/saml2/acs'));
        $acs->setAttribute('index', '1');
        $acs->setAttribute('isDefault', 'true');
        $sp->appendChild($acs);

        $slo = $document->createElement('md:SingleLogoutService');
        $slo->setAttribute('Binding', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect');
        $slo->setAttribute('Location', url('/saml2/logout'));
        $sp->appendChild($slo);

        return response($document->saveXML(), 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    private function normalizeCertificate(string $certificate): string
    {
        return preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificate);
    }
}
