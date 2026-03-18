<?php
/**
 * Plugin Name: Woo AI Blurb
 * Description: Adds a button to the product edit screen to generate a short AI description.
 * Version:     1.0.0
 * Author:      Scott Massey
 * Requires at least: 6.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WOO_AI_BLURB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_AI_BLURB_URL',  plugin_dir_url( __FILE__ ) );

class Woo_AI_Blurb {

	const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';
	const ANTHROPIC_MODEL   = 'claude-sonnet-4-20250514';
	const MAX_TOKENS        = 150;

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		add_action( 'add_meta_boxes',            array( $this, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts',     array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_woo_ai_blurb_generate', array( $this, 'ajax_generate' ) );
		add_action( 'admin_menu',                array( $this, 'register_settings_page' ) );
		add_action( 'admin_init',                array( $this, 'register_settings' ) );
	}

	// -------------------------------------------------------------------------
	// Meta box
	// -------------------------------------------------------------------------

	public function register_meta_box() {
		add_meta_box(
			'woo-ai-blurb',
			'AI Product Blurb',
			array( $this, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'woo_ai_blurb_generate', 'woo_ai_blurb_nonce' );
		?>
		<p style="margin-top:0;">Generate a short product description using AI.</p>
		<button type="button" id="woo-ai-blurb-btn" class="button button-secondary" style="width:100%;">
			Generate Blurb
		</button>
		<div id="woo-ai-blurb-status" style="margin-top:8px;font-style:italic;color:#666;"></div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Scripts
	// -------------------------------------------------------------------------

	public function enqueue_scripts( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( get_post_type() !== 'product' ) {
			return;
		}

		wp_enqueue_script(
			'woo-ai-blurb-admin',
			WOO_AI_BLURB_URL . 'assets/admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script( 'woo-ai-blurb-admin', 'wooAiBlurb', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'woo_ai_blurb_generate' ),
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	public function ajax_generate() {
		check_ajax_referer( 'woo_ai_blurb_generate', 'nonce' );

		$product_id    = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product_title = isset( $_POST['title'] )      ? sanitize_text_field( $_POST['title'] ) : '';
		$category      = isset( $_POST['category'] )   ? sanitize_text_field( $_POST['category'] ) : '';

		if ( empty( $product_title ) ) {
			wp_send_json_error( array( 'message' => 'No product title found.' ) );
		}

		$blurb = $this->generate_blurb( $product_title, $category );

		if ( is_wp_error( $blurb ) ) {
			wp_send_json_error( array( 'message' => $blurb->get_error_message() ) );
		}

		wp_send_json_success( array( 'blurb' => $blurb ) );
	}

	// -------------------------------------------------------------------------
	// AI generation — tries WP AI Services first, falls back to Claude API
	// -------------------------------------------------------------------------

	private function generate_blurb( $title, $category ) {
		$prompt = $this->build_prompt( $title, $category );

		// Try the WordPress AI Services API first (requires the ai-services plugin)
		if ( function_exists( 'ai_services' ) ) {
			$result = $this->generate_via_wp_ai_services( $prompt );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Fall back to direct Claude API call
		return $this->generate_via_claude( $prompt );
	}

	/**
	 * Use the WordPress AI Services API.
	 * @see https://wordpress.org/plugins/ai-services/
	 */
	private function generate_via_wp_ai_services( $prompt ) {
		try {
			$service  = ai_services()->get_available_service();
			$response = $service
				->get_model( array( 'feature' => 'woo-ai-blurb' ) )
				->generate_text( $prompt );

			$candidates = $response->get_candidates();
			if ( empty( $candidates ) ) {
				return new WP_Error( 'ai_empty', 'No response from AI Services.' );
			}

			$parts = $candidates[0]->get_content()->get_parts();
			return $parts[0]->get_text();

		} catch ( Exception $e ) {
			return new WP_Error( 'ai_services_error', $e->getMessage() );
		}
	}

	/**
	 * Direct call to the Anthropic Claude API using the stored API key.
	 */
	private function generate_via_claude( $prompt ) {
		$api_key = get_option( 'woo_ai_blurb_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', 'No API key set. Add one in Settings → AI Blurb.' );
		}

		$response = wp_remote_post(
			self::ANTHROPIC_API_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body' => wp_json_encode( array(
					'model'      => self::ANTHROPIC_MODEL,
					'max_tokens' => self::MAX_TOKENS,
					'system'     => 'You are a world-class eCommerce copywriter. Write punchy, benefit-led product descriptions. Output only the description text — no title, no bullet points, no preamble.',
					'messages'   => array(
						array( 'role' => 'user', 'content' => $prompt ),
					),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'API error ' . $code;
			return new WP_Error( 'claude_api_error', $msg );
		}

		if ( empty( $body['content'][0]['text'] ) ) {
			return new WP_Error( 'claude_empty', 'Empty response from Claude.' );
		}

		return trim( $body['content'][0]['text'] );
	}

	/**
	 * Build the prompt from product data.
	 */
	private function build_prompt( $title, $category ) {
		$category_line = $category ? " It is in the {$category} category." : '';
		return "Write a punchy 2-sentence product description for an online store. "
			. "Product name: {$title}.{$category_line} "
			. "Keep it customer-facing, benefit-led, and under 40 words. No bullet points.";
	}

	// -------------------------------------------------------------------------
	// Settings page
	// -------------------------------------------------------------------------

	public function register_settings_page() {
		add_options_page(
			'AI Blurb Settings',
			'AI Blurb',
			'manage_options',
			'woo-ai-blurb',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'woo_ai_blurb', 'woo_ai_blurb_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>AI Blurb Settings</h1>
			<p>Used as a fallback if the <a href="https://wordpress.org/plugins/ai-services/" target="_blank">WP AI Services plugin</a> is not active.</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'woo_ai_blurb' );
				do_settings_sections( 'woo_ai_blurb' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="woo_ai_blurb_api_key">Anthropic API Key</label></th>
						<td>
							<input
								type="password"
								id="woo_ai_blurb_api_key"
								name="woo_ai_blurb_api_key"
								value="<?php echo esc_attr( get_option( 'woo_ai_blurb_api_key' ) ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description">Your key from <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>. Used when WP AI Services is not active.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

function woo_ai_blurb_run() {
	new Woo_AI_Blurb();
}
add_action( 'plugins_loaded', 'woo_ai_blurb_run' );
