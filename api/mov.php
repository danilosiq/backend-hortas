<?php
// api/movimentacao/post_mov.php

// =====================================================
// ✅ CORS e headers - faça isto ser o primeiro bloco
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// =====================================================
// Função para responder sempre com HTTP 200 e JSON
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// Conectar ao banco (ajuste include conforme seu projeto)
// =====================================================
try {
    include __DIR__ . "/../banco_mysql.php"; // caminho relativo exemplo
    // espera-se que $conn seja PDO instanciado no arquivo incluido
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new Exception("Conexão PDO não encontrada ( \$conn )");
    }
} catch (Throwable $e) {
    // Nunca retorne 500 — sempre 200 com status erro
    send_response("erro", "Erro ao conectar ao banco de dados: " . $e->getMessage());
}

// =====================================================
// Função minimalista para validar token via tabela session
// retorna array com id_produtor ou null
// =====================================================
function validar_token_por_session(PDO $conn, ?string $token) {
    if (!$token) return null;
    try {
        $sql = "SELECT produtor_id_produtor, data_expiracao FROM session WHERE jwt_token = :jwt LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':jwt', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) return null;
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // opcional: checar expiração aqui (mas front manda data_atual, podemos pular)
        return [
            'id_produtor' => (int)$row['produtor_id_produtor'],
            'data_expiracao' => $row['data_expiracao']
        ];
    } catch (Throwable $t) {
        return null;
    }
}

// =====================================================
// Recebe JSON do body
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido recebido.");
}

// =====================================================
// Campos esperados (flexível):
// - token (obrigatório para autenticação via session table)
// - tipo: "entrada" | "saida" (obrigatório)
// - quantidade (number, obrigatório >0)
// - motivo (opcional string)
// - id_produto (opcional) OU nome_produto (+ descricao_produto + unidade) para criar/identificar
// - data_atual (opcional; não obrigamos para validação da sessão aqui)
// =====================================================

$token = isset($input['token']) ? trim($input['token']) : null;
$tipo = isset($input['tipo']) ? strtolower(trim($input['tipo'])) : null;
$quantidade = isset($input['quantidade']) ? (float)$input['quantidade'] : null;
$motivo = isset($input['motivo']) ? trim($input['motivo']) : null;
$id_produto = isset($input['id_produto']) && $input['id_produto'] !== '' ? (int)$input['id_produto'] : null;

$nome_produto = isset($input['nome_produto']) ? trim($input['nome_produto']) : null;
$descricao_produto = isset($input['descricao_produto']) ? trim($input['descricao_produto']) : null;
$unidade = isset($input['unidade']) ? trim($input['unidade']) : null;

// validações básicas
if (!$token) send_response("erro", "Token obrigatório.");
if (!$tipo || !in_array($tipo, ['entrada','saida'])) send_response("erro", "Tipo inválido. Use 'entrada' ou 'saida'.");
if (!is_numeric($quantidade) || $quantidade <= 0) send_response("erro", "Quantidade deve ser um número maior que zero.");

// autentica token
$usuario = validar_token_por_session($conn, $token);
if (!$usuario || empty($usuario['id_produtor'])) {
    send_response("erro", "Sessão inválida ou token não encontrado.");
}
$id_produtor = (int)$usuario['id_produtor'];

// =====================================================
// Obtém ID da horta do produtor (um-por-um conforme dito)
// =====================================================
try {
    $stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id_produtor LIMIT 1");
    $stmt->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        send_response("erro", "Produtor não possui horta vinculada.");
    }
    $hortaRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_horta = (int)$hortaRow['id_hortas'];
} catch (Throwable $t) {
    send_response("erro", "Erro ao buscar horta: " . $t->getMessage());
}

