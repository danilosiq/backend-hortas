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
// 🔧 Função de erro padronizada
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['status' => 'erro', 'mensagem' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 🧩 Importa conexão com o banco
// =====================================================
include 'banco_mysql.php'; // deve definir $conn (PDO)

// =====================================================
// 📩 Validação da requisição
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas POST é aceito.', 405);
}

$dados = json_decode(file_get_contents("php://input"), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido enviado.', 400);
}

$camposObrigatorios = [
    'nome_horta', 'cnpj', 'nome_produtor', 'nr_cpf',
    'email_produtor', 'senha', 'rua', 'bairro',
    'cep', 'cidade', 'estado', 'pais'
];

foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        send_error("O campo '$campo' é obrigatório.", 400);
    }
}

// =====================================================
// 🧱 Inserção no banco
// =====================================================
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
    $sql_horta = "INSERT INTO hortas (endereco_hortas_id_endereco_hortas, nr_cnpj, nome, descricao, visibilidade, receitas_geradas)
                  VALUES (:id_endereco, :cnpj, :nome, :descricao, :visibilidade, 0)";
    $stmt = $conn->prepare($sql_horta);
    $stmt->execute([
        ':id_endereco' => $id_endereco,
        ':cnpj' => htmlspecialchars($dados['cnpj']),
        ':nome' => htmlspecialchars($dados['nome_horta']),
        ':descricao' => htmlspecialchars($dados['descricao'] ?? ''),
        ':visibilidade' => $dados['visibilidade'] ?? 1
    ]);

    $id_horta = $conn->lastInsertId();

    // 3️⃣ Produtor
    $sql_produtor = "INSERT INTO produtor (hortas_id_hortas, nome_produtor, nr_cpf, email_produtor, hash_senha, telefone_produtor)
                     VALUES (:id_horta, :nome_produtor, :nr_cpf, :email_produtor, :hash_senha, :telefone)";
    $stmt = $conn->prepare($sql_produtor);
    $stmt->execute([
        ':id_horta' => $id_horta,
        ':nome_produtor' => htmlspecialchars($dados['nome_produtor']),
        ':nr_cpf' => htmlspecialchars($dados['nr_cpf']),
        ':email_produtor' => htmlspecialchars($dados['email_produtor']),
        ':hash_senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
        ':telefone' => htmlspecialchars($dados['telefone_produtor'] ?? '')
    ]);

    $conn->commit();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Horta e produtor cadastrados com sucesso!',
        'id_horta' => $id_horta,
        'id_endereco' => $id_endereco
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    $conn->rollBack();
    send_error('Erro no banco de dados: ' . $e->getMessage(), 500);
}
?>