<?php
// 💡 Inclui o boilerplate de CORS, Erro e DB
include 'cors_comum.php';

// =====================================================
// 📩 Validação dos campos obrigatórios da Horta, Endereço e Produtor ID
// =====================================================
$camposObrigatorios = [
    'id_produtor', // << NOVO CAMPO OBRIGATÓRIO AQUI
    'nome_horta', 'cnpj', 'rua', 'bairro',
    'cep', 'cidade', 'estado', 'pais'
];

foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        send_error("O campo '$campo' é obrigatório. Certifique-se de enviar o ID do Produtor.", 400);
    }
}

// Variáveis
$id_produtor = $dados['id_produtor'];
$descricao = $dados['descricao'] ?? '';
$visibilidade = $dados['visibilidade'] ?? 1;

// =====================================================
// 🧱 Inserção no banco: Endereço e Horta (AGORA É O PASSO 2)
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
    // INSERINDO produtor_id_produtor AQUI
    $sql_horta = "INSERT INTO hortas (endereco_hortas_id_endereco_hortas, produtor_id_produtor, nr_cnpj, nome, descricao, visibilidade, receitas_geradas)
                  VALUES (:id_endereco, :id_produtor, :cnpj, :nome, :descricao, :visibilidade, 0)";
    $stmt = $conn->prepare($sql_horta);
    $stmt->execute([
        ':id_endereco' => $id_endereco,
        ':id_produtor' => $id_produtor, // << VÍNCULO AO PRODUTOR
        ':cnpj' => htmlspecialchars($dados['cnpj']),
        ':nome' => htmlspecialchars($dados['nome_horta']),
        ':descricao' => htmlspecialchars($descricao),
        ':visibilidade' => $visibilidade
    ]);

    $id_horta = $conn->lastInsertId();

    $conn->commit();

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Horta e endereço cadastrados com sucesso e vinculados ao produtor!',
        'id_horta' => $id_horta,
        'id_endereco' => $id_endereco,
        'produtor_vinculado' => $id_produtor
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    $conn->rollBack();
    // Erro 23000 pode indicar FK inválida (id_produtor inexistente) ou duplicidade de CNPJ
    if ($e->getCode() === '23000') {
         send_error('Erro de vínculo/duplicidade: O Produtor ID pode ser inválido ou o CNPJ já está cadastrado.', 409);
    }
    send_error('Erro no banco de dados durante o cadastro da horta: ' . $e->getMessage(), 500);
}
?>
