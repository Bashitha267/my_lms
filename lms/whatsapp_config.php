<?php
// WhatsApp API Configuration
// Update these values with your actual WhatsApp API credentials
// If you don't have WhatsApp API, leave these as placeholders - the system will work without it

// Define constants only if not already defined (allows for optional usage)
if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', 'https://api.example.com/whatsapp/send'); // Replace with your WhatsApp API endpoint
}

if (!defined('WHATSAPP_API_EMAIL')) {
    define('WHATSAPP_API_EMAIL', 'your-email@example.com'); // Replace with your API email
}

if (!defined('WHATSAPP_API_KEY')) {
    define('WHATSAPP_API_KEY', 'your-api-key-here'); // Replace with your API key
}

// Flag to enable/disable WhatsApp functionality
if (!defined('WHATSAPP_ENABLED')) {
    define('WHATSAPP_ENABLED', false); // Set to true when you have valid credentials
}

?>

