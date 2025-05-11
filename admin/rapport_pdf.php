<?php
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Lire tout le contenu HTML de rapport.php
ob_start();
include 'rapport.php';
$htmlContent = ob_get_clean();

// Charger dans DOMDocument
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$tables = $xpath->query('//table');

if ($tables->length == 0) {
    die("Aucun tableau trouvé.");
}

// Styles de base
$cleanHtml = '
<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    th, td {
        border: 1px solid #333;
        padding: 4px;
        text-align: center;
    }
    th {
        background-color: #007BFF;
        color: white;
    }
    h2, h3 {
        text-align: center;
        margin: 10px 0;
    }
</style>
';

$cleanHtml .= '<h2>Rapport des Stocks</h2>';

// Pour chaque tableau, on ajoute le titre + le tableau
foreach ($tables as $table) {
    // Trouver le titre précédent le tableau
    $previous = $table->previousSibling;
    while ($previous && !in_array($previous->nodeName, ['h1', 'h2', 'h3'])) {
        $previous = $previous->previousSibling;
    }

    if ($previous) {
        $cleanHtml .= '<h3>' . htmlspecialchars($previous->textContent) . '</h3>';
    }

    $cleanHtml .= $dom->saveHTML($table);
}

// Création du PDF avec Dompdf
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($cleanHtml);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("etat_stock.pdf", ["Attachment" => true]);
