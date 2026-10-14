<?php
session_start();
include 'config/database.php';
$page_title = 'Home - Smart Driver';
$base_url = '';
include 'includes/header.php';
?>

<div class="container">
    <div style="text-align: center; padding: 60px 20px;">
        <h1 style="font-size: 42px; color: #1a237e; margin-bottom: 20px;">
            <i class="fas fa-car" style="color: #ffd54f;"></i> Smart Driver
        </h1>
        <p style="font-size: 18px; color: #666; max-width: 600px; margin: 0 auto 30px;">
            Your smart solution for traffic violation monitoring, 
            fuel tracking, and safer driving.
        </p>
        
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="login.php" style="padding: 14px 40px; background: #1a237e; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-sign-in-alt"></i> Driver Login
            </a>
            <a href="admin_login.php" style="padding: 14px 40px; background: #c62828; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-user-shield"></i> Admin Login
            </a>
        </div>
    </div>

    <!-- Features -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin: 40px 0;">
        <div class="card" style="text-align: center;">
            <div style="font-size: 48px; color: #1a237e; margin-bottom: 15px;">
                <i class="fas fa-gavel"></i>
            </div>
            <h3>Violation Monitoring</h3>
            <p style="color: #666;">Track and manage traffic violations with automated penalty calculation.</p>
        </div>
        
        <div class="card" style="text-align: center;">
            <div style="font-size: 48px; color: #1a237e; margin-bottom: 15px;">
                <i class="fas fa-gas-pump"></i>
            </div>
            <h3>Fuel Tracking</h3>
            <p style="color: #666;">Monitor fuel consumption and get personalized savings tips.</p>
        </div>
        
        <div class="card" style="text-align: center;">
            <div style="font-size: 48px; color: #1a237e; margin-bottom: 15px;">
                <i class="fas fa-road"></i>
            </div>
            <h3>Road Reporting</h3>
            <p style="color: #666;">Report and view road conditions, accidents, and traffic jams.</p>
        </div>
        
        <div class="card" style="text-align: center;">
            <div style="font-size: 48px; color: #1a237e; margin-bottom: 15px;">
                <i class="fas fa-trophy"></i>
            </div>
            <h3>Driver Scoring</h3>
            <p style="color: #666;">Earn points and badges for safe and efficient driving.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>