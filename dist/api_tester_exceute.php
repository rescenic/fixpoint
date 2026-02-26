<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

$url = isset($_POST['url']) ? $_POST['url'] : '';
$method = isset($_POST['method']) ? $_POST['method'] : 'GET';
$headers_json = isset($_POST['headers']) ? $_POST['headers'] : '{}';
$body = isset($_POST['body']) ? $_POST['body'] : '';
$useBpjsAuth = isset($_POST['useBpjsAuth']) ? $_POST['useBpjsAuth'] : 0;

if (empty($url)) {
    echo json_encode(['error' => 'URL required']);
    exit;
}

// Parse headers
$custom_headers = [];
try {
    $custom_headers = json_decode($headers_json, true);
    if ($custom_headers === null) {
        $custom_headers = [];
    }
} catch (Exception $e) {
    $custom_headers = [];
}

// Build request headers
$request_headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept' => 'application/json, text/plain, */*',
    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control' => 'no-cache'
];

// Merge custom headers
$request_headers = array_merge($request_headers, $custom_headers);

// BPJS Authentication
$bpjs_decrypt_data = null;
if ($useBpjsAuth == 1) {
    $consId = $_POST['consId'] ?? '';
    $secretKey = $_POST['secretKey'] ?? '';
    $userKey = $_POST['userKey'] ?? '';
    
    if (!empty($consId) && !empty($secretKey) && !empty($userKey)) {
        // Generate timestamp
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        
        // Generate signature
        $signature = hash_hmac('sha256', $consId . "&" . $tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);
        
        // Add BPJS headers
        $request_headers['x-cons-id'] = $consId;
        $request_headers['x-timestamp'] = $tStamp;
        $request_headers['x-signature'] = $encodedSignature;
        $request_headers['user_key'] = $userKey;
        
        // Store for decrypt later
        $bpjs_decrypt_data = [
            'consId' => $consId,
            'secretKey' => $secretKey,
            'timestamp' => $tStamp
        ];
    }
}

// Build cURL request
$start_time = microtime(true);
$curl = curl_init();

$curl_headers = [];
foreach ($request_headers as $key => $value) {
    $curl_headers[] = "$key: $value";
}

$curl_options = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => $curl_headers,
    CURLOPT_HEADER => true,
    CURLOPT_VERBOSE => false
];

// Add body for POST/PUT
if (($method === 'POST' || $method === 'PUT') && !empty($body)) {
    $curl_options[CURLOPT_POSTFIELDS] = $body;
}

curl_setopt_array($curl, $curl_options);

$response = curl_exec($curl);
$end_time = microtime(true);
$response_time = round(($end_time - $start_time) * 1000, 2);

$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curl_error = curl_error($curl);
$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

curl_close($curl);

// Parse response
$response_headers = [];
$response_body = '';

if ($response !== false) {
    $header = substr($response, 0, $header_size);
    $response_body = substr($response, $header_size);
    
    // Parse headers
    $header_lines = explode("\r\n", $header);
    foreach ($header_lines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $response_headers[trim($key)] = trim($value);
        }
    }
}

// Status text mapping
$status_texts = [
    200 => 'OK',
    201 => 'Created',
    204 => 'No Content',
    301 => 'Moved Permanently',
    302 => 'Found',
    304 => 'Not Modified',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    500 => 'Internal Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable'
];

$status_text = isset($status_texts[$http_code]) ? $status_texts[$http_code] : 'Unknown';

// Build result
$result = [
    'request' => [
        'url' => $url,
        'method' => $method,
        'headers' => $request_headers,
        'body' => $body,
        'timestamp' => date('Y-m-d H:i:s')
    ],
    'response' => [
        'status' => $http_code,
        'statusText' => $status_text,
        'headers' => $response_headers,
        'body' => $response_body,
        'time' => $response_time,
        'size' => strlen($response_body),
        'timestamp' => date('Y-m-d H:i:s')
    ]
];

// Jika ada error cURL
if (!empty($curl_error)) {
    $result['response']['error'] = $curl_error;
}

// BPJS Decrypt (jika ada)
if ($bpjs_decrypt_data && !empty($response_body)) {
    try {
        $json_response = json_decode($response_body, true);
        
        if (isset($json_response['response'])) {
            $encrypted_response = $json_response['response'];
            
            // Decrypt
            $kunci = $bpjs_decrypt_data['consId'] . 
                     $bpjs_decrypt_data['secretKey'] . 
                     $bpjs_decrypt_data['timestamp'];
            
            $decrypted = stringDecrypt($kunci, $encrypted_response);
            
            if ($decrypted) {
                // Decompress (jika menggunakan LZString)
                // Untuk sementara, tampilkan decrypted saja
                $result['decrypted'] = json_decode($decrypted, true);
                
                if ($result['decrypted'] === null) {
                    $result['decrypted'] = $decrypted;
                }
            }
        }
    } catch (Exception $e) {
        $result['decrypt_error'] = $e->getMessage();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Function decrypt BPJS
function stringDecrypt($key, $string) {
    if (empty($string)) {
        return null;
    }
    
    $encrypt_method = 'AES-256-CBC';
    $key_hash = hex2bin(hash('sha256', $key));
    $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);
    
    $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);
    
    return $output;
}
?>