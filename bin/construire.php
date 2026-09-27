<?php
/**
 * Builds the archive to publish.
 *
 * WHY PHP AND NOT `zip`.
 *
 * Git Bash on Windows ships `unzip` but not `zip`, so a shell one-liner fails
 * on the very machine this plugin is built on. PHP is present by definition
 * here — it is what the platform runs on — and ZipArchive is part of it.
 *
 * The archive is NOT committed. It rebuilds identically from the source, and a
 * binary in the history cannot be read in review.
 */

declare(strict_types=1);

$racine  = dirname(__DIR__);
$plugin  = 'cashmobile-gateway-for-woocommerce';
$dossier = $racine . '/' . $plugin;

if (! is_dir($dossier)) {
    fwrite(STDERR, "Plugin directory not found: {$dossier}\n");
    exit(1);
}

// The version comes from the plugin header, which is the only place that
// declares it. Reading it anywhere else invites two versions that disagree.
$entete = (string) file_get_contents($dossier . '/cashmobile.php');

if (! preg_match('/^\s*\*\s*Version:\s*(.+)$/m', $entete, $trouve)) {
    fwrite(STDERR, "No Version in the plugin header.\n");
    exit(1);
}

$version = trim($trouve[1]);

@mkdir($racine . '/dist', 0775, true);
$archive = $racine . '/dist/' . $plugin . '-v' . $version . '.zip';
@unlink($archive);

$zip = new ZipArchive();

if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create {$archive}\n");
    exit(1);
}

/** Nothing that has no business on a merchant's server. */
$exclus = static function (string $chemin): bool {
    $nom = basename($chemin);

    return $nom === '.DS_Store'
        || $nom === 'Thumbs.db'
        || str_contains(str_replace('\\', '/', $chemin), '/.git/');
};

$parcours = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$compte = 0;

foreach ($parcours as $entree) {
    /** @var SplFileInfo $entree */
    $absolu = $entree->getPathname();

    if ($exclus($absolu)) {
        continue;
    }

    // The archive carries ONE root directory, which is what the WordPress
    // installer expects.
    $interne = $plugin . '/' . str_replace('\\', '/', substr($absolu, strlen($dossier) + 1));

    if ($entree->isDir()) {
        $zip->addEmptyDir($interne);
        continue;
    }

    $zip->addFile($absolu, $interne);
    $compte++;
}

$zip->close();

$empreinte = hash_file('sha256', $archive);

printf("%s\n", str_replace('\\', '/', $archive));
printf("fichiers : %d\n", $compte);
printf("poids    : %d Ko\n", (int) round(filesize($archive) / 1024));
printf("sha256   : %s\n", $empreinte);
