<?php
$year = date('Y');
?>
    </main>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-car"></i> Smart Driver</h3>
                    <p>Your smart solution for traffic violation monitoring, fuel tracking, and safer driving.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo $root_path ?? ''; ?>index.php">Home</a></li>
                        <li><a href="<?php echo $root_path ?? ''; ?>dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo $root_path ?? ''; ?>driver/violations.php">Violations</a></li>
                        <li><a href="<?php echo $root_path ?? ''; ?>driver/fuel_records.php">Fuel Records</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><i class="fas fa-phone"></i> +251 911 234 567</p>
                    <p><i class="fas fa-envelope"></i> info@smartdriver.com</p>
                    <p><i class="fas fa-map-marker-alt"></i> Addis Ababa, Ethiopia</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo $year; ?> Smart Driver. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo $root_path ?? ''; ?>js/script.js"></script>
</body>
</html>