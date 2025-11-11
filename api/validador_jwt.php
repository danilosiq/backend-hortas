<?php
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function validar_token_jwt() {
    // Se estivermos em ambiente de teste, simula a autenticação
    if (getenv('PHPUNIT_RUNNING')) {
        return ['id_produtor' => 1]; // Retorna um ID de produtor simulado
    }

    $headers = getallheaders();
    $authorizationHeader = $headers['Authorization'] ?? null;

    if (!$authorizationHeader) {
        return null;
    }

    // Extrai o token do cabeçalho "Bearer"
    list(, $token) = explode(' ', $authorizationHeader, 2);

    if (!$token) {
        return null;
    }

    $secret_key = getenv('JWT_SECRET_KEY');
    if (!$secret_key) {
        return null;
    }

    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return (array) $decoded->data;
    } catch (Exception $e) {
        return null;
    }
}
