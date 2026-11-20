<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'mensagem' => 'Rewrite do Apache funcionando',
    'metodo' => $_SERVER['REQUEST_METHOD'],
    'caminho' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
], JSON_UNESCAPED_UNICODE);