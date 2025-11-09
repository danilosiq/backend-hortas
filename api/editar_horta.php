<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, PUT, OPTIONS"); // Adicionando PUT
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
// Lida com métodos POST e PUT
$dados = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido recebido.");
}

// =====================================================
// 🧩 Validação de campos obrigatórios para edição
// =====================================================
$camposObrigatorios = [
    'id_horta', // Novo campo obrigatório para saber qual horta editar
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
        send_response("erro", "O campo '$campo' é obrigatório para a edição.");
    }
}

$id_horta = (int)$dados['id_horta'];

// =====================================================
// 🧠 Preparação e sanitização segura dos dados
// =====================================================
$descricao = htmlspecialchars($dados['descricao'] ?? '', ENT_QUOTES, 'UTF-8');
$descricao = substr($descricao, 0, 255); // evita erro SQL 1406

// Se vier string vazia no CNPJ, vira NULL
$cnpj = trim($dados['cnpj'] ?? '');
if ($cnpj === '') {
    $cnpj = null;
}

$visibilidade = $dados['visibilidade'] ?? 1;

try {
    $conn->beginTransaction();

    // =====================================================
    // 0️⃣ Buscar ID do Endereço (necessário para o UPDATE)
    // =====================================================
    $sql_busca_endereco = "SELECT endereco_hortas_id_endereco_hortas FROM hortas WHERE id_horta = :id_horta";
    $stmt = $conn->prepare($sql_busca_endereco);
    $stmt->execute([':id_horta' => $id_horta]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        $conn->rollBack();
        send_response("erro", "Horta com ID $id_horta não encontrada.");
    }

    $id_endereco = $resultado['endereco_hortas_id_endereco_hortas'];

    // =====================================================
    // 1️⃣ Atualizar endereço
    // =====================================================
    $sql_endereco = "UPDATE endereco_hortas SET 
                     nm_rua = :rua, 
                     nr_cep = :cep, 
                     nm_bairro = :bairro, 
                     nm_estado = :estado, 
                     nm_cidade = :cidade, 
                     nm_pais = :pais
                     WHERE id_endereco_hortas = :id_endereco";
    $stmt = $conn->prepare($sql_endereco);
    $stmt->execute([
        ':rua' => htmlspecialchars($dados['rua'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':cep' => htmlspecialchars($dados['cep'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':bairro' => htmlspecialchars($dados['bairro'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':estado' => htmlspecialchars($dados['estado'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':cidade' => htmlspecialchars($dados['cidade'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':pais' => htmlspecialchars($dados['pais'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':id_endereco' => $id_endereco
    ]);

    // =====================================================
    // 2️⃣ Atualizar horta
    // =====================================================
    $sql_horta = "UPDATE hortas SET 
                  nr_cnpj = :cnpj, 
                  nome = :nome, 
                  descricao = :descricao, 
                  visibilidade = :visibilidade
                  WHERE id_horta = :id_horta";

    $stmt = $conn->prepare($sql_horta);
    $stmt->bindValue(':cnpj', $cnpj, $cnpj === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':nome', htmlspecialchars($dados['nome_horta'] ?? '', ENT_QUOTES, 'UTF-8'));
    $stmt->bindValue(':descricao', $descricao);
    $stmt->bindValue(':visibilidade', (int)$visibilidade, PDO::PARAM_INT);
    $stmt->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);

    $stmt->execute();

    $conn->commit();

    send_response("sucesso", "Horta atualizada com sucesso!", [
        'id_horta' => $id_horta,
        'id_endereco' => $id_endereco
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    // Exibe o erro SQL para debug, mas pode ser simplificado em produção
    send_response("erro", "Erro no banco de dados durante a atualização: " . $e->getMessage());
} catch (Throwable $t) {
    send_response("erro", "Erro interno durante a atualização: " . $t->getMessage());
}
?>