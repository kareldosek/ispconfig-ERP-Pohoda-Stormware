<?php
$username = 'remote_user'; 
$password = 'heslo';
$soap_location = 'https://tvujserver.cz';
$soap_uri = 'https://tvujserver.cz';

$soap = new SoapClient(null, ['location' => $soap_location, 'uri' => $soap_uri]);

try {
    $session_id = $soap->login($username, $password);
    
    // Načteme všechny klienty
    $clients = $soap->client_get_all($session_id);
    
    if (!empty($clients)) {
        echo "--- DATA PRVNÍHO NALEZENÉHO KLIENTA ---\n";
        // Vypíše všechna pole, která ISPConfig o klientovi vrací
        print_r($clients[0]); 
    } else {
        echo "Žádní klienti nenalezeni.\n";
    }

    $soap->logout($session_id);
} catch (Exception $e) {
    echo "Chyba: " . $e->getMessage() . "\n";
}