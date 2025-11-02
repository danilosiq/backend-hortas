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
// 🚫 Função para resposta JSON padronizada
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

// =====================================================
// 🧩 Validação de campos obrigatórios
// =====================================================
$camposObrigatorios = [
    'nome_horta',
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

// =====================================================
// 🧠 Preparação e sanitização segura dos dados
// =====================================================
$id_produtor = $dados['id_produtor'] ?? null;
$descricao = htmlspecialchars($dados['descricao'] ?? '', ENT_QUOTES, 'UTF-8');
$descricao = substr($descricao, 0, 255); // evita erro SQL 1406

// Se vier string vazia no CNPJ, vira NULL (para permitir UNIQUE)
$cnpj = trim($dados['cnpj'] ?? '');
if ($cnpj === '') {
    $cnpj = null;
}

$visibilidade = $dados['visibilidade'] ?? 1;

try {
    $conn->beginTransaction();

    // =====================================================
    // 1️⃣ Inserir endereço
    // =====================================================
    $sql_endereco = "INSERT INTO endereco_hortas (nm_rua, nr_cep, nm_bairro, nm_estado, nm_cidade, nm_pais) 
                     VALUES (:rua, :cep, :bairro, :estado, :cidade, :pais)";
    $stmt = $conn->prepare($sql_endereco);
    $stmt->execute([
        ':rua' => htmlspecialchars($dados['rua'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':cep' => htmlspecialchars($dados['cep'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':bairro' => htmlspecialchars($dados['bairro'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':estado' => htmlspecialchars($dados['estado'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':cidade' => htmlspecialchars($dados['cidade'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':pais' => htmlspecialchars($dados['pais'] ?? '', ENT_QUOTES, 'UTF-8')
    ]);

    $id_endereco = $conn->lastInsertId();

    // =====================================================
    // 2️⃣ Inserir horta
    // =====================================================
    $sql_horta = "INSERT INTO hortas (
                      endereco_hortas_id_endereco_hortas,
                      produtor_id_produtor,
                      nr_cnpj,
                      nome,
                      descricao,
                      visibilidade,
                      receitas_geradas
                  )
                  VALUES (
                      :id_endereco,
                      :id_produtor,
                      :cnpj,
                      :nome,
                      :descricao,
                      :visibilidade,
                      0
                  )";

    $stmt = $conn->prepare($sql_horta);
    $stmt->bindValue(':id_endereco', $id_endereco, PDO::PARAM_INT);
    $stmt->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmt->bindValue(':cnpj', $cnpj, $cnpj === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':nome', htmlspecialchars($dados['nome_horta'] ?? '', ENT_QUOTES, 'UTF-8'));
    $stmt->bindValue(':descricao', $descricao);
    $stmt->bindValue(':visibilidade', (int)$visibilidade, PDO::PARAM_INT);

    $stmt->execute();

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