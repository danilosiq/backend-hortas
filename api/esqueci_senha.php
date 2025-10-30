<?php
// 💡 Inclui o boilerplate de CORS, Erro e DB
include '_common.php';

// O fluxo será controlado pelo campo 'etapa' no JSON
$etapa = $dados['etapa'] ?? null;

if (empty($etapa)) {
    send_error('O campo "etapa" é obrigatório para iniciar o processo de recuperação.', 400);
}

// =====================================================
// ETAPA 1: IDENTIFICAÇÃO (E-mail ou CPF)
// =====================================================
if ($etapa == 1) {
    $identificador = $dados['email'] ?? $dados['cpf'] ?? null;
    if (empty($identificador)) {
        send_error('Forneça o email ou CPF para iniciar a recuperação.', 400);
    }

    // 1. Busca o Produtor
    $campo = strpos($identificador, '@') !== false ? 'email_produtor' : 'nr_cpf';
    $sql_produtor = "SELECT id_produtor FROM produtor WHERE {$campo} = :identificador";
    $stmt_produtor = $conn->prepare($sql_produtor);
    $stmt_produtor->execute([':identificador' => $identificador]);
    $produtor = $stmt_produtor->fetch(PDO::FETCH_ASSOC);

    if (!$produtor) {
        send_error('Usuário não encontrado.', 404);
    }

    $id_produtor = $produtor['id_produtor'];

    // 2. Busca as Perguntas de Segurança
    $sql_seguranca = "SELECT pergunta_1, pergunta_2 FROM seguranca_produtor WHERE produtor_id_produtor = :id";
    $stmt_seguranca = $conn->prepare($sql_seguranca);
    $stmt_seguranca->execute([':id' => $id_produtor]);
    $seguranca = $stmt_seguranca->fetch(PDO::FETCH_ASSOC);

    if (!$seguranca) {
        send_error('Perguntas de segurança não configuradas para este usuário. Contate o suporte.', 500);
    }

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Identificação concluída. Prossiga para a Etapa 2.',
        'etapa_proxima' => 2,
        'id_produtor' => $id_produtor,
        'pergunta_1' => $seguranca['pergunta_1'],
        'pergunta_2' => $seguranca['pergunta_2']
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// ETAPA 2: VERIFICAÇÃO (Respostas de Segurança)
// =====================================================
if ($etapa == 2) {
    $id_produtor = $dados['id_produtor'] ?? null;
    $resposta_1 = $dados['resposta_1'] ?? null;
    $resposta_2 = $dados['resposta_2'] ?? null;

    if (empty($id_produtor) || empty($resposta_1) || empty($resposta_2)) {
        send_error('ID do produtor e as duas respostas são obrigatórias para a Etapa 2.', 400);
    }
    
    // 1. Busca as Respostas HASH
    $sql_seguranca = "SELECT resposta_1_hash, resposta_2_hash FROM seguranca_produtor WHERE produtor_id_produtor = :id";
    $stmt_seguranca = $conn->prepare($sql_seguranca);
    $stmt_seguranca->execute([':id' => $id_produtor]);
    $seguranca = $stmt_seguranca->fetch(PDO::FETCH_ASSOC);

    if (!$seguranca) {
        send_error('Dados de segurança não encontrados.', 404);
    }
    
    // As respostas devem ser comparadas em minúsculas (seguindo a lógica de hash no cadastro)
    $r1_verificada = password_verify(strtolower($resposta_1), $seguranca['resposta_1_hash']);
    $r2_verificada = password_verify(strtolower($resposta_2), $seguranca['resposta_2_hash']);

    if ($r1_verificada && $r2_verificada) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Verificação de segurança bem-sucedida! Prossiga para a Etapa 3.',
            'etapa_proxima' => 3,
            'id_produtor' => $id_produtor
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Mensagem genérica para não dar dicas sobre qual resposta está errada
        send_error('Resposta(s) de segurança incorreta(s).', 401);
    }
    exit();
}

// =====================================================
// ETAPA 3: DEFINIR NOVA SENHA
// =====================================================
if ($etapa == 3) {
    $id_produtor = $dados['id_produtor'] ?? null;
    $nova_senha = $dados['nova_senha'] ?? null;

    if (empty($id_produtor) || empty($nova_senha)) {
        send_error('ID do produtor e a nova senha são obrigatórios para a Etapa 3.', 400);
    }
    
    // 1. Hash da nova senha
    $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
    
    // 2. Atualiza a senha no banco
    $sql_update = "UPDATE produtor SET hash_senha = :hash_senha WHERE id_produtor = :id";
    $stmt_update = $conn->prepare($sql_update);
    $success = $stmt_update->execute([':hash_senha' => $hash_nova_senha, ':id' => $id_produtor]);

    if ($success && $stmt_update->rowCount() > 0) {
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => 'Senha atualizada com sucesso! Você já pode fazer login.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        send_error('Falha ao atualizar a senha ou ID do produtor inválido.', 500);
    }
    exit();
}

// Se a etapa for inválida
send_error('Etapa de recuperação de senha inválida.', 400);

?>
