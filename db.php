<?php
// Carregar variáveis do .env (simulação simples)
$env = parse_ini_file('.env');

try {
    $pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8", $env['DB_USER'], $env['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
session_start();
?>