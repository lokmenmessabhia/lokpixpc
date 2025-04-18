<?php
// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Then check login status
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

// Include header after session management
include 'header.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';




// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lokmen13.messabhia@gmail.com'; // Your Gmail address
        $mail->Password = 'dfbk qkai wlax rscb'; // Your Gmail App Password
        $mail->SMTPSecure = 'tls'; // Enable TLS encryption
        $mail->Port = 587; // TCP port to connect to

        // Recipients
        $mail->setFrom('lokmen13.messabhia@gmail.com', 'Your Name');
        $mail->addAddress('lokmen13.messabhia@gmail.com'); // Add the recipient email address here

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333333;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .header {
                        background-color: #007bff;
                        color: white;
                        padding: 20px;
                        text-align: center;
                        border-radius: 5px 5px 0 0;
                    }
                    .content {
                        background-color: #ffffff;
                        padding: 20px;
                        border: 1px solid #dddddd;
                        border-radius: 0 0 5px 5px;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 20px;
                        padding: 20px;
                        color: #666666;
                        font-size: 12px;
                    }
                    .details {
                        background-color: #f8f9fa;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>New Contact Message</h1>
                    </div>
                    
                    <div class='content'>
                        <div class='details'>
                            <p><strong>From:</strong> " . htmlspecialchars($name) . "</p>
                            <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                            <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
                            <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                            <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
                        </div>
                    </div>

                    <div class='footer'>
                        <p>This email was sent from LokPixPC Contact Form</p>
                        <p>  " . date('Y') . " LokPixPC. All rights reserved.</p>
                        <p>Route Nationale N-16, Souk Ahras, Algeria</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Set plain text version
        $mail->AltBody = "
From: $name
Email: $email
Phone: $phone
Subject: $subject

Message:
$message

Sent from LokPixPC Contact Form
";
        
        $mail->send();
        $_SESSION['status'] = "success";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Your message has been sent successfully.',
                    confirmButtonColor: '#007bff',
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>";
    } catch (Exception $e) {
        $_SESSION['status'] = "error";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Message could not be sent. Please try again later.',
                    confirmButtonColor: '#dc3545'
                });
            });
        </script>";
    }
}

