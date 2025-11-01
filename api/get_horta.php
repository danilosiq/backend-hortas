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
// 🔧 Função de resposta padronizada (sempre 200)
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
// 📥 Valida JSON recebido
// =====================================================
$dados = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido recebido.");
}

if (empty($dados['id_produtor'])) {
    send_response("erro", "O campo 'id_produtor' é obrigatório.");
}

$id_produtor = (int)$dados['id_produtor'];

// =====================================================
// 🔍 Busca a horta do produtor (única)
// =====================================================
try {
    $sql_horta = "
        SELECT 
            h.id_hortas,
            h.nome AS nome_horta,
            h.descricao,
            h.nr_cnpj,
            h.visibilidade,
            h.receitas_geradas,
            e.id_endereco_hortas,
            e.nm_rua,
            e.nr_cep,
            e.nm_bairro,
            e.nm_estado,
            e.nm_cidade,
            e.nm_pais
        FROM hortas h
        LEFT JOIN endereco_hortas e 
            ON e.id_endereco_hortas = h.endereco_hortas_id_endereco_hortas
        WHERE h.produtor_id_produtor = :id_produtor
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql_horta);
    $stmt->bindValue(":id_produtor", $id_produtor, PDO::PARAM_INT);
    $stmt->execute();
    $horta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$horta) {
        send_response("erro", "Nenhuma horta encontrada para este produtor.");
    }

    // =====================================================
    // 🧺 Buscar produtos do estoque dessa horta
    // =====================================================
    $sql_estoque = "
        SELECT 
            es.id_estoques,
            es.ds_quantidade,
            es.dt_validade,
            es.dt_colheita,
            es.dt_plantio,
            p.id_produto,
            p.nm_produto,
            p.descricao AS descricao_produto,
            p.unidade_medida_padrao
        FROM estoques es
        LEFT JOIN produtos p 
            ON p.id_produto = es.produto_id_produto
        WHERE es.hortas_id_hortas = :id_horta
    ";

    $stmtEstoque = $conn->prepare($sql_estoque);
    $stmtEstoque->bindValue(":id_horta", $horta['id_hortas'], PDO::PARAM_INT);
    $stmtEstoque->execute();
    $estoques = $stmtEstoque->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // ✅ Retorno final
    // =====================================================
    send_response("sucesso", "Horta encontrada com sucesso.", [
        'horta' => [
            'id_hortas' => $horta['id_hortas'],
            'nome' => $horta['nome_horta'],
            'descricao' => $horta['descricao'],
            'cnpj' => $horta['nr_cnpj'],
            'visibilidade' => $horta['visibilidade'],
            'receitas_geradas' => $horta['receitas_geradas'],
            'endereco' => [
                'rua' => $horta['nm_rua'],
                'bairro' => $horta['nm_bairro'],
                'cep' => $horta['nr_cep'],
                'cidade' => $horta['nm_cidade'],
                'estado' => $horta['nm_estado'],
                'pais' => $horta['nm_pais'],
            ],
            'estoques' => $estoques
        ]
    ]);

} catch (PDOException $e) {
    send_response("erro", "Erro no banco de dados: " . $e->getMessage());
} catch (Throwable $t) {
    send_response("erro", "Erro interno no servidor: " . $t->getMessage());
}
?>