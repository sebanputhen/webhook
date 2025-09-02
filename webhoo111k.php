<?php
// api/webhook.php - Main webhook file for Vercel

header('Content-Type: text/plain');

$VERIFY_TOKEN = 'hello123';

// Log incoming requests (Vercel has built-in logging)
error_log("Webhook called: " . $_SERVER['REQUEST_METHOD'] . " - " . $_SERVER['REQUEST_URI']);

// Handle GET request (Facebook verification)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hub_mode = $_GET['hub_mode'] ?? '';
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';
    
    error_log("Verification: mode=$hub_mode, token=$hub_verify_token");
    
    if ($hub_mode === 'subscribe' && $hub_verify_token === $VERIFY_TOKEN) {
        // Verification successful
        http_response_code(200);
        echo $hub_challenge;
        error_log("✅ Verification SUCCESS");
        exit;
    } else {
        // Verification failed
        http_response_code(403);
        echo 'Verification failed';
        error_log("❌ Verification FAILED");
        exit;
    }
}

// Handle POST request (incoming webhook data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log("📨 Webhook data received: " . substr($input, 0, 200));
    
    // Process webhook data
    if (!empty($input)) {
        $data = json_decode($input, true);
        
        if ($data && isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'messages') {
                            processMessages($change['value']);
                        }
                    }
                }
            }
        }
    }
    
    http_response_code(200);
    echo 'OK';
    exit;
}

// Default response
http_response_code(200);
echo 'WhatsApp Webhook is running on Vercel - ' . date('Y-m-d H:i:s');

function processMessages($messageData) {
    // Handle incoming messages
    if (isset($messageData['messages'])) {
        foreach ($messageData['messages'] as $message) {
            $messageId = $message['id'];
            $fromNumber = $message['from'];
            $messageType = $message['type'];
            
            $messageText = '';
            if ($messageType === 'text') {
                $messageText = $message['text']['body'];
            } elseif ($messageType === 'document') {
                $messageText = 'Document: ' . ($message['document']['filename'] ?? 'file');
            }
            
            error_log("📱 New message from $fromNumber: $messageText");
            
            // Here you can:
            // 1. Save to external database
            // 2. Send to your VB.NET app via HTTP
            // 3. Store in Vercel KV database
            // 4. Forward to email/webhook
            
            // Example: Forward to your VB.NET app
            forwardToVBApp($fromNumber, $messageText, $messageType);
        }
    }
}

function forwardToVBApp($fromNumber, $messageText, $messageType) {
    try {
        // Send to your VB.NET application's endpoint
        $vb_endpoint = 'http://yourvbapp.com/receive-whatsapp'; // Replace with your endpoint
        
        $postData = json_encode([
            'from_number' => $fromNumber,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'timestamp' => time()
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $postData,
                'timeout' => 10
            ]
        ]);
        
        $result = file_get_contents($vb_endpoint, false, $context);
        error_log("Forwarded to VB.NET app: " . ($result ? 'Success' : 'Failed'));
        
    } catch (Exception $e) {
        error_log("Failed to forward to VB.NET: " . $e->getMessage());
    }
}
?>
