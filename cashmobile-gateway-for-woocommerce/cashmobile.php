<?php
/**
 * Plugin Name: CashMobile Gateway for WooCommerce
 * Plugin URI: https://cashmobile.net/developer
 * Description: Accept payments on your WooCommerce store through CashMobile.
 * Version: 2.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: CashMobile
 * Author URI: https://cashmobile.net
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cashmobile-gateway-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 *
 * ---------------------------------------------------------------------------
 * WHY VERSION 2.0.0 AND NOT 1.1.1
 *
 * Versions before 2.0.0 marked WooCommerce orders as completed on the strength
 * of the return redirect alone — they never asked the gateway whether the
 * payment had actually succeeded. Since our gateway sends the payer back to the
 * return URL on FAILURE as well, a declined payment completed the order and
 * released the goods.
 *
 * That is a behavioural break, not a patch: a store upgrading from an earlier
 * version gets different — correct — behaviour, and the major version says so.
 * ---------------------------------------------------------------------------
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CASHMOBILE_WC_VERSION', '2.1.1');
define('CASHMOBILE_WC_FILE', __FILE__);
define('CASHMOBILE_WC_PATH', plugin_dir_path(__FILE__));

/**
 * Declare compatibility with WooCommerce's High-Performance Order Storage.
 *
 * Without this declaration WooCommerce shows the store owner an
 * "incompatible plugin" warning and may refuse to enable HPOS. This plugin
 * only ever touches orders through the CRUD API, so it is compatible.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            CASHMOBILE_WC_FILE,
            true
        );
    }
});

/**
 * Tell the store owner plainly when WooCommerce is missing.
 *
 * The previous plugin returned silently, leaving someone to wonder why the
 * payment method never appeared.
 */
add_action('admin_notices', function () {
    if (class_exists('WooCommerce')) {
        return;
    }

    echo '<div class="notice notice-error"><p>'
        . esc_html__('CashMobile Gateway for WooCommerce needs WooCommerce to be installed and active.', 'cashmobile-gateway-for-woocommerce')
        . '</p></div>';
});

add_action('plugins_loaded', function () {
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once CASHMOBILE_WC_PATH . 'includes/class-cashmobile-gateway.php';

    add_filter('woocommerce_payment_gateways', function ($methods) {
        $methods[] = 'CashMobile_WC_Gateway';

        return $methods;
    });
});

/**
 * The BLOCK checkout registers payment methods separately.
 *
 * A gateway can be available server-side and still be invisible to the buyer:
 * the block checkout renders only what registered with its own registry, and it
 * is what a new WooCommerce store gets by default. Without this hook the plugin
 * installs, reports itself active, and shows nothing at the checkout.
 */
add_action('woocommerce_blocks_loaded', function () {
    if (! class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        return;
    }

    require_once CASHMOBILE_WC_PATH . 'includes/class-cashmobile-blocks.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        /*
         | AUCUN TYPE DECLARE sur le parametre, volontairement.
         |
         | La classe du registre a change de namespace entre les versions de
         | WooCommerce — `Blocks\Registry\PaymentMethodRegistry` dans
         | certaines, `Blocks\Payments\PaymentMethodRegistry` dans d'autres.
         | Un type declare couple donc le plugin a une version precise, et
         | l'erreur qui en resulte est FATALE : elle tombe pendant
         | `wp-settings.php` et emporte tout le site, pas seulement la caisse.
         */
        function ($registre) {
            if (is_object($registre) && method_exists($registre, 'register')) {
                $registre->register(new CashMobile_WC_Blocks());
            }
        }
    );
});

/**
 * A direct link to the settings from the plugins list.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $reglages = admin_url('admin.php?page=wc-settings&tab=checkout&section=cashmobile');

    array_unshift(
        $links,
        '<a href="' . esc_url($reglages) . '">' . esc_html__('Settings', 'cashmobile-gateway-for-woocommerce') . '</a>'
    );

    return $links;
});
