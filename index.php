<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>R-ILMS (RCMP Management System-Inventory) - IT Asset Management</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            /* NEW RCMP-Inspired Colors (Clean & Tech/Medical) */
            --primary-blue: #002147;      /* Keep a dark blue base for text/structure */
            --accent-cyan: #00A3C9;       /* Light Blue/Cyan for highlights */
            --accent-green: #A7D737;      /* Lime Green for secondary highlights */
            --background-light: #ffffff; 
            --off-white: #f8f9fc;
            --text-dark: #222;
            --text-muted: #666;
            --border-color: #e0e0e0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: var(--background-light); 
            color: var(--text-dark);
            scroll-behavior: smooth;
        }

        /* Container for Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navbar - Clean and Professional */
        .navbar {
            background: var(--background-light);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 3px solid var(--accent-cyan); /* Using New Accent Cyan */
        }
        .navbar-layout { 
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 10px; 
        }
        .navbar-left img {
            height: 38px; 
        }
        .navbar-title {
            color: var(--primary-blue);
            font-size: 18px; 
            font-weight: 700;
            line-height: 1.2;
        }
        .navbar-right a {
            color: white;
            text-decoration: none;
            background: var(--accent-cyan); /* Using New Accent Cyan */
            padding: 8px 20px; 
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0, 163, 201, 0.2);
        }
        .navbar-right a:hover {
            background: var(--primary-blue);
            box-shadow: 0 6px 15px rgba(0, 33, 71, 0.2);
        }

        /* Hero Section - Split Layout */
        .hero {
            height: 70vh;
            min-height: 550px;
            display: flex;
            align-items: center;
            background: var(--background-light);
            position: relative;
            padding-top: 50px;
        }
        .hero-layout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 50px;
            width: 100%;
        }
        .hero-text {
            flex: 1;
            padding-right: 20px;
            animation: slideInLeft 1s ease-out forwards;
        }
        .hero-graphic {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            animation: fadeIn 1s ease-out forwards;
        }
        .hero-graphic img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        /* Hero Text Styling */
        .hero h1 {
            font-size: 48px;
            margin: 10px 0 20px 0;
            font-weight: 800;
            color: var(--primary-blue);
            line-height: 1.2;
        }
        .hero h1 strong {
            color: var(--accent-cyan); /* Using New Accent Cyan */
        }
        .hero p {
            font-size: 18px;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 30px;
            max-width: 500px;
        }
        .cta-button {
            display: inline-block;
            background: var(--primary-blue);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.3s ease;
        }
        .cta-button:hover {
            background: var(--accent-cyan);
            transform: translateY(-2px);
        }

        /* Features Section */
        .content-wrapper {
            padding: 80px 0;
            background: var(--off-white);
        }
        .section-title {
            text-align: center;
            color: var(--primary-blue);
            font-size: 36px;
            margin-bottom: 60px;
            font-weight: 800;
        }

        /* Features Grid */
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
        }
        .card {
            background: var(--background-light);
            border-radius: 12px;
            padding: 30px;
            text-align: left;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--accent-green); /* Using New Accent Green */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        .card .icon {
            font-size: 40px;
            color: var(--accent-cyan); /* Using New Accent Cyan */
            margin-bottom: 15px;
        }
        .card h3 {
            color: var(--primary-blue);
            font-size: 20px;
            margin-top: 0;
            font-weight: 700;
        }
        .card p {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-muted);
        }

        /* About Section */
        .about {
            background: var(--primary-blue); /* Keep dark primary blue for contrast */
            color: white;
            padding: 60px 20px;
            border-radius: 12px;
            text-align: center;
            margin: 80px auto;
        }
        .about h2 {
            color: var(--accent-green); /* Using New Accent Green */
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .about p {
            font-size: 16px;
            margin: 15px auto 0;
            line-height: 1.8;
            max-width: 800px;
            opacity: 0.9;
        }

        /* Footer */
        footer {
            background: var(--primary-blue);
            color: white;
            text-align: center;
            padding: 25px;
            font-size: 14px;
        }
        footer strong {
            color: var(--accent-green); /* Using New Accent Green */
            font-weight: 600;
        }

        /* Scroll Animation (no change) */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .animate-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Modal (updated colors) */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: var(--background-light);
            color: var(--text-dark);
            padding: 40px;
            border-radius: 12px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-content .icon {
            font-size: 3rem;
            color: var(--accent-cyan); /* Using New Accent Cyan */
        }
        .modal-content h3 {
            margin: 15px 0;
            color: var(--primary-blue);
        }
        .modal-content button {
            margin-top: 20px;
            padding: 12px 25px;
            background: var(--accent-green); /* Using New Accent Green */
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-content button:hover {
            background: #90c226; /* Slightly darker green for hover */
            transform: translateY(-2px);
        }


        /* Responsive Design (no change) */
        @media (max-width: 992px) {
            .hero-layout {
                flex-direction: column;
                text-align: center;
                gap: 40px;
            }
            .hero-text {
                padding-right: 0;
            }
            .hero-graphic {
                display: none; 
            }
            .hero {
                height: auto;
                min-height: 400px;
                padding-bottom: 50px;
            }
            .features {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .hero h1 { font-size: 36px; }
            .features { grid-template-columns: 1fr; }
            .navbar-title { display: none; }
        }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="container navbar-layout">
            <div class="navbar-left">
                <img src="img/Logo-UniKL-PCM.jpg" alt="UniKL RCMP Logo">
                <div class="navbar-title">RCMP Inventory Loan Management System</div>
            </div>
            <div class="navbar-right">
                <a href="login.php">Log In</a>
            </div>
        </div>
    </div>

    <div class="hero">
        <div class="container">
            <div class="hero-layout">
                <div class="hero-text">
                    <p style="font-size: 16px; font-weight: 500; color: var(--accent-cyan); margin-bottom: 5px; text-transform: uppercase;">R-ILMS: Royal College of Medicine Perak</p>
                    <h1>Precision Asset Tracking for <strong>UniKL RCMP.</strong></h1>
                    <p>The definitive <strong>IT Asset Management System</strong> designed for accuracy and accountability. Manage loans, track inventory lifecycle, and generate precise audit reports for all campus equipment.</p>
                    <a href="#features-section" class="cta-button">Explore Core Features <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="hero-graphic">
                    <img src="img/view.png" alt="IT Asset Management System Dashboard Mockup">
                </div>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="container">
            <h2 class="section-title animate-on-scroll" id="features-section">System Highlights</h2>
            <div class="features">
                <div class="card animate-on-scroll">
                    <div class="icon"><i class="fas fa-barcode"></i></div>
                    <h3>Inventory Tracking (Barcode/Tag)</h3>
                    <p>Register, categorize, and permanently track all equipment using serial numbers and unique <strong>UniKL asset tags</strong> for robust accountability.</p>
                </div>
                <div class="card animate-on-scroll" style="transition-delay: 0.1s;">
                    <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                    <h3>Controlled Loan Lifecycle</h3>
                    <p>The core <strong>Check-In/Check-Out</strong> function ensures every asset movement is logged, providing full visibility over who holds which equipment and for how long.</p>
                </div>
                <div class="card animate-on-scroll" style="transition-delay: 0.2s;">
                    <div class="icon"><i class="fas fa-cogs"></i></div>
                    <h3>Maintenance & Audit Logs</h3>
                    <p>Maintain a complete history of equipment condition, repair status, and generate detailed utilization reports necessary for annual IT audits.</p>
                </div>
            </div>

            <div class="about animate-on-scroll">
                <h2>Designed for IT Department, Serving the Campus.</h2>
                <p>R-ILMS is an essential tool for the <strong>UniKL IT Department</strong> at the Royal College of Medicine Perak. Its purpose is to streamline asset issuance (laptops, projectors, testing gear) to faculty and students, minimizing loss and ensuring operational readiness across all medical and administrative facilities.</p>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Universiti Kuala Lumpur <strong>Royal College of Medicine Perak (RCMP)</strong> IT Department. All rights reserved.</p>
        </div>
    </footer>

    <div class="modal" id="disclaimerModal">
        <div class="modal-content">
            <div class="icon"><i class="fas fa-bullhorn"></i></div>
            <h3>Important Notice for Borrowers</h3>
            <p><strong>All borrowed items must be collected and returned personally at the UniKL IT Department office.</strong></p>
            <button onclick="closeModal()">I Understand</button>
        </div>
    </div>

<script>
    // MODAL LOGIC (Using SESSIONSTORAGE)
    const modal = document.getElementById("disclaimerModal");
    
    window.closeModal = function() {
        modal.style.display = "none";
        sessionStorage.setItem('noticeShown', 'true');    
    }
    
    window.onload = () => {
        if (!sessionStorage.getItem('noticeShown')) {
            setTimeout(() => {
                modal.style.display = "flex";
            }, 1000);
        }

        setupScrollAnimation();
    };

    // Scroll Animation Logic
    function setupScrollAnimation() {
        const scrollElements = document.querySelectorAll(".animate-on-scroll");
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target); 
                }
            });
        }, { threshold: 0.1 });

        scrollElements.forEach(el => {
            observer.observe(el);
        });
    }
</script>

</body>
</html>