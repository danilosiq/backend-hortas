<?php

class AuthController
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function handleAuthRequest(array $input)
    {
        $jwt = isset($input['token']) ? htmlspecialchars(strip_tags($input['token'])) : null;
        $dataAtual = isset($input['data_atual']) ? htmlspecialchars(strip_tags($input['data_atual'])) : date('Y-m-d H:i:s');

        $id_produtor = null;
        $dataExpiracao = null;

        if ($jwt) {
            try {
                $sql = "SELECT data_expiracao, produtor_id_produtor
                        FROM session
                        WHERE jwt_token = :jwt
                        LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':jwt', $jwt);
                $stmt->execute();

                $sessao = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sessao) {
                    $dataExpiracao = $sessao['data_expiracao'];
                    $id_produtor = $sessao['produtor_id_produtor'];

                    if (strtotime($dataAtual) > strtotime($dataExpiracao)) {
                        $delete = $this->conn->prepare("DELETE FROM session WHERE jwt_token = :jwt");
                        $delete->bindValue(':jwt', $jwt);
                        $delete->execute();
                        $id_produtor = null;
                    }
                }
            } catch (Throwable $t) {
                $id_produtor = null;
            }
        }

        return $this->send_response('sucesso', 'Requisição processada.', [
            'id_produtor' => $id_produtor,
            'expira_em' => $dataExpiracao
        ]);
    }

    private function send_response($status, $mensagem, $extra = [])
    {
        return array_merge([
            'status' => $status,
            'mensagem' => $mensagem
        ], $extra);
    }
}