// =====================================================
// Lógica produto -> estoque -> movimentação
// - se id_produto fornecido: tenta usar
// - se id_produto não fornecido: busca por nome_produto; se não existir, cria
// =====================================================
try {
    $conn->beginTransaction();

    // 1) resolve id_produto (cria se necessário)
    if ($id_produto && $id_produto > 0) {
        // certificar que produto existe
        $p = $conn->prepare("SELECT id_produto, nm_produto FROM produtos WHERE id_produto = :id_produto LIMIT 1");
        $p->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
        $p->execute();
        if ($p->rowCount() === 0) {
            // produto não existe — vamos falhar graciosamente pedindo nome (mas não jogamos HTTP error)
            $id_produto = null;
        } else {
            $prodRow = $p->fetch(PDO::FETCH_ASSOC);
            $nome_produto = $nome_produto ?? $prodRow['nm_produto'];
        }
    }

    if (!$id_produto) {
        // precisa de nome_produto mínimo para criar/identificar
        if (!$nome_produto || mb_strlen($nome_produto) < 2) {
            $conn->rollBack();
            send_response("erro", "Produto não informado corretamente. Forneça id_produto ou nome_produto.");
        }

        // procura produto por nome (case-insensitive)
        $pFind = $conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto) = LOWER(:nome) LIMIT 1");
        $pFind->bindValue(':nome', $nome_produto);
        $pFind->execute();

        if ($pFind->rowCount() > 0) {
            $row = $pFind->fetch(PDO::FETCH_ASSOC);
            $id_produto = (int)$row['id_produto'];
        } else {
            // cria produto
            $pIns = $conn->prepare("INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES (:nome, :descricao, :unidade)");
            $pIns->bindValue(':nome', $nome_produto);
            $pIns->bindValue(':descricao', $descricao_produto ?? null);
            // valida unidade - aceita 'g','kg','ton','unidade' ou nulo
            $allowed = ['g','kg','ton','unidade'];
            $unitToSave = in_array($unidade, $allowed) ? $unidade : null;
            $pIns->bindValue(':unidade', $unitToSave);
            $pIns->execute();
            $id_produto = (int)$conn->lastInsertId();
        }
    }

    // 2) verifica se já existe um registro em estoques para esta (horta, produto)
    $sFind = $conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas = :id_horta AND produto_id_produto = :id_produto LIMIT 1");
    $sFind->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
    $sFind->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
    $sFind->execute();

    if ($sFind->rowCount() === 0) {
        // cria novo estoque com quantidade inicial dependendo do tipo
        $initialQty = ($tipo === 'entrada') ? $quantidade : 0.0;
        $sIns = $conn->prepare("INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantidade, dt_plantio, dt_colheita, dt_validade) VALUES (:id_horta, :id_produto, :quantidade, NULL, NULL, NULL)");
        $sIns->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
        $sIns->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
        $sIns->bindValue(':quantidade', $initialQty);
        $sIns->execute();
        $id_estoque = (int)$conn->lastInsertId();
        $novaQuantidade = $initialQty;
    } else {
        // atualiza estoque existente
        $estoqueRow = $sFind->fetch(PDO::FETCH_ASSOC);
        $id_estoque = (int)$estoqueRow['id_estoques'];
        $currentQty = (float)$estoqueRow['ds_quantidade'];
        if ($tipo === 'entrada') {
            $novaQuantidade = $currentQty + $quantidade;
        } else {
            $novaQuantidade = $currentQty - $quantidade;
            if ($novaQuantidade < 0) $novaQuantidade = 0;
        }
        $sUpd = $conn->prepare("UPDATE estoques SET ds_quantidade = :quantidade WHERE id_estoques = :id_estoque");
        $sUpd->bindValue(':quantidade', $novaQuantidade);
        $sUpd->bindValue(':id_estoque', $id_estoque, PDO::PARAM_INT);
        $sUpd->execute();
    }

    // 3) registra movimentação (entrada ou saida)
    if ($tipo === 'entrada') {
        $m = $conn->prepare("INSERT INTO entradas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    } else {
        $m = $conn->prepare("INSERT INTO saidas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    }
    $m->bindValue(':id_estoque', $id_estoque, PDO::PARAM_INT);
    $m->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $m->bindValue(':quantidade', $quantidade);
    $m->bindValue(':motivo', $motivo ?? null);
    $m->execute();

    $conn->commit();

    // resposta de sucesso
    send_response("sucesso", "Movimentação registrada com sucesso.", [
        'id_produto' => $id_produto,
        'nome_produto' => $nome_produto,
        'id_estoque' => $id_estoque,
        'id_horta' => $id_horta,
        'nova_quantidade' => $novaQuantidade,
        'tipo' => $tipo
    ]);

} catch (Throwable $t) {
    // garante rollback e retorna erro dentro de JSON 200
    try { $conn->rollBack(); } catch(Throwable $_){}
    send_response("erro", "Erro ao registrar movimentação: " . $t->getMessage());
}