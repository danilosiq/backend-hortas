<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// Verifique se o token foi enviado
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(['error' => 'Token não fornecido']);
    exit;
}

list($jwt) = sscanf($authHeader, 'Bearer %s');

if (!$jwt) {
    http_response_code(401);
    echo json_encode(['error' => 'Formato de token inválido']);
    exit;
}

// Conexão com o banco
foreach ([__DIR__.'/db_connection.php',__DIR__.'/../db_connection.php'] as $f){
    if (file_exists($f)){
        include $f;
    }
}


// Valide o token
$stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :t AND data_expiracao > NOW()");
$stmt->bindValue(':t', $jwt);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido ou expirado']);
    exit;
}

$id_produtor = (int)$stmt->fetchColumn();

// Lógica de movimentação
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $horta_id = isset($_GET['horta_id']) ? (int)$_GET['horta_id'] : null;

    if ($horta_id) {
        $stmt = $conn->prepare("SELECT e.*, p.nm_produto FROM estoques e JOIN produtos p ON e.produto_id_produto = p.id_produto WHERE e.hortas_id_hortas = :horta_id");
        $stmt->bindValue(':horta_id', $horta_id);
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
    } else {
        echo json_encode([]);
    }
}
