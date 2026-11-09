<?php

namespace Database\Seeders\Demo;

use GdImage;
use RuntimeException;

/**
 * Las imágenes de la demo: retratos dibujados y hojas de libreta, generados al
 * vuelo con GD.
 *
 * **Dibujadas y no fotos de stock**, a propósito: una foto de un perro real en
 * una demo pública es la foto de alguien, y una con licencia dudosa no se puede
 * poner en un portfolio. Un dibujo simple se lee enseguida como ejemplo.
 *
 * Se dibuja al doble del tamaño final y se achica al terminar: GD no suaviza los
 * bordes de las elipses rellenas, y el remuestreo es el antialias.
 *
 * Todo sale a archivos temporales que el seeder pasa por `ImagenService` y
 * `ArchivoService`, como una subida real: así la demo ejercita el mismo camino
 * (WebP, miniaturas, disco privado) que usa la gente.
 */
class Ilustraciones
{
    private const LADO = 1200;

    private const ESCALA = 2;

    /** @var list<string> */
    private array $temporales = [];

    /**
     * Un retrato de la mascota en una escena.
     *
     * @param  'perro'|'gato'  $especie
     * @param  array{0: int, 1: int, 2: int}  $pelaje
     * @param  'plaza'|'playa'|'casa'|'cumple'|'cachorro'  $escena
     */
    public function retrato(string $especie, array $pelaje, string $escena, int $semilla = 1): string
    {
        mt_srand($semilla);

        $l = self::LADO * self::ESCALA;
        $img = $this->lienzo($l, $l);

        $this->fondo($img, $escena, $l);

        // Un cachorro es más chico y va más abajo: es la foto del primer día.
        $tamanio = $escena === 'cachorro' ? 0.78 : 1.0;
        $cx = (int) ($l * (0.5 + (mt_rand(-40, 40) / 1000)));
        $cy = (int) ($l * ($escena === 'cachorro' ? 0.60 : 0.52));

        $especie === 'gato'
            ? $this->gato($img, $cx, $cy, (int) ($l * 0.23 * $tamanio), $pelaje)
            : $this->perro($img, $cx, $cy, (int) ($l * 0.23 * $tamanio), $pelaje);

        if ($escena === 'cumple') {
            $this->gorrito($img, $cx + (int) ($l * 0.05 * $tamanio), $cy - (int) ($l * 0.27 * $tamanio), (int) ($l * 0.09 * $tamanio));
        }

        return $this->guardar($img, $l, $l, 'png');
    }

