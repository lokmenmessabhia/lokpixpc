<?php
session_start();
include 'db_connect.php';
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Lokpix</title>
 <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>;
            --accent-color: <?= htmlspecialchars($settings['accent_color'] ?? '#0b5ed7') ?>;
            --text-color: var(--header-text);
            --text-secondary: var(--header-text-secondary);
            --border-color: var(--header-border);
            --bg-color: var(--header-bg);
            --bg-secondary: var(--dropdown-hover-bg);
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Poppins, sans-serif') ?>;
            background: linear-gradient(135deg, var(--bg-color) 0%, var(--bg-secondary) 100%);
        margin: 0;
        padding: 0;
            color: var(--text-color);
            line-height: 1.6;
    }

    .about-container {
        max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
    }

        .about-header {
            text-align: center;
            margin-bottom: 50px;
        position: relative;
            padding-bottom: 20px;
    }

        .about-header:after {
        content: '';
        position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 3px;
    }

        .about-header h1 {
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 20px;
        }

        .about-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
    }

        .about-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .about-card {
            background: var(--bg-color);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

        .about-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }

        .about-card h2 {
            color: var(--text-color);
            font-size: 1.5rem;
            margin-bottom: 15px;
        position: relative;
            padding-bottom: 10px;
    }

        .about-card h2:after {
        content: '';
        position: absolute;
            bottom: 0;
        left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        border-radius: 2px;
    }

        .about-card p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
    }

        .team-section {
            margin-top: 80px;
        }

        .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
    }

        .team-member {
            background: var(--bg-color);
        border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        transition: transform 0.3s ease;
    }

        .team-member:hover {
        transform: translateY(-5px);
    }

        .team-member img {
            width: 100%;
            height: 250px;
            object-fit: cover;
    }

        .team-info {
            padding: 20px;
        text-align: center;
    }

        .team-info h3 {
            color: var(--text-color);
            margin: 0 0 5px 0;
            font-size: 1.2rem;
    }

        .team-info p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.9rem;
        }

        .stats-section {
            margin-top: 80px;
            padding: 60px 0;
            background: var(--bg-color);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
    }
    
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
}

        .stat-item {
    text-align: center;
}

        .stat-number {
            font-size: 2.5rem;
    font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
}

        .stat-label {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .about-header h1 {
                font-size: 2rem;
}

            .about-header p {
                font-size: 1rem;
}

            .about-card {
                padding: 20px;
}

            .team-member img {
                height: 200px;
            }

            .stats-section {
                padding: 40px 0;
}

            .stat-number {
                font-size: 2rem;
}

            .stat-label {
                font-size: 1rem;
}
        }
 </style>
</head>
<body>
                <div class="about-container">
        <div class="about-header">
            <h1>About Ecotech</h1>
            <p>Your trusted partner in technology solutions. We're dedicated to providing high-quality computer hardware, software, and expert services to meet all your tech needs.</p>
        </div>

        <div class="about-grid">
            <div class="about-card">
                <h2>Our Mission</h2>
                <p>To provide cutting-edge technology solutions while maintaining the highest standards of customer service and technical expertise.</p>
    </div>
            <div class="about-card">
                <h2>Our Vision</h2>
                <p>To be the leading technology provider, empowering individuals and businesses with innovative solutions for their digital needs.</p>
    </div>
            <div class="about-card">
                <h2>Our Values</h2>
                <p>Quality, Innovation, Integrity, and Customer Satisfaction are the cornerstones of our business philosophy.</p>
    </div>
</div>

        <div class="team-section">
            <div class="about-header">
                <h1>Our Team</h1>
                <p>Meet the dedicated professionals who make Ecotech your trusted technology partner.</p>
            </div>
            <div class="team-grid">
                <div class="team-member">
                    <img src="assets/images/team/team1.jpg" alt="Team Member">
                    <div class="team-info">
                        <h3>John Doe</h3>
                        <p>Founder & CEO</p>
                    </div>
                </div>
                <div class="team-member">
                    <img src="assets/images/team/team2.jpg" alt="Team Member">
                    <div class="team-info">
                        <h3>Jane Smith</h3>
                        <p>Technical Director</p>
                    </div>
                </div>
                <div class="team-member">
                    <img src="assets/images/team/team3.jpg" alt="Team Member">
                    <div class="team-info">
                        <h3>Mike Johnson</h3>
                        <p>Customer Service Manager</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">5000+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">1000+</div>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>