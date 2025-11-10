<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetapkan Kata Laluan Baharu</title>
    <!-- Muatkan Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .spinner { border-top-color: #ffffff; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <!-- Card Utama -->
    <div class="w-full max-w-sm bg-white p-8 rounded-xl shadow-2xl">
        <h2 class="text-3xl font-extrabold text-indigo-700 mb-4 text-center">Kata Laluan Baharu</h2>
        <p id="instructionText" class="text-gray-500 mb-8 text-center text-sm">Sila masukkan kod pengesahan, kemudian tetapkan kata laluan baharu.</p>

        <form id="resetForm">
            <!-- Input Tersembunyi untuk Email (WAJIB ADA dari URL) -->
            <input type="hidden" name="email" id="hiddenEmail">
            
            <!-- Medan OTP BARU -->
            <div class="mb-5">
                <!-- PENTING: Peringatan untuk pengguna tentang OTP yang betul, yang akan datang dari DB / e-mel sebenar -->
                <label for="otp" class="block text-sm font-medium text-gray-700 mb-2 text-left">Kod Pengesahan (OTP)</label>
                <!-- Nama medan di sini ialah 'token' -->
                <input type="text" name="token" id="otp" placeholder="Masukkan kod yang diterima" required maxlength="6"
                       class="w-full text-center text-xl font-bold tracking-widest px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out shadow-sm">
            </div>

            <!-- MASA LUPUT OTP & Butang HANTAR SEMULA OTP BAHARU -->
            <div class="flex justify-between items-center mb-6">
                <!-- Tambah info masa luput -->
                <p id="otpTimerText" class="text-xs text-gray-500">Kod luput dalam <span id="cooldownDisplay">60s</span></p>

                 <button type="button" id="resendOtpButton" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 disabled:text-gray-400 disabled:cursor-not-allowed transition duration-150">
                    Hantar Semula Kod
                 </button>
            </div>

            <!-- Medan Kata Laluan Baharu -->
            <div class="mb-5">
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2 text-left">Kata Laluan Baharu</label>
                <!-- Tambah nama 'new_password' untuk dihantar ke PHP -->
                <input type="password" name="new_password" id="new_password" required minlength="6"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out shadow-sm">
            </div>

            <!-- Medan Sahkan Kata Laluan -->
            <div class="mb-5">
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2 text-left">Sahkan Kata Laluan</label>
                <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out shadow-sm">
            </div>

            <button type="submit" id="resetButton"
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out transform hover:scale-[1.01] active:scale-95">
                Tetapkan Kata Laluan
            </button>
        </form>

        <!-- Container Mesej -->
        <div id="messageContainer" class="mt-6 p-4 rounded-lg hidden text-left" role="alert">
            <p id="resultText" class="text-sm font-medium"></p>
        </div>
    </div>

    <script>
        // Gunakan nama fail PHP anda untuk pemprosesan, atau ganti dengan fail sebenar anda.
        const RESET_API_ENDPOINT = 'verify_otp_form.php'; 

        const form = document.getElementById("resetForm");
        const resetButton = document.getElementById("resetButton");
        const messageContainer = document.getElementById('messageContainer');
        const resultText = document.getElementById('resultText');
        const hiddenEmailInput = document.getElementById('hiddenEmail');
        const otpInput = document.getElementById('otp'); 
        const resendOtpButton = document.getElementById('resendOtpButton');
        const cooldownDisplay = document.getElementById('cooldownDisplay');
        const otpTimerText = document.getElementById('otpTimerText'); 

        let cooldownSeconds = 0; 
        
        function getUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);
            return {
                email: urlParams.get('email')
            };
        }

        function startResendCooldown() {
            // Kita gunakan 60 saat sebagai cooldown hantar semula
            cooldownSeconds = 60; 
            resendOtpButton.disabled = true;
            otpTimerText.classList.remove('hidden'); 

            cooldownDisplay.textContent = `${cooldownSeconds}s`;
            resendOtpButton.textContent = 'Menunggu...';

            const timer = setInterval(() => {
                cooldownSeconds--;
                if (cooldownSeconds >= 0) {
                    cooldownDisplay.textContent = `${cooldownSeconds}s`;
                }
                
                if (cooldownSeconds < 0) {
                    clearInterval(timer);
                    resendOtpButton.disabled = false;
                    resendOtpButton.textContent = 'Hantar Semula Kod';
                    otpTimerText.classList.add('hidden'); 
                }
            }, 1000);
        }

        // Fungsi Simulasi untuk Hantar Semula OTP (boleh diabaikan buat masa ini)
        async function resendOtp() {
            const email = hiddenEmailInput.value;
            if (!email) {
                displayMessage(false, "Sila pastikan e-mel wujud dalam URL sebelum menghantar semula.");
                return;
            }

            // Mulakan Pemasa Cooldown
            startResendCooldown();
            
            displayMessage(true, "Meminta kod pengesahan baharu...");
            otpInput.value = ''; 

            // --- SIMULASI PANGGILAN API UNTUK HANTAR SEMULA ---
            await new Promise(resolve => setTimeout(resolve, 2000)); 

            // Di sini, dalam sistem sebenar, anda akan memanggil API 'resend-otp'
            displayMessage(true, `Kod pengesahan baharu telah dihantar ke ${email}.`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const params = getUrlParams();
            if (params.email) {
                hiddenEmailInput.value = params.email; 
                document.getElementById('instructionText').innerHTML = `Sila masukkan kod pengesahan dan kata laluan baharu untuk <b>${params.email}</b>.`;
                startResendCooldown(); 
            } else {
                displayMessage(false, "Sesi tidak sah. E-mel tidak dikesan.");
                resendOtpButton.disabled = true; 
                otpTimerText.classList.add('hidden');
            }
        });

        resendOtpButton.addEventListener('click', resendOtp);

        function displayMessage(isSuccess, message) {
            resultText.textContent = message;
            messageContainer.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
            
            if (isSuccess) {
                messageContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
            } else {
                messageContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
            }
            messageContainer.classList.remove('hidden');
        }

        form.addEventListener("submit", async function(e) {
            e.preventDefault();

            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const email = hiddenEmailInput.value;
            const token = otpInput.value; // Ambil nilai token/OTP

            // Semakan kata laluan sisi klien
            if (newPassword !== confirmPassword) {
                displayMessage(false, "Kata laluan baharu dan pengesahan tidak sepadan.");
                return;
            }
            if (newPassword.length < 6) {
                displayMessage(false, "Kata laluan mesti sekurang-kurangnya 6 aksara.");
                return;
            }
            if (token.length < 6) { 
                 displayMessage(false, "Sila masukkan kod pengesahan yang lengkap.");
                return;
            }

            // Lumpuhkan butang semasa memproses
            resetButton.disabled = true;
            resendOtpButton.disabled = true;
            resetButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Mengemas kini...';
            messageContainer.classList.add('hidden');
            
            // Siapkan data untuk dihantar ke PHP
            const formData = new FormData();
            formData.append('email', email);
            formData.append('token', token); // Hantar sebagai 'token'
            formData.append('new_password', newPassword); // Hantar kata laluan baru

            try {
                const response = await fetch(RESET_API_ENDPOINT, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    displayMessage(data.success, "Kata laluan berjaya ditetapkan! Mengarahkan ke Log Masuk...");
                    
                    // PENGARAHAN AKHIR: Alihkan ke halaman log masuk
                    setTimeout(() => {
                        window.location.href = `login.html`; 
                    }, 3000);

                } else {
                    displayMessage(data.success, `Gagal: ${data.message}`); 
                }

            } catch (error) {
                console.error('Error during password reset:', error);
                displayMessage(false, "Ralat rangkaian atau pelayan. Sila cuba lagi.");
            }

            // Sentiasa pulihkan butang
            resetButton.disabled = false; 
            resetButton.textContent = 'Tetapkan Kata Laluan';
            // Pulihkan butang hantar semula hanya jika pemasa sudah tamat
            if (cooldownSeconds <= 0) {
                 resendOtpButton.disabled = false;
            }
        });
    </script>
</body>
</html>
