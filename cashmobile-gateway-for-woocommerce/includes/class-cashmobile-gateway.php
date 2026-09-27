<?php
/**
 * The CashMobile payment gateway for WooCommerce.
 *
 * THE DEFECT THIS CLASS EXISTS TO FIX.
 *
 * Versions before 2.0.0 confirmed a payment by comparing a token in the return
 * URL against one stored on the order, and then calling
 * `update_status('completed')`. They never asked the gateway anything.
 *
 * CashMobile sends the payer back to the return URL on FAILURE as well, with
 * `type=error` in the query string. A declined payment therefore completed the
 * order and released the goods. No attacker was needed: it happened on every
 * failed payment.
 *
 * Here, the return handler asks the gateway. Nothing else completes an order.
 *
 * THREE SMALLER THINGS ALSO PUT RIGHT.
 *
 *  - The API ACCESS TOKEN was used as the return-URL secret, so a credential
 *    travelled through the customer's browser history, the referrer header and
 *    the store's log files. A secret generated per order is used instead, and
 *    it is good for nothing but matching that one return.
 *  - `update_status('completed')` skipped `payment_complete()`, so stock was
 *    never reduced and a physical order jumped straight to "shipped".
 *  - An HTTP library was bundled inside the plugin (1.4 MB, never updated,
 *    liable to clash with another plugin's copy of it). WordPress ships an HTTP
 *    client; we use that.
 */

if (! defined('ABSPATH')) {
    exit;
}

class CashMobile_WC_Gateway extends WC_Payment_Gateway
{
    /** Meta key holding the return secret for one order. */
    private const META_SECRET = '_cashmobile_return_secret';

    /** Meta key holding the gateway's payment token for one order. */
    private const META_JETON = '_cashmobile_payment_token';

    public function __construct()
    {
        $this->id                 = 'cashmobile';
        $this->has_fields         = false;
        $this->method_title       = esc_html__('CashMobile', 'cashmobile-gateway-for-woocommerce');
        $this->method_description = esc_html__('Accept payments through CashMobile. Your buyer pays on a CashMobile page and comes back to your store.', 'cashmobile-gateway-for-woocommerce');

        $icone       = $this->get_option('custom_icon');
        $this->icon  = ! empty($icone)
            ? $icone
            : apply_filters('cashmobile_gateway_icon', plugins_url('assets/icon.png', dirname(__FILE__)));

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_cashmobile_gateway', [$this, 'traiter_le_retour']);
        add_action('admin_enqueue_scripts', [$this, 'scripts_admin']);
        add_action('wp_enqueue_scripts', [$this, 'styles_boutique']);
    }

