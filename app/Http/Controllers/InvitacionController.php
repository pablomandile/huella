<?php

namespace App\Http\Controllers;

use App\Enums\RolCuidador;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla que abre quien recibe una invitación.
 *
 * Vive **fuera** de `auth`: el invitado puede no tener cuenta todavía y hay que
 * poder decirle qué hacer. Lo que la protege es la firma de la URL, que además
 * lleva el email adentro: reenviar el mail no le sirve a un tercero.
 *
 * No muestra ni un dato clínico. Nombre, especie, foto y quién invita: lo
 * suficiente para reconocer de qué se trata y nada más, porque cualquiera con
 * el enlace llega hasta acá.
 */
class InvitacionController extends Controller
{
    public function mostrar(Request $request, Mascota $mascota): Response
    {
        $usuario = $request->user();
        $email = mb_strtolower((string) $request->query('email'));

        // Para que el ingreso o el registro devuelvan acá solos.
        if ($usuario === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('invitaciones/Aceptar', [
            'mascota' => [
                'nombre' => $mascota->nombre,
                'especie_etiqueta' => $mascota->especie->etiqueta(),
                'foto_url' => $mascota->foto_perfil
                    ? route('mascotas.foto-perfil', ['mascota' => $mascota->id, 'min' => 1])
                    : null,
            ],
            'invitadoPor' => $mascota->propietario->name,
            'email' => $email,
            // Qué va a poder hacer, dicho antes de aceptar y no después.
            'rol' => $this->rolInvitado($request)->value,
            'puedeEditar' => $this->rolInvitado($request)->puedeEditar(),
            // Wayfinder no puede generar una URL firmada: el POST va al string
            // que armó el servidor, con su firma incluida.
            'urlFirmada' => $request->fullUrl(),
            'estado' => $this->estado($usuario, $mascota, $email),
        ]);
    }

    public function aceptar(Request $request, Mascota $mascota): RedirectResponse
    {
        $usuario = $request->user();
        $email = mb_strtolower((string) $request->query('email'));
        $estado = $this->estado($usuario, $mascota, $email);

        // Se revalida entero: que el front haya mostrado el botón no prueba nada.
        if ($estado === 'ya_tiene_acceso') {
            return redirect()->route('mascotas.show', $mascota);
        }

        abort_unless($estado === 'listo', 403);

        /*
         * `attach` sobre una fila que no existe, y nunca `syncWithoutDetaching`:
         * ese **actualiza** los atributos del pivote si la fila ya está, así que
         * un propietario que abre su propia invitación quedaría degradado a
         * lector de su propia mascota. El unique(mascota_id, usuario_id) tampoco
         * lo frenaría, porque no inserta.
         */
        $mascota->cuidadores()->attach($usuario->id, [
            'rol' => $this->rolInvitado($request)->value,
        ]);

        return redirect()
            ->route('mascotas.show', $mascota)
            ->with('success', "Ya podés ver la ficha de {$mascota->nombre}.");
    }

    /**
     * El rol que concede esta invitación.
     *
     * Viaja en la URL **firmada**: alterarlo invalida la firma y la ruta contesta
     * 403 antes de llegar acá. Aun así se vuelve a validar contra la lista blanca
     * —nunca `Propietario`— porque una autorización que depende de un solo
     * candado depende de que ese candado nunca falle, y el candado acá es una
     * clave de aplicación que algún día se puede filtrar o rotar mal.
     *
     * Sin el parámetro se cae a `Lector`: es el rol que menos puede, y así una
     * invitación vieja —de antes de que existiera el rol en la URL— sigue
     * funcionando sin conceder de más.
     */
    private function rolInvitado(Request $request): RolCuidador
    {
        $rol = RolCuidador::tryFrom((string) $request->query('rol'));

        return in_array($rol, RolCuidador::invitables(), true)
            ? $rol
            : RolCuidador::Lector;
    }

    /**
     * En qué situación está quien abrió la invitación.
     *
     * @return 'sin_sesion'|'sin_verificar'|'otra_cuenta'|'ya_tiene_acceso'|'listo'
     */
    private function estado(?User $usuario, Mascota $mascota, string $email): string
    {
        return match (true) {
            $usuario === null => 'sin_sesion',
            // Sin esto, cualquiera podría registrarse declarando el email de otro
            // y quedarse con la invitación.
            ! $usuario->hasVerifiedEmail() => 'sin_verificar',
            mb_strtolower($usuario->email) !== $email => 'otra_cuenta',
            $mascota->rolDe($usuario) !== null => 'ya_tiene_acceso',
            default => 'listo',
        };
    }
}
