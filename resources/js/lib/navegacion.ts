import {
    BellRing,
    BookMarked,
    House,
    PawPrint,
    PillBottle,
    Settings,
    Stethoscope,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as catalogosIndex } from '@/routes/catalogos';
import { index as mascotasIndex } from '@/routes/mascotas';
import { index as medicacionIndex } from '@/routes/medicacion';
import { edit as editProfile } from '@/routes/profile';
import { index as recordatoriosIndex } from '@/routes/recordatorios';
import { elegir as elegirMascotaVisitas } from '@/routes/visitas';
import type { NavItem } from '@/types';

/**
 * Definición única de la navegación de Huella.
 *
 * La usan tanto la sidebar de escritorio como la barra inferior del celular:
 * es el mismo árbol de rutas en los dos, no dos apps distintas. Cada fase suma
 * acá sus destinos (línea de tiempo, salud, seguimiento) y aparecen solos en
 * ambas navegaciones.
 */
export const destinosPrincipales: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: House,
    },
    {
        title: 'Mascotas',
        href: mascotasIndex(),
        icon: PawPrint,
    },
    /*
     * Las visitas también se llegan desde la ficha de cada mascota. Acá el
     * destino es el paso previo que pregunta de quién es la visita: con una sola
     * mascota redirige solo, así que no cuesta un click de más.
     */
    {
        title: 'Visitas',
        href: elegirMascotaVisitas(),
        icon: Stethoscope,
    },
    {
        title: 'Agenda',
        href: recordatoriosIndex(),
        icon: BellRing,
    },
    {
        title: 'Medicación',
        href: medicacionIndex(),
        icon: PillBottle,
    },
    {
        title: 'Catálogos',
        href: catalogosIndex(),
        icon: BookMarked,
    },
];

/**
 * Lo que va en la barra inferior del celular. Se mantiene corto a propósito:
 * más de cinco destinos no entran sin achicar el área táctil por debajo de 44px
 * ni cortar las etiquetas.
 *
 * Catálogos queda afuera y no es un olvido: se cargan una vez al principio y
 * después casi nunca. En el celular se llega por el menú de usuario, que es
 * donde uno busca la configuración; en escritorio está en la sidebar.
 *
 * Visitas también queda afuera, por la misma cuenta de cinco: sumarla haría seis
 * y habría que achicar el área táctil o cortar las etiquetas. Está en el menú, y
 * el camino corto sigue siendo la ficha de la mascota que estás mirando.
 */
export const destinosBarraInferior: NavItem[] = [
    ...destinosPrincipales.filter(
        (destino) => !['Catálogos', 'Visitas'].includes(destino.title),
    ),
    {
        title: 'Perfil',
        href: editProfile(),
        icon: Settings,
    },
];
