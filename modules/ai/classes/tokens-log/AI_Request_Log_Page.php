<?php
declare( strict_types=1 );
namespace Aiutoma\Modules\Ai\Classes\TokensLog;
/**
 * Admin page for viewing AI request logs.
 *
 * @package WordPress\AI\Logging
 */





defined( 'ABSPATH' ) || exit;

/**
 * Renders the AI Request Logs screen under Tools.
 *
 * @since 1.0.0
 */
class AI_Request_Log_Page {

	/**
	 * Menu slug for the settings screen.
	 */
	private const PAGE_SLUG = 'ai-request-logs';

	/**
	 * Log manager instance.
	 */
	private AI_Request_Log_Manager $manager;

	/**
	 * Constructor.
	 *
	 * @param \WordPress\AI\Logging\AI_Request_Log_Manager $manager Manager dependency.
	 */
	public function __construct( AI_Request_Log_Manager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Registers the Tools page.
	 */
	public function register_menu(): void {
		$page_hook = add_submenu_page(
			'aiutoma',
			__( 'AI Tokens Log', 'aiutoma' ),
			__( 'AI Tokens Log', 'aiutoma' ),
			'manage_options',
			'ai-tokens-log',
			array( $this, 'render_page' ),
			99
		);

		if ( ! $page_hook ) {
			return;
		}

		add_action( "load-{$page_hook}", array( $this, 'on_load' ) );
	}

	/**
	 * Ensures assets are loaded when the page is visited.
	 */
	public function on_load(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueues the React bundle and passes localized data.
	 */
	public function enqueue_assets(): void {
		// Enqueue bundled DataViews styles — copied into build/ by the build step.
		// Falls back to the WP-registered handle if available in a future WP release.
		$dataviews_css = AIUTOMA_PATH . 'modules/ai/assets/tokens-log/dataviews.css';
		if ( ! wp_styles()->query( 'wp-dataviews' ) && file_exists( $dataviews_css ) ) {
			wp_enqueue_style(
				'ai-dataviews',
				AIUTOMA_URL . 'modules/ai/assets/tokens-log/dataviews.css',
				array(),
				(string) filemtime( $dataviews_css )
			);
		}

		$asset_file = AIUTOMA_PATH . 'modules/ai/assets/tokens-log/ai-request-logs.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'dependencies' => array(), 'version' => '1.0.0' );
		
		wp_enqueue_script(
			'aiutoma_request_logs',
			AIUTOMA_URL . 'modules/ai/assets/tokens-log/ai-request-logs.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		
		wp_enqueue_style(
			'aiutoma_request_logs',
			AIUTOMA_URL . 'modules/ai/assets/tokens-log/ai-request-logs.css',
			array( 'wp-components' ),
			$asset['version']
		);

		/*
		 * Explicitly load translations for the `wp-dataviews` script.
		 * The DataViews component ships its own UI strings that are only
		 * inlined by WordPress in block-editor contexts.
		 */
		wp_set_script_translations( 'wp-dataviews', 'default' );

		wp_localize_script(
			'aiutoma_request_logs',
			'aiutomaRequestLogsSettings',
			array(
				'rest'             => array(
					'nonce'  => wp_create_nonce( 'wp_rest' ),
					'root'   => esc_url_raw( rest_url() ),
					'routes' => array(
						'logs'    => 'aiutoma/v1/logs',
						'summary' => 'aiutoma/v1/logs/summary',
						'filters' => 'aiutoma/v1/logs/filters',
					),
				),
				'initialState'     => array(
					'summary' => $this->manager->get_summary( 'day' ),
					'filters' => $this->manager->get_filter_options(),
				),
				'connectorsUrl'    => admin_url( 'options-connectors.php' ),
				'providerMetadata' => $this->get_provider_metadata(),
			)
		);
	}

	/**
	 * Outputs the root DOM node for the React app.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['aiutoma_budget_cap_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aiutoma_budget_cap_nonce'] ) ), 'aiutoma_save_budget_cap' ) ) {
			$budget_cap = isset( $_POST['aiutoma_token_budget_cap'] ) ? intval( $_POST['aiutoma_token_budget_cap'] ) : 0;
			update_option( 'aiutoma_token_budget_cap', $budget_cap );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Budget cap updated.', 'aiutoma' ) . '</p></div>';
		}

		$current_cap = get_option( 'aiutoma_token_budget_cap', 0 );
		?>
		<div class="wrap ai-request-logs">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ai Tokens log', 'aiutoma' ); ?></h1>
			<hr class="wp-header-end">
			
			<div class="aiutoma-token-budget-card">
				<form method="post" action="" class="aiutoma-token-budget-form">
					<?php wp_nonce_field( 'aiutoma_save_budget_cap', 'aiutoma_budget_cap_nonce' ); ?>
					<strong><?php esc_html_e( 'Monthly Token Budget Cap:', 'aiutoma' ); ?></strong>
					<input type="number" name="aiutoma_token_budget_cap" value="<?php echo esc_attr( $current_cap ); ?>" min="0" step="1000" class="aiutoma-token-budget-input">
					<span class="aiutoma-token-budget-desc"><?php esc_html_e( '(Set to 0 to disable. All Agent tools will stop if this token limit is exceeded).', 'aiutoma' ); ?></span>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Cap', 'aiutoma' ); ?></button>
				</form>
				<?php if ( $current_cap > 0 ) : 
					$summary_month = $this->manager->get_summary( 'month' );
					$tokens_used   = isset( $summary_month['total_tokens'] ) ? (int) $summary_month['total_tokens'] : 0;
					$percentage    = min( 100, ( $tokens_used / $current_cap ) * 100 );
					$bar_color     = $percentage > 90 ? '#d63638' : ( $percentage > 75 ? '#dba617' : '#00a32a' );
				?>
				<div class="aiutoma-token-usage-container">
					<strong><?php esc_html_e( 'Current Month Token Usage:', 'aiutoma' ); ?></strong>
					<span class="aiutoma-token-usage-text"><?php echo esc_html( number_format_i18n( $tokens_used ) . ' / ' . number_format_i18n( $current_cap ) . ' (' . round( $percentage, 1 ) . '%)' ); ?></span>
					<div class="aiutoma-token-progress-bar">
						<div class="aiutoma-token-progress-fill" style="width: <?php echo esc_attr( $percentage ); ?>%; background-color: <?php echo esc_attr( $bar_color ); ?>;"></div>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<div id="ai-request-logs-root"></div>
		</div>
		<?php
	}

	/**
	 * Builds the provider metadata payload sent to the React app.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_provider_metadata(): array {
		$providers = array();

		foreach ( wp_get_connectors() as $slug => $connector_data ) {
			if ( ! is_array( $connector_data ) || 'ai_provider' !== ( $connector_data['type'] ?? '' ) ) {
				continue;
			}

			$auth = isset( $connector_data['authentication'] ) && is_array( $connector_data['authentication'] )
				? $connector_data['authentication']
				: array();

			$entry = array(
				'id'   => (string) $slug,
				'name' => isset( $connector_data['name'] ) && is_string( $connector_data['name'] ) ? $connector_data['name'] : (string) $slug,
				'type' => 'none' === ( $auth['method'] ?? '' ) ? 'client' : 'cloud',
			);

			if ( ! empty( $connector_data['logo_url'] ) && is_string( $connector_data['logo_url'] ) ) {
				$entry['logo'] = $connector_data['logo_url'];
			}

			if ( ! empty( $auth['credentials_url'] ) && is_string( $auth['credentials_url'] ) ) {
				$entry['url'] = $auth['credentials_url'];
			}

			$providers[ (string) $slug ] = $entry;
		}

		return $providers;
	}
}
