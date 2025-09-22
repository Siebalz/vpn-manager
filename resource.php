<?php
require('routeros_api.class.php');

$API = new RouterosAPI();
$API->port = 5728;

$host = 'id-1.routerverse.my.id';
$user = 'iqbal';
$pass = 'asddsawswsas123';

if ($API->connect($host, $user, $pass)) {
    $res = $API->comm('/system/resource/print')[0];
    $API->disconnect();

    echo json_encode([
        "cpu" => $res['cpu-load'],
        "free_mem" => round($res['free-memory']/1024/1024,1),
        "total_mem" => round($res['total-memory']/1024/1024,1),
        "uptime" => $res['uptime']
        
    ]);
} else {
    echo json_encode(["error" => "Tidak bisa konek ke MikroTik"]);
}
