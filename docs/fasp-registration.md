# FASP Registration Implementation

This document describes the WordPress ActivityPub plugin's implementation of the FASP registration specification v0.1.

## Overview

The FASP registration implementation allows external FASP providers to register with this WordPress installation to provide auxiliary services. This follows the [FASP registration specification v0.1](https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/registration.md).

## Architecture

The implementation uses WordPress options instead of custom database tables for simplicity and compatibility:

- **Registration data**: Stored in `activitypub_fasp_registrations` option
- **Capability data**: Stored in `activitypub_fasp_capabilities` option

## Components

### REST API Endpoints

#### Registration Endpoint (`POST /wp-json/activitypub/1.0/registration`)

Handles registration requests from FASP providers.

**Request format:**
```json
{
  "name": "Example FASP",
  "baseUrl": "https://fasp.example.com",
  "serverId": "b2ks6vm8p23w",
  "publicKey": "FbUJDVCftINc9FlgRu2jLagCVvOa7I2Myw8aidvkong="
}
```

**Response format:**
```json
{
  "faspId": "dfkl3msw6ps3",
  "publicKey": "KvVQVgD4/WcdgbUDWH7EVaYX9W7Jz5fGWt+Wg8h+YvI=",
  "registrationCompletionUri": "https://example.com/wp-admin/admin.php?page=activitypub-fasp-registrations&highlight=dfkl3msw6ps3"
}
```

#### Capability Endpoints

- `POST /wp-json/activitypub/1.0/capabilities/{identifier}/{version}/activation` - Enable capability
- `DELETE /wp-json/activitypub/1.0/capabilities/{identifier}/{version}/activation` - Disable capability

### Admin Interface

The admin interface is available at **WP Admin > ActivityPub > FASP Registrations**.

Features:
- View pending registration requests
- Approve or reject registrations
- View approved registrations
- Display public key fingerprints for verification
- Manage registered FASPs

### Classes

#### `Fasp_Controller`
- Handles all FASP REST API endpoints (provider info, registration, capability activation)
- Processes registration requests
- Manages capability activation/deactivation

#### `Fasp`
- Manages registration data using WordPress options
- Provides methods for approval/rejection
- Handles capability management
- Adds FASP base URL to nodeinfo metadata

#### `Fasp_Admin`
- WordPress admin interface (in `wp-admin` folder)
- Registration management UI
- Action handlers for approve/reject/delete

## Security Features

### Server Keypair Reuse
- Reuses the application actor's RSA keypair for FASP responses
- Avoids generating per-registration key material
- Never persists private keys inside registration records

### Public Key Fingerprints
- SHA-256 fingerprints of public keys for verification
- Displayed in admin interface for manual verification
- Follows FASP specification requirements

### Nonce Protection
- All admin actions protected with WordPress nonces
- CSRF protection for registration management

## Data Storage

### Registration Data Structure
```php
array(
    'fasp_id' => 'unique-fasp-id',
    'name' => 'FASP Provider Name',
    'base_url' => 'https://fasp.example.com',
    'server_id' => 'server-id-from-fasp',
    'fasp_public_key' => 'base64-encoded-public-key',
    'fasp_public_key_fingerprint' => 'sha256-fingerprint-of-public-key',
    'server_public_key' => 'base64-encoded-server-public-key',
    'status' => 'pending|approved|rejected',
    'requested_at' => 'YYYY-MM-DD HH:MM:SS',
    'approved_at' => 'YYYY-MM-DD HH:MM:SS',
    'approved_by' => user_id,
)
```

### Capability Data Structure
```php
array(
    'fasp_id_capability_vN' => array(
        'fasp_id' => 'fasp-id',
        'identifier' => 'capability-name',
        'version' => 1,
        'enabled' => true|false,
        'updated_at' => 'YYYY-MM-DD HH:MM:SS',
    ),
)
```

## Usage Examples

### Testing Registration
```bash
curl -X POST "https://example.com/wp-json/activitypub/1.0/registration" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test FASP Provider",
    "baseUrl": "https://fasp.example.com",
    "serverId": "test-server-123",
    "publicKey": "dGVzdC1wdWJsaWMta2V5"
  }'
```

### Testing Capability Activation
```bash
# Enable capability
curl -X POST "https://example.com/wp-json/activitypub/1.0/capabilities/trends/1/activation" \
  -H "Authorization: Signature ..."

# Disable capability
curl -X DELETE "https://example.com/wp-json/activitypub/1.0/capabilities/trends/1/activation" \
  -H "Authorization: Signature ..."
```

## Testing

Run FASP tests (including registration):
```bash
./vendor/bin/phpunit tests/phpunit/tests/includes/class-test-fasp.php
```

## Future Enhancements

1. **Ed25519 Signature Verification**: Implement proper Ed25519 signature verification for capability endpoints
2. **Webhook Notifications**: Notify FASPs when registrations are approved/rejected
3. **Capability Discovery**: Auto-discover supported capabilities from FASP providers
4. **Registration Expiry**: Implement registration expiration and renewal
5. **Audit Logging**: Log all registration and capability changes

## Compliance

This implementation follows the FASP registration specification v0.1:
- ✅ Registration endpoint (`/registration`)
- ✅ Capability activation endpoints (`/capabilities/{id}/{version}/activation`)
- ✅ Ed25519 keypair generation
- ✅ Public key fingerprint verification
- ✅ Admin interface for registration management
- ✅ Registration completion URI
- ⚠️ Ed25519 signature verification (placeholder implementation)

## References

- [FASP Registration Specification v0.1](https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/registration.md)
- [FASP Protocol Basics](https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/protocol_basics.md)
- [Ed25519 Signature Specification](https://tools.ietf.org/html/rfc8032)
