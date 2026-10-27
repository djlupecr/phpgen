<?php
namespace PhpGen\Validation;

/**
 * Validação matemática de documentos brasileiros.
 * - CPF: 11 dígitos, módulo 11 com pesos 10..2 e 11..2
 * - CNPJ: 14 dígitos, módulo 11 com pesos 5..2 (2x) e 6..2
 * - Rejeita sequências repetidas (111.111.111-11 etc.)
 */
class BrValidator
{
    /** Remove tudo que não é dígito */
    public static function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?? '';
    }

    // ============================================================
    // CPF
    // ============================================================
    public static function isValidCpf(string $cpf): bool
    {
        $cpf = self::onlyDigits($cpf);
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        // Primeiro dígito
        $sum = 0;
        for ($i = 0; $i < 9; $i++) $sum += (int)$cpf[$i] * (10 - $i);
        $d1 = ($sum * 10) % 11;
        if ($d1 === 10) $d1 = 0;
        if ($d1 !== (int)$cpf[9]) return false;

        // Segundo dígito
        $sum = 0;
        for ($i = 0; $i < 10; $i++) $sum += (int)$cpf[$i] * (11 - $i);
        $d2 = ($sum * 10) % 11;
        if ($d2 === 10) $d2 = 0;
        return $d2 === (int)$cpf[10];
    }

    // ============================================================
    // CNPJ
    // ============================================================
    public static function isValidCnpj(string $cnpj): bool
    {
        $cnpj = self::onlyDigits($cnpj);
        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

        // Primeiro dígito
        $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += (int)$cnpj[$i] * $weights1[$i];
        $d1 = $sum % 11;
        $d1 = ($d1 < 2) ? 0 : 11 - $d1;
        if ($d1 !== (int)$cnpj[12]) return false;

        // Segundo dígito
        $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) $sum += (int)$cnpj[$i] * $weights2[$i];
        $d2 = $sum % 11;
        $d2 = ($d2 < 2) ? 0 : 11 - $d2;
        return $d2 === (int)$cnpj[13];
    }

    // ============================================================
    // Data e Hora
    // ============================================================
    private static function isValidDate(string $v): bool
    {
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) return false;
        [$d, $m, $y] = array_map('intval', explode('/', $v));
        return checkdate($m, $d, $y);
    }

    // [FIX] Valida hora no intervalo real: 00:00 a 23:59
    private static function isValidTime(string $v): bool
    {
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $v)) {
            return false;
        }
        return true;
    }

    // ============================================================
    // Dispatcher por "maskType" (usado no wizard e no handle)
    // ============================================================
    public static function validateByType(string $type, string $value): bool
    {
        $type = strtolower($type);
        return match ($type) {
            'cpf'      => self::isValidCpf($value),
            'cnpj'     => self::isValidCnpj($value),
            'telefone' => preg_match('/^\d{10,11}$/', self::onlyDigits($value)) === 1,
            'cep'      => preg_match('/^\d{8}$/', self::onlyDigits($value)) === 1,
            'data'     => self::isValidDate($value),
            'hora'     => self::isValidTime($value),   // [FIX] valida intervalo real
            default    => true,
        };
    }

    /** Formata CPF/CNPJ/telefone para o banco (opcional) */
    public static function formatByType(string $type, string $value): string
    {
        $v = self::onlyDigits($value);
        return match (strtolower($type)) {
            'cpf' => strlen($v) === 11
                ? substr($v,0,3).'.'.substr($v,3,3).'.'.substr($v,6,3).'-'.substr($v,9,2)
                : $value,
            'cnpj' => strlen($v) === 14
                ? substr($v,0,2).'.'.substr($v,2,3).'.'.substr($v,5,3).'/'.substr($v,8,4).'-'.substr($v,12,2)
                : $value,
            'telefone' => strlen($v) === 11
                ? '('.substr($v,0,2).') '.substr($v,2,5).'-'.substr($v,7,4)
                : (strlen($v) === 10
                    ? '('.substr($v,0,2).') '.substr($v,2,4).'-'.substr($v,6,4)
                    : $value),
            'cep' => strlen($v) === 8
                ? substr($v,0,5).'-'.substr($v,5,3)
                : $value,
            default => $value,
        };
    }
}