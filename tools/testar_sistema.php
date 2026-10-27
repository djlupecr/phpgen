<?php
/**
 * testar_sistema.php — Testes unitários das funções críticas do sistema.
 * Uso: php tools/testar_sistema.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Config;

$ok = 0; $fail = 0;

function assertTest(string $nome, bool $cond): void {
    global $ok, $fail;
    if ($cond) {
        echo "  [OK]   $nome\n";
        $ok++;
    } else {
        echo "  [FAIL] $nome\n";
        $fail++;
    }
}

echo "============================================================\n";
echo "  PHPGEN - Testes Unitários (Core)\n";
echo "============================================================\n\n";

echo "1. Configuração:\n";
assertTest('namespace gerado está correto', Config::$generatedNamespace === 'PhpGen\\Generated');
assertTest('JWT secret tem >= 32 bytes', strlen(Config::$jwtSecret) >= 32);
assertTest('Webhook secret está definido', !empty(Config::$webhookSecret));
assertTest('JWT e Webhook são diferentes', Config::$jwtSecret !== Config::$webhookSecret);
echo "\n";

echo "2. Validação de Documentos (BrValidator):\n";
assertTest('CPF válido (529.982.247-25)', \PhpGen\Validation\BrValidator::isValidCpf('529.982.247-25'));
assertTest('CPF inválido (111.111.111-11)', !\PhpGen\Validation\BrValidator::isValidCpf('111.111.111-11'));
assertTest('CNPJ válido (11.222.333/0001-81)', \PhpGen\Validation\BrValidator::isValidCnpj('11.222.333/0001-81'));
assertTest('CNPJ inválido', !\PhpGen\Validation\BrValidator::isValidCnpj('11.222.333/0001-80'));
assertTest('Data válida (29/02/2024)', \PhpGen\Validation\BrValidator::validateByType('data', '29/02/2024'));
assertTest('Data inválida (29/02/2023)', !\PhpGen\Validation\BrValidator::validateByType('data', '29/02/2023'));
assertTest('Hora válida (23:59)', \PhpGen\Validation\BrValidator::validateByType('hora', '23:59'));
assertTest('Hora inválida (24:00)', !\PhpGen\Validation\BrValidator::validateByType('hora', '24:00'));
echo "\n";

echo "3. JWT (Encode/Decode):\n";
try {
    $payload = ['iss' => 'test', 'iat' => time(), 'exp' => time() + 60, 'sub' => 1, 'jti' => bin2hex(random_bytes(8))];
    $jwt = \Firebase\JWT\JWT::encode($payload, Config::$jwtSecret, 'HS256');
    assertTest('JWT gerado com sucesso', !empty($jwt));
    
    $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key(Config::$jwtSecret, 'HS256'));
    assertTest('JWT decodificado corretamente', $decoded->sub === 1 && $decoded->iss === 'test');
    
    try {
        \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key('chave_errada', 'HS256'));
        assertTest('JWT com chave errada deve falhar', false);
    } catch (\Throwable $e) {
        assertTest('JWT com chave errada é rejeitado', true);
    }
} catch (\Throwable $e) {
    assertTest('Erro ao gerar/decodificar JWT', false);
}
echo "\n";

echo "4. HTML Sanitizer:\n";
$htmlSujo = '<p>Olá <script>alert("xss")</script> Mundo</p>';
$htmlLimpo = \PhpGen\Validation\HtmlSanitizer::sanitize($htmlSujo);
assertTest('Remove tag <script>', strpos($htmlLimpo, '<script>') === false);
assertTest('Mantém texto normal', strpos($htmlLimpo, 'Olá') !== false && strpos($htmlLimpo, 'Mundo') !== false);
echo "\n";

echo "============================================================\n";
echo " RESUMO: OK=$ok | FALHAS=$fail\n";
echo "============================================================\n";

exit($fail > 0 ? 1 : 0);