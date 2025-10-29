/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Following Table Admin UI', () => {
	test.beforeEach( async ( { admin, page } ) => {
		// Navigate to the Following page.
		await admin.visitAdminPage( 'users.php', 'page=activitypub-following-list' );

		// Check if we got a permission error - if so, skip the test.
		const permissionError = page.locator( 'text=Sorry, you are not allowed to access this page' );
		const hasPermissionError = ( await permissionError.count() ) > 0;

		test.skip( hasPermissionError, 'User does not have permission to access Following page' );
	} );

	test( 'should load the Following page successfully', async ( { page } ) => {
		// Check that we're on the correct page.
		await expect( page.locator( 'h1.wp-heading-inline' ) ).toHaveText( 'Followings' );

		// Verify the follow form is present.
		await expect( page.locator( '#activitypub-follow-form' ) ).toBeVisible();

		// Verify the input field exists.
		await expect( page.locator( '#activitypub-profile' ) ).toBeVisible();

		// Verify the submit button exists.
		await expect( page.locator( '#activitypub-follow-form input[type="submit"]' ) ).toBeVisible();
	} );

	test( 'should display correct form labels and instructions', async ( { page } ) => {
		// Check the Follow heading.
		await expect( page.locator( '#col-left h2' ) ).toHaveText( 'Follow' );

		// Verify instructions are present (check the first paragraph mentioning Fediverse).
		const instructionsParagraph = page
			.locator( '#col-left .form-wrap' )
			.locator( 'p' )
			.filter( { hasText: 'Fediverse' } )
			.first();
		await expect( instructionsParagraph ).toBeVisible();
	} );

	test( 'should show error for invalid webfinger format', async ( { page } ) => {
		// Enter an invalid webfinger identifier (email-like format that will fail resolution).
		// This tests line 131 where normalize_identifier returns null for failed webfinger lookups.
		const invalidActor = 'invalid@nonexistent-domain-12345.fake';
		await page.locator( '#activitypub-profile' ).fill( invalidActor );

		// Submit the form.
		await page.locator( '#activitypub-follow-form input[type="submit"]' ).click();

		// Wait for page reload.
		await page.waitForLoadState( 'networkidle' );

		// Check for error notice.
		const notice = page.locator( '.notice.notice-error' );
		await expect( notice ).toBeVisible();

		// CRITICAL: Verify the error message shows what the user typed, not an empty string.
		// Bug: Without the fix at line 131, this would show 'Unable to follow account ""'.
		// Fix: Should show the original webfinger: "invalid@nonexistent-domain-12345.fake".
		const errorText = await notice.textContent();
		expect( errorText ).toContain( 'Unable to follow account' );
		expect( errorText ).toContain( invalidActor );

		// Verify the input field is populated with the invalid value.
		await expect( page.locator( '#activitypub-profile' ) ).toHaveValue( invalidActor );
		await expect( page.locator( '#activitypub-profile' ) ).toHaveClass( /highlight/ );
	} );

	test( 'should show error for empty form submission', async ( { page } ) => {
		// Leave the form empty and submit.
		await page.locator( '#activitypub-follow-form input[type="submit"]' ).click();

		// Wait for page reload.
		await page.waitForLoadState( 'networkidle' );

		// Check for error notice.
		const notice = page.locator( '.notice.notice-error' );
		await expect( notice ).toBeVisible();
		await expect( notice ).toContainText( 'Unable to follow account' );
	} );

	test( 'should display the followings table', async ( { page } ) => {
		// Check that the table exists.
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();

		// Verify table headers.
		const headers = page.locator( 'table.wp-list-table thead th' );
		await expect( headers ).toContainText( [ 'Username', 'Name', 'Profile', 'Last updated' ] );
	} );

	test( 'should display filter views', async ( { page } ) => {
		// Check that the view filters exist.
		await expect( page.locator( '.subsubsub' ) ).toBeVisible();

		// Verify the All, Accepted, and Pending links exist.
		await expect( page.locator( '.subsubsub a' ).filter( { hasText: 'All' } ) ).toBeVisible();
		await expect( page.locator( '.subsubsub a' ).filter( { hasText: 'Accepted' } ) ).toBeVisible();
		await expect( page.locator( '.subsubsub a' ).filter( { hasText: 'Pending' } ) ).toBeVisible();
	} );

	test( 'should display About Followings section', async ( { page } ) => {
		// Check for the informational section.
		await expect( page.locator( '.edit-term-notes strong' ) ).toHaveText( 'About Followings' );
		await expect( page.locator( '.edit-term-notes p' ) ).toContainText( 'Pending' );
		await expect( page.locator( '.edit-term-notes p' ) ).toContainText( 'ActivityPub protocol' );
	} );

	test( 'should display no profiles message when empty', async ( { page } ) => {
		// This test checks the empty state message.
		const noItemsMessage = page.locator( '.no-items' );
		const tableRows = page.locator( 'table.wp-list-table tbody tr' );
		const rowCount = await tableRows.count();

		// If there's only one row and it contains no-items message.
		if ( rowCount === 1 ) {
			const hasNoItems = ( await noItemsMessage.count() ) > 0;
			if ( hasNoItems ) {
				await expect( noItemsMessage ).toContainText( 'No profiles found' );
			}
		}
	} );

	test( 'should preserve page structure after form submission', async ( { page } ) => {
		// Submit form with invalid data.
		await page.locator( '#activitypub-profile' ).fill( 'invalid' );
		await page.locator( '#activitypub-follow-form input[type="submit"]' ).click();

		// Wait for page reload.
		await page.waitForLoadState( 'networkidle' );

		// Verify page structure is intact.
		await expect( page.locator( 'h1.wp-heading-inline' ) ).toHaveText( 'Followings' );
		await expect( page.locator( '#activitypub-follow-form' ) ).toBeVisible();
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
	} );
} );
