<?php 
// --- CONEXÃO COM O BANCO DE DADOS ---

$host = 'localhost';
$dbname = 'hortas_db'; // Nome do banco de dados
$user = 'root';
$pass = '';

try {
    // Tenta estabelecer a conexão
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    
    // Configura o PDO para lançar exceções em caso de erro, o que torna o código mais limpo e fácil de depurar.
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // ALTERAÇÃO PARA DEPURAÇÃO:
    // A linha abaixo foi modificada para exibir a mensagem de erro detalhada.
    // Isso nos dirá exatamente por que a conexão está a falhar.
    die("Erro detalhado de conexão: " . $e->getMessage());
}
?>