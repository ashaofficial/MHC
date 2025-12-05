<?php
include "config.php";  // load the JWT secret

function base64UrlEncode($data){
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data){
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT($payload){
    global $JWT_SECRET;

    $header = base64UrlEncode(json_encode([
        "alg" => "HS256",
        "typ" => "JWT"
    ]));

    $payload = base64UrlEncode(json_encode($payload));

    $signature = base64UrlEncode(
        hash_hmac("sha256", "$header.$payload", $JWT_SECRET, true)
    );

    return "$header.$payload.$signature";
}

function verifyJWT($token){
    global $JWT_SECRET;

    $parts = explode(".", $token);
    if (count($parts) != 3) return false;

    list($header, $payload, $signature) = $parts;

    $validSig = base64UrlEncode(
        hash_hmac("sha256", "$header.$payload", $JWT_SECRET, true)
    );

    if (!hash_equals($signature, $validSig)) {
        return false;
    }

    return json_decode(base64UrlDecode($payload), true);
}
?>
