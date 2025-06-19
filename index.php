<?php
require_once 'config/database.php';
require_once __DIR__ . '/assets/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/assets/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/assets/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Handle contact form submission
$contact_success = '';
$contact_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Sanitize and validate
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = "Please enter a valid email address.";
    } elseif (!$name || !$email || !$subject || !$message) {
        $contact_error = "All fields are required.";
    } else {
        // Log to DB
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
        } catch (Exception $e) {
            $contact_error = "Error saving message. Please try again later.";
        }
        // Send email to admin
        if (empty($contact_error)) {
            $mail = new PHPMailer(true);
            try {
                // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
                // SMTP config
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // or your SMTP host
                $mail->SMTPAuth = true;
                $mail->Username = 'faarisfun@gmail.com'; // <-- CHANGE THIS
                $mail->Password = 'iamj bhgr gzgm atgg'; // <-- CHANGE THIS (use app password for Gmail)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS for SSL
                $mail->Port = 587; // 465 for SSL

                $mail->setFrom('faarisfun@gmail.com', 'Naallo Contact');
                $mail->addAddress('faarisfun@gmail.com', 'Admin');
                $mail->addReplyTo($email, $name);

                $mail->Subject = "New Message from Landing Page: $subject";
                $mail->Body = "Name: $name\nEmail: $email\nSubject: $subject\nMessage:\n$message";
                $mail->AltBody = $mail->Body;

                $mail->send();

                // Optional: Auto-reply to user
                // $autoReply->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for auto-reply
                $autoReply = new PHPMailer(true);
                $autoReply->isSMTP();
                $autoReply->Host = 'smtp.gmail.com';
                $autoReply->SMTPAuth = true;
                $autoReply->Username = 'faarisfun@gmail.com'; // <-- IMPORTANT: CHANGE THIS to your actual email
                $autoReply->Password = 'iamj bhgr gzgm atgg'; // <-- IMPORTANT: CHANGE THIS to your actual app password
                $autoReply->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $autoReply->Port = 587;
                $autoReply->setFrom('faarisfun@gmail.com', 'Naallo Team');
                $autoReply->addAddress($email, $name);
                $autoReply->Subject = 'We received your message';
                $autoReply->Body = "Dear $name,\n\nWe received your message and will respond shortly.\n\nThank you,\nNaallo Team";
                $autoReply->AltBody = $autoReply->Body;
                $autoReply->send();

                $contact_success = "Thank You! Your message has been sent successfully! We will respond as soon as possible";
            } catch (Exception $e) {
                $contact_error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}. Original Exception: {$e->getMessage()}";
            }
        }
    }
}

