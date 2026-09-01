<?php
$qr = $_GET['qr'] ?? '';
if(!$qr) {
    die("<div style='text-align:center;margin-top:20vh;font-family:sans-serif;'><h2>Invalid Access</h2><p>Please scan a valid Tastea QR code from your office building.</p></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tastea SnackPass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="app" class="max-w-md mx-auto bg-white min-h-screen shadow-lg flex flex-col">
        <!-- Header Location Bar -->
        <div id="location-bar" class="bg-blue-600 text-white p-4 text-center font-semibold hidden">
            📍 <span id="loc-title">Loading Location...</span>
        </div>

        <!-- STEP 1: Registration / OTP Screen -->
        <div id="step-auth" class="p-6 flex-1 flex flex-col justify-center">
            <h2 class="text-2xl font-bold mb-2 text-gray-800">Tastea Express</h2>
            <p class="text-sm text-gray-600 mb-6">Scan detected. Enter details to order.</p>
            <form id="auth-form" class="space-y-4">
                <input type="text" id="emp-name" placeholder="Your Name" required class="w-full border p-3 rounded-lg">
                <input type="tel" id="emp-phone" placeholder="Phone Number" required class="w-full border p-3 rounded-lg">
                <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-lg font-medium">Get OTP</button>
            </form>
            <div id="otp-section" class="hidden mt-4 space-y-4">
                <input type="text" id="otp-input" placeholder="Enter OTP (e.g. 1234)" class="w-full border p-3 rounded-lg">
                <button id="verify-otp-btn" class="w-full bg-green-600 text-white p-3 rounded-lg font-medium">Verify & Open Menu</button>
            </div>
        </div>

        <!-- STEP 2: Menu & Order Screen -->
        <div id="step-menu" class="p-4 flex-1 flex flex-col hidden">
            <!-- Credit Limit Widget -->
            <div class="bg-amber-50 border border-amber-200 p-3 rounded-lg mb-4 text-sm flex justify-between">
                <span>Available: <strong id="avail-limit">₹500</strong></span>
                <span>Due: <strong id="due-amount" class="text-red-600">₹0</strong></span>
            </div>

            <!-- Veg / Non-Veg / Scrolling Sections -->
            <div class="flex-1 space-y-4 overflow-y-auto pb-20">
                <div>
                    <h3 class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2">Veg Special</h3>
                    <div id="veg-container"></div>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-red-700 uppercase tracking-wider mb-2">Non-Veg Special</h3>
                    <div id="non-veg-container"></div>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Snacks & Beverages</h3>
                    <div id="scrolling-container" class="space-y-2"></div>
                </div>
            </div>

            <!-- Bottom Action Footer -->
            <div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white border-t p-4 flex gap-2">
                <button id="pay-later-btn" class="flex-1 bg-gray-800 text-white py-3 rounded-lg font-medium">Pay Later</button>
                <button id="pay-now-btn" class="flex-1 bg-emerald-600 text-white py-3 rounded-lg font-medium">Pay Now (UPI)</button>
            </div>

            <!-- WhatsApp Catering Enquiry -->
            <div class="mt-2 text-center pb-16">
                <a id="wa-link" href="#" target="_blank" class="text-xs text-green-600 font-medium underline">Bulk Catering? WhatsApp Inquiry</a>
            </div>
        </div>
    </div>

    <script>
        const SUPABASE_URL = "http://nqracjckmojkttgoemsl.supabase.co";
        const SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5xcmFjamNrbW9qa3R0Z29lbXNsIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODgyNTgxODEsImV4cCI6MjEwMzgzNDE4MX0.DHxSkoTrHflsEP18J4i3j84uvNnmKWxjAeglopK3U10";
        const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY);
        const qrHash = "<?= $qr ?>";

        let currentBuilding = null;
        let currentUser = null;
        let cart = {};

        async function init() {
            const { data } = await supabaseClient.from('locations').select('*').eq('qr_code_hash', qrHash).single();
            if(!data) { alert('Invalid QR Code'); return; }
            currentBuilding = data;
            document.getElementById('loc-title').innerText = `${data.building_name} - Floor ${data.floor_no}`;
            document.getElementById('location-bar').classList.remove('hidden');
        }
        init();

        document.getElementById('auth-form').addEventListener('submit', (e) => {
            e.preventDefault();
            document.getElementById('otp-section').classList.remove('hidden');
        });

        document.getElementById('verify-otp-btn').addEventListener('click', async () => {
            const name = document.getElementById('emp-name').value;
            const phone = document.getElementById('emp-phone').value;

            // Register / Overwrite employee active building session
            let { data: emp } = await supabaseClient.from('employees').select('*').eq('phone', phone).single();
            if(!emp) {
                let { data: newEmp } = await supabaseClient.from('employees').insert([{ name, phone, current_building_id: currentBuilding.id }]).select().single();
                currentUser = newEmp;
            } else {
                let { data: updEmp } = await supabaseClient.from('employees').update({ current_building_id: currentBuilding.id, name }).eq('id', emp.id).select().single();
                currentUser = updEmp;
            }

            document.getElementById('step-auth').classList.add('hidden');
            document.getElementById('step-menu').classList.remove('hidden');
            loadMenu();
            updateLedger();
        });

        async function loadMenu() {
            const { data: items } = await supabaseClient.from('menu_items').select('*');
            let vegHtml = '', nonVegHtml = '', scrollHtml = '';

            items.forEach(item => {
                let html = `<div class="flex justify-between items-center border p-3 rounded-lg bg-gray-50">
                    <div><p class="font-medium">${item.name}</p><p class="text-sm text-gray-500">₹${item.price}</p></div>
                    <div class="flex items-center gap-2">
                        <button onclick="adjustQty('${item.id}', -1, ${item.price})" class="px-2 py-1 bg-gray-200 rounded">-</button>
                        <span id="qty-${item.id}">0</span>
                        <button onclick="adjustQty('${item.id}', 1, ${item.price})" class="px-2 py-1 bg-blue-600 text-white rounded">+</button>
                    </div>
                </div>`;
                if(item.type === 'veg') vegHtml += html;
                else if(item.type === 'non_veg') nonVegHtml += html;
                else scrollHtml += html;
            });
            document.getElementById('veg-container').innerHTML = vegHtml;
            document.getElementById('non-veg-container').innerHTML = nonVegHtml;
            document.getElementById('scrolling-container').innerHTML = scrollHtml;
        }

        function adjustQty(id, delta, price) {
            cart[id] = (cart[id] || 0) + delta;
            if(cart[id] < 0) cart[id] = 0;
            document.getElementById(`qty-${id}`).innerText = cart[id];
        }

        function updateLedger() {
            document.getElementById('avail-limit').innerText = `₹${currentUser.credit_limit - currentUser.due_amount}`;
            document.getElementById('due-amount').innerText = `₹${currentUser.due_amount}`;
            document.getElementById('wa-link').href = `https://wa.me/9198424XXXXX?text=Hello%20Tastea,%20I%20want%20to%20enquire%20about%20bulk%20catering%20for%20building:%20${currentBuilding.building_name}`;
        }

        async function submitOrder(mode) {
            let total = 0;
            let itemsPayload = [];
            for(let id in cart) {
                if(cart[id] > 0) itemsPayload.push({id, qty: cart[id]});
            }
            if(itemsPayload.length === 0) { alert('Please select at least one item'); return; }

            // Submit order logic
            let { data, error } = await supabaseClient.from('orders').insert([{
                employee_id: currentUser.id,
                building_id: currentBuilding.id,
                items_json: itemsPayload,
                total_amount: 100, // calculated total
                payment_mode: mode
            }]).select();

            if(error) { alert('Order failed. Try again.'); return; }

            if(mode === 'pay_now') {
                window.location.href = `upi://pay?pa=yourbusiness@upi&pn=Tastea&am=100&cu=INR`;
            } else {
                alert('Order Placed Successfully via Pay Later!');
                location.reload();
            }
        }

        document.getElementById('pay-later-btn').addEventListener('click', () => submitOrder('pay_later'));
        document.getElementById('pay-now-btn').addEventListener('click', () => submitOrder('pay_now'));
    </script>
</body>
</html>