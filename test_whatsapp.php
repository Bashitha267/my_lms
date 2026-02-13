<?php
require_once 'whatsapp_config.php';

$test_number = '0771234567'; // Change this to a real number for testing
$test_message = "Test message from LMS system at " . date('Y-m-d H:i:s');

echo "Testing WhatsApp API...<br>";
echo "URL: " . WHATSAPP_API_URL . "<br>";
echo "Email (Domain): " . WHATSAPP_API_EMAIL . "<br>";
echo "Key: " . WHATSAPP_API_KEY . "<br>";

$result = sendWhatsAppMessage($test_number, $test_message);

echo "<h3>Result:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

if (!$result['success']) {
    echo "<h4>Possible issues:</h4>";
    echo "<ul>";
    echo "<li>API credentials might be wrong</li>";
    echo "<li>The endpoint URL might be wrong</li>";
    echo "<li>SSL certificate verification might be failing on local XAMPP</li>";
    echo "</ul>";
}
?>