// Fetch system settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // fallback values if needed
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naallo | Modern Employee Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #0ea5e9;
            --dark-color: #0f172a;
            --light-color: #f1f5f9;
            --accent-color: #f97316;
        }

        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        .navbar {
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-color) !important;
        }

        .nav-link {
            font-weight: 500;
            margin: 0 10px;
            color: var(--dark-color) !important;
            position: relative;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: white;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .hero-section {
            position: relative;
            height: 100vh;
            min-height: 600px;
            background: linear-gradient(135deg,
                rgba(0, 0, 0, 0.7) 0%,
                rgba(0, 0, 0, 0.5) 50%,
                rgba(47, 46, 46, 0.7) 100%
            ), 
                        url('assets/images/background.jpg') no-repeat center center;
            background-size: cover;
            display: flex;
            align-items: center;
            color: white;
            overflow: hidden;
        }

        .hero-section::before {
            /* content: ''; */
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>') center/cover;
            opacity: 0.1;
        }

        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .hero-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 25px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: #3a5ccc;
            border-color: #3a5ccc;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 25px;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 3rem;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: var(--accent-color);
        }

        /* Feature Cards */
        .feature-card {
            border: none;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 50px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 1s ease-in;
            opacity: 0;
        }
        
        .slide-up {
            animation: slideUp 1s ease-out;
            opacity: 0;
        }
        
        .fade-in.animate,
        .slide-up.animate {
            opacity: 1;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .contact-section {
            background: white;
            padding: 80px 0;
        }

        .contact-form {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .contact-form .form-control {
            border: none;
            border-bottom: 2px solid #e1e1e1;
            border-radius: 0;
            padding: 0.75rem 0.75rem;
            background-color: transparent;
        }

        .contact-form .form-control:focus {
            box-shadow: none;
            border-color: var(--primary-color);
        }

        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--secondary-color);
            transform: translateY(-3px);
        }

        footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0;
        }

        footer h5 {
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }

        footer h5::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 30px;
            height: 2px;
            background: white;
        }

        footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        footer a:hover {
            color: white;
            transform: translateX(5px);
        }

        .card-icon-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .card-icon-wrapper i {
            font-size: 2rem;
            color: white;
            margin: 0;
            background: none;
            -webkit-background-clip: initial;
            -webkit-text-fill-color: initial;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header p {
            color: var(--text-light);
            max-width: 600px;
            margin: 1rem auto 0;
        }

        /* Solutions Section */
        .dashboard-preview {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .dashboard-preview:hover {
            transform: translateY(-5px);
        }

        /* Testimonials Section */
        .testimonial-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: transform 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
        }

        .client-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .client-name {
            margin-bottom: 0.25rem;
            color: var(--dark-color);
        }

        .client-position {
            font-size: 0.875rem;
            color: var(--primary-color);
            margin-bottom: 0;
        }

        .testimonial-text {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--dark-color);
            margin: 1rem 0;
        }

        /* Section Headers */
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
            font-weight: 700;
            color: var(--dark-color);
        }

        /* Contact Section */
        .contact-card {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .contact-form .form-control {
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
        }

        .contact-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .contact-icon {
            width: 45px;
            height: 45px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .contact-info h5 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .contact-info p {
            color: var(--text-muted);
            margin-bottom: 0;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: var(--light-color);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }

        /* Footer */
        .footer {
            background: var(--dark-color);
            color: rgba(255, 255, 255, 0.8);
            padding-top: 5rem;
            padding-bottom: 2rem;
        }

        .footer-brand {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .footer-title {
            color: white;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer hr {
            border-color: rgba(255, 255, 255, 0.1);
            margin: 2rem 0;
        }

        .copyright {
            color: rgba(255, 255, 255, 0.6);
        }

        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            margin-left: 1.5rem;
            transition: color 0.3s ease;
        }

        .footer-bottom-links a:hover {
            color: white;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 100px 0;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgeD0iMCIgeT0iMCIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
            opacity: 0.3;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .cta-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        /* Footer Styles */
        .footer-curved {
            position: relative;
            background: var(--dark-color);
            color: white;
            padding: 80px 0 30px;
            margin-top: 50px;
        }

        .footer-curved::before {
            content: '';
            position: absolute;
            top: -50px;
            left: 0;
            width: 100%;
            height: 50px;
            background: var(--dark-color);
            border-radius: 50% 50% 0 0 / 100%;
        }

        .footer-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            display: inline-block;
            text-decoration: none;
        }

        .footer-links h5 {
            font-weight: 600;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
            color: white;
        }

        .footer-links h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background-color: var(--primary-color);
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            margin-right: 10px;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .social-icon:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .copyright {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            margin-top: 50px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }

        .copyright a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
        }

        .copyright a:hover {
            color: white;
        }
        #loading-spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.85); /* Semi-transparent white */
            z-index: 1070; /* High z-index */
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        #loading-spinner-overlay .spinner-text {
            margin-top: 15px;
            color: var(--primary-color);
            font-weight: 500;
            font-size: 1.1rem;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top" style="background-color: rgba(255, 255, 255, 0.85) !important;">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo htmlspecialchars($settings['company_name'] ?? 'Naallo'); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#solutions">Solutions</a>
                    </li>
                   
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="ms-lg-3 mt-3 mt-lg-0">
                   
                    <a href="login.php" class="btn btn-primary">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="hero-content">
                        <span class="hero-badge">
                            <i class="fas fa-star me-1"></i> Trusted by 500+ companies worldwide
                        </span>
                        <h1 class="hero-title">Revolutionize Your Workforce Management</h1>
                        <p class="hero-subtitle">Naallo provides modern HR solutions to streamline your employee management, boost productivity, and drive business growth.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="login.php" class="btn btn-primary btn-lg px-4">
                                <i class="fas fa-rocket me-2"></i> Get Started
                            </a>
                            <a href="#features" class="btn btn-outline-light btn-lg px-4">
                                <i class="fas fa-info-circle me-2"></i> Learn More
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 py-lg-7">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title slide-up">Powerful Features</h2>
                <p class="text-muted">Everything you need to manage your workforce effectively</p>
            </div>
            <div class="row">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h3>Time Tracking</h3>
                        <p>Accurate time tracking with clock-in/out functionality, overtime calculation, and customizable work schedules.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card slide-up" style="animation-delay: 0.2s;">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h3>Payroll Management</h3>
                        <p>Automated payroll processing with tax calculations, direct deposit, and comprehensive reporting.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card slide-up" style="animation-delay: 0.4s;">
                        <div class="feature-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3>Onboarding</h3>
                        <p>Streamlined onboarding process with digital forms, document management, and task automation.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Leave Management</h3>
                        <p>Easy request and approval of vacation, sick days, and other leave types with calendar integration.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card slide-up" style="animation-delay: 0.2s;">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Performance Reviews</h3>
                        <p>Customizable review cycles with 360° feedback, goal tracking, and development planning.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card slide-up" style="animation-delay: 0.4s;">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Mobile Access</h3>
                        <p>Full-featured mobile app for employees and managers to stay connected anywhere.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Solutions Section -->
    <section id="solutions" class="py-5 py-lg-7 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <img src="assets\images\about naallo.png" alt="Analytics Dashboard" class="img-fluid dashboard-preview slide-up">
                </div>
                <div class="col-lg-6">
                    <h2 class="section-title slide-up">Comprehensive HR Solutions</h2>
                    <p class="mb-4 fade-in">Naallo provides an all-in-one platform to manage your workforce efficiently and effectively.</p>
                    
                    <div class="d-flex mb-4 fade-in" style="animation-delay: 0.2s;">
                        <div class="me-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div>
                            <h4>Centralized Employee Data</h4>
                            <p class="mb-0">Store and manage all employee information in one secure, cloud-based location.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4 fade-in" style="animation-delay: 0.4s;">
                        <div class="me-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div>
                            <h4>Compliance Management</h4>
                            <p class="mb-0">Stay compliant with labor laws and regulations with automated alerts and documentation.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex fade-in" style="animation-delay: 0.6s;">
                        <div class="me-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div>
                            <h4>Advanced Reporting</h4>
                            <p class="mb-0">Generate custom reports and gain insights into your workforce with powerful analytics.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

   

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="cta-title slide-up">Ready to Transform Your HR Management?</h2>
                    <p class="cta-subtitle fade-in">Join thousands of businesses that trust Naallo to streamline their employee management processes.</p>
                    <div class="d-flex justify-content-center mt-4 fade-in" style="animation-delay: 0.2s;">
                        <a href="login.php" class="btn btn-light btn-lg px-5">Get Started</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Get in Touch Section -->
    <section id="contact" class="py-5 py-lg-7 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title slide-up">Get in Touch</h2>
                <p class="text-muted">Have questions? We'd love to hear from you.</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-card slide-up">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <form id="contactForm" class="contact-form" method="POST" action="#contact">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Your Name" required>
                                        <label for="name"><i class="fas fa-user me-2"></i>Your Name</label>
                                    </div>
                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Your Email" required>
                                        <label for="email"><i class="fas fa-envelope me-2"></i>Your Email</label>
                                    </div>
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="subject" name="subject" placeholder="Subject" required>
                                        <label for="subject"><i class="fas fa-tag me-2"></i>Subject</label>
                                    </div>
                                    <div class="form-floating mb-4">
                                        <textarea class="form-control" id="message" name="message" placeholder="Your Message" style="height: 150px" required></textarea>
                                        <label for="message"><i class="fas fa-comment me-2"></i>Your Message</label>
                                    </div>
                                    <button type="submit" name="contact_submit" class="btn btn-primary w-100 py-3">Send Message</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <div class="contact-info">
                                    <h4 class="mb-4">Contact Information</h4>
                                    <div class="d-flex mb-4">
                                        <div class="contact-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h5>Address</h5>
                                            <p><?php echo htmlspecialchars($settings['company_address'] ?? '123 Business Avenue, Suite 100<br>Dubai, UAE'); ?></p>
                                        </div>
                                    </div>
                                    <div class="d-flex mb-4">
                                        <div class="contact-icon">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h5>Phone</h5>
                                            <p><?php echo htmlspecialchars($settings['company_phone'] ?? '+971 4 123 4567'); ?></p>
                                        </div>
                                    </div>
                                    <div class="d-flex mb-4">
                                        <div class="contact-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h5>Email</h5>
                                            <p><?php echo htmlspecialchars($settings['company_email'] ?? 'info@Naallo.com'); ?></p>
                                        </div>
                                    </div>
                                    <div class="social-links mt-5">
                                        <a href="#" class="me-3"><i class="fab fa-facebook-f"></i></a>
                                        <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                                        <a href="#" class="me-3"><i class="fab fa-linkedin-in"></i></a>
                                        <a href="#"><i class="fab fa-instagram"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-curved">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-5 mb-lg-0">
                    <a href="#" class="footer-logo"><?php echo htmlspecialchars($settings['company_name'] ?? 'Naallo'); ?></a>
                    <p class="mb-4" style="max-width: 300px;">Modern employee management solutions for businesses of all sizes.</p>
                    <div class="d-flex">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <div class="footer-links">
                        <h5>Product</h5>
                        <ul>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#solutions">Solutions</a></li>
                            <li><a href="#">Updates</a></li>
                            <li><a href="#">Roadmap</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <div class="footer-links">
                        <h5>Company</h5>
                        <ul>
                            <li><a href="#">About Us</a></li>
                            <li><a href="#">Careers</a></li>
                            <li><a href="#">Blog</a></li>
                            <li><a href="#">Press</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <div class="footer-links">
                        <h5>Resources</h5>
                        <ul>
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">Tutorials</a></li>
                            <li><a href="#">API Docs</a></li>
                            <li><a href="#">Community</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="footer-links">
                        <h5>Contact</h5>
                        <ul>
                            <li><a href="mailto:<?php echo htmlspecialchars($settings['company_email'] ?? 'info@naallo.com'); ?>"><?php echo htmlspecialchars($settings['company_email'] ?? 'info@naallo.com'); ?></a></li>
                            <li><a href="tel:<?php echo htmlspecialchars($settings['company_phone'] ?? '+1234567890'); ?>"><?php echo htmlspecialchars($settings['company_phone'] ?? '+1234567890'); ?></a></li>
                            <li><a href="#">Support</a></li>
                            <li><a href="#">Sales</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 text-center copyright">
                    <p class="mb-0">&copy; 2025 Naallo. All rights reserved. | <a href="#" class="text-white">Privacy Policy</a> | <a href="#" class="text-white">Terms of Service</a></p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Intersection Observer for scroll animations
        const animateOnScroll = () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.fade-in, .slide-up').forEach((el) => observer.observe(el));
        };

        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            animateOnScroll();
        });
    </script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
