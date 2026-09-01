<?php
// Simple configuration and Supabase REST credentials placeholder
// Replace with your actual Supabase URL and Service/Anon Key
$supabase_url = "http://nqracjckmojkttgoemsl.supabase.co";
$supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5xcmFjamNrbW9qa3R0Z29lbXNsIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODgyNTgxODEsImV4cCI6MjEwMzgzNDE4MX0.DHxSkoTrHflsEP18J4i3j84uvNnmKWxjAeglopK3U10";

function callSupabase($endpoint, $method = 'GET', $data = []) {
    global $supabase_url, $supabase_key;
    $ch = curl_init("$supabase_url/rest/v1/$endpoint");
    $headers = [
        "apikey: $supabase_key",
        "Authorization: Bearer $supabase_key",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($method == 'POST' || $method == 'PATCH') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_location'])) {
        $hash = md5($_POST['building_name'] . $_POST['floor_no'] . $_POST['block_no'] . time());
        callSupabase('locations', 'POST', [
            'building_name' => $_POST['building_name'],
            'floor_no' => $_POST['floor_no'],
            'block_no' => $_POST['block_no'],
            'qr_code_hash' => $hash
        ]);
    } elseif (isset($_POST['add_menu'])) {
        callSupabase('menu_items', 'POST', [
            'name' => $_POST['menu_name'],
            'type' => $_POST['menu_type'],
            'price' => $_POST['price'],
            'is_daily_special' => isset($_POST['is_special']) ? true : false,
            'available_stock' => $_POST['stock']
        ]);
    }
}

$locations = callSupabase('locations?select=*');
$menu = callSupabase('menu_items?select=*');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tastea SnackPass - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-4xl mx-auto space-y-8">
        <h1 class="text-2xl font-bold text-gray-800">Tastea Admin Dashboard</h1>

        <!-- Add Location & Generate QR -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4">Add Building / Location & Generate QR</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="text" name="building_name" placeholder="Building Name" required class="border p-2 rounded">
                <input type="text" name="floor_no" placeholder="Floor Number" required class="border p-2 rounded">
                <input type="text" name="block_no" placeholder="Block Number" required class="border p-2 rounded">
                <button type="submit" name="add_location" class="md:col-span-3 bg-blue-600 text-white p-2 rounded font-medium">Create Location & QR</button>
            </form>
        </div>

        <!-- Locations & QR Display -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4">Active QR Codes</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if(is_array($locations)) foreach($locations as $loc): ?>
                    <div class="border p-4 rounded flex items-center justify-between">
                        <div>
                            <p class="font-bold"><?= htmlspecialchars($loc['building_name']) ?></p>
                            <p class="text-sm text-gray-600">Floor: <?= htmlspecialchars($loc['floor_no']) ?> | Block: <?= htmlspecialchars($loc['block_no']) ?></p>
                        </div>
                        <div id="qrcode-<?= $loc['qr_code_hash'] ?>" class="my-2"></div>
                        <script>
                            new QRCode(document.getElementById("qrcode-<?= $loc['qr_code_hash'] ?>"), {
                                text: "https://yourdomain.com/index.php?qr=<?= $loc['qr_code_hash'] ?>",
                                width: 80, height: 80
                            });
                        </script>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Add Menu Item -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4">Add Menu Item</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="menu_name" placeholder="Item Name" required class="border p-2 rounded">
                <select name="menu_type" class="border p-2 rounded">
                    <option value="veg">Veg Special</option>
                    <option value="non_veg">Non-Veg Special</option>
                    <option value="other">Scrolling Item</option>
                </select>
                <input type="number" step="0.01" name="price" placeholder="Price" required class="border p-2 rounded">
                <input type="number" name="stock" placeholder="Stock Qty" value="100" required class="border p-2 rounded">
                <label class="flex items-center space-x-2"><input type="checkbox" name="is_special"> Daily Main Special</label>
                <button type="submit" name="add_menu" class="md:col-span-4 bg-green-600 text-white p-2 rounded font-medium">Save Menu Item</button>
            </form>
        </div>
    </div>
</body>
</html>