<?php

namespace App\Contracts;

use App\Models\Mascota;

/**
 * Todo registro clínico es, en el fondo, de una mascota — y la mascota es la
 * que sabe quién puede verla, por el pivote `mascota_usuario`.
 *
 * `RegistroClinicoPolicy` autoriza mirando únicamente esto, así que cualquier
 * entidad nueva (una aplicación de vacuna, una entrada de diario) queda
 * autorizada sin escribir una Policy más: le alcanza con implementar esta
 * interfaz y declarar la política.
 */
interface PerteneceAMascota
{
    /**
     * La mascota de la que depende este registro. Puede ser null si la cadena
     * quedó incompleta, y en ese caso la Policy niega.
     */
    public function mascotaAsociada(): ?Mascota;
}
