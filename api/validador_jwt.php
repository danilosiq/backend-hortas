<?php
// Carrega as dependências do Composer
require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

// Função para validar o token JWT enviado no cabeçalho da requisição
function validar_token_jwt() {
    // Carrega as variáveis de ambiente (onde a chave secreta do JWT deve estar)
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $jwtSecretKey = $_ENV['JWT_SECRET_KEY'] ?? null;

    if (!$jwtSecretKey) {
        send_error('A chave secreta JWT (JWT_SECRET_KEY) não foi configurada no servidor.', 500);
    }

    // Pega todos os cabeçalhos da requisição
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader) {
        send_error('Token de autenticação não fornecido.', 401);
    }

    // O token geralmente vem no formato "Bearer [token]"
    // Precisamos extrair apenas a parte do token
    list(, $token) = explode(' ', $authHeader);

    if (!$token) {
        send_error('Formato de token inválido.', 401);
    }

    try {
        // Decodifica o token. Se for inválido, uma exceção será lançada.
        $decoded = JWT::decode($token, new Key($jwtSecretKey, 'HS256'));
        
        // Retorna os dados decodificados (payload), que devem conter o ID do produtor
        return (array) $decoded->data;

    } catch (Exception $e) {
        // Se a validação falhar (token expirado, assinatura inválida, etc.)
        send_error('Acesso não autorizado: ' . $e->getMessage(), 401);
    }
}
?>
