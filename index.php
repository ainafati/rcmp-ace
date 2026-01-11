<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NexCheck | IT Asset Reservation UniKL RCMP</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary: #002147;      /* Navy UniKL */
            --accent: #00A3C9;       /* Cyan IT */
            --bg-body: #f8fafc;      
            --text-main: #1e293b;
            --text-muted: #64748b;
            --glass: rgba(255, 255, 255, 0.9);
            --shadow-premium: 0 20px 50px -15px rgba(0, 33, 71, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6; 

            /* --- PATTERN IT CONNECTIVITY (LEBIH JELAS/POP UP) --- */
            background-image: 
                radial-gradient(var(--accent) 1px, transparent 1px), 
                radial-gradient(var(--accent) 1px, transparent 1px);
            background-size: 40px 40px;
            background-position: 0 0, 20px 20px;
            background-attachment: fixed;
        }

        /* Overlay supaya teks tak tenggelam dalam pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.85) 0%, rgba(248, 250, 252, 0.5) 100%);
            z-index: -1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            width: 100%;
        }

        /* Navbar */
        .navbar {
            background: var(--glass);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            margin: 20px auto;
            width: 90%;
            max-width: 1100px;
            border-radius: 100px;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 20px;
            z-index: 1000;
            box-shadow: var(--shadow-premium);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--primary);
            font-size: 0.85rem;
        }

        .navbar-brand img { height: 35px; border-radius: 5px; }

        .btn-login {
            background: var(--primary);
            color: white;
            padding: 10px 25px;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .btn-login:hover { background: var(--accent); transform: translateY(-2px); }

        /* Hero Section */
        .hero { padding: 60px 0; text-align: center; }

        .badge-ui {
            background: white;
            color: var(--accent);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 25px;
            text-decoration: none;
            border: 1px solid rgba(0, 163, 201, 0.3);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .hero h1 {
            font-size: clamp(2.2rem, 6vw, 3.5rem);
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
            margin-bottom: 25px;
        }

        .hero h1 span { color: var(--accent); }

        /* Mockup Slider */
        .mockup-container {
            position: relative;
            max-width: 950px;
            margin: 40px auto;
            border-radius: 30px;
            padding: 12px;
            background: white;
            box-shadow: var(--shadow-premium);
        }

        .mockup-slider {
            position: relative;
            width: 100%;
            overflow: hidden;
            border-radius: 20px;
            aspect-ratio: 16 / 9;
            background: #f1f5f9;
        }

        .mockup-img {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            z-index: 1;
        }

        .mockup-img.active { opacity: 1; z-index: 2; }

        .slider-dots {
            margin-top: 25px;
            display: flex;
            justify-content: center; gap: 12px;
        }

        .dot {
            width: 10px; height: 10px;
            background: #cbd5e1;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.3s;
        }

        .dot.active { background: var(--accent); width: 30px; border-radius: 10px; }

        /* Features Section */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 60px;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: 0.4s;
            text-align: left;
            border-bottom: 4px solid transparent;
        }

        .card:hover { transform: translateY(-10px); border-color: var(--accent); }
        .card i { font-size: 2.5rem; color: var(--accent); margin-bottom: 20px; }
        .card h3 { color: var(--primary); margin-bottom: 10px; }

        /* Footer */
        .footer-rcmp { background: #0f172a; color: white; padding: 80px 0 0 0; margin-top: 100px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 40px; padding-bottom: 60px; }
        .footer-col h4 { color: var(--accent); margin-bottom: 25px; font-size: 0.9rem; text-transform: uppercase; }
        .footer-col p { font-size: 0.85rem; opacity: 0.7; margin-bottom: 15px; }
        .social-links a { color: white; font-size: 1.5rem; margin-right: 20px; transition: 0.3s; }
        .social-links a:hover { color: var(--accent); }
        .footer-logos img { height: 70px; border-radius: 8px; }
        .footer-bottom { background: #020617; padding: 25px 0; text-align: center; font-size: 0.75rem; opacity: 0.5; }

        /* MODAL POPUP */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 33, 71, 0.7);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center; 
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 45px;
            border-radius: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .btn-modal {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: 0.3s;
        }
        .btn-modal:hover { background: var(--accent); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <img src="img/Logo-UniKL-PCM.jpg" alt="Logo UniKL">
            <span>NexCheck | RCMP Reservation Check System</span>
        </div>
        <a href="login.php" class="btn-login">Login</a>
    </nav>

    <section class="hero">
        <div class="container">
            <a href="https://rcmp.unikl.edu.my/" target="_blank" class="badge-ui">
                <i class="fas fa-network-wired"></i> UniKL RCMP OFFICIAL
            </a>
<h1>Precision Inventory Tracking <br>for <span>UniKL RCMP.</span></h1>
<p>Seamlessly manage, audit, and track UniKL RCMP's IT assets with a high-precision ecosystem designed for accountability.</p>            
            <div class="mockup-container">
                <div class="mockup-slider">
                    <img src="img/view.png" class="mockup-img active" alt="Dashboard 1">
                    <img src="img/unikl.jpg" class="mockup-img" alt="Dashboard 2">
                    <img src="img/uniklrcmp.png" class="mockup-img" alt="Dashboard 3">
                </div>
                <div class="slider-dots">
                    <span class="dot active" onclick="currentSlide(0)"></span>
                    <span class="dot" onclick="currentSlide(1)"></span>
                    <span class="dot" onclick="currentSlide(2)"></span>
                </div>
            </div>

            <div class="features-grid">
                <div class="card">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Instant Booking</h3>
                    <p>Reserve laptops, projectors, and peripherals for your academic sessions in seconds.</p>
                </div>
                <div class="card">
                    <i class="fas fa-history"></i>
                    <h3>Loan History</h3>
                    <p>Keep track of all your previous and current asset borrowings with detailed timestamps.</p>
                </div>
                <div class="card">
                    <i class="fas fa-shield-virus"></i>
                    <h3>Asset Security</h3>
                    <p>Ensuring all equipment is verified and in top condition before and after every loan.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="modal" id="disclaimerModal">
        <div class="modal-content">
            <i class="fas fa-id-badge" style="font-size: 3.5rem; color: var(--accent); margin-bottom: 25px;"></i>
            <h2 style="color: var(--primary);">Important Notice</h2>
            <p style="color: var(--text-muted); margin-top: 15px;">
                All borrowed items must be collected and returned personally at the UniKL RCMP IT Department office.
            </p>
            <button onclick="closeModal()" class="btn-modal">I UNDERSTAND</button>
        </div>
    </div>

    <footer class="footer-rcmp">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4>UniKL RCMP Ipoh</h4>
                    <p><i class="fas fa-map-marker-alt"></i> No.3, Jalan Greentown, 30450 Ipoh, Perak</p>
                    <p><i class="fas fa-phone"></i> 1 300-22-7267</p>
                    <p><i class="fas fa-envelope"></i> helpdesk.rcmp@unikl.edu.my</p>
                </div>
                <div class="footer-col">
                    <h4>Follow Us</h4>
                    <div class="social-links">
                        <a href="https://www.facebook.com/UniKLRoyalCollegeofMedicinePerak/"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/uniklrcmp/"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.youtube.com/channel/UCugrLnQZro3__D4R3x-bqRg"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-col footer-logos">
                    <img src="img/Logo-UniKL-PCM.jpg" alt="Logo RCMP">
                </div>
            </div>
        </div>
        <div class="footer-bottom">
© 2025 Universiti Kuala Lumpur | All rights reserved | Legal | Site Map
        </div>
    </footer>

    <script>
        // MODAL LOGIC
        const modal = document.getElementById('disclaimerModal');
        
        window.onload = () => {
            // Nota: Guna Incognito tab kalau nak test modal keluar berkali-kali
            if (!sessionStorage.getItem('noticeShown')) {
                setTimeout(() => {
                    modal.classList.add('active');
                }, 1200);
            }
        };

        function closeModal() {
            modal.classList.remove('active');
            sessionStorage.setItem('noticeShown', 'true');
        }

        // SLIDER LOGIC
        let currentStep = 0;
        const slides = document.querySelectorAll('.mockup-img');
        const dots = document.querySelectorAll('.dot');

        function showSlide(index) {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => d.classList.remove('active'));
            slides[index].classList.add('active');
            dots[index].classList.add('active');
            currentStep = index;
        }

        function nextSlide() {
            currentStep = (currentStep + 1) % slides.length;
            showSlide(currentStep);
        }

        function currentSlide(index) {
            showSlide(index);
        }

        setInterval(nextSlide, 5000);
    </script>
</body>
</html>