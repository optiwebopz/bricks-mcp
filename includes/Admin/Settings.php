<?php
/**
 * Admin settings page.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 *
 * Handles the plugin settings page in WordPress admin.
 */
final class Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'bricks-mcp';

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'bricks_mcp_settings';

	/**
	 * Settings option group.
	 *
	 * @var string
	 */
	private const OPTION_GROUP = 'bricks_mcp_settings_group';

	/**
	 * Initialize admin settings.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ], 99 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_ajax_bricks_mcp_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_bricks_mcp_generate_app_password', [ $this, 'ajax_generate_app_password' ] );
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'bricks',
			__( 'MCP Settings', 'bricks-mcp' ),
			__( 'MCP', 'bricks-mcp' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->get_defaults(),
			]
		);

		// General settings section.
		add_settings_section(
			'bricks_mcp_general',
			__( 'General Settings', 'bricks-mcp' ),
			[ $this, 'render_general_section' ],
			self::PAGE_SLUG
		);

		// Enable/disable field.
		add_settings_field(
			'enabled',
			__( 'Enable MCP Server', 'bricks-mcp' ),
			[ $this, 'render_enabled_field' ],
			self::PAGE_SLUG,
			'bricks_mcp_general'
		);

		// Require authentication field.
		add_settings_field(
			'require_auth',
			__( 'Require Authentication', 'bricks-mcp' ),
			[ $this, 'render_require_auth_field' ],
			self::PAGE_SLUG,
			'bricks_mcp_general'
		);

		// Custom base URL field.
		add_settings_field(
			'custom_base_url',
			__( 'Custom Base URL', 'bricks-mcp' ),
			[ $this, 'render_custom_base_url_field' ],
			self::PAGE_SLUG,
			'bricks_mcp_general'
		);

		// Rate limit field.
		add_settings_field(
			'rate_limit_rpm',
			__( 'Rate Limit (requests/minute)', 'bricks-mcp' ),
			[ $this, 'render_rate_limit_rpm_field' ],
			self::PAGE_SLUG,
			'bricks_mcp_general'
		);

		// Dangerous actions field.
		add_settings_field(
			'dangerous_actions',
			__( 'Dangerous Actions', 'bricks-mcp' ),
			[ $this, 'render_dangerous_actions_field' ],
			self::PAGE_SLUG,
			'bricks_mcp_general'
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	private function get_defaults(): array {
		return [
			'enabled'           => true,
			'require_auth'      => true,
			'custom_base_url'   => '',
			'dangerous_actions' => false,
			'rate_limit_rpm'    => 120,
		];
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = [];

		$sanitized['enabled']         = ! empty( $input['enabled'] );
		$sanitized['require_auth']    = ! empty( $input['require_auth'] );
		$sanitized['custom_base_url'] = isset( $input['custom_base_url'] )
			? esc_url_raw( trim( $input['custom_base_url'] ) )
			: '';

		$sanitized['dangerous_actions'] = ! empty( $input['dangerous_actions'] );
		$sanitized['rate_limit_rpm']    = max( 10, min( 1000, (int) ( $input['rate_limit_rpm'] ?? 120 ) ) );

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bricks-mcp' ) );
		}

		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="bricks-mcp-info" style="background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'MCP Server Endpoints', 'bricks-mcp' ); ?></h3>
				<p>
					<strong><?php esc_html_e( 'MCP Endpoint:', 'bricks-mcp' ); ?></strong>
					<code><?php echo esc_html( rest_url( 'bricks-mcp/v1/mcp' ) ); ?></code>
				</p>
				<p class="description">
					<?php esc_html_e( 'This single endpoint handles all MCP protocol communication via JSON-RPC 2.0.', 'bricks-mcp' ); ?>
				</p>
			</div>

			<?php
			// Version card.
			$this->render_version_card();

			// MCP configuration tabs with test connection.
			$this->render_mcp_config();
			?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure general MCP server settings.', 'bricks-mcp' ) . '</p>';
	}

	/**
	 * Render enabled field.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
			<?php esc_html_e( 'Enable the MCP server endpoints', 'bricks-mcp' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When disabled, all MCP endpoints will return a 503 Service Unavailable response.', 'bricks-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render require authentication field.
	 *
	 * @return void
	 */
	public function render_require_auth_field(): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[require_auth]" value="1" <?php checked( ! empty( $settings['require_auth'] ) ); ?>>
			<?php esc_html_e( 'Require user authentication for MCP endpoints', 'bricks-mcp' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, only authenticated users with manage_options capability can access the MCP server.', 'bricks-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render custom base URL field.
	 *
	 * @return void
	 */
	public function render_custom_base_url_field(): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value    = $settings['custom_base_url'] ?? '';
		?>
		<input type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[custom_base_url]"
			value="<?php echo esc_attr( $value ); ?>" class="regular-text"
			placeholder="<?php echo esc_attr( get_site_url() ); ?>">
		<p class="description">
			<?php esc_html_e( 'Override the site URL used in MCP config snippets. Useful for reverse proxies or custom domains. Leave empty to use the default site URL.', 'bricks-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render dangerous actions field.
	 *
	 * @return void
	 */
	public function render_dangerous_actions_field(): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[dangerous_actions]" value="1" <?php checked( ! empty( $settings['dangerous_actions'] ) ); ?>>
			<?php esc_html_e( 'Enable dangerous actions mode', 'bricks-mcp' ); ?>
		</label>
		<div style="margin-top: 10px; padding: 10px 12px; border-left: 4px solid #d63638; background: #fcf0f0;">
			<strong><?php esc_html_e( 'Warning: This enables unrestricted write access', 'bricks-mcp' ); ?></strong>
			<p style="margin: 6px 0 0;">
				<?php esc_html_e( 'When enabled, AI tools can: write to global Bricks settings, execute custom JavaScript on pages, and modify code execution settings. Only enable this on development sites or when running trusted AI agent teams. API keys and secrets remain masked regardless of this setting.', 'bricks-mcp' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render rate limit RPM field.
	 *
	 * @return void
	 */
	public function render_rate_limit_rpm_field(): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$value    = (int) ( $settings['rate_limit_rpm'] ?? 120 );
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rate_limit_rpm]"
			value="<?php echo esc_attr( (string) $value ); ?>" min="10" max="1000" step="10" class="small-text">
		<span><?php esc_html_e( 'requests per minute per user', 'bricks-mcp' ); ?></span>
		<p class="description">
			<?php esc_html_e( 'Maximum number of MCP requests allowed per authenticated user per minute. Default: 120. Increase to 300 for intensive AI building sessions. Applies only when authentication is required.', 'bricks-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueue admin scripts on the settings page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'bricks_page_bricks-mcp' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'bricks-mcp-admin-updates',
			BRICKS_MCP_PLUGIN_URL . 'assets/js/admin-updates.js',
			[],
			BRICKS_MCP_VERSION,
			true
		);

		$current_user = wp_get_current_user();

		wp_localize_script(
			'bricks-mcp-admin-updates',
			'bricksMcpUpdates',
			[
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'bricks_mcp_settings_nonce' ),
				'currentVersion'  => BRICKS_MCP_VERSION,
				'siteUrl'         => get_site_url(),
				'restBase'        => rest_url( 'bricks-mcp/v1/' ),
				'mcpUrl'          => rest_url( 'bricks-mcp/v1/mcp' ),
				'currentUsername' => $current_user->user_login,
				'profileUrl'      => admin_url( 'profile.php' ),
				'updateCoreUrl'   => admin_url( 'update-core.php' ),
			]
		);
	}

	/**
	 * Render the version info card.
	 *
	 * Shows current version, update availability, and a "Check Now" button.
	 *
	 * @return void
	 */
	private function render_version_card(): void {
		$current_version = BRICKS_MCP_VERSION;
		$update_checker  = \BricksMCP\Plugin::get_instance()->get_update_checker();
		$update_data     = null !== $update_checker ? $update_checker->get_cached_update_data() : [];
		$has_update      = ! empty( $update_data['version'] )
			&& version_compare( $current_version, $update_data['version'], '<' );

		$border_color = $has_update ? '#dba617' : '#2271b1';
		?>
		<div class="bricks-mcp-version-card" style="background:#fff;padding:15px 20px;margin:20px 0;border-left:4px solid <?php echo esc_attr( $border_color ); ?>;">
			<h2><?php esc_html_e( 'Version', 'bricks-mcp' ); ?></h2>
			<p id="bricks-mcp-version-text">
				<strong>v<?php echo esc_html( $current_version ); ?></strong>
				<?php if ( $has_update ) : ?>
					&mdash;
					<span style="color:#dba617;font-weight:600;">
						v<?php echo esc_html( $update_data['version'] ); ?>
						<?php esc_html_e( 'available', 'bricks-mcp' ); ?>
					</span>
					<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>">
						<?php esc_html_e( 'Update', 'bricks-mcp' ); ?>
					</a>
				<?php else : ?>
					&mdash; <span style="color:#00a32a;"><?php esc_html_e( 'up to date', 'bricks-mcp' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<button type="button" id="bricks-mcp-check-update-btn" class="button">
					<?php esc_html_e( 'Check Now', 'bricks-mcp' ); ?>
				</button>
				<span id="bricks-mcp-check-update-spinner" class="spinner" style="float:none;"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render MCP configuration tabs with Claude Code and Gemini snippets.
	 *
	 * Includes copy-to-clipboard, brief instructions, and a test connection section.
	 *
	 * @return void
	 */
	private function render_mcp_config(): void {
		$current_user = wp_get_current_user();
		$username     = $current_user->user_login;

		// Build MCP endpoint URL with optional custom base URL override.
		$settings    = get_option( self::OPTION_NAME, $this->get_defaults() );
		$custom_base = $settings['custom_base_url'] ?? '';
		if ( ! empty( $custom_base ) ) {
			$mcp_url = trailingslashit( $custom_base ) . 'wp-json/bricks-mcp/v1/mcp';
		} else {
			$mcp_url = rest_url( 'bricks-mcp/v1/mcp' );
		}

		// Build Claude Code config snippet.
		$claude_config = json_encode(
			[
				'mcpServers' => [
					'bricks-mcp' => [
						'type'    => 'http',
						'url'     => $mcp_url,
						'headers' => [
							'Authorization' => 'Basic YOUR_BASE64_AUTH_STRING',
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		// Build Gemini config snippet.
		$gemini_config = json_encode(
			[
				'mcpServers' => [
					'bricks-mcp' => [
						'httpUrl' => $mcp_url,
						'headers' => [
							'Authorization' => 'Basic YOUR_BASE64_AUTH_STRING',
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		?>
		<div class="bricks-mcp-config-section" style="background:#fff;padding:15px 20px;margin:20px 0;border-left:4px solid #2271b1;">
			<h2><?php esc_html_e( 'MCP Configuration', 'bricks-mcp' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Add the following configuration to your AI tool to connect to this MCP server.', 'bricks-mcp' ); ?></p>

			<!-- Generate Setup Command -->
			<div style="margin:15px 0 20px;padding:15px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Quick Setup', 'bricks-mcp' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Generate an Application Password and get a ready-to-paste setup command.', 'bricks-mcp' ); ?></p>
				<p style="margin-top:10px;">
					<button type="button" id="bricks-mcp-generate-btn" class="button button-primary">
						<?php esc_html_e( 'Generate Setup Command', 'bricks-mcp' ); ?>
					</button>
					<span id="bricks-mcp-generate-spinner" class="spinner" style="float:none;"></span>
				</p>
				<div id="bricks-mcp-generate-error" style="display:none;margin-top:10px;"></div>

				<div id="bricks-mcp-generated-result" style="display:none;margin-top:15px;">
					<div style="padding:10px 12px;border-left:4px solid #dba617;background:#fcf0e8;margin-bottom:15px;">
						<strong><?php esc_html_e( 'Important:', 'bricks-mcp' ); ?></strong>
						<?php esc_html_e( 'This password is shown once. Copy your command now -- it cannot be retrieved later.', 'bricks-mcp' ); ?>
					</div>

					<h4 style="margin:0 0 8px;"><?php esc_html_e( 'Claude Code (one-liner):', 'bricks-mcp' ); ?></h4>
					<div style="position:relative;">
						<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;overflow:auto;margin:0;white-space:pre-wrap;word-break:break-all;"><code id="bricks-mcp-generated-command"></code></pre>
						<button type="button" class="button bricks-mcp-copy-btn" data-target="bricks-mcp-generated-command" style="position:absolute;top:8px;right:8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'bricks-mcp' ); ?>
						</button>
					</div>

					<hr style="margin:15px 0;">

					<h4 style="margin:0 0 8px;"><?php esc_html_e( 'Claude Code (JSON config):', 'bricks-mcp' ); ?></h4>
					<div style="position:relative;">
						<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;overflow:auto;margin:0;"><code id="bricks-mcp-generated-claude-config"></code></pre>
						<button type="button" class="button bricks-mcp-copy-btn" data-target="bricks-mcp-generated-claude-config" style="position:absolute;top:8px;right:8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'bricks-mcp' ); ?>
						</button>
					</div>

					<h4 style="margin:15px 0 8px;"><?php esc_html_e( 'Gemini (JSON config):', 'bricks-mcp' ); ?></h4>
					<div style="position:relative;">
						<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;overflow:auto;margin:0;"><code id="bricks-mcp-generated-gemini-config"></code></pre>
						<button type="button" class="button bricks-mcp-copy-btn" data-target="bricks-mcp-generated-gemini-config" style="position:absolute;top:8px;right:8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'bricks-mcp' ); ?>
						</button>
					</div>
				</div>
			</div>

			<h3 style="margin-top:0;"><?php esc_html_e( 'Manual Setup', 'bricks-mcp' ); ?></h3>

			<div class="bricks-mcp-tabs" style="margin-top:15px;">
				<div style="border-bottom:2px solid #ddd;margin-bottom:15px;">
					<button type="button" data-tab="claude" class="active" style="background:none;border:none;padding:8px 16px;cursor:pointer;font-size:14px;font-weight:600;border-bottom:2px solid #2271b1;margin-bottom:-2px;">
						<?php esc_html_e( 'Claude Code', 'bricks-mcp' ); ?>
					</button>
					<button type="button" data-tab="gemini" style="background:none;border:none;padding:8px 16px;cursor:pointer;font-size:14px;color:#666;border-bottom:2px solid transparent;margin-bottom:-2px;">
						<?php esc_html_e( 'Gemini', 'bricks-mcp' ); ?>
					</button>
				</div>

				<!-- Claude Code Panel -->
				<div data-panel="claude">
					<div style="position:relative;">
						<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;overflow:auto;margin:0;"><code id="bricks-mcp-claude-config"><?php echo esc_html( $claude_config ); ?></code></pre>
						<button type="button" class="button bricks-mcp-copy-btn" data-target="bricks-mcp-claude-config" style="position:absolute;top:8px;right:8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'bricks-mcp' ); ?>
						</button>
					</div>
					<p class="description" style="margin-top:10px;">
						<?php esc_html_e( 'Add this to your .mcp.json file, or use:', 'bricks-mcp' ); ?>
						<code>claude mcp add bricks-mcp <?php echo esc_html( $mcp_url ); ?> --transport http --header "Authorization: Basic ..."</code>
					</p>
					<p class="description">
						<?php
						echo wp_kses(
							__( 'Replace <code>YOUR_BASE64_AUTH_STRING</code> with the Base64-encoded value of <code>username:app_password</code>. Or use the <strong>Generate Setup Command</strong> button above for a ready-to-paste config.', 'bricks-mcp' ),
							[
								'code'   => [],
								'strong' => [],
							]
						);
						?>
					</p>
				</div>

				<!-- Gemini Panel -->
				<div data-panel="gemini" style="display:none;">
					<div style="position:relative;">
						<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:4px;overflow:auto;margin:0;"><code id="bricks-mcp-gemini-config"><?php echo esc_html( $gemini_config ); ?></code></pre>
						<button type="button" class="button bricks-mcp-copy-btn" data-target="bricks-mcp-gemini-config" style="position:absolute;top:8px;right:8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'bricks-mcp' ); ?>
						</button>
					</div>
					<p class="description" style="margin-top:10px;">
						<?php esc_html_e( 'Add this to your ~/.gemini/settings.json file.', 'bricks-mcp' ); ?>
					</p>
					<p class="description">
						<?php
						echo wp_kses(
							__( 'Replace <code>YOUR_BASE64_AUTH_STRING</code> with the Base64-encoded value of <code>username:app_password</code>. Or use the <strong>Generate Setup Command</strong> button above for a ready-to-paste config.', 'bricks-mcp' ),
							[
								'code'   => [],
								'strong' => [],
							]
						);
						?>
					</p>
				</div>
			</div>

			<!-- Test Connection -->
			<div style="margin-top:20px;padding-top:15px;border-top:1px solid #ddd;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Test Connection', 'bricks-mcp' ); ?></h3>
				<p>
					<label for="bricks-mcp-test-username"><?php esc_html_e( 'Username:', 'bricks-mcp' ); ?></label><br>
					<input type="text" id="bricks-mcp-test-username" value="<?php echo esc_attr( $username ); ?>" readonly style="width:300px;background:#f0f0f0;">
				</p>
				<p>
					<label for="bricks-mcp-test-app-password"><?php esc_html_e( 'Application Password:', 'bricks-mcp' ); ?></label><br>
					<input type="password" id="bricks-mcp-test-app-password" placeholder="<?php esc_attr_e( 'Enter your Application Password', 'bricks-mcp' ); ?>" style="width:300px;">
				</p>
				<p>
					<button type="button" id="bricks-mcp-test-connection-btn" class="button">
						<?php esc_html_e( 'Test Connection', 'bricks-mcp' ); ?>
					</button>
					<span id="bricks-mcp-test-spinner" class="spinner" style="float:none;"></span>
				</p>
				<div id="bricks-mcp-test-result" style="margin-top:10px;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Test MCP connection with Application Password.
	 *
	 * Makes a server-side authenticated request to the REST API to verify
	 * both endpoint reachability and Application Password auth.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'bricks_mcp_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'bricks-mcp' ) ], 403 );
		}

		$username     = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		$app_password = sanitize_text_field( wp_unslash( $_POST['app_password'] ?? '' ) );

		if ( empty( $username ) || empty( $app_password ) ) {
			wp_send_json_error( [ 'message' => __( 'Username and Application Password are required.', 'bricks-mcp' ) ] );
		}

		$response = wp_remote_post(
			rest_url( 'bricks-mcp/v1/mcp' ),
			[
				'headers'   => [
					'Authorization' => 'Basic ' . base64_encode( $username . ':' . $app_password ),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json, text/event-stream',
				],
				'body'      => wp_json_encode(
					[
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'initialize',
						'params'  => [
							'protocolVersion' => '2025-03-26',
							'capabilities'    => new \stdClass(),
							'clientInfo'      => [
								'name'    => 'bricks-mcp-test',
								'version' => BRICKS_MCP_VERSION,
							],
						],
					]
				),
				'timeout'   => 10,
				'sslverify' => is_ssl(),
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Endpoint unreachable: %s', 'bricks-mcp' ),
						$response->get_error_message()
					),
				]
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			wp_send_json_error( [ 'message' => __( 'Authentication failed -- check your Application Password.', 'bricks-mcp' ) ] );
		}

		if ( 200 !== $code ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %d: HTTP status code */
						__( 'Unexpected response (HTTP %d).', 'bricks-mcp' ),
						$code
					),
				]
			);
		}

		// Parse SSE response body to extract JSON-RPC result.
		$body = wp_remote_retrieve_body( $response );

		// SSE format: lines starting with "data: " contain the JSON payload.
		$json_rpc_result = null;
		foreach ( explode( "\n", $body ) as $line ) {
			$line = trim( $line );
			if ( str_starts_with( $line, 'data: ' ) ) {
				$json_rpc_result = json_decode( substr( $line, 6 ), true );
				break;
			}
		}

		if ( ! is_array( $json_rpc_result ) || ! isset( $json_rpc_result['result']['protocolVersion'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Unexpected response format.', 'bricks-mcp' ) ] );
		}

		$protocol_version = $json_rpc_result['result']['protocolVersion'];

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %s: protocol version */
					__( 'Connection successful! MCP server is reachable and authenticated (protocol %s).', 'bricks-mcp' ),
					$protocol_version
				),
			]
		);
	}

	/**
	 * AJAX handler: Generate an Application Password and return setup commands.
	 *
	 * Creates a WordPress Application Password for the current user and returns
	 * a complete claude mcp add command with auth headers, plus JSON configs
	 * for Claude Code and Gemini with real credentials.
	 *
	 * @return void
	 */
	public function ajax_generate_app_password(): void {
		check_ajax_referer( 'bricks_mcp_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'bricks-mcp' ) ], 403 );
		}

		$current_user = wp_get_current_user();
		$username     = $current_user->user_login;

		// Create Application Password.
		$result = \WP_Application_Passwords::create_new_application_password(
			$current_user->ID,
			[
				'name'   => 'Bricks MCP - Claude Code',
				'app_id' => wp_generate_uuid4(),
			]
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// $result is [ $password, $item ] -- the raw password is only available at creation time.
		$password = $result[0];

		// Build MCP endpoint URL with optional custom base URL override.
		$settings    = get_option( self::OPTION_NAME, $this->get_defaults() );
		$custom_base = $settings['custom_base_url'] ?? '';
		if ( ! empty( $custom_base ) ) {
			$mcp_url = trailingslashit( $custom_base ) . 'wp-json/bricks-mcp/v1/mcp';
		} else {
			$mcp_url = rest_url( 'bricks-mcp/v1/mcp' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$auth_string = base64_encode( $username . ':' . $password );

		// Build the complete CLI command.
		$claude_command = sprintf(
			'claude mcp add bricks-mcp %s --transport http --header "Authorization: Basic %s"',
			$mcp_url,
			$auth_string
		);

		// Build Claude Code JSON config with real credentials.
		$claude_config = wp_json_encode(
			[
				'mcpServers' => [
					'bricks-mcp' => [
						'type'    => 'http',
						'url'     => $mcp_url,
						'headers' => [
							'Authorization' => 'Basic ' . $auth_string,
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		// Build Gemini JSON config with real credentials.
		$gemini_config = wp_json_encode(
			[
				'mcpServers' => [
					'bricks-mcp' => [
						'httpUrl' => $mcp_url,
						'headers' => [
							'Authorization' => 'Basic ' . $auth_string,
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		wp_send_json_success(
			[
				'password'       => $password,
				'username'       => $username,
				'auth_string'    => $auth_string,
				'claude_command' => $claude_command,
				'claude_config'  => $claude_config,
				'gemini_config'  => $gemini_config,
				'mcp_url'        => $mcp_url,
			]
		);
	}
}