<?php if (!empty($contact_success)): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Thank You!',
    text: "Thank You! Your message has been sent successfully! We will respond as soon as possible",
    confirmButtonColor: '#4e73df'
});
</script>
<?php endif; ?>
<?php if (!empty($contact_error)): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: <?php echo json_encode($contact_error); ?>,
    confirmButtonColor: '#e74a3b'
});
</script>
<?php endif; ?>
<!-- Loading Spinner Overlay -->
<div id="loading-spinner-overlay">
    <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div class="spinner-text">Processing your request...</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactFormForSpinner = document.querySelector('#contact form');
    const spinnerOverlay = document.getElementById('loading-spinner-overlay');

    if (contactFormForSpinner && spinnerOverlay) {
        contactFormForSpinner.addEventListener('submit', function(event) {
            // Check if the form is valid from a browser's perspective (e.g. HTML5 required attributes)
            if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
                // If form is not valid according to browser built-in checks, 
                // do not show spinner, let browser handle error display.
                return;
            }
            spinnerOverlay.style.display = 'flex';
            // Form submission will proceed and page will reload.
        });
    }

    // Ensure spinner is hidden if PHP has already processed and page reloaded with a message
    const contactSuccessMessage = <?php echo json_encode(!empty($contact_success)); ?>;
    const contactErrorMessage = <?php echo json_encode(!empty($contact_error)); ?>;

    if ((contactSuccessMessage || contactErrorMessage) && spinnerOverlay) {
        spinnerOverlay.style.display = 'none';
    }
});
</script>
</body>
</html>