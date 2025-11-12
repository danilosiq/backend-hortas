<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=utf-8");

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
    include_once 'db_connection.php';
} catch (Throwable $e) {
    send_response(500, "Falha na conexão com o banco.");
    exit();
}

// =====================================================
// ✅ ATUALIZAÇÃO DO ESTOQUE
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->id_estoque)) {
        send_response(400, "ID do estoque não fornecido.");
    }
    if (!isset($data->token)) {
        send_response(400, "Token não fornecido.");
    }
    if (!isset($data->nova_quantidade)) {
        send_response(400, "Nova quantidade não fornecida.");
    }

    $id_estoque = $data->id_estoque;
    $token = $data->token;
    $nova_quantidade = $data->nova_quantidade;

    try {
        $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            send_response(401, 'Token inválido ou expirado.');
        }

        $id_produtor = $user['produtor_id_produtor'];

        // Atualizar o estoque
        $update_stmt = $conn->prepare("UPDATE estoques SET ds_quantidade = :nova_quantidade WHERE id_estoques = :id_estoque AND hortas_id_hortas IN (SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id_produtor)");
        $update_stmt->bindParam(':nova_quantidade', $nova_quantidade);
        $update_stmt->bindParam(':id_estoque', $id_estoque);
        $update_stmt->bindParam(':id_produtor', $id_produtor);
        $update_stmt->execute();

        if ($update_stmt->rowCount() > 0) {
            send_response(200, "Estoque atualizado com sucesso.");
        } else {
            send_response(404, "Estoque não encontrado ou não pertence ao produtor.");
        }
    } catch (PDOException $e) {
        send_response(500, "Erro no banco de dados: " . $e->getMessage());
    }
}
