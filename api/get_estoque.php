<?php
// =====================================================
// ✅ CORS + Preflight (antes de qualquer saída)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// =====================================================
// ✅ Função de resposta padrão
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    echo json_encode(
        array_merge(['status' => $status, 'mensagem' => $mensagem], $extra),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit();
}

// =====================================================
// ✅ Inclui conexão PDO (banco_mysql.php)
// =====================================================
$conn = null;
$candidates = [
    __DIR__ . '/banco_mysql.php',
    __DIR__ . '/../banco_mysql.php'
];
foreach ($candidates as $path) {
    if (file_exists($path) && is_readable($path)) {
        include_once $path;
        if (isset($conn) && $conn instanceof PDO) break;
    }
}
if (!$conn) send_response("erro", "Conexão PDO não encontrada.");

// =====================================================
// ✅ Lê JSON do body
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);
$id_produtor = isset($input['id_produtor']) ? (int)$input['id_produtor'] : null;
if (!$id_produtor) send_response("erro", "id_produtor obrigatório.");

// =====================================================
// ✅ Busca horta do produtor
// =====================================================
try {
    $stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id_produtor LIMIT 1");
    $stmt->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) send_response("erro", "Produtor não possui horta.");

    $id_horta = (int)$stmt->fetch(PDO::FETCH_ASSOC)['id_hortas'];
} catch (Throwable $t) {
    send_response("erro", "Erro ao buscar horta: " . $t->getMessage());
}

// =====================================================
// ✅ Busca produtos e estoque da horta
// =====================================================
try {
    $stmt = $conn->prepare("
        SELECT 
            e.id_estoques,
            p.id_produto,
            p.nm_produto,
            p.descricao,
            p.unidade_medida_padrao,
            e.ds_quantidade,
            e.dt_plantio,
            e.dt_colheita
        FROM estoques e
        JOIN produtos p ON e.produto_id_produto = p.id_produto
        WHERE e.hortas_id_hortas = :id_horta
        ORDER BY p.nm_produto ASC
    ");
    $stmt->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send_response("sucesso", "Produtos da horta carregados.", [
        'horta' => [
            'id_horta' => $id_horta,
            'produtos' => $produtos
        ]
    ]);
} catch (Throwable $t) {
    send_response("erro", "Erro ao buscar produtos: " . $t->getMessage());
}