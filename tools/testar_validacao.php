<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpGen\Validation\BrValidator;

$casos = [
    // CPF
    ['cpf', '529.982.247-25', true],
    ['cpf', '52998224725',     true],
    ['cpf', '111.111.111-11', false],
    ['cpf', '123.456.789-00', false],
    ['cpf', '529.982.247-24', false],
    // CNPJ
    ['cnpj', '11.222.333/0001-81', true],
    ['cnpj', '11222333000181',     true],
    ['cnpj', '11.222.333/0001-80', false],
    ['cnpj', '00.000.000/0000-00', false],
    // Telefone
    ['telefone', '(11) 98888-7777', true],
    ['telefone', '1133334444',      true],
    ['telefone', '123',             false],
    // CEP
    ['cep', '01310-100', true],
    ['cep', '0131010',   false],
    // Data
    ['data', '29/02/2024', true],
    ['data', '29/02/2023', false],
    // Hora
    ['hora', '23:59', true],
    ['hora', '24:00', false],
];

$ok = 0; $fail = 0;
foreach ($casos as [$tipo, $valor, $esperado]) {
    $r = BrValidator::validateByType($tipo, $valor);
    $st = ($r === $esperado) ? '✅' : '❌';
    if ($r === $esperado) $ok++; else $fail++;
    printf("%s %-10s %-20s esperado=%s obtido=%s\n", $st, $tipo, $valor, $esperado?'true':'false', $r?'true':'false');
}
echo "\n$ok ok, $fail falhas\n";
exit($fail === 0 ? 0 : 1);