include 'db_connect.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Lokpix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
 <style>
    /* General Styles */
    .about-page {
        background-color: #f8f9fa;
        color: #333;
    }

    /* About Section */
    .about-section {
        padding: 60px 0;
    }

    .about-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Profile Section */
    .about-profile {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        margin-bottom: 50px;
        text-align: center;
    }

    .about-developer {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .about-developer:hover {
        transform: translateY(-5px);
    }

    .about-developer img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        margin-bottom: 15px;
        object-fit: cover;
    }

    .about-developer h3 {
        color: #007bff;
        margin-bottom: 10px;
    }

    .about-developer a {
        display: inline-block;
        margin: 5px;
        padding: 5px 15px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 20px;
        font-size: 14px;
        transition: background 0.3s ease;
    }

    .about-developer a:hover {
        background: #0056b3;
    }

    /* About Text Section */
    .about-text {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 40px;
    }

    .about-text h1 {
        color: #007bff;
        margin-bottom: 30px;
    }

    .about-text h2 {
        color: #0056b3;
        margin: 25px 0 15px;
    }

    .about-text ul {
        padding-left: 20px;
        margin-bottom: 20px;
    }

    .about-text li {
        margin-bottom: 10px;
    }

    /* Contact Section */
    .contact-section {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .info-item {
        display: flex;
        align-items: start;
        margin-bottom: 25px;
    }

    .info-item .icon {
        font-size: 20px;
        color: #007bff;
        margin-right: 15px;
        width: 40px;
        height: 40px;
        background: #e7f1ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .info-content h3 {
        margin: 0 0 5px;
        font-size: 18px;
        color: #0056b3;
    }

    .social-links {
        margin-top: 30px;
    }

    .social-links a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        background: #007bff;
        color: white;
        border-radius: 50%;
        margin-right: 10px;
        transition: transform 0.3s ease;
    }

    .social-links a:hover {
        transform: translateY(-3px);
        background: #0056b3;
    }

    /* Contact Form */
    .contact-form {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 10px;
    }

    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #007bff;
        text-align: left;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #007bff;
        outline: none;
    }

    textarea.form-control {
        min-height: 120px;
    }

    .submit-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        cursor: pointer;
        transition: background 0.3s ease;
        width: 100%;
    }

    .submit-btn:hover {
        background: #0056b3;
    }

    /* Back to Top Button */
    .back-to-top {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #007bff;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: none;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .back-to-top.visible {
        opacity: 1;
        visibility: visible;
    }

    .back-to-top:hover {
        background: #0056b3;
        transform: translateY(-3px);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .contact-container {
            grid-template-columns: 1fr !important;
        }

        .about-profile {
            grid-template-columns: 1fr;
        }

        .about-text, .contact-section {
            padding: 20px;
        }
    }

    .contact-info-section {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    .contact-info-left {
        text-align: center;
    }

    .contact-info-left h2 {
        color: #007bff;
        margin-bottom: 30px;
    }

    .info-list {
        margin-bottom: 20px;
    }

    .info-item {
        display: flex;
        align-items: start;
        margin-bottom: 25px;
    }

    .info-icon {
        font-size: 20px;
        color: #007bff;
        margin-right: 15px;
        width: 40px;
        height: 40px;
        background: #e7f1ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .info-content {
        text-align: left;
    }

    .info-content h3 {
        margin: 0 0 5px;
        font-size: 18px;
        color: #0056b3;
    }

    .contact-info-right {
        text-align: left;
        padding: 20px;
    }

    .contact-info-right h2 {
        text-align: left;
        color: #007bff;
        margin-bottom: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #007bff;
        text-align: left;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #007bff;
        outline: none;
    }

    textarea.form-control {
        min-height: 120px;
    }

    .submit-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        cursor: pointer;
        transition: background 0.3s ease;
        width: 100%;
    }

    .submit-btn:hover {
        background: #0056b3;
    }

    /* Add these new styles for NextGen Studio section */
    .team-header {
        text-align: center;
        padding: 60px 20px;
        background: linear-gradient(135deg,rgb(145, 33, 176),rgb(126, 1, 126));
        color: white;
        border-radius: 15px;
        margin-bottom: 60px;
        position: relative;
        overflow: hidden;
    }

    .team-name {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 2px;
        position: relative;
        z-index: 1;
    }

    .team-logo {
        width: 180px;
        height: 180px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        border-radius: 50%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        position: relative;
        z-index: 1;
    }

    .team-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    /* Animated background elements */
    .team-header::before,
    .team-header::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
        z-index: 0;
    }

    .team-header::before {
        top: -100px;
        left: -100px;
        animation: float 6s infinite ease-in-out;
    }

    .team-header::after {
        bottom: -100px;
        right: -100px;
        animation: float 8s infinite ease-in-out reverse;
    }

    @keyframes float {
        0%, 100% { transform: translate(0, 0); }
        50% { transform: translate(20px, 20px); }
    }

    /* Update the about-developer cards */
    .about-developer {
        background: white;
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .about-developer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, #2193b0, #6dd5ed);
    }

    .about-developer:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    }

    .about-developer img {
        width: 180px;
        height: 180px;
        border-radius: 50%;
        margin-bottom: 25px;
        object-fit: cover;
        border: 5px solid #f8f9fa;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .about-developer h3 {
        color: #2193b0;
        font-size: 1.5rem;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .about-developer p {
        color: #666;
        margin-bottom: 20px;
        font-size: 1.1rem;
    }

    .about-developer a {
        display: inline-block;
        margin: 5px;
        padding: 8px 20px;
        background: linear-gradient(135deg, #2193b0, #6dd5ed);
        color: white;
        text-decoration: none;
        border-radius: 25px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .about-developer a:hover {
        background: linear-gradient(135deg, #6dd5ed, #2193b0);
        transform: translateY(-2px);
    }
 </style>

<script>
    // Check for status on page load
    document.addEventListener('DOMContentLoaded', function() {
        <?php if(isset($_SESSION['status']) && $_SESSION['status'] == "success"): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Your message has been sent successfully.',
                confirmButtonColor: '#007bff',
                timer: 3000,
                timerProgressBar: true
            });
        <?php elseif(isset($_SESSION['status']) && $_SESSION['status'] == "error"): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Message could not be sent. Please try again later.',
                confirmButtonColor: '#dc3545'
            });
        <?php endif; ?>
        <?php unset($_SESSION['status']); ?>
    });
