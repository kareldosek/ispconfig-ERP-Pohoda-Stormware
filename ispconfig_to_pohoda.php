<?php
// 1. Nastavení přístupů (doplň si své údaje)
$username = 'remote_user';
$password = 'heslo';
$soap_location = 'https://tvujserver.cz';
$soap_uri = 'https://tvujserver.cz';

// 2. Připojení k ISPConfig API
$client = new SoapClient(null, array(
    'location' => $soap_location,
    'uri'      => $soap_uri,
    'trace'    => 1,
    'exceptions' => 1
));

try {
    $session_id = $client->login($username, $password);
    
    // Načtení všech klientů
    $clients = $client->client_get_all($session_id);

    foreach ($clients as $c) {
        // Předpokládáme, že datum registrace je v poli 'added_date'
        // ISPConfig vrací datum často jako string 'YYYY-MM-DD'
        $added_date = new DateTime($c['added_date']);
        $today = new DateTime();
        
        // Výpočet výročí v aktuálním roce
        $anniversary = new DateTime(date('Y') . '-' . $added_date->format('m-d'));
        
        // Pokud už výročí letos bylo, počítáme s příštím rokem
        if ($anniversary < $today) {
            $anniversary->modify('+1 year');
        }

        // Rozdíl mezi dneškem a výročím
        $diff = $today->diff($anniversary);
        $days_until = $diff->days;

        // LOGIKA: Pokud je výročí přesně za 14 dní
        if ($days_until == 14) {
            generatePohodaXML($c);
            echo "Generuji fakturu pro: " . $c['contact_name'] . "\n";
        }
    }

    $client->logout($session_id);

} catch (SoapFault $e) {
    die('Chyba API: ' . $e->getMessage());
}

// 3. Funkce pro tvorbu XML
function generatePohodaXML($clientData) {
 $ico = "12345678"; // Tvé IČO
    
    // 1. UNIKÁTNÍ IDENTIFIKÁTOR (Klíč k úspěchu)
    // Složíme ho z ID klienta, měsíce a roku (např. 123-2024-02)
    // Díky tomu Pohoda pozná, že fakturu pro tohoto klienta a měsíc už má.
    $uniqueId = $clientData['client_id'] . "-" . date('Y-m');
    
    $filename = "faktura_" . $uniqueId . ".xml";
    
    // 2. HLAVIČKA DATAPACKU
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dat:dataPack xmlns:dat="http://www.stormware.cz" xmlns:inv="http://www.stormware.cz" version="2.0" id="ISPConfigImport" ico="'.$ico.'" application="ISPConfig" />');
    
    // Tady vkládáme ten unikátní identifikátor do atributu 'id'
    $item = $xml->addChild('dat:dataPackItem');
    $item->addAttribute('id', $uniqueId); 
    
    $inv = $item->addChild('inv:invoice', '', 'http://www.stormware.cz');
    $header = $inv->addChild('inv:invoiceHeader');
    
    $header->addChild('inv:invoiceType', 'issuedInvoice');
    $header->addChild('inv:date', date('Y-m-d')); // Datum vystavení
    $header->addChild('inv:dateDue', date('Y-m-d', strtotime('+14 days'))); // Splatnost
    $header->addChild('inv:text', 'Fakturace hostingových služeb');
    
  // Adresa zákazníka s ošetřením prázdných polí
    $partner = $header->addChild('inv:partnerIdentity');
    $address = $partner->addChild('inv:address');
    
    // 1. Název nebo Jméno (Pohoda vyžaduje aspoň jedno)
    if (!empty($clientData['company_name'])) {
        $address->addChild('inv:company', htmlspecialchars($clientData['company_name']));
        $address->addChild('inv:name', htmlspecialchars($clientData['contact_name']));
    } else {
        // Pokud není firma, dáme jméno do pole company (častý trik pro Pohodu)
        $address->addChild('inv:company', htmlspecialchars($clientData['contact_name']));
    }

    $address->addChild('inv:city', htmlspecialchars($clientData['city']));
    $address->addChild('inv:zip', htmlspecialchars($clientData['zip']));
    $address->addChild('inv:street', htmlspecialchars($clientData['street'])); // Nezapomeň na ulici

    // 2. IČO a DIČ (Vložíme jen pokud existují)
    if (!empty($clientData['vat_id'])) {
        $address->addChild('inv:ico', $clientData['vat_id']);
    }
    
    if (!empty($clientData['tax_id'])) {
        $address->addChild('inv:dic', $clientData['tax_id']);
    }  
	
    // Uložení souboru do složky (ujisti se, že složka existuje a má práva zápisu)
    $xml->asXML(__DIR__ . "/exporty/" . $filename);
}