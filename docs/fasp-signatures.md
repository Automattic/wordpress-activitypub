# FASP Signature Handling Implementation

## Overview

The FASP controller now implements proper HTTP Message Signatures (RFC-9421) for both request authentication and response signing, matching the existing ActivityPub signature infrastructure.

## Request Authentication

### Implementation
```php
public function authenticate_request( $request ) {
    // Use the same signature verification as other ActivityPub endpoints
    return \Activitypub\Rest\Server::verify_signature( $request );
}
```

### How it Works
1. **Delegates to Server::verify_signature()** - Uses the same authentication as inbox and other ActivityPub endpoints
2. **Signature Verification** - Validates HTTP Message Signatures using either:
   - RFC-9421 (HTTP Message Signatures) - Modern standard
   - Draft Cavage signatures - Legacy fallback
3. **Key Lookup** - Retrieves public keys from `Remote_Actors` collection using keyid
4. **Content Validation** - Verifies content-digest headers against request body
5. **Timestamp Checks** - Validates created/expires parameters to prevent replay attacks

### Authentication Flow
```
Request → Server::verify_signature() → Signature::verify_http_signature() →
HTTP_Message_Signature::verify() → Public key lookup → Signature validation
```

## Response Signing

### Implementation
```php
private function sign_response( $response, $content ) {
    // Create signature components for response
    $components = array(
        '"@status"'        => (string) $response->get_status(),
        '"content-digest"' => $response->get_headers()['Content-Digest'] ?? '',
    );

    // Sign using blog actor's private key
    $signature_base = $this->build_signature_base( $components, $params );
    \openssl_sign( $signature_base, $signature, $private_key, \OPENSSL_ALGO_SHA256 );

    // Add signature headers
    $response->header( 'Signature-Input', 'fasp=(' . $identifiers . ')' . $params );
    $response->header( 'Signature', 'fasp=:' . $signature_b64 . ':' );
}
```

### How it Works
1. **Uses Blog Actor** - Signs responses with the blog/application actor's private key
2. **RFC-9421 Components** - Signs `@status` and `content-digest` components
3. **Signature Headers** - Adds proper `Signature-Input` and `Signature` headers
4. **Error Handling** - Gracefully fails without breaking responses

## Signature Verification Process

### Incoming Request Verification
1. **Header Parsing** - Extracts `Signature-Input` and `Signature` headers
2. **Component Extraction** - Gets signed components (@method, @target-uri, content-digest)
3. **Key Retrieval** - Looks up public key using keyid parameter
4. **Signature Base** - Rebuilds signature base string per RFC-9421
5. **Cryptographic Verification** - Uses OpenSSL to verify signature
6. **Timestamp Validation** - Checks created/expires parameters

### Response Signing Process
1. **Component Selection** - Signs @status and content-digest for responses
2. **Key Access** - Uses blog actor's private key for signing
3. **Base String Creation** - Follows RFC-9421 signature base format
4. **Signing** - Uses RSA-SHA256 with OpenSSL
5. **Header Addition** - Adds structured signature headers

## Security Features

### Content Integrity
- **Content-Digest**: SHA-256 hash of request/response body
- **Signature Coverage**: Includes digest in signed components
- **Tamper Detection**: Any modification invalidates signature

### Temporal Security
- **Created Parameter**: Timestamp when signature was created
- **Expires Parameter**: Optional expiration time
- **Clock Skew**: Allows reasonable time drift between servers
- **Replay Protection**: Prevents old signatures from being reused

### Key Management
- **KeyId Parameter**: Identifies which key to use for verification
- **Public Key Lookup**: Retrieves keys from remote actor profiles
- **Key Caching**: Remote actors cached for performance
- **Key Rotation**: Supports key updates through actor profile changes

## FASP Specification Compliance

### Required Features ✅
- **Provider Info Endpoint**: Properly authenticated with signatures
- **Content-Digest Headers**: SHA-256 integrity protection
- **HTTP Message Signatures**: RFC-9421 compliance
- **Response Signing**: Signed responses for integrity

### Implementation Details
- **Signature Label**: Uses "fasp" as signature label for responses
- **Algorithm**: RSA-v1.5-SHA256 (same as other ActivityPub endpoints)
- **Components**: @status and content-digest for responses
- **Fallback**: Graceful degradation if signing fails

## Integration with ActivityPub Infrastructure

### Shared Components
- **Signature Class**: Uses existing `Signature::verify_http_signature()`
- **Actor Management**: Leverages `Actors` and `Remote_Actors` collections
- **HTTP Signature Classes**: Uses `Http_Message_Signature` implementation
- **Server Infrastructure**: Integrates with `Rest\Server::verify_signature()`

### Benefits
- **Consistency**: Same signature handling as inbox/outbox
- **Maintenance**: Uses tested and proven signature code
- **Performance**: Shares cached keys and verification logic
- **Standards**: RFC-9421 and draft signature support

## Testing Coverage

### Authentication Tests
- **Signature Verification**: Tests proper delegation to Server::verify_signature()
- **Error Handling**: Validates proper error responses
- **Integration**: Ensures compatibility with existing auth infrastructure

### Response Tests
- **Content-Digest**: Verifies proper digest header generation
- **Signature Headers**: Validates signature header format
- **Error Recovery**: Tests graceful failure when signing fails

This implementation makes the FASP endpoint secure and compliant with both the FASP specification and ActivityPub security standards.
