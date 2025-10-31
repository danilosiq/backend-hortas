<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// =====================================================
// 🚫 Nunca usar send_error com códigos HTTP != 200
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 🔌 Conexão com o banco
// =====================================================
try {
    include "banco_mysql.php";
} catch (Throwable $e) {
    send_response("erro", "Falha ao conectar ao banco: " . $e->getMessage());
}

// =====================================================
// 📥 Recebe e valida o JSON
// =====================================================
$dados = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido recebido.");
}

$camposObrigatorios = [
    'id_produtor',
    'nome_horta',
    'cnpj',
    'rua',
    'bairro',
    'cep',
    'cidade',
    'estado',
    'pais'
];

foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        send_response("erro", "O campo '$campo' é obrigatório.");
    }
}

$id_produtor = $dados['id_produtor'];
$descricao = $dados['descricao'] ?? '';
$visibilidade = $dados['visibilidade'] ?? 1;

try {
    $conn->beginTransaction();

    // 1️⃣ Endereço
    $sql_endereco = "INSERT INTO endereco_hortas (nm_rua, nr_cep, nm_bairro, nm_estado, nm_cidade, nm_pais) 
                     VALUES (:rua, :cep, :bairro, :estado, :cidade, :pais)";
    $stmt = $conn->prepare($sql_endereco);
    $stmt->execute([
        ':rua' => htmlspecialchars($dados['rua']),
        ':cep' => htmlspecialchars($dados['cep']),
        ':bairro' => htmlspecialchars($dados['bairro']),
        ':estado' => htmlspecialchars($dados['estado']),
        ':cidade' => htmlspecialchars($dados['cidade']),
        ':pais' => htmlspecialchars($dados['pais'])
    ]);

    $id_endereco = $conn->lastInsertId();

    // 2️⃣ Horta
    $sql_horta = "INSERT INTO hortas (endereco_hortas_id_endereco_hortas, produtor_id_produtor, nr_cnpj, nome, descricao, visibilidade, receitas_geradas)
                  VALUES (:id_endereco, :id_produtor, :cnpj, :nome, :descricao, :visibilidade, 0)";
    $stmt = $conn->prepare($sql_horta);
    $stmt->execute([
        ':id_endereco' => $id_endereco,
        ':id_produtor' => $id_produtor,
        ':cnpj' => htmlspecialchars($dados['cnpj']),
        ':nome' => htmlspecialchars($dados['nome_horta']),
        ':descricao' => htmlspecialchars($descricao),
        ':visibilidade' => $visibilidade
    ]);

    $id_horta = $conn->lastInsertId();

    $conn->commit();

    send_response("sucesso", "Horta cadastrada com sucesso!", [
        'id_horta' => $id_horta,
        'id_endereco' => $id_endereco,
        'id_produtor' => $id_produtor
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    send_response("erro", "Erro no banco de dados: " . $e->getMessage());
} catch (Throwable $t) {
    send_response("erro", "Erro interno: " . $t->getMessage());
}
?>