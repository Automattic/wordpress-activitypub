# ActivityPub E2E Tests

End-to-End (E2E) tests for the ActivityPub plugin using Playwright.

## Running the Tests

### Prerequisites

1. Install dependencies:
   ```bash
   npm install
   ```

2. Start the WordPress environment:
   ```bash
   npm run env-start
   ```

### Run All E2E Tests

```bash
npm run test:e2e
```

### Debug Mode

Run tests with Playwright inspector for debugging:

```bash
npm run test:e2e:debug
```

### Run Specific Test File

```bash
npx playwright test tests/e2e/specs/your-test.test.js --config tests/e2e/playwright.config.js
```

## Test Structure

Try to map the folder structure of the plugin if possible.

```
tests/e2e/
├── config/
│   └── global-setup.js             # Test environment setup.
├── specs/
│   └── your-test.test.js           # Your test file.
├── playwright.config.js            # Playwright configuration.
└── README.md                       # This file.
```

## Writing New Tests

Follow the WordPress E2E testing patterns:

```javascript
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Your Test Suite', () => {
	test( 'should do something', async ( { request, admin, page } ) => {
		// Your test code.
	} );
} );
```

### Available Fixtures

- `request` - Playwright API request context
- `admin` - Admin utilities for WordPress
- `page` - Playwright page object
- `requestUtils` - WordPress REST API utilities

## Debugging

### View Test Results

Artifacts (screenshots, videos, traces) are saved to:
```
artifacts/
└── storage-states/
    └── admin.json
```

### Browser Mode

```bash
npx playwright test --config tests/e2e/playwright.config.js --headed
```

### Specific Browser

```bash
npx playwright test --config tests/e2e/playwright.config.js --project=chromium
```

## Documentation

- [WordPress E2E Testing](https://developer.wordpress.org/block-editor/contributors/code/testing-overview/#end-to-end-testing).
- [Playwright Documentation](https://playwright.dev/docs/intro).
- [@wordpress/e2e-test-utils-playwright](https://github.com/WordPress/gutenberg/tree/trunk/packages/e2e-test-utils-playwright).

## Contributing

When adding new tests:

1. Follow existing test patterns.
2. Use descriptive test names.
3. Clean up test data in `afterEach` or `afterAll` hooks.
4. Add appropriate assertions.
5. Document complex test scenarios.
