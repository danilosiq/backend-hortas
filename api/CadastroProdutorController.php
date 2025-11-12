<?php

class CadastroProdutorController
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function createProdutor($dados)
    {
        try {
            $camposObrigatorios = [
                'nome_produtor', 'nr_cpf', 'email_produtor', 'senha',
                'pergunta_1', 'resposta_1', 'pergunta_2', 'resposta_2'
            ];

            foreach ($camposObrigatorios as $campo) {
                if (empty($dados[$campo])) {
                    return $this->send_response('erro', "O campo '$campo' é obrigatório.", 400);
                }
            }

            $telefone = $dados['telefone_produtor'] ?? '';

            $this->conn->beginTransaction();

            // 1. Produtor
            $sql_produtor = "INSERT INTO produtor (nome_produtor, nr_cpf, email_produtor, hash_senha, telefone_produtor)
                             VALUES (:nome_produtor, :nr_cpf, :email_produtor, :hash_senha, :telefone)";
            $stmt = $this->conn->prepare($sql_produtor);
            $stmt->execute([
                ':nome_produtor' => htmlspecialchars($dados['nome_produtor']),
                ':nr_cpf' => htmlspecialchars($dados['nr_cpf']),
                ':email_produtor' => htmlspecialchars($dados['email_produtor']),
                ':hash_senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
                ':telefone' => htmlspecialchars($telefone)
            ]);

            $id_produtor = $this->conn->lastInsertId();

            // 2. Segurança
            $sql_seguranca = "INSERT INTO seguranca_produtor (produtor_id_produtor, pergunta_1, resposta_1_hash, pergunta_2, resposta_2_hash)
                              VALUES (:id_produtor, :p1, :r1_hash, :p2, :r2_hash)";
            $stmt_seg = $this->conn->prepare($sql_seguranca);
            $stmt_seg->execute([
                ':id_produtor' => $id_produtor,
                ':p1' => htmlspecialchars($dados['pergunta_1']),
                ':r1_hash' => password_hash(strtolower($dados['resposta_1']), PASSWORD_DEFAULT),
                ':p2' => htmlspecialchars($dados['pergunta_2']),
                ':r2_hash' => password_hash(strtolower($dados['resposta_2']), PASSWORD_DEFAULT)
            ]);

            $this->conn->commit();

            return $this->send_response('sucesso', 'Produtor cadastrado com sucesso!', 201, ['id_produtor' => $id_produtor]);

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if ($e->getCode() === '23000') {
                return $this->send_response('erro', 'Erro: E-mail ou CPF já cadastrado.', 409);
            }
            return $this->send_response('erro', 'Erro no banco de dados: ' . $e->getMessage(), 500);
        } catch (Throwable $t) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return $this->send_response('erro', 'Erro interno: ' . $t->getMessage(), 500);
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
