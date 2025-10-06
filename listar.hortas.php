<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include "banco_mysql.php";

$resposta = array();

try {
    /*
     * MELHORIA:
     * 1. Usamos LEFT JOIN para garantir que uma horta apareça mesmo se (por algum erro) não tiver um produtor.
     * 2. Selecionamos colunas específicas para não expor dados sensíveis (como o hash_senha do produtor).
     * 3. Usamos aliases (AS) para renomear colunas e evitar conflitos de nomes, tornando o código mais claro.
    */
    $sql = "
        SELECT 
            h.id_hortas,
            h.nome AS nome_horta,
            h.descricao,
            h.nr_cnpj,
            h.receitas_geradas,
            e.nm_rua,
            e.nr_cep,
            e.nm_bairro,
            e.nm_cidade,
            e.nm_estado,
            e.nm_pais,
            p.nome_produtor
        FROM 
            hortas AS h
        JOIN 
            endereco_hortas AS e ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas
        LEFT JOIN 
            produtor AS p ON h.id_hortas = p.hortas_id_hortas
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $hortas_array = [];

    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
        /*
         * MELHORIA:
         * Em vez de retornar um resultado "plano", criamos uma estrutura aninhada.
         * Isso torna o JSON muito mais limpo e fácil de consumir no frontend.
        */
        $horta_item = array(
            "id_horta" => $linha['id_hortas'],
            "nome_horta" => $linha['nome_horta'],
            "descricao" => $linha['descricao'],
            "cnpj" => $linha['nr_cnpj'],
            "receitas_geradas" => $linha['receitas_geradas'],
            "nome_produtor" => $linha['nome_produtor'] ?? 'Não informado', // Caso o produtor seja nulo
            "endereco" => array(
                "rua" => $linha['nm_rua'],
                "cep" => $linha['nr_cep'],
                "bairro" => $linha['nm_bairro'],
                "cidade" => $linha['nm_cidade'],
                "estado" => $linha['nm_estado'],
                "pais" => $linha['nm_pais']
            )
        );
        array_push($hortas_array, $horta_item);
    }

    http_response_code(200);
    echo json_encode($hortas_array);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    // MELHORIA DE SEGURANÇA: Não exponha a mensagem de erro real em produção.
    $resposta = array("status" => "erro", "mensagem" => "Ocorreu um erro ao buscar os dados.");
    // error_log($e->getMessage()); // Opcional: Logar o erro para o desenvolvedor ver.
    echo json_encode($resposta);
}
?>
