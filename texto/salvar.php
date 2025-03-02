<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "app_texto";
$password = "123456";
$dbname = "texto";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["texto"])) {
        $texto = trim($_POST["texto"]);
        
        if (empty($texto)) {
            echo json_encode(["status" => "erro", "msg" => "Texto vazio"]);
            exit;
        }

        // Verificação de duplicação
        $stmt = $conn->prepare("SELECT COUNT(*) FROM mensagens WHERE texto = ?");
        $stmt->execute([$texto]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(["status" => "ignorado", "msg" => "Texto duplicado"]);
            exit;
        }

        // Inserção
        $stmt = $conn->prepare("INSERT INTO mensagens (texto) VALUES (?)");
        $stmt->execute([$texto]);

        echo json_encode(["status" => "sucesso", "msg" => "Texto salvo", "id" => $conn->lastInsertId()]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "erro", "msg" => "Erro: " . $e->getMessage()]);
}
