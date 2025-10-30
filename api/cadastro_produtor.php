<?php
// 💡 Inclui o boilerplate de CORS, Erro e DB
include 'cors_comum.php';

// =====================================================
// 📩 Validação dos campos obrigatórios do Produtor
// =====================================================
// Campos de produtor, incluindo as perguntas e respostas de segurança
$camposObrigatorios = [
    'nome_produtor', 'nr_cpf', 'email_produtor', 'senha',
    'pergunta_1', 'resposta_1', 'pergunta_2', 'resposta_2' // NOVOS CAMPOS OBRIGATÓRIOS
];

foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        send_error("O campo '$campo' (do produtor/segurança) é obrigatório.", 400);
    }
}

// Valores opcionais
$telefone = $dados['telefone_produtor'] ?? '';

// =====================================================
// 🧱 Inserção no banco: Produtor e Segurança
// =====================================================
try {
    $conn->beginTransaction();

    // 1️⃣ Produtor
    $sql_produtor = "INSERT INTO produtor (nome_produtor, nr_cpf, email_produtor, hash_senha, telefone_produtor)
                     VALUES (:nome_produtor, :nr_cpf, :email_produtor, :hash_senha, :telefone)";
    $stmt = $conn->prepare($sql_produtor);
    $stmt->execute([
        ':nome_produtor' => htmlspecialchars($dados['nome_produtor']),
        ':nr_cpf' => htmlspecialchars($dados['nr_cpf']),
        ':email_produtor' => htmlspecialchars($dados['email_produtor']),
        ':hash_senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
        ':telefone' => htmlspecialchars($telefone)
    ]);
    
    $id_produtor = $conn->lastInsertId();

    // 2️⃣ Segurança (Novidade: Inserção das perguntas/respostas)
    $sql_seguranca = "INSERT INTO seguranca_produtor (produtor_id_produtor, pergunta_1, resposta_1_hash, pergunta_2, resposta_2_hash)
                      VALUES (:id_produtor, :p1, :r1_hash, :p2, :r2_hash)";
    $stmt_seg = $conn->prepare($sql_seguranca);
    $stmt_seg->execute([
        ':id_produtor' => $id_produtor,
        ':p1' => htmlspecialchars($dados['pergunta_1']),
        // Hash da resposta de segurança
        ':r1_hash' => password_hash(strtolower($dados['resposta_1']), PASSWORD_DEFAULT), 
        ':p2' => htmlspecialchars($dados['pergunta_2']),
        // Hash da resposta de segurança
        ':r2_hash' => password_hash(strtolower($dados['resposta_2']), PASSWORD_DEFAULT)
    ]);

    $conn->commit();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Produtor cadastrado com sucesso! ID retornado para vincular a horta.',
        'id_produtor' => $id_produtor 
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    $conn->rollBack();
    // Tratar erros de duplicidade (e-mail, CPF)
    if ($e->getCode() === '23000') {
         send_error('Erro: E-mail ou CPF já cadastrado.', 409);
    }
    send_error('Erro no banco de dados durante o cadastro do produtor: ' . $e->getMessage(), 500);
}
?>
