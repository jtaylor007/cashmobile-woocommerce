<?php
/**
 * Makes the gateway visible in the BLOCK checkout.
 *
 * WHY THIS FILE EXISTS.
 *
 * WooCommerce has two checkouts. The classic one renders whatever
 * `WC_Payment_Gateway` returns; the block one renders only payment methods that
 * registered themselves with its JavaScript registry. A gateway can therefore be
 * perfectly available server-side — `is_available()` true, listed by
 * `get_available_payment_gateways()` — and still be invisible to the buyer.
 *
 * That is exactly what happened on the demo store, and it was not a demo
 * problem: the block checkout is what a NEW WooCommerce store gets by default.
 * Without this, the plugin would install cleanly, report itself as active, and
 * show nothing at the checkout of most merchants.
 *
 * NO BUILD STEP.
 *
 * The script is plain JavaScript reading `window.wc.wcBlocksRegistry` and
 * `window.wp.element`. A plugin distributed as a zip should not require npm to
 * be rebuilt, and a compiled bundle in the archive cannot be read in review.
 */

if (! defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class CashMobile_WC_Blocks extends AbstractPaymentMethodType
{
    /** Must match the gateway id, or the block cannot pair the two. */
    protected $name = 'cashmobile';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_cashmobile_settings', []);
    }

    /**
     * The block asks the GATEWAY, not the settings.
     *
     * Reading `enabled` from the settings array would ignore every other reason
     * a gateway hides itself — incomplete keys, above all. The buyer would then
     * see a method that cannot start a payment.
     */
    public function is_active()
    {
        $passerelles = WC()->payment_gateways()->payment_gateways();

        return isset($passerelles[$this->name]) && $passerelles[$this->name]->is_available();
    }

    public function get_payment_method_script_handles()
    {
        $poignee = 'cashmobile-blocks';

        wp_register_script(
            $poignee,
            plugins_url('assets/js/blocks.js', dirname(__FILE__)),
            ['wc-blocks-registry', 'wp-element', 'wp-html-entities', 'wp-i18n'],
            CASHMOBILE_WC_VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations($poignee, 'cashmobile-gateway-for-woocommerce');
        }

        return [$poignee];
    }

    /**
     * What the script needs to render the tile.
     */
    public function get_payment_method_data()
    {
        $passerelles = WC()->payment_gateways()->payment_gateways();
        $passerelle = $passerelles[$this->name] ?? null;

        return [
            'title'       => $passerelle ? $passerelle->get_option('title') : 'CashMobile',
            'description' => $passerelle ? $passerelle->get_option('description') : '',
            'icon'        => $passerelle ? $passerelle->icon : '',
            // Un paiement par redirection : le bloc n'a aucun champ a valider,
            // et il doit le savoir pour ne pas attendre de saisie.
            'supports'    => ['products'],
        ];
    }
}
