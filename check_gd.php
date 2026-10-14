<?php
// check_gd.php
// This checks if GD library is enabled

echo "<h2>?? Checking GD Library...</h2>";

if (extension_loaded('gd')) {
    echo "? GD Library is <strong>ENABLED</strong>!<br>";
    $gd_info = gd_info();
    echo "GD Version: <strong>" . $gd_info['GD Version'] . "</strong><br>";
    echo "? You can create car images now.<br>";
    echo "<br><a href='create_car_images.php'>?? Click here to create car images</a>";
} else {
    echo "? GD Library is <strong>NOT enabled</strong>!<br>";
    echo "Please enable it in php.ini<br>";
    echo "<br>1. Open C:\\xampp\\php\\php.ini<br>";
    echo "2. Find: ;extension=gd<br>";
    echo "3. Remove the semicolon (;)<br>";
    echo "4. Save and restart Apache<br>";
    echo "<br><a href='https://www.php.net/manual/en/image.installation.php' target='_blank'>?? How to enable GD</a>";
}
?>