<?php
/**
 * Help template for template tags.
 *
 * @package Activitypub
 */

$code_html = array( 'code' => array() );
?>

<h2><?php \esc_html_e( 'The following Template Tags are available:', 'activitypub' ); ?></h2>
<dl>
	<dt><code>[ap_title]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s title.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_content apply_filters="yes"]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s content. With <code>apply_filters</code> you can decide if filters (<code>apply_filters( \'the_content\', $content )</code>) should be applied or not (default is <code>yes</code>). The values can be <code>yes</code> or <code>no</code>. <code>apply_filters</code> attribute is optional.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_excerpt length="400"]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s excerpt (uses <code>the_excerpt</code> if that is set). If no excerpt is provided, will truncate at <code>length</code> (optional, default = 400).', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_permalink type="url"]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s permalink. <code>type</code> can be either: <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). <code>type</code> attribute is optional.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_shortlink type="url"]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s shortlink. <code>type</code> can be either <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>, to prettify the Shortlinks. <code>type</code> attribute is optional.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_hashtags]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s tags as hashtags.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_hashcats]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s categories as hashtags.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_image type=full]</code></dt>
	<dd><?php echo \wp_kses( __( 'The URL for the post&#8217;s featured image, defaults to full size. The type attribute can be any of the following: <code>thumbnail</code>, <code>medium</code>, <code>large</code>, <code>full</code>. <code>type</code> attribute is optional.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_author]</code></dt>
	<dd><?php echo \wp_kses( __( 'The author&#8217;s name.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_authorurl]</code></dt>
	<dd><?php echo \wp_kses( __( 'The URL to the author&#8217;s profile page.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_date]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s date.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_time]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s time.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_datetime]</code></dt>
	<dd><?php echo \wp_kses( __( 'The post&#8217;s date/time formated as "date @ time".', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_blogurl]</code></dt>
	<dd><?php echo \wp_kses( __( 'The URL to the site.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_blogname]</code></dt>
	<dd><?php echo \wp_kses( __( 'The name of the site.', 'activitypub' ), $code_html ); ?></dd>
	<dt><code>[ap_blogdesc]</code></dt>
	<dd><?php echo \wp_kses( __( 'The description of the site.', 'activitypub' ), $code_html ); ?></dd>
</dl>
<p><?php \esc_html_e( 'You may also use any Shortcode normally available to you on your site, however be aware that Shortcodes may significantly increase the size of your content depending on what they do.', 'activitypub' ); ?></p>
<p><?php \esc_html_e( 'Note: the old Template Tags are now deprecated and automatically converted to the new ones.', 'activitypub' ); ?></p>
<p>
<?php
echo \wp_kses(
	\__( '<a href="https://github.com/automattic/wordpress-activitypub/issues/new" target="_blank">Let us know</a> if you miss a Template Tag.', 'activitypub' ),
	array(
		'a' => array(
			'href'   => array(),
			'target' => array(),
		),
	)
);
?>
</p>
