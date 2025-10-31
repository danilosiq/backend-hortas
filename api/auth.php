<?php
// --- Cabeçalhos básicos ---
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- Lidar com requisição CORS pre-flight (OPTIONS) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$resposta = [];

try {
    include "banco_mysql.php"; // Conexão com o banco

    // --- Receber o corpo da requisição ---
    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->token) || empty($dados->data_atual)) {
        http_response_code(400);
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Token e data_atual são obrigatórios."
        ]);
        exit;
    }

    $jwt = htmlspecialchars(strip_tags($dados->token));
    $dataAtual = htmlspecialchars(strip_tags($dados->data_atual));

    // --- Verifica se a sessão existe ---
    $sql = "SELECT data_expiracao, produtor_id_produtor 
            FROM session 
            WHERE jwt_token = :jwt
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':jwt', $jwt);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Token não existe no banco
        http_response_code(401);
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Sessão inválida ou não encontrada."
        ]);
        exit;
    }

    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
    $dataExpiracao = $sessao['data_expiracao'];

    // --- Comparar as datas ---
    if (strtotime($dataAtual) > strtotime($dataExpiracao)) {
        // Sessão expirada → pode opcionalmente removê-la do banco
        $delete = $conn->prepare("DELETE FROM session WHERE jwt_token = :jwt");
        $delete->bindValue(':jwt', $jwt);
        $delete->execute();

        http_response_code(401);
        echo json_encode([
            "status" => "erro",
            "mensagem" => "Sessão expirada."
        ]);
        exit;
    }

    // --- Sessão válida ---
    http_response_code(200);
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Sessão válida.",
        "id_produtor" => $sessao['produtor_id_produtor'],
        "expira_em" => $dataExpiracao
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("PDOException: " . $e->getMessage());
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro no servidor (DB)."
    ]);
} catch (Throwable $t) {
    http_response_code(500);
    error_log("Throwable: " . $t->getMessage());
    echo json_encode([
        "status" => "erro",
        "mensagem" => "Erro interno no servidor."
    ]);
}
?>