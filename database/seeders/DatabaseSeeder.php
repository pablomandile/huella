<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Los catálogos son semilla compartida (usuario_id NULL) y también corren
        // en producción; el demo, no. Por eso están separados: al desplegar se
        // corre `db:seed --class=CatalogosSeeder` y nada más.
        $this->call(CatalogosSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
