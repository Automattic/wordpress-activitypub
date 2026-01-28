<?php
/**
 * Server-side rendering of the following block.
 *
 * @package Activitypub
 */

use Activitypub\Blocks;

/* @var array $attributes Block attributes. */
$attributes = $attributes ?? array();

/* @var WP_Block $block Current block. */
$block = $block ?? null;

/* @var string $content Block content. */
$content = $content ?? '';

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render method.
echo Blocks::render_actor_list_block( 'following', $attributes, $block, $content );
