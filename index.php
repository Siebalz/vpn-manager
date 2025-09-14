<?php
require('routeros_api.class.php');

$API = new RouterosAPI();
$API->port = 5728; // sesuaikan dengan API port

$host = 'id-1.routerverse.my.id';
$user = 'iqbal';
$pass = 'asddsawswsas123';

$message = "";
$profiles = [];

// Ambil semua profile
if ($API->connect($host, $user, $pass)) {
    $profiles = $API->comm('/ppp/profile/print');
    $API->disconnect();
}

// ====== Tambah User ======
if (isset($_POST['add_user'])) {
    if ($API->connect($host, $user, $pass)) {

        $subnet = "11.11.11."; // subnet untuk static VPN
        $startIp = 10; // mulai dari 11.11.11.10
        $maxIp   = 254;

        // 1. Ambil semua secret yang ada
        $secrets = $API->comm("/ppp/secret/print");

        // Cari IP yang sudah dipakai
        $usedIps = [];
        foreach ($secrets as $s) {
            if (isset($s['remote-address'])) {
                $usedIps[] = $s['remote-address'];
            }
        }

        // 2. Cari IP kosong berikutnya
        $newIp = null;
        for ($i = $startIp; $i <= $maxIp; $i++) {
            $candidate = $subnet . $i;
            if (!in_array($candidate, $usedIps)) {
                $newIp = $candidate;
                break;
            }
        }

        if (!$newIp) {
            $message = "❌ Gagal menambah user, IP static habis!";
        } else {
            // 3. Tambah PPP user dengan remote-address static
            $API->comm("/ppp/secret/add", [
                "name"     => $_POST['username'],
                "password" => $_POST['password'],
                "profile"  => $_POST['profile'],
                "remote-address" => $newIp
            ]);

            // 4. Generate port random NAT
            $port = rand(20000, 60000);

            // 5. Cek dulu apakah NAT sudah ada untuk user ini
            $existingNat = $API->comm("/ip/firewall/nat/print", [
                "?comment" => "VPN-{$_POST['username']}"
            ]);

            if (empty($existingNat)) {
                // Kalau belum ada, tambahkan NAT baru
                $API->comm("/ip/firewall/nat/add", [
                    "chain"       => "dstnat",
                    "action"      => "dst-nat",
                    "dst-address" => "103.195.65.170", // IP publik server
                    "to-addresses" => $newIp,
                    "to-ports"    => "8291",
                    "protocol"    => "tcp",
                    "dst-port"    => strval($port),
                    "comment"     => "VPN-{$_POST['username']}"
                ]);
            } else {
                // Kalau sudah ada, pakai port & IP dari rule lama
                $port   = $existingNat[0]['dst-port'];
                $newIp  = $existingNat[0]['to-addresses'];
            }

            // 6. Pesan sukses
            $message = "✅ User <b>{$_POST['username']}</b> berhasil ditambahkan dengan profile <b>{$_POST['profile']}</b> dan IP <b>{$newIp}</b>!<br><br>";
            $message .= "🌐 Akses via: <b>{$host}:{$port}</b><br><br>";

            // 7. Script client
            $clientScript = <<<EOT
/interface l2tp-client
add name={$_POST['username']}-vpn connect-to=$host user={$_POST['username']} password={$_POST['password']} disabled=no
EOT;

            $message .= "<b>Script untuk Client:</b><br>
            <textarea style='width:100%;height:120px;' readonly>$clientScript</textarea>";
        }

        $API->disconnect();
    }
}


// ====== Edit User ======
if (isset($_POST['edit_user'])) {
    if ($API->connect($host, $user, $pass)) {
        $API->comm("/ppp/secret/set", [
            ".id"      => $_POST['id'],
            "name"     => $_POST['username'],
            "password" => $_POST['password'],
            "profile"  => $_POST['profile']
        ]);
        $message = "✏️ User <b>{$_POST['username']}</b> berhasil diupdate!";
        $API->disconnect();
    }
}

