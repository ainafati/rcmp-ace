<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UniKL A.C.E. (Asset Check Effective) - IT Asset Management</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --unikl-blue: #002147;
            --unikl-orange: #f58220;
            --light-blue: #f0f5ff;
            --text-dark: #333;
            --text-light: #555;
        }

        body {
            margin: 0;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            background: #f8f9fc;
            color: var(--text-dark);
            scroll-behavior: smooth;
        }

        /* Navbar */
        .navbar {
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-left img {
            height: 50px; /* Logo Size INCREASED */
        }
        .navbar-title {
            color: var(--unikl-blue);
            font-size: 20px;
            font-weight: 700;
        }
        .navbar-right a {
            color: white;
            text-decoration: none;
            background: var(--unikl-orange);
            padding: 12px 22px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(245, 130, 32, 0.3);
        }
        .navbar-right a:hover {
            background: #ff9d40;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 130, 32, 0.4);
        }

        /* Hero Section */
        .hero {
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            color: white;
        }
        .hero::after {
            content: "";
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(0, 33, 71, 0.7), rgba(0, 0, 0, 0.3));
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 40px 60px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInHero 1s ease-out forwards;
        }
        @keyframes fadeInHero {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hero h1 {
            font-size: 42px;
            margin: 0;
            font-weight: 700;
        }
        .hero p {
            font-size: 18px;
            margin-top: 15px;
            opacity: 0.9;
        }

        /* Slider */
        .hero .slider {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
        }
        .hero .slider img {
            width: 100%; height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0; left: 0;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }
        .hero .slider img.active {
            opacity: 1;
        }
        
        /* Content Section Wrapper */
        .content-wrapper {
            max-width: 1100px;
            margin: 60px auto;
            padding: 0 20px;
        }

        /* Features */
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 35px rgba(0, 33, 71, 0.1);
        }
        .card .icon {
            font-size: 40px;
            color: var(--unikl-orange);
            margin-bottom: 15px;
        }
        .card h3 {
            color: var(--unikl-blue);
            font-size: 20px;
            margin-top: 0;
        }
        .card p {
            font-size: 15px;
            line-height: 1.7;
            color: var(--text-light);
        }

        /* About Section */
        .about {
            margin-top: 80px;
            background: var(--light-blue);
            padding: 50px;
            border-radius: 20px;
            text-align: center;
        }
        .about h2 {
            color: var(--unikl-blue);
            font-size: 28px;
        }
        .about p {
            font-size: 16px;
            margin: 15px auto 0;
            line-height: 1.8;
            max-width: 700px;
            color: var(--text-light);
        }

        /* Footer */
        footer {
            background: var(--unikl-blue);
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 60px;
        }
        
        /* Scroll Animation */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .animate-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Modal */
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
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.5s ease;
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.9); opacity: 0;}
            to { transform: scale(1); opacity: 1;}
        }
        .modal-content .icon {
            font-size: 3rem;
            color: var(--unikl-orange);
        }
        .modal-content h3 {
            margin: 15px 0;
            color: var(--unikl-blue);
        }
        .modal-content button {
            margin-top: 20px;
            padding: 12px 25px;
            background: var(--unikl-orange);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-content button:hover {
            background: #ff9d40;
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .features {
                grid-template-columns: 1fr;
            }
            .navbar-title {
                font-size: 18px;
            }
        }
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .hero-content { padding: 30px 20px; }
            .hero h1 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .content-wrapper { margin: 40px auto; }
            .about { padding: 40px 20px; }
            .navbar-title {
                display: none; 
            }
        }

    </style>
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
            <img src="img/Logo-UniKL-PCM.jpg" alt="UniKL Logo">
        </div>
        <div class="navbar-title">UniKL A.C.E. (Asset Check Effective)</div>
        <div class="navbar-right">
            <a href="login.php">Log In</a>
        </div>
    </div>

    <div class="hero">
        <div class="slider">
            <img src="img/view.png" class="active" alt="Campus view">
            <img src="img/unikl.jpg" alt="UniKL RCMP">
        </div>
        <div class="hero-content">
            <h1>Welcome to UniKL A.C.E.</h1>
            <p>The Effective IT Asset Management System. Your platform for tracking and recording asset movement at the <strong>Royal College of Medicine Perak (RCMP) Ipoh Campus</strong>.</p>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="features">
            <div class="card animate-on-scroll">
                <div class="icon"><i class="fas fa-boxes-stacked"></i></div>
                <h3>Asset Management</h3>
                <p>Register, track, and manage all UniKL IT equipment using serial numbers and asset tags.</p>
            </div>
            <div class="card animate-on-scroll" style="transition-delay: 0.1s;">
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Check-In/Check-Out</h3>
                <p>The core function for recording asset loans by users and ensuring timely returns are logged.</p>
            </div>
            <div class="card animate-on-scroll" style="transition-delay: 0.2s;">
                <div class="icon"><i class="fas fa-chart-pie"></i></div>
                <h3>Reports & Audits</h3>
                <p>Generate detailed reports on asset utilization, loan status, and the complete record history of each equipment.</p>
            </div>
        </div>

        <div class="about animate-on-scroll">
            <h2>About UniKL A.C.E.</h2>
            <p>This system is exclusively designed for the <strong>UniKL IT Department</strong> to implement an efficient <strong>Check-In & Check-Out</strong> process for digital assets such as laptops, projectors, and lab equipment. It helps administrators and technicians monitor usage, check conditions, and ensure transparent asset management.</p>
        </div>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Universiti Kuala Lumpur <strong>Royal College of Medicine Perak (RCMP)</strong> IT Department. All rights reserved.
    </footer>

    <div class="modal" id="disclaimerModal">
        <div class="modal-content">
            <div class="icon"><i class="fas fa-bullhorn"></i></div>
            <h3>Important Notice</h3>
            <p><strong>All borrowed items must be collected personally from the UniKL IT Department office.</strong></p>
            <button onclick="closeModal()">I Understand</button>
        </div>
    </div>

<script>
    // Slider Logic
    let currentImage = 0;
    const images = document.querySelectorAll(".hero .slider img");
    const totalImages = images.length;
    
    function showNextImage() {
        images[currentImage].classList.remove("active");
        currentImage = (currentImage + 1) % totalImages;
        images[currentImage].classList.add("active");
    }
    setInterval(showNextImage, 5000);

    // =======================================================
    // MODAL LOGIC (Using SESSIONSTORAGE)
    // =======================================================
    const modal = document.getElementById("disclaimerModal");
    
    function closeModal() {
        modal.style.display = "none";
        // 1. Store a flag in sessionStorage
        sessionStorage.setItem('noticeShown', 'true'); 
    }
    
    window.onload = () => {
        // 2. Check if the user HAS NOT seen the notice in this session
        if (!sessionStorage.getItem('noticeShown')) {
            setTimeout(() => {
                modal.style.display = "flex";
            }, 1000); // Show popup after 1 second
        }
    };
    // =======================================================

    // Scroll Animation Logic
    const scrollElements = document.querySelectorAll(".animate-on-scroll");
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target); // Animate only once
            }
        });
    }, { threshold: 0.1 });

    scrollElements.forEach(el => {
        observer.observe(el);
    });
</script>

</body>
</html>