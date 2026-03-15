<?php

/**
 * Plugin Name: Woo AI Blurb
 * Description: Adds a button to the product edit screen to generate a short AI description.
 * Version:     0.1.0
 * Author:      Scott Massey
 * Requires at least: 6.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 */

if (! defined('ABSPATH')) {
  exit;
}

/**
 * Main plugin class.
 * Hooks into the WooCommerce product edit screen to add AI description generation.
 */
class Woo_AI_Blurb {

  public function __construct() {
    add_action('init', array($this, 'init'));
  }

  public function init() {
    // TODO: register meta box
    // TODO: enqueue admin scripts
    // TODO: register AJAX handler
  }
}

/**
 * Boot the plugin after all plugins are loaded so WooCommerce is available.
 */
function woo_ai_blurb_run() {
  new Woo_AI_Blurb();
}
add_action('plugins_loaded', 'woo_ai_blurb_run');
