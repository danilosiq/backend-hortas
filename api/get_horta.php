<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// ✅ CONFIGURAÇÕES DO BANCO DE DADOS
// =====================================================

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
    include "db_connection.php";
} catch (PDOException $e) {
    send_response(500, "Erro de conexão com o banco de dados: " . $e->getMessage());
}

// =====================================================
// ✅ OBTENÇÃO DAS HORTAS
// =====================================================
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $token = $_GET['token'] ?? null;

    if (!$token) {
        send_response(400, "Token não fornecido.");
    }

    try {
        $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            send_response(401, 'Token inválido ou expirado.');
        }

        $id_produtor = $user['produtor_id_produtor'];

        $sql_hortas = "SELECT id_hortas, nm_horta FROM hortas WHERE produtor_id_produtor = :produtor_id_produtor";
        $stmt_hortas = $conn->prepare($sql_hortas);
        $stmt_hortas->bindParam(':produtor_id_produtor', $id_produtor);
        $stmt_hortas->execute();
        $hortas = $stmt_hortas->fetchAll(PDO::FETCH_ASSOC);

        send_response(200, "Hortas obtidas com sucesso.", $hortas);
    } catch (PDOException $e) {
        send_response(500, "Erro no banco de dados: " . $e->getMessage());
    }
}
