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

        /* Container - Kunci utama untuk elak "rapat border" */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px; /* Ruang bernafas kiri & kanan */
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
            font-size: 1.2rem;
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
            width: 100%;
            max-width: 950px;
            border-radius: 25px;
            box-shadow: 0 40px 100px -20px rgba(0, 33, 71, 0.2);
            border: 8px solid white;
            transition: 0.5s;
        }

        /* Features Section - Improved Grid */
        .features-wrapper { background: white; padding: 100px 0; margin-top: 50px; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px; /* Jarak antara kad */
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

        .btn-modal:hover { background: var(--accent); }

        @media (max-width: 768px) {
            .container { padding: 0 25px; }
            .hero h1 { font-size: 2.5rem; }
            .navbar { width: 95%; padding: 10px 20px; }
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
            <div class="badge-ui"><i class="fas fa-shield-alt"></i> UniKL RCMP OFFICIAL</div>
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