<?php
/**
 * Server-side rendering of the followers block.
 *
 * @package Activitypub
 *
 * @var array     $attributes Block attributes.
 * @var \WP_Block $block      Current block.
 * @var string    $content    Block content.
 */

use Activitypub\Blocks;

return Blocks::render_actor_list_block( 'followers', $attributes, $block, $content ?? '' );