// ====== Delete User ======
if (isset($_GET['delete'])) {
    if ($API->connect($host, $user, $pass)) {

        // 1. Ambil username dulu dari secret id
        $secret = $API->comm("/ppp/secret/print", [
            ".proplist" => "name",
            ".id"       => $_GET['delete']
        ]);

        if (!empty($secret)) {
            $username = $secret[0]['name'];

            // 2. Hapus secret
            $API->comm("/ppp/secret/remove", [
                ".id" => $_GET['delete']
            ]);

            // 3. Ambil semua NAT rules
            $natRules = $API->comm("/ip/firewall/nat/print");

            if (!empty($natRules)) {
                foreach ($natRules as $rule) {
                    if (isset($rule['comment']) && $rule['comment'] === "VPN-$username") {
                        $API->comm("/ip/firewall/nat/remove", [
                            ".id" => $rule[".id"]
                        ]);
                    }
                }
                $message = "🗑️ User <b>$username</b> dan NAT rule terkait berhasil dihapus!";
            } else {
                $message = "🗑️ User <b>$username</b> berhasil dihapus, tapi NAT rule tidak ditemukan.";
            }
        }

        $API->disconnect();

        // redirect balik ke halaman utama
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>VPN Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>VPN Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-800 min-h-screen p-6">

    <div class="container mx-auto">
        <h1 class="text-3xl font-bold mb-6">VPN Manager</h1>

        <?php if ($message) { ?>
            <div class="p-4 mb-6 bg-green-100 text-green-700 rounded shadow">
                <?php echo $message; ?>
            </div>
        <?php } ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Form PPP User -->
            <div class="bg-white p-6 rounded shadow max-w-md">
                <h2 class="text-xl font-semibold mb-4">Tambah PPP User</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="id" id="edit_id">

                    <div>
                        <label class="block text-sm font-medium">Username</label>
                        <input type="text" name="username" id="edit_username" required
                            class="w-full border rounded p-2 focus:ring focus:ring-blue-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Password</label>
                        <input type="text" name="password" id="edit_password" required
                            class="w-full border rounded p-2 focus:ring focus:ring-blue-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Profile</label>
                        <select name="profile" id="edit_profile" required
                            class="w-full border rounded p-2 focus:ring focus:ring-blue-200">
                            <option value="">-- Pilih Profile --</option>
                            <?php foreach ($profiles as $p) { ?>
                                <option value="<?php echo $p['name']; ?>"><?php echo $p['name']; ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" name="add_user" id="btn_add"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Tambah
                        </button>
                        <button type="submit" name="edit_user" id="btn_edit"
                            class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 hidden">
                            Update
                        </button>
                    </div>
                </form>
            </div>

            <!-- Status MikroTik -->
            <div class="md:col-span-2">
                <h2 class="text-xl font-semibold mb-4">Status MikroTik</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class='bg-white p-4 rounded shadow text-center'>
                        <p class='text-sm text-gray-500'>CPU Load</p>
                        <p class='text-xl font-bold' id="cpu_load">-</p>
                    </div>

                    <div class='bg-white p-4 rounded shadow text-center'>
                        <p class='text-sm text-gray-500'>Free Memory</p>
                        <p class='text-xl font-bold' id="free_mem">-</p>
                    </div>

                    <div class='bg-white p-4 rounded shadow text-center'>
                        <p class='text-sm text-gray-500'>Total Memory</p>
                        <p class='text-xl font-bold' id="total_mem">-</p>
                    </div>

                    <div class='bg-white p-4 rounded shadow text-center'>
                        <p class='text-sm text-gray-500'>Uptime</p>
                        <p class='text-xl font-bold' id="uptime">-</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-2 mt-6">
            <h2 class="text-xl font-semibold mb-4">Monitoring Chart</h2>
            <div class="bg-white p-4 rounded shadow">
                <canvas id="cpuChart" height="100"></canvas>
            </div>
        </div>

        <!-- Daftar User -->
        <div class="mt-10 bg-white p-6 rounded shadow">
            <h2 class="text-xl font-semibold mb-4">Daftar PPP User</h2>
            <?php
            if ($API->connect($host, $user, $pass)) {
                $secrets = $API->comm('/ppp/secret/print');

                if (count($secrets) > 0) {
                    echo "<div class='overflow-x-auto'>
                      <table class='w-full border border-gray-200 text-sm'>
                        <thead class='bg-gray-100'>
                          <tr>
                            <th class='p-2 border'>No</th>
                            <th class='p-2 border'>Username</th>
                            <th class='p-2 border'>Profile</th>
                            <th class='p-2 border'>Remote Address</th>
                            <th class='p-2 border'>Akses Via</th>
                            <th class='p-2 border'>Last Logged Out</th>
                            <th class='p-2 border'>Aksi</th>
                          </tr>
                        </thead>
                        <tbody>";

                    $no = 1;
                    foreach ($secrets as $row) {
                        // Cari Nat rule berdasarkan comment
                        $natRule = $API->comm("/ip/firewall/nat/print", [
                            "?comment" => "VPN-" . $row['name']
                        ]);
                        $akses = "-";
                        if (count($natRule) > 0) {
                            $dstPort = $natRule[0]['dst-port'];
                            $akses = "{$host}:{$dstPort}";
                        }

                        echo "<tr class='hover:bg-gray-50'>
                          <td class='p-2 border'>" . $no++ . "</td>
                          <td class='p-2 border'>" . $row['name'] . "</td>
                          <td class='p-2 border'>" . $row['profile'] . "</td>
                          <td class='p-2 border'>" . ($row['remote-address'] ?? '-') . "</td>
                          <td class='p-2 border text-blue-600 font-medium'>" . $akses . "</td>
                          <td class='p-2 border'>" . ($row['last-logged-out'] ?? '-') . "</td>
                          <td class='p-2 border'>
                            <a href='#' class='bg-yellow-500 text-white px-2 py-1 rounded'
                               onclick=\"editUser('{$row['.id']}','{$row['name']}','{$row['profile']}')\">Edit</a>
                            <a href='?delete={$row['.id']}' class='bg-red-600 text-white px-2 py-1 rounded'
                               onclick='return confirm(\"Hapus user ini?\")'>Delete</a>
                          </td>
                        </tr>";
                    }

                    echo "    </tbody></table></div>";
                } else {
                    echo "Belum ada user.";
                }

                $API->disconnect();
            }
            ?>
        </div>
    </div>
    <!-- PPP Users & Active -->
    <div id="ppp_users" class="mt-8"></div>
    <div id="ppp_active" class="mt-4"></div>
    </div>

    <script>
        let cpuData = [];
        let memData = [];
        let labels = [];

        // Setup Chart.js
        const ctx = document.getElementById('cpuChart').getContext('2d');
        const cpuChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                        label: 'CPU Load (%)',
                        data: cpuData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.3
                    },
                    {
                        label: 'Free Memory (MB)',
                        data: memData,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: "Waktu"
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function editUser(id, username, profile) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_password').value = "";
            document.getElementById('edit_profile').value = profile;

            document.getElementById('btn_add').classList.add("hidden");
            document.getElementById('btn_edit').classList.remove("hidden");
        }

        async function loadData() {
            let res = await fetch("data.php");
            let data = await res.json();

            if (data.error) {
                document.getElementById("ppp_users").innerHTML = `<div class="text-red-600">${data.error}</div>`;
                return;
            }

            let usersHtml = `
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-3">PPP Users</h3>
                <table class="w-full border text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border px-3 py-2">User</th>
                            <th class="border px-3 py-2">Profile</th>
                        </tr>
                        </thead>
                    <tbody>`;

            data.secrets.forEach(u => {
                usersHtml += `<tr><td class="border px-3 py-2">${u.name}</td><td class="border px-3 py-2">${u.profile}</td></tr>`;
            });
            usersHtml += "</tbody></table></div>";
            document.getElementById("ppp_users").innerHTML = usersHtml;

            let activeHtml = `<div class="bg-white shadow rounded-lg p-6 mt-4">
                <h3 class="text-lg font-semibold mb-3">Active Connections</h3>
                <table class="w-full border">
                    <thead class="bg-gray-100"><tr><th class="border px-3 py-2">Name</th><th class="border px-3 py-2">Address</th><th class="border px-3 py-2">Uptime</th></tr></thead>
                    <tbody>`;

            data.active.forEach(a => {
                activeHtml += `<tr><td class="border px-3 py-2">${a.name}</td><td class="border px-3 py-2">${a.address}</td><td class="border px-3 py-2">${a.uptime}</td></tr>`;
            });
            activeHtml += "</tbody></table></div>";
            document.getElementById("ppp_active").innerHTML = activeHtml;
        }
        async function loadResource() {
            let res = await fetch("resource.php");
            let data = await res.json();

            if (!data.error) {
                document.getElementById("cpu_load").innerText = data.cpu + "%";
                document.getElementById("free_mem").innerText = data.free_mem + " MB";
                document.getElementById("total_mem").innerText = data.total_mem + " MB";
                document.getElementById("uptime").innerText = data.uptime;

                // Tambahkan data ke chart
                let now = new Date().toLocaleTimeString();
                labels.push(now);
                cpuData.push(data.cpu);
                memData.push(data.free_mem);

                // Batas data max 10 titik biar tidak terlalu panjang
                if (labels.length > 10) {
                    labels.shift();
                    cpuData.shift();
                    memData.shift();
                }

                cpuChart.update();
            }
        }
        setInterval(loadResource, 5000);
        loadResource();
        loadData();
        setInterval(loadData, 5000);
    </script>
</body>

</html>