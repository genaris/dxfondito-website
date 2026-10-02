<?php

declare(strict_types=1);

namespace DxFondito\Tests\Registry;

use DxFondito\Registry\LicenseeName;
use DxFondito\Registry\RegistryList;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegistryListTest extends TestCase
{
    public function testReadsTheListOfEnacom(): void
    {
        $list = RegistryList::enacom((string) file_get_contents(__DIR__ . '/fixtures/enacom.html'));

        // A line without a name is not in the list.
        self::assertSame(['LU9ZZA' => 'JUANA ISABEL EJEMPLO', 'LU9ZZB' => 'PEDRO MUESTRA PRUEBA NUÑEZ'], $list);
    }

    public function testRefusesAPageOfEnacomWithOtherColumns(): void
    {
        $this->expectException(RuntimeException::class);

        RegistryList::enacom('<table><tr><th>Nombre</th><th>Indicativo</th></tr></table>');
    }

    public function testReadsTheListOfUrsec(): void
    {
        $list = RegistryList::ursec((string) file_get_contents(__DIR__ . '/fixtures/ursec.ods'));

        // The given names come first.
        self::assertSame(['CX9ZZA' => 'JUAN PRUEBA EJEMPLO', 'CX9ZZB' => 'MARIA TEST MUESTRA DEMO'], $list);
    }

    public function testRefusesAFileThatIsNotAnOdsFile(): void
    {
        $this->expectException(RuntimeException::class);

        RegistryList::ursec('not a zip file');
    }

    public function testFindsTheNewestListOnThePageOfUrsec(): void
    {
        $html = '<a href="/sites/x/files/2026-04/Nomina%20CX%20Vigentes%20Abril%202026.ods">ODS</a>'
            . '<a href="/sites/x/files/2026-07/Nomina%20Distintivo%20Especial%20Julio%202026.ods">ODS</a>'
            . "<a href='/sites/x/files/2026-07/Nomina%20CX%20Vigentes%20Julio%202026.ods' download>ODS</a>";

        self::assertSame(
            'https://www.gub.uy/sites/x/files/2026-07/Nomina%20CX%20Vigentes%20Julio%202026.ods',
            RegistryList::ursecLink($html, 'https://www.gub.uy'),
        );
    }

    public function testFormatsANameForTheQslCard(): void
    {
        self::assertSame('Juana Isabel Ejemplo', LicenseeName::format('JUANA ISABEL EJEMPLO'));
        self::assertSame('Pedro Muestra Prueba Nuñez', LicenseeName::format('PEDRO MUESTRA PRUEBA NUÑEZ'));
        self::assertSame('Maria de los Angeles del Valle', LicenseeName::format('MARIA DE LOS ANGELES DEL VALLE'));
        self::assertSame("Jose D'Angelo Perez-Garcia", LicenseeName::format("JOSE D'ANGELO  PEREZ-GARCIA"));
    }
}
