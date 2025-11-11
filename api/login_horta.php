<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// ✅ FUNÇÃO PARA ENVIAR RESPOSTA
// =====================================================
function send_response($status, $message, $data = null)
{
    http_response_code($status);
    $response = ['message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// =====================================================
// ✅ CONEXÃO COM O BANCO DE DADOS
// =====================================================
try {
    include "db_connection.php"; // Conexão com o banco
} catch (PDOException $e) {
    send_response(500, "Erro de conexão com o banco de dados: " . $e->getMessage());
}

// =====================================================
// ✅ VALIDAÇÃO DE LOGIN E GERAÇÃO DE TOKEN
// =====================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $email = $data->email ?? null;
    $senha = $data->senha ?? null;

    if (!$email || !$senha) {
        send_response(400, 'Email e senha são obrigatórios.');
    }

    try {
        // Busca o produtor pelo email
        $stmt = $conn->prepare("SELECT id_produtor, senha FROM produtor WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica a senha
        if ($user && password_verify($senha, $user['senha'])) {
            // Gera um token JWT simples (sem bibliotecas externas)
            $payload = [
                'id_produtor' => $user['id_produtor'],
                'exp' => time() + (24 * 3600) // Expira em 24 horas
            ];
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
            $token = $base64UrlHeader . "." . $base64UrlPayload;

            // Salva a sessão no banco
            $exp_date = date('Y-m-d H:i:s', $payload['exp']);
            $session_stmt = $conn->prepare("INSERT INTO session (jwt_token, data_expiracao, produtor_id_produtor) VALUES (:token, :exp_date, :id_produtor)");
            $session_stmt->bindParam(':token', $token);
            $session_stmt->bindParam(':exp_date', $exp_date);
            $session_stmt->bindParam(':id_produtor', $user['id_produtor']);
            $session_stmt->execute();

            send_response(200, 'Login bem-sucedido!', ['token' => $token]);
        } else {
            send_response(401, 'Email ou senha inválidos.');
        }
    } catch (PDOException $e) {
        send_response(500, 'Erro no servidor: ' . $e->getMessage());
    }
}
