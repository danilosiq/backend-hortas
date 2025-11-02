<?php
// api/mov.php
// =====================================================
// ✅ CORS e headers — primeiro bloco sempre
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
// Função padrão de resposta (sempre HTTP 200 e JSON)
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    // garante que nada foi enviado antes (por segurança)
    if (!headers_sent()) {
        http_response_code(200);
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// =====================================================
// Tenta localizar e incluir banco_mysql.php sem gerar warnings
// =====================================================
function try_include_banco(): ?PDO {
    $candidates = [
        __DIR__ . '/banco_mysql.php',
        __DIR__ . '/../banco_mysql.php',
        __DIR__ . '/../../banco_mysql.php',
        __DIR__ . '/../api/banco_mysql.php',
        __DIR__ . '/../../api/banco_mysql.php',
        // caminhos absolutos comuns (ajuste se necessário)
        '/var/task/user/api/banco_mysql.php',
        '/var/task/user/banco_mysql.php',
    ];

    foreach ($candidates as $path) {
        if (file_exists($path) && is_readable($path)) {
            // incluir em scope isolado para evitar sobrescrever variáveis inesperadas
            /** @noinspection PhpIncludeInspection */
            include_once $path;
            // espera-se que o arquivo defina $conn (PDO)
            if (isset($conn) && $conn instanceof PDO) {
                return $conn;
            }
            // se o arquivo usa outro nome ($pdo, $db), você pode adaptá-lo aqui
        }
    }

    return null;
}

// =====================================================
// Carrega conexão PDO
// =====================================================
$conn = try_include_banco();
if (!$conn) {
    send_response("erro", "Arquivo banco_mysql.php não encontrado ou \$conn não inicializado. Verifique caminho e conteúdo do arquivo.");
}

// =====================================================
// Função para validar token na tabela `session` (minimal)
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
        return [
            'id_produtor' => (int)$row['produtor_id_produtor'],
            'data_expiracao' => $row['data_expiracao']
        ];
    } catch (Throwable $t) {
        return null;
    }
}

// =====================================================
// Lê JSON do body
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido recebido.");
}

// Campos aceitos:
// token (obrigatório), tipo (entrada|saida), quantidade (>0), motivo (opcional),
// id_produto (opcional) ou nome_produto (+ descricao_produto + unidade),
// dt_plantio (opcional YYYY-MM-DD), dt_colheita (opcional YYYY-MM-DD)

$token = isset($input['token']) ? trim($input['token']) : null;
$tipo = isset($input['tipo']) ? strtolower(trim($input['tipo'])) : null;
$quantidade = isset($input['quantidade']) ? (float)$input['quantidade'] : null;
$motivo = isset($input['motivo']) ? trim($input['motivo']) : null;
$id_produto = isset($input['id_produto']) && $input['id_produto'] !== '' ? (int)$input['id_produto'] : null;

$nome_produto = isset($input['nome_produto']) ? trim($input['nome_produto']) : null;
$descricao_produto = isset($input['descricao_produto']) ? trim($input['descricao_produto']) : null;
$unidade = isset($input['unidade']) ? trim($input['unidade']) : null;
$dt_plantio = isset($input['dt_plantio']) ? trim($input['dt_plantio']) : null;
$dt_colheita = isset($input['dt_colheita']) ? trim($input['dt_colheita']) : null;

// validações básicas
if (!$token) send_response("erro", "Token obrigatório.");
if (!$tipo || !in_array($tipo, ['entrada','saida'])) send_response("erro", "Tipo inválido. Use 'entrada' ou 'saida'.");
if (!is_numeric($quantidade) || $quantidade <= 0) send_response("erro", "Quantidade deve ser um número maior que zero.");

// valida formato de data se fornecido (YYYY-MM-DD)
$regexData = '/^\d{4}-\d{2}-\d{2}$/';
if ($dt_plantio && !preg_match($regexData, $dt_plantio)) {
    send_response("erro", "Formato inválido de dt_plantio. Use YYYY-MM-DD.");
}
if ($dt_colheita && !preg_match($regexData, $dt_colheita)) {
    send_response("erro", "Formato inválido de dt_colheita. Use YYYY-MM-DD.");
}

// autentica token
$usuario = validar_token_por_session($conn, $token);
if (!$usuario || empty($usuario['id_produtor'])) {
    send_response("erro", "Sessão inválida ou token não encontrado.");
}
$id_produtor = (int)$usuario['id_produtor'];

// obtém horta do produtor (1-por-1)
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

