<?php
/**
 * AUTO-BILLING SCRIPT: ISPConfig -> Pohoda XML
 * Funkce: Kontroluje výročí registrace (14 dní před) a generuje unikátní XML pro Pohodu.
 */

// Kontrola přítopnosti PHP SOAP modulu v systému
if (!extension_loaded('soap')) {
    die("Chyba: PHP modul SOAP není aktivní. Pro opravu použij: sudo apt install php-soap\n");
}
// --- KONFIGURACE ---
$username = 'remote_user_v_ispconfigu';
$password = 'tve_silne_heslo';
$soap_location = 'https://tvujserver.cz';
$soap_uri = 'https://tvujserver.cz';

$moje_ico = "12345678"; // Tvé IČO pro hlavičku XML
$cena_hostingu = 1200;    // Částka v CZK bez DPH (nebo celková, dle tvého nastavení v Pohodě)
$export_dir = __DIR__ . "/exporty"; // Složka pro XML soubory

// Vytvoření složky, pokud neexistuje
if (!file_exists($export_dir)) mkdir($export_dir, 0755, true);

// --- PŘIPOJENÍ K API ---
$soap = new SoapClient(null, [
    'location' => $soap_location,
    'uri'      => $soap_uri,
    'trace'    => 1,
    'exceptions' => 1
]);

try {
    $session_id = $soap->login($username, $password);
    $clients = $soap->client_get_all($session_id);

    foreach ($clients as $c) {
     // 1. FILTR: Pokud je klient v ISPConfigu zakázán/zamknut, ignorujeme ho
        // ISPConfig vrací 'y' pro zamknuté (locked) a 'n' pro povolené
        if (isset($c['locked']) && $c['locked'] === 'y') {
            echo "SKIP: Klient " . ($c['company_name'] ?: $c['contact_name']) . " je zakázán, přeskakuji.\n";
            continue;
        }

        // Kontrola, zda má klient vyplněné datum registrace
        if (empty($c['added_date']) || $c['added_date'] == '0000-00-00') continue;

        // --- LOGIKA VÝROČÍ (14 DNÍ PŘEDEM) ---
        $added = new DateTime($c['added_date']);
        $today = new DateTime('today');
        
        // Výročí v aktuálním nebo příštím roce
        $anniversary = new DateTime(date('Y') . '-' . $added->format('m-d'));
        if ($anniversary < $today) {
            $anniversary->modify('+1 year');
        }

        $diff = $today->diff($anniversary);
        
        // Kontrola: Je výročí přesně za 14 dní?
        if ($diff->days === 14 && $diff->invert === 0) {
            generatePohodaXML($c, $moje_ico, $cena_hostingu, $export_dir);
            echo "SUCCESS: Generuji fakturu pro " . ($c['company_name'] ?: $c['contact_name']) . "\n";
        }
    }

    $soap->logout($session_id);

} catch (Exception $e) {
    die("CHYBA: " . $e->getMessage() . "\n");
}

// --- FUNKCE PRO GENEROVÁNÍ XML ---
function generatePohodaXML($data, $moje_ico, $cena, $dir) {
    // Unikátní ID pro Pohodu (ID-ROK-MESIC) - zabrání duplicitnímu importu
    $uniqueId = $data['client_id'] . "-" . date('Y-m');
    $filename = "faktura_" . $uniqueId . ".xml";

    // Základní struktura XML s Namespaces pro Pohodu
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dat:dataPack xmlns:dat="http://www.stormware.cz" xmlns:inv="http://www.stormware.cz" xmlns:typ="http://www.stormware.cz" version="2.0" id="ISPConfigImport" ico="'.$moje_ico.'" application="ISPConfig" />');

    $item = $xml->addChild('dat:dataPackItem');
    $item->addAttribute('id', $uniqueId);

    $inv = $item->addChild('inv:invoice', '', 'http://www.stormware.cz');
    $header = $inv->addChild('inv:invoiceHeader');

    // Hlavička faktury
    $header->addChild('inv:invoiceType', 'issuedInvoice');
    $header->addChild('inv:date', date('Y-m-d'));
    $header->addChild('inv:dateDue', date('Y-m-d', strtotime('+14 days')));
    $header->addChild('inv:text', 'Fakturace ročního hostingu a správy');

    // Identifikace partnera s ošetřením chybějících dat
    $partner = $header->addChild('inv:partnerIdentity');
    $addr = $partner->addChild('typ:address', '', 'http://www.stormware.cz');
    
    // Pokud není firma, použijeme jméno kontaktu jako název firmy (pro Pohodu nutné)
    $name = !empty($data['company_name']) ? $data['company_name'] : $data['contact_name'];
    $addr->addChild('typ:company', htmlspecialchars($name));
    $addr->addChild('typ:city', htmlspecialchars($data['city'] ?? ''));
    $addr->addChild('typ:street', htmlspecialchars($data['street'] ?? ''));
    $addr->addChild('typ:zip', htmlspecialchars($data['zip'] ?? ''));

    if (!empty($data['vat_id'])) $addr->addChild('typ:ico', $data['vat_id']);
    if (!empty($data['tax_id'])) $addr->addChild('typ:dic', $data['tax_id']);

    // POLOŽKA FAKTURY
    $details = $inv->addChild('inv:invoiceDetail');
    $invItem = $details->addChild('inv:invoiceItem');
    $invItem->addChild('inv:text', 'Roční webhosting a údržba serveru');
    $invItem->addChild('inv:quantity', 1);
    $invItem->addChild('inv:unit', 'rok');
    $invItem->addChild('inv:homeCurrency')->addChild('typ:unitPrice', $cena);

    // Uložení souboru
    $xml->asXML($dir . "/" . $filename);

}
