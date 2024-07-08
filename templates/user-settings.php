<?php
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
$user = \Activitypub\Collection\Users::get_by_id( \get_current_user_id() ); ?>
<h2 id="activitypub"><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h2>

<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></label>
			</th>
			<td>
				<p>
					<code><?php echo \esc_html( $user->get_webfinger() ); ?></code> or
					<code><?php echo \esc_url( $user->get_url() ); ?></code>
				</p>
				<?php // translators: the webfinger resource ?>
				<p class="description"><?php \printf( \esc_html__( 'Follow "@%s" by searching for it on Mastodon, Friendica, etc.', 'activitypub' ), \esc_html( $user->get_webfinger() ) ); ?></p>
			</td>
		</tr>
		<tr class="activitypub-user-description-wrap">
			<th>
				<label for="activitypub-user-description"><?php \esc_html_e( 'Biography', 'activitypub' ); ?></label>
			</th>
			<td>
				<textarea name="activitypub-user-description" id="activitypub-user-description" rows="5" cols="30" placeholder="<?php echo \esc_html( get_user_meta( \get_current_user_id(), 'description', true ) ); ?>"><?php echo \esc_html( $args['description'] ); ?></textarea>
				<p class="description"><?php \esc_html_e( 'If you wish to use different biographical info for the fediverse, enter your alternate bio here.', 'activitypub' ); ?></p>
			</td>
			<?php wp_nonce_field( 'activitypub-user-description', '_apnonce' ); ?>
		</tr>
		<tr scope="row">
			<th>
				<label><?php \esc_html_e( 'Extra fields', 'activitypub' ); ?></label>
			</th>
			<td>
				<p class="description"><?php \esc_html_e( 'Your homepage, social profiles, pronouns, age, anything you want.', 'activitypub' ); ?></p>

				<table class="widefat striped activitypub-extra-fields" role="presentation" style="margin: 15px 0;">
				<?php
					$extra_fields = new WP_Query(
						array(
							'post_type' => 'ap_extrafield',
							'nopaging'  => true,
							'status'    => 'publish',
							'author'    => get_current_user_id(),
						)
					);

					foreach ( $extra_fields->posts as $extra_field ) {
						?>
				<tr>
					<td><?php echo \esc_html( $extra_field->post_title ); ?></td>
					<td><?php echo \wp_kses_post( \get_the_excerpt( $extra_field ) ); ?></td>
					<td>
						<a href="<?php echo \esc_url( \get_edit_post_link( $extra_field->ID ) ); ?>" class="button">
							<?php \esc_html_e( 'Edit', 'activitypub' ); ?>
						</a>
					</td>
				</tr>
				<?php } ?>
				</table>

				<p>
					<a href="<?php echo esc_url( admin_url( '/post-new.php?post_type=ap_extrafield' ) ); ?>" class="button">
						<?php esc_html_e( 'Add new', 'activitypub' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( '/edit.php?post_type=ap_extrafield' ) ); ?>">
						<?php esc_html_e( 'Manage all', 'activitypub' ); ?>
					</a>
				</p>
			</td>
		</tr>
	</tbody>
</table>
