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
// 🔧 Função de resposta padronizada (sempre HTTP 200)
// =====================================================
function send_response($success, $message, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 📦 Conexão com o banco (arquivo deve definir $conn como PDO)
// =====================================================
include 'banco_mysql.php'; // espera-se que crie $conn (PDO)
if (!isset($conn) || !$conn) {
    send_response(false, "Erro de conexão com o banco de dados.");
}

// =====================================================
// 📩 Verifica método HTTP
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, "Método inválido. Use POST.");
}

// =====================================================
// 🧠 Lê corpo da requisição
// =====================================================
$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    send_response(false, "Corpo JSON inválido ou vazio.");
}

// =====================================================
// 📋 Validação de campos obrigatórios
// =====================================================
// Agora exigimos email para localizar o usuário
$campos_obrigatorios = ['email', 'novaSenha', 'confirmarSenha', 'pergunta1', 'pergunta2', 'resposta1', 'resposta2'];
foreach ($campos_obrigatorios as $campo) {
    if (empty($input[$campo]) && $input[$campo] !== '0') {
        send_response(false, "O campo '$campo' é obrigatório.");
    }
}

// =====================================================
// 🔑 Valida senha
// =====================================================
if ($input['novaSenha'] !== $input['confirmarSenha']) {
    send_response(false, "As senhas não coincidem.");
}

// sanitize / extract
$email = trim($input['email']);
$novaSenhaRaw = $input['novaSenha'];
$pergunta1_in = trim($input['pergunta1']);
$pergunta2_in = trim($input['pergunta2']);
$resposta1_in = $input['resposta1'];
$resposta2_in = $input['resposta2'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_response(false, "Email inválido.");
}

try {
    // =====================================================
    // 🔍 Localiza produtor por email
    // =====================================================
    $sqlProdutor = "SELECT id_produtor, nome_produtor, hash_senha FROM produtor WHERE email_produtor = :email LIMIT 1";
    $stmtProd = $conn->prepare($sqlProdutor);
    $stmtProd->bindValue(':email', $email, PDO::PARAM_STR);
    $stmtProd->execute();
    $produtor = $stmtProd->fetch(PDO::FETCH_ASSOC);

    if (!$produtor) {
        send_response(false, "Produtor com esse e-mail não encontrado.");
    }

    $id_produtor = (int)$produtor['id_produtor'];

    // =====================================================
    // 🔍 Busca registro de segurança para esse produtor
    // =====================================================
    $sqlSeg = "SELECT id_seguranca, pergunta_1, resposta_1_hash, pergunta_2, resposta_2_hash 
               FROM seguranca_produtor 
               WHERE produtor_id_produtor = :id_produtor
               LIMIT 1";
    $stmtSeg = $conn->prepare($sqlSeg);
    $stmtSeg->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmtSeg->execute();
    $seg = $stmtSeg->fetch(PDO::FETCH_ASSOC);

    if (!$seg) {
        send_response(false, "Não foi encontrada configuração de perguntas de segurança para este usuário.");
    }

    // =====================================================
    // ✅ Verifica se as perguntas fornecidas coincidem com as cadastradas
    // =====================================================
    // Normalizamos comparando sem diferenças de espaçamento e caixa (case-insensitive)
    $normalize = function($s) {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)), 'UTF-8');
    };

    if ($normalize($pergunta1_in) !== $normalize($seg['pergunta_1']) ||
        $normalize($pergunta2_in) !== $normalize($seg['pergunta_2'])) {
        send_response(false, "As perguntas não correspondem às cadastradas para este usuário.");
    }

    // =====================================================
    // ✅ Verifica respostas (hash)
    // =====================================================
    $hash1 = $seg['resposta_1_hash'];
    $hash2 = $seg['resposta_2_hash'];

    if (!password_verify($resposta1_in, $hash1) || !password_verify($resposta2_in, $hash2)) {
        send_response(false, "Respostas incorretas para as perguntas de segurança.");
    }

    // =====================================================
    // 💾 Atualiza a senha do produtor
    // =====================================================
    $novaSenhaHash = password_hash($novaSenhaRaw, PASSWORD_DEFAULT);

    $sqlUpdate = "UPDATE produtor SET hash_senha = :hash WHERE id_produtor = :id_produtor";
    $stmtUpd = $conn->prepare($sqlUpdate);
    $stmtUpd->bindValue(':hash', $novaSenhaHash, PDO::PARAM_STR);
    $stmtUpd->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmtUpd->execute();

    if ($stmtUpd->rowCount() > 0) {
        send_response(true, "Senha alterada com sucesso!");
    } else {
        // caso a senha já seja igual ao hash atual (improvável), ou update não afetou linhas
        send_response(false, "Nenhuma alteração realizada. Verifique os dados.");
    }

} catch (Throwable $e) {
    // Log no servidor, mas retornar mensagem genérica ao cliente
    error_log("ERRO forgot_pass.php: " . $e->getMessage());
    send_response(false, "Erro interno: " . $e->getMessage());
}