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
            background-image: radial-gradient(var(--accent) 0.5px, transparent 0.5px);
            background-size: 30px 30px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* --- NAVBAR RESPONSIVE --- */
        .navbar {
            background: var(--glass);
            backdrop-filter: blur(15px);
            margin: 15px auto;
            width: 92%;
            border-radius: 100px;
            padding: 10px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 15px;
            z-index: 1000;
            box-shadow: var(--shadow-premium);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            color: var(--primary);
        }

        .navbar-brand img { height: 35px; border-radius: 5px; }
        .navbar-brand span { font-size: 0.85rem; }

        .btn-login {
            background: var(--primary);
            color: white;
            padding: 8px 20px;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.3s;
        }

        /* --- HERO SECTION --- */
        .hero { padding: 40px 0; text-align: center; }

        .badge-ui {
            background: white;
            color: var(--accent);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            border: 1px solid rgba(0, 163, 201, 0.2);
        }

        .hero h1 {
            font-size: clamp(1.8rem, 7vw, 3.2rem);
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero h1 span { color: var(--accent); }

        /* --- MOCKUP SLIDER FIX --- */
        .mockup-container {
            position: relative;
            max-width: 850px;
            margin: 30px auto;
            border-radius: 20px;
            padding: 8px;
            background: white;
            box-shadow: var(--shadow-premium);
        }

        .mockup-slider {
            position: relative;
            width: 100%;
            overflow: hidden;
            border-radius: 15px;
            aspect-ratio: 16 / 9;
        }

        .mockup-img {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
        }

        .mockup-img.active { opacity: 1; }

        .slider-dots {
            margin-top: 15px;
            display: flex;
            justify-content: center; gap: 8px;
        }

        .dot {
            width: 8px; height: 8px;
            background: #cbd5e1;
            border-radius: 50%;
            cursor: pointer;
        }

        .dot.active { background: var(--accent); width: 25px; border-radius: 10px; }

        /* --- FEATURES GRID --- */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 50px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 25px;
            text-align: left;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            transition: 0.3s;
        }

        .card:hover { transform: translateY(-5px); }
        .card i { font-size: 2rem; color: var(--accent); margin-bottom: 15px; }

        /* --- FOOTER RESPONSIVE FIX --- */
        .footer-rcmp { 
            background: #0f172a; 
            color: white; 
            padding: 60px 0 0 0; 
            margin-top: 80px; 
        }

        .footer-grid { 
            display: grid; 
            grid-template-columns: 1fr; /* Mobile default */
            gap: 40px; 
            padding-bottom: 50px; 
        }

        .footer-col h4 { color: var(--accent); margin-bottom: 20px; font-size: 0.9rem; text-transform: uppercase; }
        .footer-col p { font-size: 0.85rem; opacity: 0.7; margin-bottom: 12px; }
        
        .social-links { display: flex; gap: 15px; }
        .social-links a { color: white; font-size: 1.4rem; transition: 0.3s; }
        .social-links a:hover { color: var(--accent); }

        /* FIX LOGO FOOTER MELETUP */
        .footer-logos {
            display: flex;
            justify-content: center;
        }

        .footer-logos img { 
            height: auto; 
            max-width: 180px; /* Hadkan saiz logo */
            width: 100%;
            border-radius: 8px; 
        }

        .footer-bottom { 
            background: #020617; 
            padding: 20px 0; 
            text-align: center; 
            font-size: 0.75rem; 
            opacity: 0.5; 
        }

        /* --- MEDIA QUERIES --- */
        @media (min-width: 768px) {
            .footer-grid { grid-template-columns: 2fr 1fr 1fr; }
            .footer-logos { justify-content: flex-end; }
            .footer-logos img { max-width: 200px; }
        }

        @media (max-width: 600px) {
            .navbar-brand span { display: none; }
            .navbar-brand::after { content: "NexCheck RCMP"; font-size: 0.8rem; }
            .hero { padding: 30px 0; }
            .footer-grid { text-align: center; justify-items: center; }
            .social-links { justify-content: center; }
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 33, 71, 0.75);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center; 
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
        }
        .btn-modal {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 25px;
        }
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
            <p>Seamlessly manage, audit, and track UniKL RCMP's IT assets with a high-precision ecosystem.</p>            
            
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
                    <p>Reserve laptops and projectors for your academic sessions in seconds.</p>
                </div>
                <div class="card">
                    <i class="fas fa-history"></i>
                    <h3>Loan History</h3>
                    <p>Keep track of all your previous and current asset borrowings.</p>
                </div>
                <div class="card">
                    <i class="fas fa-shield-virus"></i>
                    <h3>Asset Security</h3>
                    <p>Ensuring all equipment is verified before and after every loan.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="modal" id="disclaimerModal">
        <div class="modal-content">
            <i class="fas fa-id-badge" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
            <h2 style="color: var(--primary);">Important Notice</h2>
            <p style="color: var(--text-muted); margin-top: 10px;">
                All borrowed items must be collected and returned personally at the UniKL RCMP IT Department.
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
            &copy; 2025 Universiti Kuala Lumpur | All rights reserved
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