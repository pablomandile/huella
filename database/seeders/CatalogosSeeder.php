<?php

namespace Database\Seeders;

use App\Enums\CategoriaMedicamento;
use App\Enums\Especie;
use App\Enums\EtapaVida;
use App\Enums\GamaAlimento;
use App\Enums\TipoAlimento;
use App\Models\Alimento;
use App\Models\Medicamento;
use App\Models\Vacuna;
use Illuminate\Database\Seeder;

/**
 * Semilla compartida de los catálogos, con lo que se consigue en Argentina.
 *
 * Todos estos registros tienen `usuario_id` NULL: los ve todo el mundo y no
 * los edita nadie. Quien necesite otros valores duplica y edita su copia
 * (regla de negocio 4).
 *
 * Va aparte de `DatabaseSeeder` a propósito: los datos de demo son de demo,
 * pero esto corre también en producción, con
 * `php artisan db:seed --class=CatalogosSeeder`.
 *
 * Es idempotente: se puede volver a correr para sumar altas sin duplicar lo
 * que ya está.
 *
 * OJO con la regla de negocio 7: el sistema registra, no aconseja. Acá va la
 * identidad del producto y nada más. **Ninguna dosis, ninguna posología.**
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->vacunas();
        $this->medicamentos();
        $this->alimentos();
    }

    /**
     * Los meses de refuerzo son la sugerencia que precarga la próxima dosis
     * al aplicar; queda siempre editable, porque cada veterinaria maneja su
     * propio plan.
     */
    private function vacunas(): void
    {
        $vacunas = [
            // Perros
            ['Quíntuple', Especie::Perro, 'Moquillo, hepatitis, parvovirosis, parainfluenza y leptospirosis.', 12, false],
            ['Séxtuple', Especie::Perro, 'Quíntuple más coronavirus canino.', 12, false],
            ['Óctuple', Especie::Perro, 'Séxtuple con cepas adicionales de leptospira.', 12, false],
            ['Antirrábica', Especie::Perro, 'Obligatoria por la ley nacional 22.953.', 12, true],
            ['Tos de las perreras', Especie::Perro, 'Traqueobronquitis infecciosa canina (Bordetella).', 12, false],
            ['Giardia', Especie::Perro, null, 12, false],

            // Gatos
            ['Triple felina', Especie::Gato, 'Panleucopenia, rinotraqueítis y calicivirus.', 12, false],
            ['Cuádruple felina', Especie::Gato, 'Triple felina más clamidiosis.', 12, false],
            ['Leucemia felina', Especie::Gato, 'FeLV.', 12, false],
            ['Antirrábica', Especie::Gato, 'Obligatoria por la ley nacional 22.953.', 12, true],
        ];

        foreach ($vacunas as [$nombre, $especie, $descripcion, $refuerzo, $obligatoria]) {
            Vacuna::updateOrCreate(
                ['usuario_id' => null, 'nombre' => $nombre, 'especie' => $especie],
                [
                    'descripcion' => $descripcion,
                    'meses_refuerzo' => $refuerzo,
                    'obligatoria' => $obligatoria,
                ],
            );
        }
    }

    /**
     * Antiparasitarios y drogas de uso habitual. Los de marca van con su
     * principio activo al lado, que es lo que suele figurar en la receta.
     */
    private function medicamentos(): void
    {
        $c = CategoriaMedicamento::class;

        $medicamentos = [
            // [nombre comercial, droga, laboratorio, presentación, categoría, receta]
            ['Drontal Plus', 'Praziquantel + pirantel + febantel', 'Elanco', 'Comprimidos', $c::AntiparasitarioInterno, false],
            ['Endogard', 'Praziquantel + pirantel + febantel', 'Virbac', 'Comprimidos', $c::AntiparasitarioInterno, false],
            ['Total Full', 'Praziquantel + pirantel + oxantel', 'Brouwer', 'Comprimidos', $c::AntiparasitarioInterno, false],
            ['Milbemax', 'Milbemicina oxima + praziquantel', 'Elanco', 'Comprimidos', $c::AntiparasitarioInterno, false],
            ['Endovel', 'Praziquantel + pirantel', 'Holliday-Scott', 'Comprimidos', $c::AntiparasitarioInterno, false],

            ['NexGard', 'Afoxolaner', 'Boehringer Ingelheim', 'Comprimidos masticables', $c::AntiparasitarioExterno, false],
            ['NexGard Spectra', 'Afoxolaner + milbemicina oxima', 'Boehringer Ingelheim', 'Comprimidos masticables', $c::AntiparasitarioExterno, false],
            ['Bravecto', 'Fluralaner', 'MSD Salud Animal', 'Comprimidos masticables', $c::AntiparasitarioExterno, false],
            ['Simparica', 'Sarolaner', 'Zoetis', 'Comprimidos masticables', $c::AntiparasitarioExterno, false],
            ['Frontline Plus', 'Fipronil + S-metopreno', 'Boehringer Ingelheim', 'Pipeta', $c::AntiparasitarioExterno, false],
            ['Advantix', 'Imidacloprid + permetrina', 'Elanco', 'Pipeta', $c::AntiparasitarioExterno, false],
            ['Revolution', 'Selamectina', 'Zoetis', 'Pipeta', $c::AntiparasitarioExterno, false],

            ['Cefalexina', 'Cefalexina', null, 'Comprimidos o suspensión', $c::Antibiotico, true],
            ['Amoxicilina con ácido clavulánico', 'Amoxicilina + ácido clavulánico', null, 'Comprimidos o suspensión', $c::Antibiotico, true],
            ['Enrofloxacina', 'Enrofloxacina', null, 'Comprimidos o inyectable', $c::Antibiotico, true],
            ['Metronidazol', 'Metronidazol', null, 'Comprimidos o suspensión', $c::Antibiotico, true],
            ['Doxiciclina', 'Doxiciclina', null, 'Comprimidos', $c::Antibiotico, true],

            ['Meloxicam', 'Meloxicam', null, 'Comprimidos, gotas o inyectable', $c::Antiinflamatorio, true],
            ['Carprofeno', 'Carprofeno', null, 'Comprimidos', $c::Antiinflamatorio, true],
            ['Previcox', 'Firocoxib', 'Boehringer Ingelheim', 'Comprimidos', $c::Antiinflamatorio, true],
            ['Tramadol', 'Tramadol', null, 'Comprimidos o gotas', $c::Analgesico, true],
            ['Gabapentina', 'Gabapentina', null, 'Comprimidos', $c::Analgesico, true],

            ['Apoquel', 'Oclacitinib', 'Zoetis', 'Comprimidos', $c::Dermatologico, true],
            ['Pervinal Ótico', 'Antibiótico y antiinflamatorio ótico', 'Holliday-Scott', 'Gotas', $c::Dermatologico, true],
            ['Champú con clorhexidina', 'Clorhexidina', null, 'Frasco', $c::Dermatologico, false],

            ['Condroprotector', 'Glucosamina + condroitín sulfato', null, 'Comprimidos', $c::Suplemento, false],
            ['Omega 3', 'Ácidos grasos EPA y DHA', null, 'Cápsulas o líquido', $c::Suplemento, false],
        ];

        foreach ($medicamentos as [$nombre, $droga, $laboratorio, $presentacion, $categoria, $receta]) {
            Medicamento::updateOrCreate(
                ['usuario_id' => null, 'nombre_comercial' => $nombre],
                [
                    'droga' => $droga,
                    'laboratorio' => $laboratorio,
                    'presentacion' => $presentacion,
                    'categoria' => $categoria,
                    'requiere_receta' => $receta,
                ],
            );
        }
    }

    /**
     * Marcas que se consiguen en cualquier veterinaria o pet shop del país.
     * La idea no es tener el mercado entero, sino que el 90% de la gente
     * encuentre lo que le da de comer sin tener que tipearlo.
     */
    private function alimentos(): void
    {
        $seco = TipoAlimento::BalanceadoSeco;
        $perro = Especie::Perro;
        $gato = Especie::Gato;

        $alimentos = [
            // [marca, nombre, tipo, gama, especie, etapa, medicado]
            ['Royal Canin', 'Medium Adult', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Royal Canin', 'Medium Puppy', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Cachorro, false],
            ['Royal Canin', 'Maxi Adult', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Royal Canin', 'Mini Adult', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Pro Plan', 'Adult Complete', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Pro Plan', 'Puppy Complete', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Cachorro, false],
            ['Eukanuba', 'Adult Maintenance', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Old Prince', 'Novel Adult', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Vitalcan', 'Balanced Adult', $seco, GamaAlimento::Premium, $perro, EtapaVida::Adulto, false],
            ['Excellent', 'Adulto', $seco, GamaAlimento::Premium, $perro, EtapaVida::Adulto, false],
            ['Dog Chow', 'Adultos', $seco, GamaAlimento::Estandar, $perro, EtapaVida::Adulto, false],
            ['Sieger', 'Adulto', $seco, GamaAlimento::Premium, $perro, EtapaVida::Adulto, false],
            ['Nutrique', 'Adulto', $seco, GamaAlimento::SuperPremium, $perro, EtapaVida::Adulto, false],
            ['Raza', 'Adultos', $seco, GamaAlimento::Estandar, $perro, EtapaVida::Adulto, false],

            ['Royal Canin', 'Renal', $seco, GamaAlimento::Medicado, $perro, EtapaVida::Todas, true],
            ['Royal Canin', 'Gastrointestinal', $seco, GamaAlimento::Medicado, $perro, EtapaVida::Todas, true],
            ['Royal Canin', 'Hypoallergenic', $seco, GamaAlimento::Medicado, $perro, EtapaVida::Todas, true],
            ["Hill's", 'Prescription Diet i/d', $seco, GamaAlimento::Medicado, $perro, EtapaVida::Todas, true],

            ['Royal Canin', 'Indoor', $seco, GamaAlimento::SuperPremium, $gato, EtapaVida::Adulto, false],
            ['Royal Canin', 'Sterilised', $seco, GamaAlimento::SuperPremium, $gato, EtapaVida::Adulto, false],
            ['Royal Canin', 'Kitten', $seco, GamaAlimento::SuperPremium, $gato, EtapaVida::Cachorro, false],
            ['Pro Plan', 'Cat Adult', $seco, GamaAlimento::SuperPremium, $gato, EtapaVida::Adulto, false],
            ['Excellent', 'Cat Adulto', $seco, GamaAlimento::Premium, $gato, EtapaVida::Adulto, false],
            ['Cat Chow', 'Adultos', $seco, GamaAlimento::Estandar, $gato, EtapaVida::Adulto, false],
            ['Royal Canin', 'Urinary S/O', $seco, GamaAlimento::Medicado, $gato, EtapaVida::Todas, true],
            ['Whiskas', 'Sobrecito', TipoAlimento::Humedo, GamaAlimento::Estandar, $gato, EtapaVida::Adulto, false],
        ];

        foreach ($alimentos as [$marca, $nombre, $tipo, $gama, $especie, $etapa, $medicado]) {
            Alimento::updateOrCreate(
                ['usuario_id' => null, 'marca' => $marca, 'nombre' => $nombre, 'especie' => $especie],
                [
                    'tipo' => $tipo,
                    'gama' => $gama,
                    'etapa' => $etapa,
                    'medicado' => $medicado,
                ],
            );
        }
    }
}
