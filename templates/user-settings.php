<?php $user = \Activitypub\Collection\Users::get_by_id( \get_current_user_id() ); ?>
<h2 id="activitypub"><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h2>

<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label><?php \esc_html_e( 'Profile identifier', 'activitypub' ); ?></label>
			</th>
			<td>
				<p>
					<code><?php echo \esc_html( $user->get_resource() ); ?></code> or
					<code><?php echo \esc_url( $user->get_url() ); ?></code>
				</p>
				<?php // translators: the webfinger resource ?>
				<p class="description"><?php \printf( \esc_html__( 'Try to follow "@%s" by searching for it on Mastodon,Friendica & Co.', 'activitypub' ), \esc_html( $user->get_resource() ) ); ?></p>
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
	</tbody>
</table>
