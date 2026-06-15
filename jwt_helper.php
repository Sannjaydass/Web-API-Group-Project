<?php
function generateJWT($user_id, $username) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expiration = $issuedAt + 900; // 15 minutes = 900 seconds
    
    $payload = json_encode([
        'user_id' => $user_id,
        'username' => $username,
        'iat' => $issuedAt,
        'exp' => $expiration
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $secret = 'your_jwt_secret_key_2026_travelai';
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function verifyJWT($token) {
    $secret = 'your_jwt_secret_key_2026_travelai';
    $tokenParts = explode('.', $token);
    
    if (count($tokenParts) != 3) {
        return false;
    }
    
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $payloadData = json_decode($payload, true);
    
    // Check if token is expired
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return false;
    }
    
    $expectedSignature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $secret, true);
    $expectedSignatureBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
    
    if ($tokenParts[2] !== $expectedSignatureBase64) {
        return false;
    }
    
    return $payloadData;
}
?>