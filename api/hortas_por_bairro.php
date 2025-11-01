<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// =====================================================
// 🔧 Função de resposta padronizada (nunca 500, sempre 200)
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// =====================================================
// 🔌 Conectar ao banco (via PDO)
// =====================================================
try {
    include 'banco_mysql.php'; // $conn (PDO)
    if (!isset($conn) || !$conn) {
        send_response("erro", "Falha na conexão com o banco de dados.");
    }
} catch (Throwable $e) {
    send_response("erro", "Erro ao conectar ao banco de dados.");
}

// =====================================================
// 📩 Validação de método e JSON recebido
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response("erro", "Método inválido. Use POST.");
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido enviado.");
}

$bairro = trim($input['bairro'] ?? '');

if (empty($bairro)) {
    send_response("erro", "Campo obrigatório: bairro.");
}

// =====================================================
// 🔍 Busca as hortas pelo bairro
// =====================================================
try {
    $sql = "SELECT 
                h.nome, 
                h.descricao, 
                e.nm_rua AS endereco, 
                e.nm_bairro AS bairro
            FROM hortas h
            INNER JOIN endereco_hortas e 
                ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas
            WHERE e.nm_bairro = :bairro
            ORDER BY h.nome ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':bairro', $bairro);
    $stmt->execute();

    $hortas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send_response("sucesso", "Busca realizada com sucesso.", [
        "bairro" => $bairro,
        "quantidade" => count($hortas),
        "hortas" => $hortas
    ]);

} catch (Throwable $t) {
    send_response("erro", "Erro ao buscar hortas: " . $t->getMessage());
}
?>