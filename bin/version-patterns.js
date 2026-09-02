/**
 * Version-marker replacement patterns for the release script.
 *
 * Kept in its own module so `bin/__tests__/release.test.js` can exercise the
 * patterns the release actually uses, rather than a copy that drifts from them.
 */

/*
 * Skip a match sitting on a comment line.
 *
 * `includes/class-migration.php` documents the convention by showing a
 * `version_compare()` call with the marker still in it. That example is
 * instructions for the next developer, not code, so rewriting it every
 * release would slowly destroy it.
 */
const NOT_IN_COMMENT = '(?<!^[ \\t]*(?:\\*|//|#|/\\*)[^\\n]*)';

/*
 * A `'unreleased'` sitting where a function takes an argument.
 *
 * Anchoring on the name of the function it belongs to would be more precise,
 * but it cannot be made to work: the name and the version sit on different
 * lines whenever the call is wrapped, `.` does not cross a newline, and every
 * bound loose enough to span the arguments in between is also loose enough to
 * swallow a `;` inside a translated message.
 *
 * What keeps this off unrelated text is the shape of the match. The literal
 * has to be exactly `'unreleased'`, it has to sit in an argument slot, and it
 * must not be on a comment line. The leading comma matters too: none of these
 * functions take the version first, so requiring an argument ahead of it
 * leaves `\__( 'unreleased', 'activitypub' )` alone, where the word is the
 * thing being translated rather than a version.
 */
const VERSION_ARGUMENT = `(?<=,\\s*)'unreleased'(?=\\s*[,)])`;

/**
 * Build the PHP patterns for one version.
 *
 * @param {string} version The version being released.
 * @return {Array<{search: RegExp, replace: string}>} Patterns for updateVersionInFile().
 */
const phpVersionPatterns = ( version ) => [
	{
		search: /@since unreleased/gi,
		replace: `@since ${ version }`,
	},
	{
		search: /@deprecated unreleased/gi,
		replace: `@deprecated ${ version }`,
	},
	{
		search: new RegExp( NOT_IN_COMMENT + VERSION_ARGUMENT, 'gim' ),
		replace: `'${ version }'`,
	},
];

module.exports = { phpVersionPatterns };
