<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - UniKL A.C.E. RCMP</title>
    <script src="https:
    <link rel="stylesheet" href="https:
    
    <style>
        /* Define UniKL color palette as custom CSS variables */
        @import url('https:
        
        :root {
            --unikl-navy: #002147; /* Primary Dark Navy Blue */
            --unikl-orange: #f58220; /* Secondary Orange */
            --unikl-blue-accent: #005a9c; /* Lighter Blue Accent */
            --light-bg: #f5f8ff; /* Very light blue/gray background */
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--light-bg); 
        }

        /* Map UniKL colors to Tailwind styles */
        .text-unikl-navy { color: var(--unikl-navy); }
        .bg-unikl-navy { background-color: var(--unikl-navy); }
        .hover\:bg-unikl-navy-dark:hover { background-color: #003a73; }
        .focus\:ring-unikl-orange:focus { --tw-ring-color: var(--unikl-orange); }
        .text-unikl-blue-accent { color: var(--unikl-blue-accent); }
        .hover\:text-unikl-orange:hover { color: var(--unikl-orange); }

        /* General styles */
        .form-card {
            box-shadow: 0 15px 40px rgba(0, 33, 71, 0.2);
        }
        .spinner { border-top-color: #ffffff; }

        /* Input focus styling using UniKL Orange */
        input:focus {
            border-color: var(--unikl-orange) !important;
            box-shadow: 0 0 0 2px rgba(245, 130, 32, 0.25) !important;
        }

        /* Message Alert Styles */
        .alert-success { background-color: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; }
        .alert-error { background-color: #f8d7da; border: 1px solid #f5c2c7; color: #842029; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-sm bg-white p-8 rounded-xl form-card transition duration-300">
        
        <div class="flex flex-col items-center mb-6">
            <img src="assets/unikl-logo.png" alt="UniKL Logo" class="h-14 w-auto mb-3"> 
            <h2 class="text-3xl font-extrabold text-unikl-navy text-center" style="font-weight: 800;">
                Set New Password
            </h2>
        </div>
        
        <p id="instructionText" class="text-gray-600 mb-6 text-center text-sm font-medium">
            Please enter the verification code and set your new password.
        </p>

        <form id="resetForm">
            <input type="hidden" name="email" id="hiddenEmail" value="<?= $email ?>">
            
            <div class="mb-5">
                <label for="otp" class="block text-sm font-semibold text-gray-700 mb-2 text-left">Verification Code (OTP)</label>
                <input type="text" name="token" id="otp" placeholder="Enter 6-digit code" required maxlength="6"
                        class="w-full text-center text-xl font-bold tracking-widest px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-unikl-orange transition duration-150 ease-in-out shadow-sm">
            </div>

            <div class="flex justify-between items-center mb-6">
                <p id="otpTimerText" class="text-xs text-gray-500 hidden">Code expires in <span id="cooldownDisplay" class="font-bold">60s</span></p>

                <button type="button" id="resendOtpButton" class="text-sm font-semibold text-unikl-blue-accent hover:text-unikl-orange disabled:text-gray-400 disabled:cursor-not-allowed transition duration-150 ml-auto">
                    Resend Code
                </button>
            </div>

            <div class="mb-5">
                <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2 text-left">New Password</label>
                <div class="relative">
                    <input type="password" name="new_password" id="new_password" required minlength="6"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-unikl-orange transition duration-150 ease-in-out shadow-sm pr-10">
                    <button type="button" id="toggleNewPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-unikl-blue-accent">
                        <i id="newPasswordIcon" class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="mb-5">
                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2 text-left">Confirm Password</label>
                <div class="relative">
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-unikl-orange transition duration-150 ease-in-out shadow-sm pr-10">
                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-unikl-blue-accent">
                        <i id="confirmPasswordIcon" class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="resetButton"
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-sm font-bold text-white bg-unikl-navy hover:bg-unikl-navy-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-unikl-orange transition duration-150 ease-in-out transform hover:scale-[1.01] active:scale-95">
                Set New Password
            </button>
        </form>

        <div id="messageContainer" class="mt-6 p-4 rounded-lg hidden text-left" role="alert">
            <p id="resultText" class="text-sm font-semibold"></p>
        </div>
        
        <p class="mt-6 text-center text-sm text-gray-500">
            <a href="login.php" class="font-bold text-unikl-blue-accent hover:text-unikl-orange transition duration-150">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Log In
            </a>
        </p>

    </div>

    <script>
        const form = document.getElementById("resetForm");
        const resetButton = document.getElementById("resetButton");
        const messageContainer = document.getElementById('messageContainer');
        const resultText = document.getElementById('resultText');
        const hiddenEmailInput = document.getElementById('hiddenEmail');
        const otpInput = document.getElementById('otp'); 
        const resendOtpButton = document.getElementById('resendOtpButton');
        const cooldownDisplay = document.getElementById('cooldownDisplay');
        const otpTimerText = document.getElementById('otpTimerText');
        const originalResetButtonText = 'Set New Password';
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        
        const toggleNewPasswordButton = document.getElementById('toggleNewPassword');
        const toggleConfirmPasswordButton = document.getElementById('toggleConfirmPassword');
        const newPasswordIcon = document.getElementById('newPasswordIcon');
        const confirmPasswordIcon = document.getElementById('confirmPasswordIcon');

        const RESEND_API_URL = 'forgot_password_api.php'; 
        const VERIFY_API_URL = 'verify_otp_api.php'; 

        let cooldownSeconds = 0; 
        
        function getUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);
            return {
                email: urlParams.get('email')
            };
        }

        function displayMessage(isSuccess, message) {
            resultText.textContent = message;
            messageContainer.className = 'mt-6 p-4 rounded-lg text-left'; 
            
            if (isSuccess) {
                messageContainer.classList.add('alert-success');
            } else {
                messageContainer.classList.add('alert-error');
            }
            messageContainer.classList.remove('hidden');

            setTimeout(() => {
                messageContainer.classList.add('hidden');
            }, 8000); 
        }
        
        function startResendCooldown() {
            cooldownSeconds = 60; 
            resendOtpButton.disabled = true;
            otpTimerText.classList.remove('hidden');

            cooldownDisplay.textContent = `${cooldownSeconds}s`;
            resendOtpButton.textContent = 'Waiting...';

            const timer = setInterval(() => {
                cooldownSeconds--;
                if (cooldownSeconds >= 0) {
                    cooldownDisplay.textContent = `${cooldownSeconds}s`;
                }
                
                if (cooldownSeconds < 0) {
                    clearInterval(timer);
                    resendOtpButton.disabled = false;
                    resendOtpButton.textContent = 'Resend Code';
                    otpTimerText.classList.add('hidden');
                }
            }, 1000);
        }

        async function resendOtp() {
            const email = hiddenEmailInput.value;
            if (!email) {
                displayMessage(false, "Session invalid. Email not detected.");
                return;
            }

            startResendCooldown();
            displayMessage(true, "Requesting new verification code...");
            otpInput.value = '';

            try {
                const resendData = new FormData();
                resendData.append('email', email);

                const response = await fetch(RESEND_API_URL, { 
                    method: 'POST',
                    body: resendData
                });

                const data = await response.json();

                if (data.success) {
                    displayMessage(true, data.message); 
                } else {
                    displayMessage(false, `Resend Failed: ${data.message}`);
                }

            } catch (error) {
                displayMessage(false, `Error: Failed to connect to OTP resend server. (${error.message})`);
            }
        }

        /**
         * Toggles the visibility of a password input field.
         * @param {HTMLElement} inputField - The password input element.
         * @param {HTMLElement} iconElement - The icon element (fa-eye/fa-eye-slash).
         */
        function togglePasswordVisibility(inputField, iconElement) {
            const type = inputField.getAttribute('type') === 'password' ? 'text' : 'password';
            inputField.setAttribute('type', type);
            
            
            if (type === 'text') {
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            } else {
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            }
        }

        
        document.addEventListener('DOMContentLoaded', () => {
            const params = getUrlParams();
            if (params.email) {
                hiddenEmailInput.value = params.email; 
                document.getElementById('instructionText').innerHTML = `Please enter the verification code and new password for <b>${params.email}</b>.`;
                startResendCooldown(); 
            } else {
                document.getElementById('instructionText').textContent = "Session invalid. Please go back to the forgot password page.";
                displayMessage(false, "Session invalid. Email not detected.");
                resendOtpButton.disabled = true; 
                otpTimerText.classList.add('hidden');
            }
        });

        
        toggleNewPasswordButton.addEventListener('click', (e) => {
            e.preventDefault();
            togglePasswordVisibility(newPasswordInput, newPasswordIcon);
        });

        toggleConfirmPasswordButton.addEventListener('click', (e) => {
            e.preventDefault();
            togglePasswordVisibility(confirmPasswordInput, confirmPasswordIcon);
        });

        
        resendOtpButton.addEventListener('click', resendOtp);

        
        form.addEventListener("submit", async function(e) {
            e.preventDefault();

            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            
            if (newPassword !== confirmPassword) {
                displayMessage(false, "New password and confirmation do not match.");
                return;
            }
            if (newPassword.length < 6) {
                displayMessage(false, "Password must be at least 6 characters long.");
                return;
            }
            if (otpInput.value.length < 6) { 
                displayMessage(false, "Please enter the complete 6-digit verification code.");
                return;
            }

            
            resetButton.disabled = true;
            resendOtpButton.disabled = true;
            resetButton.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white spinner" xmlns="http:
            messageContainer.classList.add('hidden');

            const formData = new FormData(form);
            
            try {
                const response = await fetch(VERIFY_API_URL, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Network error while processing the request.');
                }

                const data = await response.json();

                if (data.success) {
                    displayMessage(data.success, data.message + " Redirecting to Login...");
                    
                    setTimeout(() => {
                        window.location.href = `login.php`; 
                    }, 3000);

                } else {
                    displayMessage(data.success, `Failed: ${data.message}`); 
                }

            } catch (error) {
                displayMessage(false, `System Error: Failed to connect to server. (${error.message})`);
            }

            
            resetButton.disabled = false; 
            resetButton.textContent = originalResetButtonText;
            if (cooldownSeconds <= 0) {
                resendOtpButton.disabled = false;
            }
        });
    </script>
</body>
</html>