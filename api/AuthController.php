<?php
require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController
{
    private $conn;
    private $secret_key;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->secret_key = getenv('JWT_SECRET_KEY');
    }

    public function login(array $data)
    {
        $email = $data['email'] ?? null;
        $senha = $data['senha'] ?? null;

        if (!$email || !$senha) {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Email e senha são obrigatórios.'], 400);
        }

        $stmt = $this->conn->prepare("SELECT id_produtor, hash_senha FROM produtor WHERE email_produtor = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($senha, $user['hash_senha'])) {
            $payload = [
                'iat' => time(),
                'exp' => time() + (60*60*24), // 24 horas
                'data' => [
                    'id_produtor' => $user['id_produtor'],
                    'email' => $email
                ]
            ];
            $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

            // Salva a sessão no banco
            $this->save_session($user['id_produtor'], $jwt, date('Y-m-d H:i:s', $payload['exp']));

            return $this->send_response(['status' => 'sucesso', 'token' => $jwt], 200);
        } else {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Credenciais inválidas.'], 401);
        }
    }

    private function save_session($id_produtor, $token, $expiration) {
        $stmt = $this->conn->prepare("INSERT INTO session (produtor_id_produtor, jwt_token, data_expiracao) VALUES (:id, :token, :exp)");
        $stmt->execute(['id' => $id_produtor, 'token' => $token, 'exp' => $expiration]);
    }

    private function send_response($data, $statusCode)
    {
        return [
            'statusCode' => $statusCode,
            'body' => $data
        ];
    }
}