    /**
     * Una hoja escaneada de la libreta sanitaria: papel, sello del veterinario y
     * una tabla de aplicaciones escrita a mano (o casi).
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $filas  fecha, vacuna, lote, próxima
     */
    public function hojaLibreta(string $mascota, string $titulo, array $filas, int $hoja): string
    {
        $ancho = 1240 * self::ESCALA;
        $alto = 1754 * self::ESCALA;
        $img = $this->lienzo($ancho, $alto);
        $s = self::ESCALA;

        // Papel apenas amarillento, con el borde gastado de una libreta de bolsillo.
        imagefilledrectangle($img, 0, 0, $ancho, $alto, $this->color($img, 250, 246, 232));
        imagefilledrectangle($img, 0, 0, $ancho, 14 * $s, $this->color($img, 232, 226, 205));

        $tinta = $this->color($img, 40, 52, 70);
        $gris = $this->color($img, 120, 128, 140);
        $lineas = $this->color($img, 196, 205, 214);
        $azul = $this->color($img, 32, 70, 150);

        $this->texto($img, 'LIBRETA SANITARIA', 90 * $s, 150 * $s, 46 * $s, $tinta, true);
        $this->texto($img, $titulo, 90 * $s, 225 * $s, 30 * $s, $gris);
        $this->texto($img, "Nombre: {$mascota}", 90 * $s, 320 * $s, 30 * $s, $tinta);
        $this->texto($img, "Hoja {$hoja}", 1020 * $s, 150 * $s, 26 * $s, $gris);

        // Encabezado de la tabla.
        $columnas = [90, 320, 660, 860];
        $encabezados = ['Fecha', 'Vacuna / producto', 'Lote', 'Próxima'];
        foreach ($encabezados as $i => $encabezado) {
            $this->texto($img, $encabezado, $columnas[$i] * $s, 430 * $s, 26 * $s, $tinta, true);
        }

        imagesetthickness($img, 2 * $s);
        imageline($img, 80 * $s, 455 * $s, 1160 * $s, 455 * $s, $tinta);
        imagesetthickness($img, $s);

        $y = 540;
        for ($renglon = 0; $renglon < 11; $renglon++) {
            imageline($img, 80 * $s, ($y + 20) * $s, 1160 * $s, ($y + 20) * $s, $lineas);

            if (isset($filas[$renglon])) {
                foreach ($filas[$renglon] as $i => $valor) {
                    // Un leve temblor de renglón a renglón: está escrito a mano.
                    $this->texto($img, $valor, ($columnas[$i] + mt_rand(-4, 4)) * $s, ($y + mt_rand(-3, 3)) * $s, 28 * $s, $azul);
                }

                // El sello redondo del veterinario, encima de la fila.
                $this->sello($img, 1110 * $s, ($y - 12) * $s, 40 * $s);
            }

            $y += 100;
        }

        $this->texto($img, 'Documento de ejemplo — demo de Huella', 90 * $s, 1690 * $s, 22 * $s, $gris);

        return $this->guardar($img, $ancho, $alto, 'jpg');
    }

    /** Borra los temporales que quedaron sin mover. */
    public function limpiar(): void
    {
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }

