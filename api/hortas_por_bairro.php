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
// 🔧 Função de resposta padronizada (sempre 200)
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
// 🔌 Conexão com o banco (PDO)
// =====================================================
try {
    include 'banco_mysql.php'; // define $conn
    if (!isset($conn) || !$conn) {
        send_response("erro", "Falha na conexão com o banco de dados.");
    }
} catch (Throwable $e) {
    send_response("erro", "Erro ao conectar ao banco de dados.");
}

// =====================================================
// 📩 Validação do método e JSON recebido
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
// 🔍 Busca as hortas pelo bairro (com id_produtor)
// =====================================================
try {
    $sql = "SELECT 
                h.id_hortas AS id_horta,
                h.nome, 
                h.descricao, 
                h.produtor_id_produtor AS id_produtor,
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