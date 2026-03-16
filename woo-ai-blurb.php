<?php
/**
 * Plugin Name: Woo AI Blurb
 * Description: Adds a button to the product edit screen to generate a short AI description.
 * Version:     0.2.0
 * Author:      Scott Massey
 * Requires at least: 6.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_AI_Blurb {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_woo_ai_blurb_generate', array( $this, 'ajax_generate' ) );
	}

	/**
	 * Register the meta box on the product edit screen.
	 */
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

	/**
	 * Render the meta box content.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'woo_ai_blurb_generate', 'woo_ai_blurb_nonce' );
		?>
		<p style="margin-top:0;">Generate a short product description using AI.</p>
		<button type="button" id="woo-ai-blurb-btn" class="button button-secondary" style="width:100%;">
			Generate Blurb
		</button>
		<div id="woo-ai-blurb-status" style="margin-top:8px;font-style:italic;color:#666;"></div>

		<script>
		// TODO: wire up the button click to the AJAX handler
		document.getElementById( 'woo-ai-blurb-btn' ).addEventListener( 'click', function() {
			document.getElementById( 'woo-ai-blurb-status' ).textContent = 'Coming soon...';
		} );
		</script>
		<?php
	}

	/**
	 * Enqueue admin scripts on the product edit screen.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on the product edit screen
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( get_post_type() !== 'product' ) {
			return;
		}
		// Script will move to its own file once the AJAX is working
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * AJAX handler — generates the blurb via AI.
	 * API call not wired up yet.
	 */
	public function ajax_generate() {
		check_ajax_referer( 'woo_ai_blurb_generate', 'nonce' );

		$product_title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';

		if ( empty( $product_title ) ) {
			wp_send_json_error( array( 'message' => 'No product title provided.' ) );
		}

		// TODO: call WP AI Services API or OpenAI here
		$blurb = 'Placeholder — API not connected yet.';

		wp_send_json_success( array( 'blurb' => $blurb ) );
	}
}

function woo_ai_blurb_run() {
	new Woo_AI_Blurb();
}
add_action( 'plugins_loaded', 'woo_ai_blurb_run' );
