<?php
require('routeros_api.class.php');

$API = new RouterosAPI();
$API->port = 5728;   // ganti ke port API kamu (atau 5728 kalau pakai service yang sudah dibuka)

if ($API->connect('103.195.65.170', 'iqbal', 'asddsawswsas123')) {

    // Ambil data PPP active
    $active = $API->comm('/ppp/active/print');

    echo "<h2>Daftar PPP Active</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>No</th><th>Name</th><th>Service</th><th>Address</th><th>Uptime</th></tr>";

    $no = 1;
    foreach ($active as $user) {
        echo "<tr>";
        echo "<td>".$no++."</td>";
        echo "<td>".$user['name']."</td>";
        echo "<td>".$user['service']."</td>";
        echo "<td>".$user['address']."</td>";
        echo "<td>".$user['uptime']."</td>";
        echo "</tr>";
    }

    echo "</table>";

    $API->disconnect();
} else {
    echo "Gagal konek ke MikroTik!";
}
?>
