<?php

use Illuminate\Database\Capsule\Manager as DB;

require_once('vendor/autoload.php');
require_once('src/Anonymizer.php');

$config = require_once('config.php');

$db = new DB;

$db->addConnection(array_merge(
    [
        'driver' => 'mysql',
        'host' => 'localhost',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ],
    $config
));

// Make this Capsule instance available globally via static methods... (optional)
$db->setAsGlobal();

$anonymizer = new Anonymizer($db);

$anonymizer->users();
$anonymizer->authors();
$anonymizer->publications();
$anonymizer->crossref();
$anonymizer->datacite();
$anonymizer->orcid();
$anonymizer->lucene();

echo "Anonymization complete.\nYou may need to flush the OJS/OMP/OPS data cache before some changes will be reflected in the UI.\n";