// operação principal: criar produto se necessário, atualizar/insert estoque, registrar movimentação
try {
    $conn->beginTransaction();

    // Resolver id_produto (criar se não existir)
    if ($id_produto && $id_produto > 0) {
        $p = $conn->prepare("SELECT id_produto, nm_produto FROM produtos WHERE id_produto = :id_produto LIMIT 1");
        $p->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
        $p->execute();
        if ($p->rowCount() === 0) {
            // produto informado não existe -> vamos ignorar id e seguir para criação via nome
            $id_produto = null;
        } else {
            $prodRow = $p->fetch(PDO::FETCH_ASSOC);
            $nome_produto = $nome_produto ?? $prodRow['nm_produto'];
        }
    }

    if (!$id_produto) {
        if (!$nome_produto || mb_strlen($nome_produto) < 2) {
            $conn->rollBack();
            send_response("erro", "Produto não informado corretamente. Forneça id_produto válido ou nome_produto.");
        }

        $pFind = $conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto) = LOWER(:nome) LIMIT 1");
        $pFind->bindValue(':nome', $nome_produto);
        $pFind->execute();

        if ($pFind->rowCount() > 0) {
            $row = $pFind->fetch(PDO::FETCH_ASSOC);
            $id_produto = (int)$row['id_produto'];
        } else {
            $pIns = $conn->prepare("INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES (:nome, :descricao, :unidade)");
            $allowed = ['g','kg','ton','unidade'];
            $unitToSave = in_array($unidade, $allowed) ? $unidade : null;
            $pIns->bindValue(':nome', $nome_produto);
            $pIns->bindValue(':descricao', $descricao_produto ?: null);
            $pIns->bindValue(':unidade', $unitToSave);
            $pIns->execute();
            $id_produto = (int)$conn->lastInsertId();
        }
    }

    // Procura estoque para (horta, produto)
    $sFind = $conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas = :id_horta AND produto_id_produto = :id_produto LIMIT 1");
    $sFind->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
    $sFind->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
    $sFind->execute();

    if ($sFind->rowCount() === 0) {
        // sem estoque anterior
        if ($tipo === 'saida') {
            $conn->rollBack();
            send_response("erro", "Não é possível registrar saída de produto sem entrada prévia.");
        }

        $initialQty = $quantidade;
        $sIns = $conn->prepare("INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantidade, dt_plantio, dt_colheita, dt_validade) VALUES (:id_horta, :id_produto, :quantidade, :dt_plantio, :dt_colheita, NULL)");
        $sIns->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
        $sIns->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
        $sIns->bindValue(':quantidade', $initialQty);
        $sIns->bindValue(':dt_plantio', $dt_plantio ?: null);
        $sIns->bindValue(':dt_colheita', $dt_colheita ?: null);
        $sIns->execute();
        $id_estoque = (int)$conn->lastInsertId();
        $novaQuantidade = $initialQty;
    } else {
        // já existe estoque
        $estoqueRow = $sFind->fetch(PDO::FETCH_ASSOC);
        $id_estoque = (int)$estoqueRow['id_estoques'];
        $currentQty = (float)$estoqueRow['ds_quantidade'];

        if ($tipo === 'entrada') {
            $novaQuantidade = $currentQty + $quantidade;
        } else {
            if ($quantidade > $currentQty) {
                $conn->rollBack();
                send_response("erro", "Quantidade de saída ({$quantidade}) maior que estoque atual ({$currentQty}).");
            }
            $novaQuantidade = $currentQty - $quantidade;
        }

        $sUpd = $conn->prepare("UPDATE estoques SET ds_quantidade = :qtd, dt_plantio = COALESCE(:dt_plantio, dt_plantio), dt_colheita = COALESCE(:dt_colheita, dt_colheita) WHERE id_estoques = :id");
        $sUpd->bindValue(':qtd', $novaQuantidade);
        $sUpd->bindValue(':dt_plantio', $dt_plantio ?: null);
        $sUpd->bindValue(':dt_colheita', $dt_colheita ?: null);
        $sUpd->bindValue(':id', $id_estoque, PDO::PARAM_INT);
        $sUpd->execute();
    }

    // registra movimentação
    if ($tipo === 'entrada') {
        $m = $conn->prepare("INSERT INTO entradas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    } else {
        $m = $conn->prepare("INSERT INTO saidas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    }
    $m->bindValue(':id_estoque', $id_estoque, PDO::PARAM_INT);
    $m->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $m->bindValue(':quantidade', $quantidade);
    $m->bindValue(':motivo', $motivo ?: null);
    $m->execute();

    $conn->commit();

    send_response("sucesso", "Movimentação registrada com sucesso.", [
        'id_produto' => $id_produto,
        'nome_produto' => $nome_produto,
        'id_estoque' => $id_estoque,
        'id_horta' => $id_horta,
        'nova_quantidade' => $novaQuantidade,
        'tipo' => $tipo
    ]);

} catch (Throwable $t) {
    try { $conn->rollBack(); } catch (Throwable $_) {}
    send_response("erro", "Erro ao registrar movimentação: " . $t->getMessage());
}