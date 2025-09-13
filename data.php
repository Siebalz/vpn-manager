<?php
require('routeros_api.class.php');

$API = new RouterosAPI();
$API->port = 5728;

$host = '103.195.65.170';
$user = 'iqbal';
$pass = 'asddsawswsas123';

if ($API->connect($host, $user, $pass)) {
    $secrets = $API->comm('/ppp/secret/print');
    $active  = $API->comm('/ppp/active/print');
    $API->disconnect();

    echo json_encode([
        "secrets" => $secrets,
        "active"  => $active
    ]);
} else {
    echo json_encode(["error" => "Gagal konek ke MikroTik"]);
}
