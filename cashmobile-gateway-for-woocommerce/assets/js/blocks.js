/**
 * Registers CashMobile in the WooCommerce block checkout.
 *
 * Plain JavaScript on purpose: a plugin shipped as a zip should not need npm to
 * be rebuilt, and a compiled bundle inside the archive cannot be read in review.
 *
 * The gateway is a REDIRECT gateway — the buyer leaves for a CashMobile page and
 * comes back. There is therefore nothing to collect here, and `content` is a
 * description rather than a form. Declaring fields we do not have would make the
 * block wait for input that never arrives.
 */
(function (wc, wp) {
    'use strict';

    if (!wc || !wc.wcBlocksRegistry || !wp || !wp.element) {
        // Rien a faire plutot qu'une erreur de console : une version de
        // WooCommerce sans le registre des blocs utilise la caisse classique,
        // qui affiche la passerelle d'elle-meme.
        return;
    }

    var createElement = wp.element.createElement;
    var decodeEntities = (wp.htmlEntities && wp.htmlEntities.decodeEntities) || function (s) { return s; };
    /*
     * OU WOOCOMMERCE DEPOSE NOS DONNEES.
     *
     * Selon la version, la sortie de `get_payment_method_data()` se lit sous
     * `paymentMethodData.cashmobile` ou sous `cashmobile_data`. Sur WooCommerce
     * 11 c'est la premiere ; lire la seconde seule donnait un objet vide, donc
     * une tuile sans description ni logo — visible, mais nue.
     *
     * On essaie les deux plutot que de parier sur une version, exactement comme
     * pour la classe du registre cote PHP.
     */
    function lireReglages() {
        var wcs = window.wc && window.wc.wcSettings;

        if (!wcs || !wcs.getSetting) {
            return {};
        }

        var groupe = wcs.getSetting('paymentMethodData', {}) || {};

        if (groupe.cashmobile) {
            return groupe.cashmobile;
        }

        return wcs.getSetting('cashmobile_data', {}) || {};
    }

    var reglages = lireReglages();

    var titre = decodeEntities(reglages.title || 'CashMobile');
    var description = decodeEntities(reglages.description || '');

    /** Le libelle : le logo si nous en avons un, le titre sinon. */
    function Libelle(props) {
        var composants = props.components || {};

        if (reglages.icon && composants.PaymentMethodLabel) {
            return createElement(
                'span',
                { className: 'cashmobile-label' },
                createElement('img', {
                    src: reglages.icon,
                    alt: titre,
                    style: { maxWidth: '100px', height: 'auto', marginRight: '8px' }
                }),
                createElement(composants.PaymentMethodLabel, { text: titre })
            );
        }

        return composants.PaymentMethodLabel
            ? createElement(composants.PaymentMethodLabel, { text: titre })
            : titre;
    }

    function Contenu() {
        return description
            ? createElement('div', { className: 'cashmobile-description' }, description)
            : null;
    }

    wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'cashmobile',
        label: createElement(Libelle, null),
        content: createElement(Contenu, null),
        // La zone d'edition de l'administrateur montre la meme chose : ce qu'il
        // voit en construisant la page est ce que l'acheteur verra.
        edit: createElement(Contenu, null),
        canMakePayment: function () { return true; },
        ariaLabel: titre,
        supports: {
            features: (reglages.supports) || ['products']
        }
    });
})(window.wc, window.wp);
