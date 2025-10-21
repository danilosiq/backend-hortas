<?php
include 'banco_mysql.php';

// Busca todos os bairros cadastrados nas hortas
$sql_bairros = "SELECT DISTINCT e.nm_bairro FROM hortas h INNER JOIN endereco_hortas e ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas ORDER BY e.nm_bairro ASC";
$res_bairros = mysqli_query($conn, $sql_bairros);

// Captura o bairro selecionado pelo usuário (pelo GET)
$bairroSelecionado = $_GET['bairro'] ?? "";

// Se o usuário escolheu um bairro, busca as hortas desse bairro
$hortas = [];
if ($bairroSelecionado != "") {
    $sql_hortas = " SELECT h.nome, h.descricao, e.nm_rua AS endereco, e.nm_bairro AS bairro FROM hortas h INNER JOIN endereco_hortas e ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas 
    WHERE e.nm_bairro = '$bairroSelecionado'
    ";
    $res_hortas = mysqli_query($conn, $sql_hortas);

    while ($row = mysqli_fetch_assoc($res_hortas)) {
        $hortas[] = $row;
    }
}
?>

//pedi pro chat fazer esse front só pra ver mais ou menos kkkk

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Hortas por Bairro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f3f3;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #2b7a0b;
        }
        form {
            margin-bottom: 20px;
        }
        select {
            padding: 8px;
            font-size: 16px;
        }
        button {
            padding: 8px 12px;
            background: #2b7a0b;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 15px;
            width: 260px;
        }
        .card h3 {
            margin: 0 0 10px;
            color: #2b7a0b;
        }
        .card p {
            margin: 4px 0;
        }
    </style>
</head>
<body>

<h1>Encontre hortas próximas</h1>


//aqui ele vai mostrar as opçoes de bairro para o usuário escolher (SELECT)
<form method="GET">
    <label for="bairro">Selecione seu bairro:</label>
    <select name="bairro" id="bairro" required>
        <option value="">-- Escolha um bairro --</option>
        <?php while ($b = mysqli_fetch_assoc($res_bairros)): ?>
            <option value="<?= $b['nm_bairro'] ?>" <?= ($bairroSelecionado == $b['nm_bairro']) ? "selected" : "" ?>>
                <?= htmlspecialchars($b['nm_bairro']) ?>
            </option>
        <?php endwhile; ?>
    </select>
    <button type="submit">Buscar</button>
</form>

//Se encontrar os bairro ele apresenta em forma de card, se não ele devolve 'nenhuma horta nessa região' (if e else básico)

<?php if ($bairroSelecionado != ""): ?>
    <h2>Hortas na região <?= htmlspecialchars($bairroSelecionado) ?>:</h2>

    <div class="cards">
        <?php if (count($hortas) > 0): ?>
            <?php foreach ($hortas as $h): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($h['nome']) ?></h3>
                    <p><strong>Endereço:</strong> <?= htmlspecialchars($h['endereco']) ?></p>
                    <p><strong>Bairro:</strong> <?= htmlspecialchars($h['bairro']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Nenhuma horta encontrada nessa região.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>