</script>
</head>
<body>
<div class="about-page">
    <main>
        <section class="about-section">
            <div class="about-container">
                <!-- Team Name and Logo Section -->
                <div class="team-header">
                    <h1 class="team-name">NextGen Studio</h1>
                    <div class="team-logo">
                        <img src="team.png" alt="NextGen Studio Logo">
                    </div>
                </div>

                <!-- Developer Profiles -->
                <div class="about-profile">
                    <div class="about-developer">
                        <img src="https://b.top4top.io/p_3272e9f641.jpg" alt="Developer 1">
                        <h3>LOKMANE MESSABHIA</h3>
                        <p>Backend Developer</p>
                        <a href="https://www.linkedin.com/in/lokmen-messabhia-5b511a265/?original_referer=https%3A%2F%2Fwww%2Egoogle%2Ecom%2F&originalSubdomain=dz" target="_blank">Linkedin</a>
                        <a href="mailto:lokmen16.messabhia@gmail.com">Email</a>
                    </div>
                    <div class="about-developer">
                        <img src="https://j.top4top.io/p_3277n6dv61.jpg" alt="Developer 2">
                        <h3>Saiffi Med Ali Zakaria</h3>
                        <p>Frontend Developer</p>
                        <a href="https://www.instagram.com/sf.zakaria__" target="_blank">Instagram</a>
                        <a href="mailto:saiffizakaria56@gmail.com">Email</a>
                    </div>
                    <div class="about-developer">
                        <img src="https://c.top4top.io/p_3273yb3z20.jpg" alt="Developer 3">
                        <h3>Hammoudi Wajdi</h3>
                        <p>Security and Model View Controller</p>
                        <a href="https://www.instagram.com/wajdi2.0" target="_blank">Instagram</a>
                        <a href="mailto:amine@example.com">Email</a>
                    </div>
                    <div class="about-developer">
                        <img src="https://g.top4top.io/p_3344zxhrx1.jpg" alt="Developer 2">
                        <h3>Remadnia Raouf</h3>
                        <p>UI/UX Designer</p>
                        <a href="https://www.instagram.com/westerllund" target="_blank">Instagram</a>
                        <a href="mailto:saiffizakaria56@gmail.com">Email</a>
                    </div>
                    <div class="about-developer">
                        <img src="https://j.top4top.io/p_33444dqdd1.jpg" alt="Developer 3">
                        <h3>Saoudi Ahmad</h3>
                        <p>Tester and Model View Controller</p>
                        <a href="https://www.instagram.com/magentuos" target="_blank">Instagram</a>
                        <a href="mailto:amine@example.com">Email</a>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="contact-info-section">
                    <div class="contact-grid">
                        <div class="contact-info-left">
                            <h2>Contact Information</h2>
                            <div class="info-list">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="info-content">
                                        <h3>Address</h3>
                                        <p>Route Nationale N-16, Souk Ahras, Algeria</p>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="info-content">
                                        <h3>Phone</h3>
                                        <p>+213 794159854</p>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="info-content">
                                        <h3>Email</h3>
                                        <p>lokmen13.messabhia@gmail.com</p>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="info-content">
                                        <h3>Working Hours</h3>
                                        <p>Mon - Sat: 9:00 AM - 8:00 PM</p>
                                    </div>
                                </div>

                                <div class="social-links">
                                    <a href="#" class="facebook"><i class="fab fa-facebook-f"></i></a>
                                    <a href="#" class="twitter"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="instagram"><i class="fab fa-instagram"></i></a>
                                    <a href="#" class="linkedin"><i class="fab fa-linkedin-in"></i></a>
                                </div>
                            </div>
                        </div>

                        <div class="contact-info-right">
                            <h2>Send Us a Message</h2>
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" name="name" required class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" required class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="tel" name="phone" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Subject</label>
                                    <input type="text" name="subject" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Message</label>
                                    <textarea name="message" required class="form-control"></textarea>
                                </div>
                                
                                <button type="submit" class="submit-btn">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- About EcoTech Section -->
                <div class="about-text">
                    <h1>About EcoTech</h1>
                    <p>Welcome to EcoTech, your premier destination for sustainable technology solutions and responsible electronics recycling. We are dedicated to providing high-quality computer hardware, software, and accessories while promoting environmental responsibility through our recycling initiatives.</p>
                    
                    <h2>Our Story</h2>
                    <p>Founded in 2024, EcoTech emerged from a vision to combine technology retail with environmental stewardship. What began as a small local store has evolved into a comprehensive platform that not only offers top-tier computer products but also leads the way in electronics recycling and sustainability efforts in our region.</p>
                    
                    <h2>Our Mission</h2>
                    <p>Our dual mission is to make cutting-edge technology accessible while promoting responsible electronics disposal and recycling. We believe in creating a sustainable future where technology and environmental consciousness go hand in hand.</p>

                    <h2>Our Recycling Initiative</h2>
                    <p>At EcoTech, we're committed to reducing electronic waste through our comprehensive recycling program:</p>
                    <ul>
                        <li>Free electronics recycling drop-off service for all customers</li>
                        <li>Proper disposal of hazardous materials found in electronics</li>
                        <li>Data destruction and privacy protection services</li>
                        <li>Trade-in programs for upgrading to newer, energy-efficient devices</li>
                        <li>Educational resources on sustainable technology practices</li>
                    </ul>

                    <h2>Why Choose EcoTech?</h2>
                    <ul>
                        <li>Environmentally conscious technology solutions and products</li>
                        <li>Expert guidance on sustainable tech choices</li>
                        <li>Competitive pricing with eco-friendly options</li>
                        <li>Responsible recycling services for old electronics</li>
                        <li>Community-focused environmental initiatives</li>
                    </ul>

                    <h2>Our Team</h2>
                    <p>Our team consists of both tech enthusiasts and environmental specialists who are passionate about creating a sustainable future. We stay current with both technological advances and environmental best practices to provide you with the most sustainable solutions possible.</p>

                    <p>We're committed to making a positive impact on both technology and the environment. If you have any questions about our products or recycling services, please don't hesitate to contact us.</p>
                </div>
            </div>
        </section>
    </main>
</div>

        
        <?php
include 'footer.php';
?>
        
    </div>
    <button class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        const backToTopButton = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>