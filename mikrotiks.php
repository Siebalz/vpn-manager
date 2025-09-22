<?php
session_start();

// tombol keluar router
if (isset($_POST['unset_router'])) {
    unset($_SESSION['router_id'], $_SESSION['router_host'], $_SESSION['router_user'], $_SESSION['router_pass'], $_SESSION['router_port']);
    header("Location: mikrotiks.php");
    exit;
}

// proteksi: hanya user yang login boleh akses
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// tempat file penyimpanan (bisa ubah ke path lain)
define('MK_FILE', __DIR__ . '/mikrotiks.json');

// helper: load mikrotik list
function load_mikrotiks()
{
    if (!file_exists(MK_FILE)) {
        return [];
    }
    $json = file_get_contents(MK_FILE);
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

// helper: save mikrotik list
function save_mikrotiks($arr)
{
    $json = json_encode(array_values($arr), JSON_PRETTY_PRINT);
    file_put_contents(MK_FILE, $json, LOCK_EX);
    @chmod(MK_FILE, 0600);
}

// pesan user
$message = "";

// Handle tambah
if (isset($_POST['add_mk'])) {
    $host  = trim($_POST['host'] ?? '');
    $user  = trim($_POST['mk_user'] ?? '');
    $pass  = trim($_POST['mk_pass'] ?? '');
    $label = trim($_POST['label'] ?? $host);
    $port  = trim($_POST['port'] ?? '8728');

    if ($host === '' || $user === '' || $pass === '') {
        $message = "❌ Isi semua field terlebih dahulu.";
    } else {
        $list = load_mikrotiks();
        $id = time() . rand(100, 999);
        $list[] = [
            'id' => $id,
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'label' => $label,
            'port' => $port,
            'created_at' => date('c')
        ];
        save_mikrotiks($list);
        $message = "✅ MikroTik ditambahkan.";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $list = load_mikrotiks();
    $new = array_filter($list, fn($it) => ($it['id'] ?? '') !== $id);
    save_mikrotiks(array_values($new));

    if (isset($_SESSION['router_id']) && $_SESSION['router_id'] === $id) {
        unset($_SESSION['router_id'], $_SESSION['router_host'], $_SESSION['router_user'], $_SESSION['router_pass'], $_SESSION['router_port']);
    }

    header("Location: mikrotiks.php");
    exit;
}

// Handle set aktif (verifikasi login MikroTik)
if (isset($_POST['set_active'])) {
    $id = $_POST['router_id'] ?? '';
    $list = load_mikrotiks();
    foreach ($list as $it) {
        if ($it['id'] === $id) {
            require_once 'routeros_api.class.php';
            $API = new RouterosAPI();
            $API->port = $it['port'] ?? 8728;

            if ($API->connect($it['host'], $it['user'], $it['pass'])) {
                $_SESSION['router_id']   = $it['id'];
                $_SESSION['router_host'] = $it['host'];
                $_SESSION['router_user'] = $it['user'];
                $_SESSION['router_pass'] = $it['pass'];
                $_SESSION['router_port'] = $it['port'];
                $API->disconnect();

                header("Location: index.php");
                exit;
            } else {
                $message = "❌ Gagal login ke MikroTik {$it['label']} ({$it['host']}:{$it['port']}).";
            }
        }
    }
}

// Handle edit
if (isset($_POST['edit_mk'])) {
    $id    = $_POST['id'] ?? '';
    $host  = trim($_POST['host'] ?? '');
    $user  = trim($_POST['mk_user'] ?? '');
    $pass  = trim($_POST['mk_pass'] ?? '');
    $label = trim($_POST['label'] ?? $host);
    $port  = trim($_POST['port'] ?? '8728');

    $list = load_mikrotiks();
    foreach ($list as &$it) {
        if ($it['id'] === $id) {
            $it['host'] = $host;
            $it['user'] = $user;
            $it['pass'] = $pass;
            $it['label'] = $label;
            $it['port'] = $port;
            $it['updated_at'] = date('c');
            break;
        }
    }
    save_mikrotiks($list);
    $message = "✏️ Router diperbarui.";
}

$mikrotiks = load_mikrotiks();

// edit data
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $eid = $_GET['edit_id'];
    foreach ($mikrotiks as $m) {
        if ($m['id'] === $eid) {
            $edit_data = $m;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Manage MikroTik - VPN Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-8">
<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage MikroTik</h1>
        <div class="flex items-center space-x-3">
            <a href="index.php" class="bg-gray-700 text-white px-3 py-2 rounded">Back</a>
            <a href="logout.php" class="bg-red-600 text-white px-3 py-2 rounded">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded bg-red-100 text-red-700"><?= $message ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form tambah / edit -->
        <div class="bg-white p-6 rounded shadow">
            <h2 class="text-lg font-semibold mb-4"><?= $edit_data ? 'Edit MikroTik' : 'Tambah MikroTik' ?></h2>
            <form method="POST" class="space-y-4">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_data['id']) ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium">Label (opsional)</label>
                    <input type="text" name="label" value="<?= htmlspecialchars($edit_data['label'] ?? '') ?>" class="w-full border rounded p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">IP / Host</label>
                    <input type="text" name="host" required value="<?= htmlspecialchars($edit_data['host'] ?? '') ?>" class="w-full border rounded p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">Username</label>
                    <input type="text" name="mk_user" required value="<?= htmlspecialchars($edit_data['user'] ?? '') ?>" class="w-full border rounded p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">Password</label>
                    <input type="text" name="mk_pass" required value="<?= htmlspecialchars($edit_data['pass'] ?? '') ?>" class="w-full border rounded p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">API Port</label>
                    <input type="number" name="port" value="<?= htmlspecialchars($edit_data['port'] ?? '8728') ?>" class="w-full border rounded p-2">
                </div>

                <div class="flex space-x-2">
                    <?php if ($edit_data): ?>
                        <button type="submit" name="edit_mk" class="bg-yellow-500 text-white px-4 py-2 rounded">Update</button>
                        <a href="mikrotiks.php" class="bg-gray-300 px-4 py-2 rounded">Batal</a>
                    <?php else: ?>
                        <button type="submit" name="add_mk" class="bg-blue-600 text-white px-4 py-2 rounded">Tambah</button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (isset($_SESSION['router_id'])): ?>
                <form method="POST" class="mt-4">
                    <button type="submit" name="unset_router"
                        class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Keluar dari Router (<?= htmlspecialchars($_SESSION['router_host']) ?>)
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-6 text-xs text-gray-500">
                <p>Catatan: data tersimpan di <code>mikrotiks.json</code>. Pastikan file ini memiliki permission aman.</p>
            </div>
        </div>

        <!-- List Mikrotik -->
        <div class="lg:col-span-2 bg-white p-6 rounded shadow">
            <h2 class="text-lg font-semibold mb-4">Daftar MikroTik</h2>

            <?php if (count($mikrotiks) === 0): ?>
                <div class="text-gray-500">Belum ada MikroTik terdaftar.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 text-left">Label</th>
                                <th class="p-2 text-left">Host</th>
                                <th class="p-2 text-left">Port</th>
                                <th class="p-2 text-left">User</th>
                                <th class="p-2 text-left">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mikrotiks as $m): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2"><?= htmlspecialchars($m['label'] ?? $m['host']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($m['host']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($m['port'] ?? '8728') ?></td>
                                    <td class="p-2"><?= htmlspecialchars($m['user']) ?></td>
                                    <td class="p-2">
                                        <div class="flex items-center gap-2">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="router_id" value="<?= htmlspecialchars($m['id']) ?>">
                                                <button type="submit" name="set_active" class="px-3 py-1 rounded <?= (isset($_SESSION['router_id']) && $_SESSION['router_id'] === $m['id']) ? 'bg-green-600 text-white' : 'bg-gray-200' ?>">
                                                    <?= (isset($_SESSION['router_id']) && $_SESSION['router_id'] === $m['id']) ? 'Aktif' : 'Pilih' ?>
                                                </button>
                                            </form>
                                            <a href="mikrotiks.php?edit_id=<?= $m['id'] ?>" class="px-3 py-1 rounded bg-yellow-400 text-white">Edit</a>
                                            <a href="mikrotiks.php?delete=<?= $m['id'] ?>" onclick="return confirm('Hapus router ini?')" class="px-3 py-1 rounded bg-red-600 text-white">Hapus</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (isset($_SESSION['router_id'])): ?>
                    <div class="mt-4 p-3 bg-blue-50 rounded">
                        Router aktif: <strong><?= htmlspecialchars($_SESSION['router_host'] ?? '') ?></strong> 
                        (port: <?= htmlspecialchars($_SESSION['router_port'] ?? '8728') ?>, 
                        user: <?= htmlspecialchars($_SESSION['router_user'] ?? '') ?>)
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
