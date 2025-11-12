<?php
class MovimentacaoController
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    private function send_response($data, $statusCode)
    {
        return [
            'statusCode' => $statusCode,
            'body' => $data
        ];
    }

    public function addMov(array $input, $id_produtor)
    {
        $nome_produto = trim($input['nome_produto'] ?? '');
        $descricao_produto = trim($input['descricao_produto'] ?? '');
        $unidade = trim($input['unidade'] ?? '');
        $quantidade = (float)($input['quantidade'] ?? 0);
        $dt_plantio = trim($input['dt_plantio'] ?? null);
        $dt_colheita = trim($input['dt_colheita'] ?? null);
        $motivo = trim($input['motivo'] ?? null);

        if (!$id_produtor || !$nome_produto || $quantidade <= 0) {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Dados inválidos'], 400);
        }

        // Horta
        $stmt = $this->conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor=:id LIMIT 1");
        $stmt->bindValue(':id', $id_produtor);
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Produtor sem horta'], 404);
        }
        $id_horta = (int)$stmt->fetchColumn();

        $transactionOwner = !$this->conn->inTransaction();
        try {
            if ($transactionOwner) {
                $this->conn->beginTransaction();
            }

            // Produto
            $p = $this->conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto)=LOWER(:n) LIMIT 1");
            $p->bindValue(':n', $nome_produto);
            $p->execute();
            if ($p->rowCount() > 0) {
                $id_produto = (int)$p->fetchColumn();
            } else {
                $ins = $this->conn->prepare("INSERT INTO produtos(nm_produto,descricao,unidade_medida_padrao) VALUES(:n,:d,:u)");
                $ins->bindValue(':n', $nome_produto);
                $ins->bindValue(':d', $descricao_produto ?: null);
                $ins->bindValue(':u', $unidade ?: null);
                $ins->execute();
                $id_produto = (int)$this->conn->lastInsertId();
            }

            // Estoque
            $s = $this->conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas=:h AND produto_id_produto=:p LIMIT 1");
            $s->bindValue(':h', $id_horta);
            $s->bindValue(':p', $id_produto);
            $s->execute();

            if ($s->rowCount() == 0) {
                $ins = $this->conn->prepare("INSERT INTO estoques(hortas_id_hortas,produto_id_produto,ds_quantidade,dt_plantio,dt_colheita) VALUES(:h,:p,:q,:pl,:co)");
                $ins->bindValue(':h', $id_horta);
                $ins->bindValue(':p', $id_produto);
                $ins->bindValue(':q', $quantidade);
                $ins->bindValue(':pl', $dt_plantio ?: null);
                $ins->bindValue(':co', $dt_colheita ?: null);
                $ins->execute();
                $id_estoque = (int)$this->conn->lastInsertId();
                $novaQtd = $quantidade;
            } else {
                $row = $s->fetch(PDO::FETCH_ASSOC);
                $id_estoque = (int)$row['id_estoques'];
                $novaQtd = (float)$row['ds_quantidade'] + $quantidade;
                $upd = $this->conn->prepare("UPDATE estoques SET ds_quantidade=:q WHERE id_estoques=:id");
                $upd->bindValue(':q', $novaQtd);
                $upd->bindValue(':id', $id_estoque);
                $upd->execute();
            }

            // Log de movimentação
            $m = $this->conn->prepare("INSERT INTO entradas_estoque(estoques_id_estoques,produtor_id_produtor,quantidade,motivo) VALUES(:e,:pr,:q,:m)");
            $m->bindValue(':e', $id_estoque);
            $m->bindValue(':pr', $id_produtor);
            $m->bindValue(':q', $quantidade);
            $m->bindValue(':m', $motivo ?: null);
            $m->execute();

            if ($transactionOwner) {
                $this->conn->commit();
            }
            return $this->send_response(['status' => 'sucesso', 'id_produto' => $id_produto, 'nova_quantidade' => $novaQtd], 200);
        } catch (Throwable $t) {
            if ($transactionOwner) {
                $this->conn->rollBack();
            }
            error_log("ERRO ADD_MOV: " . $t->getMessage());
            return $this->send_response(['status' => 'erro', 'mensagem' => $t->getMessage()], 500);
        }
    }
}
