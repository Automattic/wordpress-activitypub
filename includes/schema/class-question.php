<?php
/**
 * Question schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of a poll. ActivityStreams calls a Question an intransitive activity, but servers send polls as posts: with an author and no actor.
 *
 * @since unreleased
 */
class Question extends Note {
}
