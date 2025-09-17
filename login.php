<?php
session_start();

// ganti sesuai kebutuhan
$valid_user = "admin";
$valid_pass = "12345";

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? "";
    $password = $_POST['password'] ?? "";

    if ($username === $valid_user && $password === $valid_pass) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    } else {
        $message = "❌ Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - VPN Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 h-screen flex items-center justify-center">

    <div class="grid grid-cols-2 w-[1000px] h-[600px] bg-white shadow-lg rounded-lg overflow-hidden">
        
        <!-- Kolom kiri (Login) -->
        <div class="p-10 flex flex-col justify-center bg-gray-50">
            <h1 class="text-2xl font-bold mb-6 text-center">Login VPN Manager</h1>

            <?php if ($message): ?>
                <div class="p-3 mb-4 text-red-600 bg-red-100 rounded">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Username</label>
                    <input type="text" name="username" required class="w-full border rounded p-2 focus:ring focus:ring-blue-200">
                </div>
                <div>
                    <label class="block text-sm font-medium">Password</label>
                    <input type="password" name="password" required class="w-full border rounded p-2 focus:ring focus:ring-blue-200">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                    Login
                </button>
            </form>
        </div>

        <!-- Kolom kanan (Konten lain, bebas isi gambar/teks) -->
        <div class="bg-blue-600 text-white flex flex-col justify-center items-center p-10">
            <h2 class="text-3xl font-bold mb-4">Selamat Datang!</h2>
            <p class="text-lg text-center">
                Kelola VPN Anda dengan mudah, cepat, dan aman melalui VPN Manager.
            </p>
        </div>
    </div>

</body>
</html>
