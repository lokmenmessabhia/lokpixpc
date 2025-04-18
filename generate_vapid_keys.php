<?php
require_once 'vendor/autoload.php';
use Minishlink\WebPush\VAPID;

$vapid = VAPID::createVapidKeys();

// Save keys to a file
$keys = [
    'publicKey' => $vapid['publicKey'],
    'privateKey' => $vapid['privateKey']
];

// Save to config file
file_put_contents('vapid_keys.php', "<?php\ndefine('VAPID_PUBLIC_KEY', '" . $keys['publicKey'] . "');\ndefine('VAPID_PRIVATE_KEY', '" . $keys['privateKey'] . "');");

echo "VAPID keys have been generated and saved to vapid_keys.php\n";
echo "Public Key: " . $vapid['publicKey'] . "\n";
echo "Private Key: " . $vapid['privateKey'] . "\n";
