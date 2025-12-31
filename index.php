<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NexCheck | Intelligence Asset Management</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary: #002147;      /* Navy RCMP */
            --accent: #00A3C9;       /* Cyan RCMP */
            --bg-body: #f8fafc;      /* Background bersih */
            --text-main: #1e293b;
            --text-muted: #64748b;
            --glass: rgba(255, 255, 255, 0.9);
            --shadow-premium: 0 20px 40px -10px rgba(0, 33, 71, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.6; 
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            width: 100%;
        }

        /* Navbar Pill */
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
            font-size: 1rem;
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
        .hero { padding: 80px 0; text-align: center; }

        .badge-ui {
            background: rgba(0, 163, 201, 0.1);
            color: var(--accent);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 25px;
            letter-spacing: 1px;
        }

        .hero h1 {
            font-size: clamp(2.2rem, 6vw, 4rem);
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
            margin-bottom: 25px;
            letter-spacing: -1px;
        }

        .hero h1 span { color: var(--accent); }

        .hero p {
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 50px;
            font-size: 1.1rem;
        }

        /* Mockup Display */
        .mockup-img {
            width: 200%;
            max-width: 950px;
            border-radius: 25px;
            box-shadow: 0 40px 100px -20px rgba(0, 33, 71, 0.2);
            border: 8px solid white;
            transition: 0.5s;
        }

        /* Features Section */
        .features-wrapper { background: white; padding: 100px 0; margin-top: 50px; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .card {
            background: #ffffff;
            padding: 50px 40px;
            border-radius: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: var(--shadow-premium);
            transition: 0.4s ease;
            text-align: left;
        }

        .card:hover {
            transform: translateY(-12px);
            border-color: var(--accent);
            box-shadow: 0 30px 60px -10px rgba(0, 163, 201, 0.15);
        }

        .card i {
            font-size: 2.8rem;
            color: var(--accent);
            margin-bottom: 25px;
            display: block;
        }

        .card h3 { margin-bottom: 15px; font-size: 1.4rem; color: var(--primary); }

        /* FOOTER KORPORAT (NEW) */
        .footer-rcmp {
            background: #1a1a1a;
            color: white;
            padding: 60px 0 0 0;
            margin-top: 50px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 40px;
            padding-bottom: 50px;
        }

        .footer-col h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-col p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .footer-col i { color: var(--accent); margin-top: 3px; }

        .social-links { display: flex; gap: 15px; margin-top: 20px; }
        .social-links a { 
            color: white; font-size: 1.2rem; opacity: 0.7; transition: 0.3s; 
        }
        .social-links a:hover { opacity: 1; color: var(--accent); }

        .footer-logos { text-align: right; }
        .footer-logos img.main-logo { height: 65px; margin-bottom: 25px; border-radius: 5px; }
        
        .cert-icons { 
            display: flex; justify-content: flex-end; gap: 15px; flex-wrap: wrap; 
        }
        .cert-icons img { 
            height: 45px; background: rgba(255,255,255,0.05); padding: 5px; border-radius: 5px; 
        }

        .footer-bottom {
            background: #000033;
            padding: 20px 0;
            text-align: center;
            font-size: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            opacity: 0.8;
        }

        /* Modal Premium */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 33, 71, 0.2);
            backdrop-filter: blur(12px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 60px 40px;
            border-radius: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 50px 100px rgba(0,0,0,0.1);
            transform: scale(0.9);
            transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .modal.active { display: flex; }
        .modal.active .modal-content { transform: scale(1); }

        .btn-modal {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px 40px;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: 0.3s;
        }

        @media (max-width: 992px) {
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .footer-logos { text-align: left; }
            .cert-icons { justify-content: flex-start; }
        }

        @media (max-width: 768px) {
            .container { padding: 0 25px; }
            .hero h1 { font-size: 2.5rem; }
            .navbar { width: 95%; padding: 10px 20px; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <img src="img/Logo-UniKL-PCM.jpg" alt="Logo">
            <span>NexCheck | RCMP Inventory Reservation Check System</span>
        </div>
        <a href="login.php" class="btn-login">Login</a>
    </nav>

    <section class="hero">
        <div class="container">
<a href="https://rcmp.unikl.edu.my/" target="_blank" class="badge-ui" style="text-decoration: none;">
    <i class="fas fa-shield-alt"></i> UniKL RCMP OFFICIAL
</a>
            <h1>Precision Inventory Tracking <br>for <span>UniKL RCMP.</span></h1>
            <p>Seamlessly manage, audit, and track UniKL RCMP's IT assets with a high-precision ecosystem designed for accountability.</p>
            
            <div class="mockup-wrapper">
                <img src="img/view.png" class="mockup-img" alt="System View">
            </div>
        </div>
    </section>

    <section class="features-wrapper">
        <div class="container">
            <div class="features-grid">
                <div class="card">
                    <i class="fas fa-microchip"></i>
                    <h3>Digital Inventory</h3>
                    <p>Every equipment is cataloged with precision using UniKL asset tags and digital serial tracking.</p>
                </div>
                <div class="card">
                    <i class="fas fa-sync-alt"></i>
                    <h3>Live Workflow</h3>
                    <p>Monitor real-time loan statuses, check-ins, and return schedules across the entire campus.</p>
                </div>
                <div class="card">
                    <i class="fas fa-file-contract"></i>
                    <h3>Automated Audit</h3>
                    <p>Generate high-fidelity reports for annual department audits and maintenance history instantly.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer-rcmp">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4>UNIVERSITI KUALA LUMPUR <br>Royal College of Medicine Perak</h4>
                    <p><i class="fas fa-map-marker-alt"></i> No.3, Jalan Greentown, 30450 Ipoh Perak</p>
                    <p><i class="fas fa-phone"></i> 1 300-22-7267</p>
                    <p><i class="fas fa-print"></i> +605 - 2432 636</p>
                </div>

                <div class="footer-col">
                    <h4>CONNECT WITH #UniKLRCMP</h4>
                    <div class="social-links">
                        <a href="https://www.facebook.com/UniKLRoyalCollegeofMedicinePerak/"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/uniklrcmp/"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.youtube.com/channel/UCugrLnQZro3__D4R3x-bqRg"><i class="fab fa-youtube"></i></a>
                    </div>
                    <p style="margin-top: 20px; opacity: 0.6; font-size: 0.75rem;">
                        MOE Registration Certification No: <br>DU011(W)
                    </p>
                </div>

                <div class="footer-col footer-logos">
                    <img src="img/Logo-UniKL-PCM.jpg" class="main-logo" alt="UniKL Logo">
                    <div class="cert-icons">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="container">
                © 2025 Universiti Kuala Lumpur | All rights reserved | Legal | Site Map
            </div>
        </div>
    </footer>

    <div class="modal" id="disclaimerModal">
        <div class="modal-content">
            <i class="fas fa-id-card-alt" style="font-size: 4rem; color: var(--accent); margin-bottom: 25px;"></i>
            <h2>Security Protocol</h2>
            <p style="color: var(--text-muted);">Attention: All borrowers must physically present their ID and the asset at the IT Department for verification during collection and return.</p>
            <button onclick="closeModal()" class="btn-modal">I UNDERSTAND</button>
        </div>
    </div>

    <script>
        const modal = document.getElementById('disclaimerModal');

        window.onload = () => {
            if (!sessionStorage.getItem('noticeShown')) {
                setTimeout(() => {
                    modal.classList.add('active');
                }, 1000);
            }
        };

        function closeModal() {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                sessionStorage.setItem('noticeShown', 'true');
            }, 300);
        }
    </script>
</body>
</html>