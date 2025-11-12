<?php

class CadastroHortaController
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function createHorta($dados)
    {
        try {
            if (!is_array($dados)) {
                throw new InvalidArgumentException("Dados de entrada inválidos.");
            }

            $camposObrigatorios = [
                'nome_horta',
                'rua',
                'bairro',
                'cep',
                'cidade',
                'estado',
                'pais'
            ];

            foreach ($camposObrigatorios as $campo) {
                if (empty($dados[$campo])) {
                    return $this->send_response("erro", "O campo '$campo' é obrigatório.");
                }
            }

            $id_produtor = $dados['id_produtor'] ?? null;
            $descricao = htmlspecialchars($dados['descricao'] ?? '', ENT_QUOTES, 'UTF-8');
            $descricao = substr($descricao, 0, 255);

            $cnpj = trim($dados['cnpj'] ?? '');
            if ($cnpj === '') {
                $cnpj = null;
            }

            $visibilidade = $dados['visibilidade'] ?? 1;

            $this->conn->beginTransaction();

            $sql_endereco = "INSERT INTO endereco_hortas (nm_rua, nr_cep, nm_bairro, nm_estado, nm_cidade, nm_pais)
                             VALUES (:rua, :cep, :bairro, :estado, :cidade, :pais)";
            $stmt = $this->conn->prepare($sql_endereco);
            $stmt->execute([
                ':rua' => htmlspecialchars($dados['rua'] ?? '', ENT_QUOTES, 'UTF-8'),
                ':cep' => htmlspecialchars($dados['cep'] ?? '', ENT_QUOTES, 'UTF-8'),
                ':bairro' => htmlspecialchars($dados['bairro'] ?? '', ENT_QUOTES, 'UTF-8'),
                ':estado' => htmlspecialchars($dados['estado'] ?? '', ENT_QUOTES, 'UTF-8'),
                ':cidade' => htmlspecialchars($dados['cidade'] ?? '', ENT_QUOTES, 'UTF-8'),
                ':pais' => htmlspecialchars($dados['pais'] ?? '', ENT_QUOTES, 'UTF-8')
            ]);

            $id_endereco = $this->conn->lastInsertId();

            $sql_horta = "INSERT INTO hortas (
                              endereco_hortas_id_endereco_hortas,
                              produtor_id_produtor,
                              nr_cnpj,
                              nome,
                              descricao,
                              visibilidade,
                              receitas_geradas
                          )
                          VALUES (
                              :id_endereco,
                              :id_produtor,
                              :cnpj,
                              :nome,
                              :descricao,
                              :visibilidade,
                              0
                          )";

            $stmt = $this->conn->prepare($sql_horta);
            $stmt->bindValue(':id_endereco', $id_endereco, PDO::PARAM_INT);
            $stmt->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
            $stmt->bindValue(':cnpj', $cnpj, $cnpj === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':nome', htmlspecialchars($dados['nome_horta'] ?? '', ENT_QUOTES, 'UTF-8'));
            $stmt->bindValue(':descricao', $descricao);
            $stmt->bindValue(':visibilidade', (int)$visibilidade, PDO::PARAM_INT);

            $stmt->execute();

            $id_horta = $this->conn->lastInsertId();
            $this->conn->commit();

            return $this->send_response("sucesso", "Horta cadastrada com sucesso!", [
                'id_horta' => $id_horta,
                'id_endereco' => $id_endereco,
                'id_produtor' => $id_produtor
            ]);

        } catch (PDOException $e) {
            $this->conn->rollBack();
            return $this->send_response("erro", "Erro no banco de dados: " . $e->getMessage());
        } catch (Throwable $t) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return $this->send_response("erro", "Erro interno: " . $t->getMessage());
        }
    }

    private function send_response($status, $mensagem, $extra = [])
    {
        return array_merge([
            'status' => $status,
            'mensagem' => $mensagem
        ], $extra);
    }
}
