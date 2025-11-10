<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UniKL A.C.E. RCMP</title>
    <script src="https:
    <link rel="stylesheet" href="https:
    
    <style>
        /* Tetapkan palet warna UniKL sebagai custom CSS variables */
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

        /* Gantikan warna Tailwind Indigo dengan warna UniKL */
        /* Anda boleh menggunakan utiliti Tailwind dengan nilai heks/RGB secara terus, tetapi CSS variables memastikan konsistensi */
        .text-unikl-navy { color: var(--unikl-navy); }
        .bg-unikl-navy { background-color: var(--unikl-navy); }
        .hover\:bg-unikl-navy-dark:hover { background-color: #003a73; }
        .focus\:ring-unikl-orange:focus { --tw-ring-color: var(--unikl-orange); }
        .text-unikl-orange { color: var(--unikl-orange); }
        .text-unikl-blue-accent { color: var(--unikl-blue-accent); }
        .hover\:text-unikl-orange-light:hover { color: #ff9d40; }

        /* Gaya untuk spinner */
        .spinner { border-top-color: #ffffff; }
        
        /* Gaya untuk borang */
        .form-card {
            box-shadow: 0 15px 40px rgba(0, 33, 71, 0.2);
        }

        /* Override Tailwind border focus untuk input */
        input:focus {
            border-color: var(--unikl-orange) !important;
            box-shadow: 0 0 0 2px rgba(245, 130, 32, 0.25) !important;
        }

        /* Gaya Mesej Alert */
        .alert-success {
            background-color: #d1e7dd; /* Light Green */
            border-color: #badbcc;
            color: #0f5132;
        }
        .alert-error {
            background-color: #f8d7da; /* Light Red */
            border-color: #f5c2c7;
            color: #842029;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white p-8 rounded-xl form-card transition duration-300 transform hover:scale-[1.005]">
        
        <div class="flex flex-col items-center mb-6">
            
            <img src="assets/unikl-logo.png" alt="UniKL Logo" class="h-16 w-auto mb-4"> 
            <h2 class="text-3xl font-extrabold text-unikl-navy text-center" style="font-weight: 800;">
                Forgot Password
            </h2>
            <p class="text-gray-500 text-center text-sm font-medium mt-1">
                RCMP NexCheck Portal
            </p>
        </div>

        <p class="text-gray-600 mb-6 text-center text-sm font-medium">
            Enter your email address (Registered in RCMP's system) to receive a reset code (OTP).
        </p>

        <form id="forgotForm">
            <div class="mb-5">
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2 text-left">Email Address</label>
                <input type="email" name="email" id="email" placeholder="contoh@unikl.edu.my" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:border-unikl-orange transition duration-150 ease-in-out shadow-sm">
            </div>

            <button type="submit" id="submitButton"
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-bold text-white bg-unikl-navy hover:bg-unikl-navy-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-unikl-orange transition duration-150 ease-in-out transform hover:scale-[1.01] active:scale-95"
                    style="font-size: 16px;">
                Send Reset Code (OTP)
            </button>
        </form>

        <div id="messageContainer" class="mt-6 p-4 rounded-lg hidden text-left border" role="alert">
            <p id="resultText" class="text-sm font-semibold"></p>
        </div>

        <p class="mt-8 text-center text-sm text-gray-500">
            Remember your password? 
            <a href="login.php" class="font-bold text-unikl-blue-accent hover:text-unikl-orange transition duration-150">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Log In
            </a>
        </p>
    </div>

    <script>
        const API_URL = "forgot_password_api.php";
        const form = document.getElementById("forgotForm");
        const submitButton = document.getElementById("submitButton");
        const messageContainer = document.getElementById('messageContainer');
        const resultText = document.getElementById('resultText');
        const originalButtonText = 'Send Reset Code (OTP)';

        function displayMessage(isSuccess, message) {
            resultText.textContent = message;
            messageContainer.className = 'mt-6 p-4 rounded-lg text-left border'; 
            
            if (isSuccess) {
                messageContainer.classList.add('alert-success', 'border-green-500');
            } else {
                messageContainer.classList.add('alert-error', 'border-red-500');
            }
            messageContainer.classList.remove('hidden');

            
            setTimeout(() => {
                messageContainer.classList.add('hidden');
            }, 8000); 
        }

        form.addEventListener("submit", async function(e) {
            e.preventDefault();

            
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white spinner" xmlns="http:
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Sending...
            `;
            messageContainer.classList.add('hidden');

            const formData = new FormData(this);
            const email = formData.get('email');

            try {
                const res = await fetch(API_URL, {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(formData)
                });

                const data = await res.json();
                
                if (res.ok && data.success) {
                    displayMessage(true, data.message);
                    
                    
                    setTimeout(() => {
                        window.location.href = `verify_otp_form.php?email=${encodeURIComponent(email)}`;
                    }, 2000);

                } else {
                    const errorMessage = data.message || `Error ${res.status}: Connection failed.`;
                    displayMessage(false, `Failed: ${errorMessage}`); 
                }

            } catch (error) {
                console.error("Fetch Error:", error);
                displayMessage(false, `Error: Server connection failed. Please check the network.`);
            } finally {
                
                if (submitButton.disabled && messageContainer.classList.contains('alert-error')) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            }
        });
    </script>
</body>
</html>