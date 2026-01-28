<?php
/**
 * Server-side rendering of the following block.
 *
 * @package Activitypub
 *
 * @var array     $attributes Block attributes.
 * @var \WP_Block $block      Current block.
 * @var string    $content    Block content.
 */

use Activitypub\Blocks;

return Blocks::render_actor_list_block( 'following', $attributes, $block, $content ?? '' );
