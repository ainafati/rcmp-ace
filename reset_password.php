<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetapkan Kata Laluan Baharu</title>
    
    <script src="https:
    <style>
        @import url('https:
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .spinner { border-top-color: #ffffff; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    
    <div class="w-full max-w-sm bg-white p-8 rounded-xl shadow-2xl">
        <h2 class="text-3xl font-extrabold text-indigo-700 mb-4 text-center">Kata Laluan Baharu</h2>
        <p id="instructionText" class="text-gray-500 mb-8 text-center text-sm">Sila masukkan kod pengesahan, kemudian tetapkan kata laluan baharu.</p>

        <form id="resetForm">
            
            <input type="hidden" name="email" id="hiddenEmail">
            
            
            <div class="mb-5">
                
                <label for="otp" class="block text-sm font-medium text-gray-700 mb-2 text-left">Kod Pengesahan (OTP)</label>
                
                <input type="text" name="token" id="otp" placeholder="Masukkan kod yang diterima" required maxlength="6"
                       class="w-full text-center text-xl font-bold tracking-widest px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out shadow-sm">
            </div>

            
            <div class="flex justify-between items-center mb-6">
                
                <p id="otpTimerText" class="text-xs text-gray-500">Kod luput dalam <span id="cooldownDisplay">60s</span></p>

                 <button type="button" id="resendOtpButton" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 disabled:text-gray-400 disabled:cursor-not-allowed transition duration-150">
                    Hantar Semula Kod
                 </button>
            </div>

            
            <div class="mb-5">
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2 text-left">Kata Laluan Baharu</label>
                
                <input type="password" name="new_password" id="new_password" required minlength="6"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out shadow-sm">
            </div>

            
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

        
        <div id="messageContainer" class="mt-6 p-4 rounded-lg hidden text-left" role="alert">
            <p id="resultText" class="text-sm font-medium"></p>
        </div>
    </div>

    <script>
        
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

        
        async function resendOtp() {
            const email = hiddenEmailInput.value;
            if (!email) {
                displayMessage(false, "Sila pastikan e-mel wujud dalam URL sebelum menghantar semula.");
                return;
            }

            
            startResendCooldown();
            
            displayMessage(true, "Meminta kod pengesahan baharu...");
            otpInput.value = ''; 

            
            await new Promise(resolve => setTimeout(resolve, 2000)); 

            
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
            const token = otpInput.value; 

            
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

            
            resetButton.disabled = true;
            resendOtpButton.disabled = true;
            resetButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white spinner" xmlns="http:
            messageContainer.classList.add('hidden');
            
            
            const formData = new FormData();
            formData.append('email', email);
            formData.append('token', token); 
            formData.append('new_password', newPassword); 

            try {
                const response = await fetch(RESET_API_ENDPOINT, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    displayMessage(data.success, "Kata laluan berjaya ditetapkan! Mengarahkan ke Log Masuk...");
                    
                    
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

            
            resetButton.disabled = false; 
            resetButton.textContent = 'Tetapkan Kata Laluan';
            
            if (cooldownSeconds <= 0) {
                 resendOtpButton.disabled = false;
            }
        });
    </script>
</body>
</html>
