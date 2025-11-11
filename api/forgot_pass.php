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
    ], $extra));
    exit();
}

// =====================================================
// 📦 Conexão com o banco
// =====================================================
include 'banco_mysql.php'; // este arquivo deve criar o objeto $conn (PDO)

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
if (!$input) {
    send_response(false, "Corpo JSON inválido ou vazio.");
}

// =====================================================
// 📋 Validação de campos obrigatórios
// =====================================================
$campos_obrigatorios = ['novaSenha', 'confirmarSenha', 'pergunta1', 'pergunta2', 'resposta1', 'resposta2'];
foreach ($campos_obrigatorios as $campo) {
    if (empty($input[$campo])) {
        send_response(false, "O campo '$campo' é obrigatório.");
    }
}

// =====================================================
// 🔑 Valida senha
// =====================================================
if ($input['novaSenha'] !== $input['confirmarSenha']) {
    send_response(false, "As senhas não coincidem.");
}

$novaSenha = password_hash($input['novaSenha'], PASSWORD_DEFAULT);
$pergunta1 = $input['pergunta1'];
$pergunta2 = $input['pergunta2'];
$resposta1 = $input['resposta1'];
$resposta2 = $input['resposta2'];

// =====================================================
// 🧩 Verifica se o produtor existe pelas perguntas
// =====================================================
try {
    $sql = "SELECT sp.produtor_id_produtor, sp.resposta_1_hash, sp.resposta_2_hash 
            FROM seguranca_produtor sp
            WHERE sp.pergunta_1 = ? AND sp.pergunta_2 = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$pergunta1, $pergunta2]);
    $seguranca = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seguranca) {
        send_response(false, "Perguntas de segurança não encontradas.");
    }

    // Valida respostas
    if (
        !password_verify($resposta1, $seguranca['resposta_1_hash']) ||
        !password_verify($resposta2, $seguranca['resposta_2_hash'])
    ) {
        send_response(false, "Respostas incorretas para as perguntas de segurança.");
    }

    $id_produtor = $seguranca['produtor_id_produtor'];

    // =====================================================
    // 💾 Atualiza a senha do produtor
    // =====================================================
    $update = $conn->prepare("UPDATE produtor SET hash_senha = ? WHERE id_produtor = ?");
    $update->execute([$novaSenha, $id_produtor]);

    if ($update->rowCount() > 0) {
        send_response(true, "Senha alterada com sucesso!");
    } else {
        send_response(false, "Nenhuma alteração realizada. Verifique os dados.");
    }

} catch (Throwable $e) {
    send_response(false, "Erro interno: " . $e->getMessage());
}
?>