<?php
namespace Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use PhpGen\Validation\BrValidator;

class BrValidatorTest extends TestCase
{
    /**
     * @dataProvider cpfProvider
     */
    public function testCpf(string $cpf, bool $esperado): void
    {
        $this->assertSame($esperado, BrValidator::isValidCpf($cpf));
    }

    public static function cpfProvider(): array
    {
        return [
            'válido formatado' => ['529.982.247-25', true],
            'válido sem máscara' => ['52998224725', true],
            'todos dígitos iguais' => ['111.111.111-11', false],
            'inválido' => ['123.456.789-00', false],
        ];
    }

    /**
     * @dataProvider cnpjProvider
     */
    public function testCnpj(string $cnpj, bool $esperado): void
    {
        $this->assertSame($esperado, BrValidator::isValidCnpj($cnpj));
    }

    public static function cnpjProvider(): array
    {
        return [
            'válido formatado' => ['11.222.333/0001-81', true],
            'válido sem máscara' => ['11222333000181', true],
            'inválido' => ['11.222.333/0001-80', false],
        ];
    }

    /**
     * @dataProvider horaProvider
     */
    public function testHora(string $hora, bool $esperado): void
    {
        $this->assertSame($esperado, BrValidator::validateByType('hora', $hora));
    }

    public static function horaProvider(): array
    {
        return [
            'válida' => ['23:59', true],
            'inválida' => ['24:00', false],
            'inválida negativa' => ['-1:00', false],
        ];
    }
}
