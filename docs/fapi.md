# Fediverse Auxiliary Service Provider (FAPI) Implementation

This document describes the WordPress ActivityPub plugin's implementation of the Fediverse Auxiliary Service Provider (FAPI) specification v0.1.

## Overview

The FAPI implementation allows the WordPress ActivityPub plugin to act as a Fediverse Auxiliary Service Provider, enabling other fediverse servers to discover and interact with auxiliary services provided by this WordPress installation.

## Specification Compliance

This implementation follows the [FAPI specification v0.1](https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/tree/main/general/v0.1) including:

- **Provider Info Endpoint**: `/wp-json/activitypub/v1/fapi/provider_info`
- **Nodeinfo Integration**: Adds `faspBaseUrl` to nodeinfo metadata
- **Content Integrity**: Implements SHA-256 content-digest headers
- **Authentication Ready**: Prepared for HTTP Message Signatures (RFC-9421)

## Endpoints

### Provider Info (`GET /wp-json/activitypub/v1/fapi/provider_info`)

Returns information about this FAPI provider including:

```json
{
  "name": "Example Site ActivityPub FAPI",
  "privacyPolicy": [
    {
      "url": "https://example.com/privacy-policy/",
      "language": "en_US"
    }
  ],
  "capabilities": [],
  "signInUrl": "https://example.com/wp-admin/",
  "contactEmail": "admin@example.com"
}
```

#### Required Fields

- `name`: Provider name (site name + "ActivityPub FAPI")
- `privacyPolicy`: Array of privacy policy URLs and languages
- `capabilities`: Array of supported capabilities (empty by default)

#### Optional Fields

- `signInUrl`: WordPress admin URL for provider sign-in
- `contactEmail`: Site admin email address
- `fediverseAccount`: Fediverse account for updates (not configured by default)

## Configuration

### Capabilities

Capabilities can be added via the `activitypub_fapi_capabilities` filter:

```php
add_filter( 'activitypub_fapi_capabilities', function( $capabilities ) {
    $capabilities[] = array(
        'id'      => 'my_capability',
        'version' => '1.0',
    );
    return $capabilities;
} );
```

### Nodeinfo Integration

The FAPI base URL is automatically added to nodeinfo metadata as `faspBaseUrl`:

```json
{
  "metadata": {
    "faspBaseUrl": "https://example.com/wp-json/activitypub/v1/fapi"
  }
}
```

## Security Features

### Content Integrity

All responses include a `Content-Digest` header with SHA-256 hash:

```http
Content-Digest: sha-256=:RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg=:
```

### Authentication (Planned)

The implementation is prepared for HTTP Message Signatures authentication:
- Signature verification using Ed25519
- Request validation with `@method`, `@target-uri`, and `content-digest`
- Response signing with `@status` and `content-digest`

Currently, authentication allows all requests for development purposes.

## Development

### Testing

Run FAPI tests:

```bash
./vendor/bin/phpunit tests/phpunit/tests/includes/class-test-fapi.php
```

### Implementation Status

- ✅ Provider info endpoint implemented
- ✅ Nodeinfo integration added
- ✅ Content-digest headers added
- ✅ Basic test coverage
- ⏳ HTTP Message Signatures authentication (placeholder)
- ⏳ Capability specifications (extensible via filters)

## Usage Examples

### Discovering FAPI Base URL

1. Query nodeinfo: `GET /.well-known/nodeinfo`
2. Follow nodeinfo URL and find `metadata.faspBaseUrl`
3. Use base URL for FAPI endpoints

### Querying Provider Information

```bash
curl -X GET "https://example.com/wp-json/activitypub/v1/fapi/provider_info" \
  -H "Accept: application/json"
```

## Future Enhancements

Potential areas for expansion:

1. **Full Authentication**: Complete HTTP Message Signatures implementation
2. **Capability Specifications**: Implement specific FAPI capabilities (trends, search, etc.)
3. **Registration Endpoints**: Server registration and key exchange
4. **Rate Limiting**: Implement proper rate limiting with Retry-After headers
5. **Admin Interface**: WordPress admin interface for FAPI configuration

## Standards Compliance

This implementation aims to be compliant with:

- [FAPI Specification v0.1](https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/tree/main/general/v0.1)
- [RFC-9530: Digest Fields](https://tools.ietf.org/html/rfc9530.html)
- [RFC-9421: HTTP Message Signatures](https://tools.ietf.org/html/rfc9421.html) (when implemented)
- [ActivityPub Protocol](https://www.w3.org/TR/activitypub/)
