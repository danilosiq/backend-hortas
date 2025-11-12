<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE");
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
// ✅ DELEÇÃO DO PRODUTO
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->id_produto)) {
        send_response(400, "ID do produto não fornecido.");
    }
    if (!isset($data->token)) {
        send_response(400, "Token não fornecido.");
    }

    $id_produto = $data->id_produto;
    $token = $data->token;

    try {
        $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            send_response(401, 'Token inválido ou expirado.');
        }

        $id_produtor = $user['produtor_id_produtor'];

        // Deletar o produto
        $delete_stmt = $conn->prepare("DELETE FROM produtos WHERE id_produto = :id_produto AND id_produtor = :id_produtor");
        $delete_stmt->bindParam(':id_produto', $id_produto);
        $delete_stmt->bindParam(':id_produtor', $id_produtor);
        $delete_stmt->execute();

        if ($delete_stmt->rowCount() > 0) {
            send_response(200, "Produto deletado com sucesso.");
        } else {
            send_response(404, "Produto não encontrado ou não pertence ao produtor.");
        }
    } catch (PDOException $e) {
        send_response(500, "Erro no banco de dados: " . $e->getMessage());
    }
}
