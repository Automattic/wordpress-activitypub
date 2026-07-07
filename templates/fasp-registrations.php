<?php
/**
 * ActivityPub FASP Registrations template.
 *
 * @package Activitypub
 */

use Activitypub\Fasp\Registrations;

// phpcs:disable WordPress.Security.NonceVerification.Recommended

$pending_registrations  = Registrations::get_by_status( 'pending' );
$approved_registrations = Registrations::get_by_status( 'approved' );
$highlighted_id         = isset( $_GET['highlight'] ) ? \sanitize_text_field( \wp_unslash( $_GET['highlight'] ) ) : '';

/**
 * Filters the FASP capability identifiers this site knows how to use.
 *
 * Capabilities offered by a provider can only be enabled when the site
 * implements their server side. Add an identifier (e.g. `data_sharing`)
 * to make the matching provider capabilities selectable.
 *
 * @since unreleased
 *
 * @param string[] $supported Supported capability identifiers. Default empty.
 */
$supported_capabilities = \apply_filters( 'activitypub_fasp_supported_capabilities', array() );
?>

<hr class="wp-header-end">

<?php \settings_errors( 'activitypub_fasp' ); ?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<p class="description">
		<?php \esc_html_e( 'Auxiliary services are external tools that can integrate with your site to provide additional Fediverse features, such as moderation tools, search indexing, or content discovery services. When a service requests access, you can review and approve it here.', 'activitypub' ); ?>
	</p>

	<?php if ( ! empty( $pending_registrations ) ) : ?>
		<h2><?php \esc_html_e( 'Pending Requests', 'activitypub' ); ?></h2>
		<p class="description">
			<?php \esc_html_e( 'These services have requested access to your site. Review the details carefully before approving. Only approve services you trust.', 'activitypub' ); ?>
		</p>
		<div class="fasp-registrations-list">
			<?php foreach ( $pending_registrations as $registration ) : ?>
				<?php
				$fingerprint = $registration['fasp_public_key_fingerprint'] ?? Registrations::get_public_key_fingerprint( $registration['fasp_public_key'] );
				$nonce       = \wp_create_nonce( 'fasp_registration_' . $registration['fasp_id'] );
				$highlighted = $highlighted_id === $registration['fasp_id'];
				?>
				<div class="activitypub-settings-accordion fasp-registration-card <?php echo $highlighted ? 'highlighted' : ''; ?>">
					<div class="fasp-registration-header">
						<h3 class="fasp-registration-name"><?php echo \esc_html( $registration['name'] ); ?></h3>
						<div class="fasp-registration-actions">
							<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
								<input type="hidden" name="action" value="approve_fasp_registration">
								<input type="hidden" name="fasp_id" value="<?php echo \esc_attr( $registration['fasp_id'] ); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo \esc_attr( $nonce ); ?>">
								<input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Approve', 'activitypub' ); ?>">
							</form>
							<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
								<input type="hidden" name="action" value="reject_fasp_registration">
								<input type="hidden" name="fasp_id" value="<?php echo \esc_attr( $registration['fasp_id'] ); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo \esc_attr( $nonce ); ?>">
								<input type="submit" class="button" value="<?php \esc_attr_e( 'Reject', 'activitypub' ); ?>" onclick="return confirm('<?php echo \esc_js( \__( 'Are you sure you want to reject this request? The service will not be able to access your site.', 'activitypub' ) ); ?>')">
							</form>
						</div>
					</div>

					<div class="fasp-registration-details">
						<div class="fasp-registration-detail">
							<strong><?php \esc_html_e( 'Service URL', 'activitypub' ); ?></strong>
							<a href="<?php echo \esc_url( $registration['base_url'] ); ?>" target="_blank" rel="noopener"><?php echo \esc_html( $registration['base_url'] ); ?></a>
						</div>
						<div class="fasp-registration-detail">
							<strong><?php \esc_html_e( 'Requested', 'activitypub' ); ?></strong>
							<?php echo \esc_html( \wp_date( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), \strtotime( $registration['requested_at'] ) ) ); ?>
						</div>
					</div>

					<details class="fasp-technical-details">
						<summary><?php \esc_html_e( 'Technical Details', 'activitypub' ); ?></summary>
						<div class="fasp-registration-details">
							<div class="fasp-registration-detail">
								<strong><?php \esc_html_e( 'Server ID', 'activitypub' ); ?></strong>
								<code><?php echo \esc_html( $registration['server_id'] ); ?></code>
							</div>
							<div class="fasp-registration-detail">
								<strong><?php \esc_html_e( 'Registration ID', 'activitypub' ); ?></strong>
								<code><?php echo \esc_html( $registration['fasp_id'] ); ?></code>
							</div>
						</div>
						<div class="fasp-registration-detail fasp-registration-fingerprint">
							<strong><?php \esc_html_e( 'Security Fingerprint', 'activitypub' ); ?></strong>
							<p class="description"><?php \esc_html_e( 'This unique identifier verifies the service\'s identity. If you\'re in contact with the service provider, you can ask them to confirm this fingerprint matches.', 'activitypub' ); ?></p>
							<code class="fasp-fingerprint"><?php echo \esc_html( $fingerprint ); ?></code>
						</div>
					</details>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $approved_registrations ) ) : ?>
		<h2><?php \esc_html_e( 'Connected Services', 'activitypub' ); ?></h2>
		<p class="description">
			<?php \esc_html_e( 'These services are currently connected to your site. You can disconnect a service at any time by deleting it.', 'activitypub' ); ?>
		</p>
		<div class="fasp-registrations-list">
			<?php foreach ( $approved_registrations as $registration ) : ?>
				<?php
				$fingerprint = $registration['fasp_public_key_fingerprint'] ?? Registrations::get_public_key_fingerprint( $registration['fasp_public_key'] );
				$nonce       = \wp_create_nonce( 'fasp_registration_' . $registration['fasp_id'] );
				?>
				<div class="activitypub-settings-accordion fasp-registration-card">
					<div class="fasp-registration-header">
						<h3 class="fasp-registration-name"><?php echo \esc_html( $registration['name'] ); ?></h3>
						<div class="fasp-registration-actions">
							<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
								<input type="hidden" name="action" value="delete_fasp_registration">
								<input type="hidden" name="fasp_id" value="<?php echo \esc_attr( $registration['fasp_id'] ); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo \esc_attr( $nonce ); ?>">
								<input type="submit" class="button button-link-delete" value="<?php \esc_attr_e( 'Disconnect', 'activitypub' ); ?>" onclick="return confirm('<?php echo \esc_js( \__( 'Are you sure you want to disconnect this service? It will no longer have access to your site.', 'activitypub' ) ); ?>')">
							</form>
						</div>
					</div>

					<div class="fasp-registration-details">
						<div class="fasp-registration-detail">
							<strong><?php \esc_html_e( 'Service URL', 'activitypub' ); ?></strong>
							<a href="<?php echo \esc_url( $registration['base_url'] ); ?>" target="_blank" rel="noopener"><?php echo \esc_html( $registration['base_url'] ); ?></a>
						</div>
						<div class="fasp-registration-detail">
							<strong><?php \esc_html_e( 'Connected Since', 'activitypub' ); ?></strong>
							<?php echo \esc_html( \wp_date( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), \strtotime( $registration['approved_at'] ) ) ); ?>
						</div>
					</div>

					<div class="fasp-registration-capabilities">
						<strong><?php \esc_html_e( 'Capabilities', 'activitypub' ); ?></strong>
						<p class="description">
							<?php \esc_html_e( 'Features this service offers. Enable only the ones you want it to use; the service is notified about every change.', 'activitypub' ); ?>
						</p>
						<?php
						// Read the persisted copy on render: a slow or unreachable provider must never block the page.
						$provider_info = ( isset( $registration['provider_info'] ) && \is_array( $registration['provider_info'] ) ) ? $registration['provider_info'] : null;
						?>
						<?php if ( null === $provider_info ) : ?>
							<p class="description fasp-capabilities-error">
								<?php \esc_html_e( 'The list of capabilities has not been loaded from the service yet.', 'activitypub' ); ?>
							</p>
							<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
								<input type="hidden" name="action" value="refresh_fasp_provider_info">
								<input type="hidden" name="fasp_id" value="<?php echo \esc_attr( $registration['fasp_id'] ); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo \esc_attr( $nonce ); ?>">
								<input type="submit" class="button" value="<?php \esc_attr_e( 'Load Capabilities', 'activitypub' ); ?>">
							</form>
						<?php else : ?>
							<ul class="fasp-capabilities-list">
								<?php foreach ( $provider_info['capabilities'] as $capability ) : ?>
									<?php
									if ( empty( $capability['id'] ) || ! isset( $capability['version'] ) ) {
										continue;
									}

									$capability_id      = $capability['id'];
									$capability_version = (string) $capability['version'];
									$is_supported       = \in_array( $capability_id, $supported_capabilities, true );
									$is_enabled         = Registrations::is_capability_enabled( $registration['fasp_id'], $capability_id, $capability_version );
									$capability_nonce   = \wp_create_nonce( 'fasp_capability_' . $registration['fasp_id'] );
									?>
									<li class="fasp-capability">
										<code><?php echo \esc_html( $capability_id ); ?></code>
										<span class="fasp-capability-version">
											<?php
											/* translators: %s: version number of a service capability. */
											echo \esc_html( \sprintf( \__( 'Version %s', 'activitypub' ), $capability_version ) );
											?>
										</span>
										<?php if ( ! $is_supported ) : ?>
											<em class="description"><?php \esc_html_e( 'Not yet supported by this plugin.', 'activitypub' ); ?></em>
										<?php else : ?>
											<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
												<input type="hidden" name="action" value="toggle_fasp_capability">
												<input type="hidden" name="fasp_id" value="<?php echo \esc_attr( $registration['fasp_id'] ); ?>">
												<input type="hidden" name="identifier" value="<?php echo \esc_attr( $capability_id ); ?>">
												<input type="hidden" name="version" value="<?php echo \esc_attr( $capability_version ); ?>">
												<input type="hidden" name="enable" value="<?php echo $is_enabled ? '0' : '1'; ?>">
												<input type="hidden" name="_wpnonce" value="<?php echo \esc_attr( $capability_nonce ); ?>">
												<?php if ( $is_enabled ) : ?>
													<input type="submit" class="button" value="<?php \esc_attr_e( 'Disable', 'activitypub' ); ?>">
												<?php else : ?>
													<input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Enable', 'activitypub' ); ?>">
												<?php endif; ?>
											</form>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<details class="fasp-technical-details">
						<summary><?php \esc_html_e( 'Technical Details', 'activitypub' ); ?></summary>
						<div class="fasp-registration-details">
							<div class="fasp-registration-detail">
								<strong><?php \esc_html_e( 'Server ID', 'activitypub' ); ?></strong>
								<code><?php echo \esc_html( $registration['server_id'] ); ?></code>
							</div>
							<div class="fasp-registration-detail">
								<strong><?php \esc_html_e( 'Registration ID', 'activitypub' ); ?></strong>
								<code><?php echo \esc_html( $registration['fasp_id'] ); ?></code>
							</div>
						</div>
						<div class="fasp-registration-detail fasp-registration-fingerprint">
							<strong><?php \esc_html_e( 'Security Fingerprint', 'activitypub' ); ?></strong>
							<p class="description"><?php \esc_html_e( 'This unique identifier verifies the service\'s identity.', 'activitypub' ); ?></p>
							<code class="fasp-fingerprint"><?php echo \esc_html( $fingerprint ); ?></code>
						</div>
					</details>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( empty( $pending_registrations ) && empty( $approved_registrations ) ) : ?>
		<div class="fasp-empty-state">
			<p><?php \esc_html_e( 'No auxiliary services have requested access to your site yet.', 'activitypub' ); ?></p>
			<p class="description"><?php \esc_html_e( 'When a Fediverse service wants to integrate with your site, it will appear here for your approval.', 'activitypub' ); ?></p>
		</div>
	<?php endif; ?>
</div>
