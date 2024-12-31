<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

function isUrlAllowed($url, $allowedUrlPrefixes) {
    foreach ($allowedUrlPrefixes as $pattern) {
        // Convert the wildcard pattern to a regular expression
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        
        // Check if the URL matches the pattern
        if (preg_match($regex, $url)) {
            return true;
        }
    }
    return false;
}

// Whitelist of allowed URL prefixes
$allowedUrlPrefixes = [
    'https://*.stv.livebox.sk/*',
    'https://media.cms.markiza.sk/embed/*',
    'https://www.ceskatelevize.cz/services/ivysilani/*',
    'https://www.ceskatelevize.cz/ivysilani/*',
    'https://media.cms.nova.cz/embed/*'
    // Add more allowed URL prefixes here
];

// Get the 'q' parameter from the request
$encodedUrl = $_GET['q'] ?? '';

// Decode the URL
$url = urldecode($encodedUrl);

// Check if the URL starts with any of the allowed prefixes
$isAllowed = isUrlAllowed($url, $allowedUrlPrefixes);

if ($isAllowed) {
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Get all headers from the original request
    $headers = getallheaders();
    $curlHeaders = [];
    
    // Format headers for cURL
    foreach ($headers as $name => $value) {
        // Skip host header as it should match the target URL
        if (strtolower($name) !== 'host') {
            $curlHeaders[] = "$name: $value";
        }
    }
    
    // Set cURL options to mirror the original request
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
    ]);
    
    // If this is a POST request, forward the POST data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    }
    
    // Execute the request
    $response = curl_exec($ch);
    
    if ($response === false) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error: " . curl_error($ch);
        curl_close($ch);
        exit;
    }
    
    // Get the header size and separate headers from body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Close cURL session
    curl_close($ch);
   
    // Forward relevant headers from the response
    foreach (explode("\r\n", $responseHeaders) as $header) {
        
            header($header);
        
    }

    // Output the response body
    echo $body;
} else {
    // URL is not allowed
    header("HTTP/1.1 403 Forbidden");
    echo "It seems that this URL is not allowed for proxying. This is purely a temporary solution for sktv-forwarders project. Well there... are you being naughty? ;)";
}