    // -----------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => esc_html__('Enable/Disable', 'cashmobile-gateway-for-woocommerce'),
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable CashMobile', 'cashmobile-gateway-for-woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => esc_html__('Title', 'cashmobile-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => esc_html__('What the buyer sees at checkout.', 'cashmobile-gateway-for-woocommerce'),
                'default'     => esc_html__('CashMobile', 'cashmobile-gateway-for-woocommerce'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'   => esc_html__('Description', 'cashmobile-gateway-for-woocommerce'),
                'type'    => 'textarea',
                'default' => esc_html__('Pay with CashMobile.', 'cashmobile-gateway-for-woocommerce'),
            ],

            /*
             | ONE SITE FIELD AND A MODE, RATHER THAN A FREE-TEXT BASE URL.
             |
             | Earlier versions asked for the full base URL by hand, which made the
             | commonest integration mistake possible: a sandbox key against the
             | production URL, refused by the gateway with a message that says
             | nothing about which half is wrong. Here the mode picks the path,
             | so the two cannot disagree.
             */
            'site_url' => [
                'title'       => esc_html__('CashMobile address', 'cashmobile-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => esc_html__('Without a trailing slash, for example https://cashmobile.net', 'cashmobile-gateway-for-woocommerce'),
                'default'     => 'https://cashmobile.net',
                'desc_tip'    => true,
            ],
            'mode' => [
                'title'       => esc_html__('Mode', 'cashmobile-gateway-for-woocommerce'),
                'type'        => 'select',
                'description' => esc_html__('Use Sandbox with sandbox keys while you integrate. Switch both together.', 'cashmobile-gateway-for-woocommerce'),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => [
                    'sandbox' => esc_html__('Sandbox (test)', 'cashmobile-gateway-for-woocommerce'),
                    'live'    => esc_html__('Live', 'cashmobile-gateway-for-woocommerce'),
                ],
            ],
            'client_id' => [
                'title' => esc_html__('Client ID', 'cashmobile-gateway-for-woocommerce'),
                'type'  => 'text',
            ],
            'secret_id' => [
                'title' => esc_html__('Secret ID', 'cashmobile-gateway-for-woocommerce'),
                'type'  => 'password',
            ],
            'debug' => [
                'title'       => esc_html__('Logging', 'cashmobile-gateway-for-woocommerce'),
                'type'        => 'checkbox',
                'label'       => esc_html__('Log gateway events to WooCommerce → Status → Logs', 'cashmobile-gateway-for-woocommerce'),
                'description' => esc_html__('Keys and tokens are never written to the log.', 'cashmobile-gateway-for-woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ],
            'custom_icon' => [
                'title'             => esc_html__('Custom icon', 'cashmobile-gateway-for-woocommerce'),
                'type'              => 'text',
                'description'       => esc_html__('Optional. Replaces the CashMobile logo at checkout.', 'cashmobile-gateway-for-woocommerce'),
                'default'           => '',
                'css'               => 'width: 300px;',
                'class'             => 'cashmobile-icon-upload',
                'custom_attributes' => ['readonly' => 'readonly'],
            ],
        ];
    }

    /**
     * Refuse to switch on with settings that cannot work.
     *
     * Saving a half-configured gateway leaves the store showing a payment
     * method that fails at the checkout — the buyer discovers the problem, not
     * the owner.
     */
    public function process_admin_options()
    {
        $enregistre = parent::process_admin_options();

        if ($this->get_option('enabled') !== 'yes') {
            return $enregistre;
        }

        $manquants = [];

        foreach (['site_url', 'client_id', 'secret_id'] as $champ) {
            if (trim((string) $this->get_option($champ)) === '') {
                $manquants[] = $champ;
            }
        }

        if ($manquants !== []) {
            $this->update_option('enabled', 'no');

            WC_Admin_Settings::add_error(
                esc_html__('CashMobile was left disabled: fill in the address, the Client ID and the Secret ID first.', 'cashmobile-gateway-for-woocommerce')
            );
        }

        return $enregistre;
    }

    public function is_available()
    {
        if ('yes' !== $this->get_option('enabled')) {
            return false;
        }

        return trim((string) $this->get_option('site_url')) !== ''
            && trim((string) $this->get_option('client_id')) !== ''
            && trim((string) $this->get_option('secret_id')) !== '';
    }

    // -----------------------------------------------------------------
    // Paying
    // -----------------------------------------------------------------

    public function process_payment($order_id)
    {
        $commande = wc_get_order($order_id);

        if (! $commande) {
            wc_add_notice(esc_html__('This order could not be found.', 'cashmobile-gateway-for-woocommerce'), 'error');

            return ['result' => 'fail', 'redirect' => ''];
        }

        $this->journal(sprintf('Order %d: starting payment.', $order_id));

        /*
         | A SECRET PER ORDER, generated here and never sent anywhere except in
         | the return URL. It proves "this return belongs to this order"; it
         | proves nothing about payment, which is what the gateway is asked.
         */
        $secret = wp_generate_password(40, false, false);
        $commande->update_meta_data(self::META_SECRET, $secret);
        $commande->save();

        $jeton_acces = $this->demander_un_jeton();

        if (is_wp_error($jeton_acces)) {
            $this->echouer($jeton_acces->get_error_message(), $order_id);

            return ['result' => 'fail', 'redirect' => ''];
        }

        $paiement = $this->ouvrir_le_paiement($jeton_acces, $commande, $secret);

        if (is_wp_error($paiement)) {
            $this->echouer($paiement->get_error_message(), $order_id);

            return ['result' => 'fail', 'redirect' => ''];
        }

        $commande->update_meta_data(self::META_JETON, $paiement['token']);
        $commande->save();

        $this->journal(sprintf('Order %d: redirecting the buyer to CashMobile.', $order_id));

        return [
            'result'   => 'success',
            'redirect' => $paiement['payment_url'],
        ];
    }

    /**
     * THE RETURN HANDLER — and the only place an order becomes paid.
     */
    public function traiter_le_retour()
    {
        $order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
        $recu     = isset($_GET['cm_secret']) ? sanitize_text_field(wp_unslash($_GET['cm_secret'])) : '';

        $commande = $order_id ? wc_get_order($order_id) : null;

        if (! $commande) {
            $this->journal(sprintf('Return for an unknown order: %d.', $order_id));
            wp_safe_redirect(wc_get_page_permalink('shop'));
            exit;
        }

        $attendu = (string) $commande->get_meta(self::META_SECRET);

        // `hash_equals` : une comparaison de secret ne doit pas fuir sa
        // progression par le temps qu'elle prend.
        if ($attendu === '' || ! hash_equals($attendu, $recu)) {
            $this->journal(sprintf('Order %d: the return secret does not match.', $order_id));
            wp_safe_redirect($commande->get_checkout_payment_url());
            exit;
        }

        /*
         | ALREADY PAID? Do nothing and send them on.
         |
         | A buyer who refreshes the return page, or a browser that replays it,
         | must not reduce stock twice or fire the paid hooks twice.
         */
        if ($commande->is_paid()) {
            wp_safe_redirect($this->get_return_url($commande));
            exit;
        }

        $etat = $this->lire_le_statut((string) $commande->get_meta(self::META_JETON));

        if (is_wp_error($etat)) {
            /*
             | WE COULD NOT REACH THE GATEWAY. The order is left exactly as it
             | is — neither paid nor failed. Marking it failed here would cancel
             | an order the buyer may well have paid for; the truth is simply
             | not known yet, and the note says so.
             */
            $this->journal(sprintf('Order %d: status unavailable (%s). Left untouched.', $order_id, $etat->get_error_message()));

            $commande->add_order_note(
                esc_html__('CashMobile: the payment status could not be read. The order has been left as it is — check it in your CashMobile dashboard before cancelling.', 'cashmobile-gateway-for-woocommerce')
            );

            wc_add_notice(
                esc_html__('We could not confirm your payment yet. If you have paid, your order will be updated — please do not pay twice.', 'cashmobile-gateway-for-woocommerce'),
                'notice'
            );

            wp_safe_redirect($this->get_return_url($commande));
            exit;
        }

        if (empty($etat['paid'])) {
            /*
             | NOT PAID. This is the case the old plugin completed.
             */
            $this->journal(sprintf('Order %d: not paid (status %s).', $order_id, (string) ($etat['status'] ?? 'unknown')));

            $commande->update_status(
                'failed',
                sprintf(
                    /* translators: %s: the status reported by CashMobile. */
                    esc_html__('CashMobile reported the payment as %s.', 'cashmobile-gateway-for-woocommerce'),
                    (string) ($etat['status'] ?? 'unknown')
                )
            );

            wc_add_notice(esc_html__('Your payment was not completed. You can try again.', 'cashmobile-gateway-for-woocommerce'), 'error');
            wp_safe_redirect($commande->get_checkout_payment_url());
            exit;
        }

        /*
         | PAID. `payment_complete()` rather than `update_status('completed')`:
         | it records the transaction id, reduces stock, and lets WooCommerce
         | pick the right status for what was sold — a physical order becomes
         | "processing", not "shipped".
         */
        $commande->payment_complete((string) ($etat['trx_id'] ?? ''));
        $commande->add_order_note(
            sprintf(
                /* translators: %s: the CashMobile transaction identifier. */
                esc_html__('Paid through CashMobile. Transaction: %s', 'cashmobile-gateway-for-woocommerce'),
                (string) ($etat['trx_id'] ?? '—')
            )
        );

        $this->journal(sprintf('Order %d: paid and completed.', $order_id));

        wp_safe_redirect($this->get_return_url($commande));
        exit;
    }

    // -----------------------------------------------------------------
    // The gateway
    // -----------------------------------------------------------------

    /**
     * The API base, built from the address and the mode.
     */
    private function base(): string
    {
        $site = untrailingslashit(trim((string) $this->get_option('site_url')));

        return $this->get_option('mode') === 'live'
            ? $site . '/pay/api/v1'
            : $site . '/pay/sandbox/api/v1';
    }

    /**
     * @return string|WP_Error
     */
    private function demander_un_jeton()
    {
        $reponse = $this->appeler('/authentication/token', [
            'client_id' => $this->get_option('client_id'),
            'secret_id' => $this->get_option('secret_id'),
        ]);

        if (is_wp_error($reponse)) {
            return $reponse;
        }

        $jeton = $reponse['data']['access_token'] ?? null;

        if (! $jeton) {
            return new WP_Error('cashmobile_no_token', esc_html__('CashMobile did not return an access token. Check the Client ID, the Secret ID, and that the mode matches your keys.', 'cashmobile-gateway-for-woocommerce'));
        }

        return (string) $jeton;
    }

    /**
     * @return array{payment_url:string,token:string}|WP_Error
     */
    private function ouvrir_le_paiement(string $jeton_acces, WC_Order $commande, string $secret)
    {
        $retour = add_query_arg(
            [
                'order_id'  => $commande->get_id(),
                'cm_secret' => $secret,
            ],
            WC()->api_request_url('cashmobile_gateway')
        );

        $reponse = $this->appeler('/payment/create', [
            'amount'     => (string) $commande->get_total(),
            'currency'   => $commande->get_currency(),
            'return_url' => $retour,
            'cancel_url' => $commande->get_cancel_order_url(),
            // The store's own order number, handed back by /payment/status.
            'custom'     => (string) $commande->get_id(),
        ], ['Authorization' => 'Bearer ' . $jeton_acces]);

        if (is_wp_error($reponse)) {
            return $reponse;
        }

        $url   = $reponse['data']['payment_url'] ?? null;
        $jeton = $reponse['data']['token'] ?? null;

        if (! $url || ! $jeton) {
            return new WP_Error('cashmobile_no_payment', esc_html__('CashMobile did not return a payment URL.', 'cashmobile-gateway-for-woocommerce'));
        }

        return ['payment_url' => (string) $url, 'token' => (string) $jeton];
    }

    /**
     * The authoritative answer: was this payment made?
     *
     * @return array<string,mixed>|WP_Error
     */
    private function lire_le_statut(string $jeton)
    {
        if ($jeton === '') {
            return new WP_Error('cashmobile_no_token', esc_html__('This order carries no CashMobile payment token.', 'cashmobile-gateway-for-woocommerce'));
        }

        $reponse = $this->appeler('/payment/status', [
            'client_id' => $this->get_option('client_id'),
            'secret_id' => $this->get_option('secret_id'),
            'token'     => $jeton,
        ]);

        if (is_wp_error($reponse)) {
            return $reponse;
        }

        if (! isset($reponse['data'])) {
            return new WP_Error('cashmobile_bad_status', esc_html__('CashMobile returned an unreadable status.', 'cashmobile-gateway-for-woocommerce'));
        }

        return (array) $reponse['data'];
    }

    /**
     * One HTTP call, through WordPress rather than a bundled client.
     *
     * @param  array<string,mixed>  $corps
     * @param  array<string,string> $entetes
     * @return array<string,mixed>|WP_Error
     */
    private function appeler(string $chemin, array $corps, array $entetes = [])
    {
        $reponse = wp_remote_post($this->base() . $chemin, [
            // Long enough for a slow link, short enough that a checkout does
            // not hang on an unreachable gateway.
            'timeout' => 30,
            'headers' => array_merge([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ], $entetes),
            'body' => wp_json_encode($corps),
        ]);

        if (is_wp_error($reponse)) {
            return new WP_Error('cashmobile_http', $reponse->get_error_message());
        }

        $code    = (int) wp_remote_retrieve_response_code($reponse);
        $decode  = json_decode(wp_remote_retrieve_body($reponse), true);

        if (! is_array($decode)) {
            return new WP_Error('cashmobile_bad_json', sprintf(
                /* translators: %d: HTTP status code. */
                esc_html__('CashMobile replied with something other than JSON (HTTP %d).', 'cashmobile-gateway-for-woocommerce'),
                $code
            ));
        }

        if ($code < 200 || $code >= 300) {
            /*
             | Surface the gateway's own message. It says things like "this
             | currency is not enabled for payments on this platform", which is
             | exactly what the store owner needs to read — far better than a
             | generic failure.
             */
            $messages = $decode['message']['error'] ?? $decode['message'] ?? [];
            $texte    = is_array($messages) ? implode(' ', array_map('strval', $messages)) : (string) $messages;

            return new WP_Error('cashmobile_refused', $texte !== '' ? $texte : sprintf(
                /* translators: %d: HTTP status code. */
                esc_html__('CashMobile refused the request (HTTP %d).', 'cashmobile-gateway-for-woocommerce'),
                $code
            ));
        }

        return $decode;
    }

    // -----------------------------------------------------------------
    // Plumbing
    // -----------------------------------------------------------------

    private function echouer(string $message, int $order_id): void
    {
        $this->journal(sprintf('Order %d: %s', $order_id, $message));

        wc_add_notice(
            sprintf(
                /* translators: %s: the reason the payment could not start. */
                esc_html__('We could not start the payment: %s', 'cashmobile-gateway-for-woocommerce'),
                $message
            ),
            'error'
        );
    }

    /**
     * Logs an event — never a credential.
     *
     * The old plugin wrote `print_r` of every API response into the store log,
     * access token included. WooCommerce log files have historically been
     * readable by anyone who could guess their name.
     */
    private function journal(string $message): void
    {
        if ('yes' !== $this->get_option('debug', 'yes')) {
            return;
        }

        wc_get_logger()->info($message, ['source' => 'cashmobile']);
    }

    public function styles_boutique(): void
    {
        wp_add_inline_style('woocommerce-general', '
            .payment_method_cashmobile img { width: 100px; height: auto; }
            .payment_method_cashmobile label { display: inline-flex; }
        ');
    }

    public function scripts_admin($hook): void
    {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'cashmobile-admin',
            plugins_url('assets/js/admin-script.js', dirname(__FILE__)),
            ['jquery'],
            CASHMOBILE_WC_VERSION,
            true
        );
    }
}
