# CashMobile Gateway for WooCommerce

Le plugin WooCommerce de CashMobile. Distribue aux marchands depuis
https://cashmobile.net/developer/woocommerce

Depot : https://github.com/jtaylor007/cashmobile-woocommerce

**Ce depot est prive.** Le plugin est publie sous GPL-2.0-or-later, comme le veut
l'ecosysteme WordPress : quiconque recoit l'archive a donc droit a la source. Le
rendre public est le moyen le plus simple de tenir cela — et, pour un plugin de
paiement, une source lisible est un argument de confiance plutot qu'une
concession. Tant qu'il reste prive, la page developpeur masque le lien vers lui
(reglage `PLUGICIEL_WOOCOMMERCE_DEPOT`, laisse vide).

## Pourquoi ce depot est separe du backend

Ce code tourne sur le serveur de quelqu'un d'autre. Il a sa propre version, son
propre journal de modifications, et les marchands rapporteront des anomalies
*contre une version* — melanger son historique a celui de la plateforme rendrait
cela illisible. Le corriger ne doit pas non plus exiger de redeployer
cashmobile.net.

## Origine, et ce qui a change

Il part du plugin livre avec QRPay Pro (`reference/`, hors git). La version 2.0.0
en est une reecriture, pour une raison qui n'etait pas cosmetique :

**l'original marquait la commande « completed » sur la seule redirection de
retour**, sans jamais demander a la passerelle si le paiement avait eu lieu. Or
CashMobile renvoie le payeur vers `return_url` aussi en cas d'ECHEC. Un paiement
refuse completait donc la commande et liberait la marchandise — sans qu'aucun
attaquant n'ait a s'en meler.

Le journal complet est dans `cashmobile-gateway-for-woocommerce/readme.txt`.

## Construire une version

```bash
bin/construire.sh
```

Rend `dist/cashmobile-gateway-for-woocommerce-v<version>.zip` et son empreinte
SHA-256. L'empreinte est publiee a cote du lien de telechargement : un marchand
installe du code sur son serveur, il doit pouvoir verifier ce qu'il a recu.

## Avant de publier une version

1. `php -l` sur chaque fichier PHP.
2. Un paiement reussi en sandbox, de bout en bout.
3. **Un paiement refuse en sandbox** — le payeur de test prevu pour cela — et
   verifier que la commande reste impayee. C'est le defaut d'origine : ce test
   ne se saute jamais.
4. Un retour rejoue (rafraichir la page de retour) : le stock ne doit pas
   diminuer deux fois.
5. Passerelle injoignable (couper le reseau au retour) : la commande doit rester
   intacte, avec une note.
