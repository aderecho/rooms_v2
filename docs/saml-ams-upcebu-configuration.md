# SAML Configuration for UP Cebu AMS

This document describes how to configure Rooms SAML login against the UP Cebu AMS SAML endpoint.

## AMS URL

```text
https://ams.upcebu.edu.ph/
```

Use this URL as the base URL for the IdP values. Confirm the exact metadata, SSO, and SLO paths from the AMS administrator before production use.

## Rooms Service Provider URLs

Set `APP_URL` to the public URL of the Rooms application. The SAML endpoints are generated from that value.

For local development:

```env
APP_URL=http://localhost:8000
```

For production, replace it with the public Rooms URL:

```env
APP_URL=https://rooms.your-domain.edu.ph
```

Rooms exposes these SAML endpoints:

```text
Metadata: ${APP_URL}/saml2/metadata
Login:    ${APP_URL}/saml2/login
ACS:      ${APP_URL}/saml2/acs
Logout:   ${APP_URL}/saml2/logout
```

Give these URLs to the AMS administrator when registering Rooms as a service provider.

## Required `.env` Values

Add or update these values in `.env`:

```env
# SAML Service Provider identity for Rooms
SAML_SP_ENTITY_ID="${APP_URL}/saml2/metadata"

# UP Cebu AMS IdP identity
SAML_IDP_ENTITY_ID="https://ams.upcebu.edu.ph/saml2/metadata"

# AMS signing certificate.
# Paste the certificate as a single line, including BEGIN/END markers if provided.
SAML_IDP_PUBLIC_CERT="-----BEGIN CERTIFICATE-----PASTE_AMS_CERTIFICATE_HERE-----END CERTIFICATE-----"

# Optional. Only needed if Rooms must sign SAML requests or metadata with a private key.
SAML_IDP_PRIVATE_KEY=
SAML_IDP_KEY_PASSPHRASE=

# Assertion validation timing
SAML_ASSERTION_TTL_SECONDS=300
SAML_CLOCK_SKEW_SECONDS=60

# Signature behavior
SAML_REQUIRE_SIGNED_REQUESTS=false
SAML_SIGN_RESPONSES=true
SAML_SIGN_ASSERTIONS=true
```

After editing `.env`, clear cached configuration:

```bash
php artisan config:clear
php artisan cache:clear
```

## SAML Config UI Values

In Rooms, go to:

```text
Settings > SAML Config
```

Create or update the active IdP configuration:

```text
Name: UP Cebu AMS
Mode: IdP
Entity ID: https://ams.upcebu.edu.ph/saml2/metadata
SSO URL: https://ams.upcebu.edu.ph/saml2/sso
SLO URL: https://ams.upcebu.edu.ph/saml2/slo
X.509 Certificate: paste AMS certificate
Signing Algorithm: rsa-sha256
Default Relay State: /MainDashboard
Status: Active
Active provider: Yes
```

If AMS uses different SAML paths, replace the three IdP URLs above with the values from AMS metadata.

## Required SAML Attributes

Rooms currently matches SAML users against existing `user_accounts` records by email.

AMS must send the user email in the SAML assertion, preferably as the NameID:

```text
NameID Format: urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress
NameID Value: user email address
```

The email must already exist in the Rooms `user_accounts.email` column. If the user does not exist, Rooms redirects to the user-not-found page and instructs the user to contact the Rooms administrator.

## AMS Registration Checklist

Provide these values to the AMS administrator:

```text
SP Entity ID: ${APP_URL}/saml2/metadata
ACS URL: ${APP_URL}/saml2/acs
SLO URL: ${APP_URL}/saml2/logout
NameID Format: emailAddress
Required user identifier: email address
```

Request these values from the AMS administrator:

```text
IdP Entity ID
IdP SSO URL
IdP SLO URL
IdP X.509 signing certificate
Whether AuthnRequests must be signed
Whether assertions/responses are signed
```

## Testing

1. Visit Rooms metadata:

```text
${APP_URL}/saml2/metadata
```

2. Start SAML login:

```text
${APP_URL}/saml2/login
```

3. Confirm successful login redirects to:

```text
/MainDashboard
```

4. If login fails, check:

```text
Settings > SAML Config
```

Also review `saml_audit_events` in the database for accepted or rejected SAML events.

## Common Issues

- `SAML issuer does not match the active IdP`: the configured Entity ID does not match the issuer in the AMS response.
- `SAML destination does not match this ACS endpoint`: `APP_URL` or AMS ACS registration does not match the actual Rooms URL.
- `SAML audience does not match this service provider`: `SAML_SP_ENTITY_ID` does not match what AMS sends as the audience.
- `SAML assertion did not include an email NameID`: AMS is not sending the email as NameID.
- User not found page: the SAML email is valid, but no matching Rooms account exists.