        $this->temporales = [];
    }

    private function lienzo(int $ancho, int $alto): GdImage
    {
        if ($ancho < 1 || $alto < 1) {
            throw new RuntimeException("Lienzo inválido: {$ancho}×{$alto}.");
        }

        $img = imagecreatetruecolor($ancho, $alto);

        if ($img === false) {
            throw new RuntimeException('GD no pudo crear el lienzo.');
        }

        return $img;
    }

    private function fondo(GdImage $img, string $escena, int $l): void
    {
        [$cielo, $piso, $alturaPiso] = match ($escena) {
            'playa' => [[178, 222, 240], [238, 214, 164], 0.70],
            'casa' => [[238, 228, 214], [176, 132, 100], 0.74],
            'cumple' => [[252, 236, 214], [214, 196, 232], 0.76],
            'cachorro' => [[226, 240, 236], [196, 222, 208], 0.78],
            default => [[200, 232, 246], [140, 196, 120], 0.68],
        };

        imagefilledrectangle($img, 0, 0, $l, $l, $this->color($img, ...$cielo));
        imagefilledrectangle($img, 0, (int) ($l * $alturaPiso), $l, $l, $this->color($img, ...$piso));

        if ($escena === 'playa') {
            // Mar y sol.
            imagefilledrectangle($img, 0, (int) ($l * 0.60), $l, (int) ($l * 0.70), $this->color($img, 70, 150, 200));
            imagefilledellipse($img, (int) ($l * 0.82), (int) ($l * 0.16), (int) ($l * 0.16), (int) ($l * 0.16), $this->color($img, 255, 212, 92));
        }

        if ($escena === 'plaza') {
            // Un par de arbustos al fondo.
            $verde = $this->color($img, 104, 168, 96);
            imagefilledellipse($img, (int) ($l * 0.12), (int) ($l * 0.66), (int) ($l * 0.34), (int) ($l * 0.22), $verde);
            imagefilledellipse($img, (int) ($l * 0.92), (int) ($l * 0.65), (int) ($l * 0.30), (int) ($l * 0.24), $verde);
            imagefilledellipse($img, (int) ($l * 0.18), (int) ($l * 0.18), (int) ($l * 0.20), (int) ($l * 0.08), $this->color($img, 250, 252, 255));
        }

        if ($escena === 'casa') {
            // El respaldo de un sillón.
            imagefilledrectangle($img, (int) ($l * 0.04), (int) ($l * 0.50), (int) ($l * 0.96), (int) ($l * 0.76), $this->color($img, 92, 120, 140));
            imagefilledellipse($img, (int) ($l * 0.06), (int) ($l * 0.62), (int) ($l * 0.10), (int) ($l * 0.30), $this->color($img, 80, 106, 126));
            imagefilledellipse($img, (int) ($l * 0.94), (int) ($l * 0.62), (int) ($l * 0.10), (int) ($l * 0.30), $this->color($img, 80, 106, 126));
        }

        if ($escena === 'cumple') {
            // Papel picado.
            $colores = [[236, 92, 120], [92, 176, 220], [250, 196, 72], [120, 196, 140]];
            for ($i = 0; $i < 60; $i++) {
                $c = $colores[$i % 4];
                $x = mt_rand(0, $l);
                $y = mt_rand(0, (int) ($l * 0.7));
                imagefilledrectangle($img, $x, $y, $x + (int) ($l * 0.012), $y + (int) ($l * 0.024), $this->color($img, ...$c));
            }
        }
    }

    /** @param  array{0: int, 1: int, 2: int}  $pelaje */
    private function perro(GdImage $img, int $cx, int $cy, int $r, array $pelaje): void
    {
        $base = $this->color($img, ...$pelaje);
        $oscuro = $this->color($img, ...$this->tono($pelaje, 0.72));
        $claro = $this->color($img, ...$this->tono($pelaje, 1.25));

        // Cuerpo, sentado.
        imagefilledellipse($img, $cx, $cy + (int) ($r * 1.55), (int) ($r * 2.3), (int) ($r * 2.0), $base);
        imagefilledellipse($img, $cx, $cy + (int) ($r * 1.65), (int) ($r * 1.1), (int) ($r * 1.4), $claro);

        // Orejas caídas.
        imagefilledellipse($img, $cx - (int) ($r * 0.92), $cy + (int) ($r * 0.15), (int) ($r * 0.62), (int) ($r * 1.35), $oscuro);
        imagefilledellipse($img, $cx + (int) ($r * 0.92), $cy + (int) ($r * 0.15), (int) ($r * 0.62), (int) ($r * 1.35), $oscuro);

        // Cabeza y hocico.
        imagefilledellipse($img, $cx, $cy, (int) ($r * 2.0), (int) ($r * 1.9), $base);
        imagefilledellipse($img, $cx, $cy + (int) ($r * 0.45), (int) ($r * 1.05), (int) ($r * 0.8), $claro);

        // Lengua, ojos y nariz.
        imagefilledellipse($img, $cx, $cy + (int) ($r * 0.88), (int) ($r * 0.30), (int) ($r * 0.36), $this->color($img, 232, 110, 130));
        imagefilledellipse($img, $cx, $cy + (int) ($r * 0.66), (int) ($r * 0.62), (int) ($r * 0.22), $claro);
        $this->ojos($img, $cx, $cy - (int) ($r * 0.15), $r, false);
        imagefilledellipse($img, $cx, $cy + (int) ($r * 0.3), (int) ($r * 0.34), (int) ($r * 0.24), $this->color($img, 34, 30, 32));

        // Collar.
        imagesetthickness($img, (int) ($r * 0.12));
        imagearc($img, $cx, $cy + (int) ($r * 0.55), (int) ($r * 1.6), (int) ($r * 1.0), 30, 150, $this->color($img, 214, 64, 72));
        imagesetthickness($img, 1);
        imagefilledellipse($img, $cx, $cy + (int) ($r * 1.08), (int) ($r * 0.22), (int) ($r * 0.22), $this->color($img, 246, 196, 64));
    }

    /** @param  array{0: int, 1: int, 2: int}  $pelaje */
    private function gato(GdImage $img, int $cx, int $cy, int $r, array $pelaje): void
    {
        $base = $this->color($img, ...$pelaje);
        $oscuro = $this->color($img, ...$this->tono($pelaje, 0.7));
        $claro = $this->color($img, ...$this->tono($pelaje, 1.35));
        $rosa = $this->color($img, 240, 160, 170);

        // Cuerpo y cola.
        imagesetthickness($img, (int) ($r * 0.26));
        imagearc($img, $cx + (int) ($r * 1.3), $cy + (int) ($r * 1.9), (int) ($r * 1.2), (int) ($r * 1.6), 180, 300, $oscuro);
        imagesetthickness($img, 1);
        imagefilledellipse($img, $cx, $cy + (int) ($r * 1.55), (int) ($r * 2.0), (int) ($r * 2.0), $base);
        imagefilledellipse($img, $cx, $cy + (int) ($r * 1.7), (int) ($r * 0.9), (int) ($r * 1.3), $claro);

        // Orejas en punta, con el interior rosa.
        foreach ([-1, 1] as $lado) {
            imagefilledpolygon($img, [
                $cx + $lado * (int) ($r * 0.95), $cy - (int) ($r * 0.25),
                $cx + $lado * (int) ($r * 0.80), $cy - (int) ($r * 1.20),
                $cx + $lado * (int) ($r * 0.25), $cy - (int) ($r * 0.80),
            ], $base);
            imagefilledpolygon($img, [
                $cx + $lado * (int) ($r * 0.80), $cy - (int) ($r * 0.40),
                $cx + $lado * (int) ($r * 0.74), $cy - (int) ($r * 0.98),
                $cx + $lado * (int) ($r * 0.40), $cy - (int) ($r * 0.74),
            ], $rosa);
        }

        imagefilledellipse($img, $cx, $cy, (int) ($r * 2.0), (int) ($r * 1.75), $base);

        // Rayas de atigrado en la frente.
        imagesetthickness($img, (int) ($r * 0.07));
        foreach ([-0.22, 0, 0.22] as $d) {
            imageline($img, $cx + (int) ($r * $d), $cy - (int) ($r * 0.82), $cx + (int) ($r * $d * 0.8), $cy - (int) ($r * 0.5), $oscuro);
        }
        imagesetthickness($img, 1);

        imagefilledellipse($img, $cx, $cy + (int) ($r * 0.42), (int) ($r * 0.9), (int) ($r * 0.6), $claro);
        $this->ojos($img, $cx, $cy - (int) ($r * 0.1), $r, true);

        // Nariz y bigotes.
        imagefilledpolygon($img, [
            $cx - (int) ($r * 0.12), $cy + (int) ($r * 0.25),
            $cx + (int) ($r * 0.12), $cy + (int) ($r * 0.25),
            $cx, $cy + (int) ($r * 0.4),
        ], $rosa);

        $bigote = $this->color($img, 250, 250, 250);
        imagesetthickness($img, max(2, (int) ($r * 0.02)));
        foreach ([-1, 1] as $lado) {
            foreach ([-0.06, 0.06, 0.18] as $d) {
                imageline(
                    $img,
                    $cx + $lado * (int) ($r * 0.3), $cy + (int) ($r * (0.42 + $d * 0.3)),
                    $cx + $lado * (int) ($r * 1.15), $cy + (int) ($r * (0.32 + $d)),
                    $bigote,
                );
            }
        }
        imagesetthickness($img, 1);
    }

    private function ojos(GdImage $img, int $cx, int $cy, int $r, bool $felino): void
    {
        $negro = $this->color($img, 30, 30, 34);
        $brillo = $this->color($img, 255, 255, 255);
        $iris = $felino ? $this->color($img, 150, 196, 90) : $negro;

        foreach ([-1, 1] as $lado) {
            $x = $cx + $lado * (int) ($r * 0.42);
            imagefilledellipse($img, $x, $cy, (int) ($r * 0.30), (int) ($r * ($felino ? 0.34 : 0.30)), $iris);

            if ($felino) {
                imagefilledellipse($img, $x, $cy, (int) ($r * 0.09), (int) ($r * 0.28), $negro);
            }

            imagefilledellipse($img, $x - (int) ($r * 0.05), $cy - (int) ($r * 0.06), (int) ($r * 0.09), (int) ($r * 0.09), $brillo);
        }
    }

    private function gorrito(GdImage $img, int $x, int $y, int $r): void
    {
        imagefilledpolygon($img, [
            $x - $r, $y + $r,
            $x + $r, $y + $r,
            $x + (int) ($r * 0.2), $y - (int) ($r * 1.6),
        ], $this->color($img, 92, 176, 220));

        imagefilledellipse($img, $x + (int) ($r * 0.2), $y - (int) ($r * 1.6), (int) ($r * 0.5), (int) ($r * 0.5), $this->color($img, 250, 196, 72));
    }

    private function sello(GdImage $img, int $x, int $y, int $r): void
    {
        $tinta = imagecolorallocatealpha($img, 150, 40, 60, 60);

        if ($tinta === false) {
            throw new RuntimeException('GD no pudo reservar el color del sello.');
        }

        imagesetthickness($img, (int) ($r * 0.08));
        imageellipse($img, $x, $y, $r * 2, $r * 2, $tinta);
        imageellipse($img, $x, $y, (int) ($r * 1.5), (int) ($r * 1.5), $tinta);
        imagesetthickness($img, 1);
        $this->texto($img, 'MV', $x - (int) ($r * 0.42), $y + (int) ($r * 0.22), (int) ($r * 0.55), $tinta, true);
    }

    private function texto(GdImage $img, string $texto, int $x, int $y, int $tamanio, int $color, bool $negrita = false): void
    {
        imagettftext($img, $tamanio * 0.75, 0, $x, $y, $color, $this->fuente($negrita), $texto);
    }

    /**
     * DejaVu viene con dompdf, que es dependencia de producción (el PDF de la
     * historia clínica): está en el server sin instalar nada.
     */
    private function fuente(bool $negrita): string
    {
        $ruta = base_path('vendor/dompdf/dompdf/lib/fonts/'.($negrita ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));

        if (! is_file($ruta)) {
            throw new RuntimeException("No está la fuente {$ruta}.");
        }

        return $ruta;
    }

    private function color(GdImage $img, int $r, int $g, int $b): int
    {
        $color = imagecolorallocate($img, $this->canal($r), $this->canal($g), $this->canal($b));

        if ($color === false) {
            throw new RuntimeException('GD no pudo reservar un color.');
        }

        return $color;
    }

    /** @return int<0, 255> */
    private function canal(int $valor): int
    {
        return max(0, min(255, $valor));
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     * @return array{0: int, 1: int, 2: int}
     */
    private function tono(array $rgb, float $factor): array
    {
        return [
            (int) min(255, $rgb[0] * $factor),
            (int) min(255, $rgb[1] * $factor),
            (int) min(255, $rgb[2] * $factor),
        ];
    }

    private function guardar(GdImage $img, int $ancho, int $alto, string $formato): string
    {
        $final = $this->lienzo(intdiv($ancho, self::ESCALA), intdiv($alto, self::ESCALA));
        imagecopyresampled($final, $img, 0, 0, 0, 0, intdiv($ancho, self::ESCALA), intdiv($alto, self::ESCALA), $ancho, $alto);

        $ruta = tempnam(sys_get_temp_dir(), 'huella-demo-');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal.');
        }

        $this->temporales[] = $ruta;

        $ok = $formato === 'jpg' ? imagejpeg($final, $ruta, 86) : imagepng($final, $ruta, 6);

        if (! $ok) {
            throw new RuntimeException('GD no pudo escribir la imagen.');
        }

        return $ruta;
    }
}
