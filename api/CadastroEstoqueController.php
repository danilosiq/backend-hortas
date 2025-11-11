<?php

class CadastroEstoqueController
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function createEstoque($dados, $id_produtor)
    {
        try {
            if (
                !$dados ||
                empty($dados->hortas_id_hortas) ||
                empty($dados->produto_id_produto) ||
                !isset($dados->ds_quantiade)
            ) {
                return $this->send_response('erro', 'Campos obrigatórios não preenchidos.', 400);
            }

            if (!$id_produtor) {
                return $this->send_response('erro', 'ID do produtor não fornecido.', 401);
            }

            // A query não utiliza mais o id_produtor diretamente, mas a validação acima é mantida
            // por razões de segurança, garantindo que o endpoint que chama este método
            // está a autenticar corretamente o utilizador.

            $sql = "INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantiade, dt_validade, dt_colheita, dt_plantio)
                    VALUES (:id_horta, :id_produto, :quantidade, :dt_validade, :dt_colheita, :dt_plantio)";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindValue(':id_horta', (int)$dados->hortas_id_hortas);
            $stmt->bindValue(':id_produto', (int)$dados->produto_id_produto);
            $stmt->bindValue(':quantidade', $dados->ds_quantiade);
            $stmt->bindValue(':dt_validade', !empty($dados->dt_validade) ? $dados->dt_validade : null);
            $stmt->bindValue(':dt_colheita', !empty($dados->dt_colheita) ? $dados->dt_colheita : null);
            $stmt->bindValue(':dt_plantio', !empty($dados->dt_plantio) ? $dados->dt_plantio : null);

            if ($stmt->execute()) {
                return $this->send_response('sucesso', 'Lote de produto cadastrado no estoque com sucesso!', 201);
            } else {
                return $this->send_response('erro', 'Não foi possível cadastrar o lote no estoque.', 503);
            }

        } catch (PDOException $e) {
            return $this->send_response('erro', 'Erro no banco de dados: ' . $e->getMessage(), 500);
        }
    }

    private function send_response($status, $mensagem, $statusCode, $extra = [])
    {
        http_response_code($statusCode);
        return array_merge([
            'status' => $status,
            'mensagem' => $mensagem
        ], $extra);
    }
}
