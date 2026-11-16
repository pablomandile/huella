import {
    Activity,
    Apple,
    BellRing,
    Building2,
    House,
    PawPrint,
    Pill,
    PillBottle,
    Settings,
    Stethoscope,
    Syringe,
    UserRound,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as alimentosIndex } from '@/routes/catalogos/alimentos';
import { index as medicamentosIndex } from '@/routes/catalogos/medicamentos';
import { index as vacunasIndex } from '@/routes/catalogos/vacunas';
import { index as veterinariasIndex } from '@/routes/catalogos/veterinarias';
import { index as veterinariosIndex } from '@/routes/catalogos/veterinarios';
import { index as mascotasIndex } from '@/routes/mascotas';
import { index as medicacionIndex } from '@/routes/medicacion';
import { edit as editProfile } from '@/routes/profile';
import { index as recordatoriosIndex } from '@/routes/recordatorios';
import { elegir as elegirMascotaSeguimiento } from '@/routes/seguimiento';
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
    /*
     * Peso, alimentación, vacunas, desparasitaciones, celo y baños. Igual que
     * Visitas, pasa antes por la pregunta de qué mascota.
     */
    {
        title: 'Seguimiento',
        href: elegirMascotaSeguimiento(),
        icon: Activity,
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
];

/**
 * Los catálogos, cada uno con su entrada, bajo el subtítulo «Datos
 * recurrentes»: es lo que se carga una vez y se elige después en cada visita,
 * tratamiento o dieta. Antes era una sola entrada «Catálogos» que llevaba a un
 * panel intermedio, un click de más para llegar a cualquiera de los cinco.
 */
export const datosRecurrentes: NavItem[] = [
    {
        title: 'Veterinarias',
        href: veterinariasIndex(),
        icon: Building2,
    },
    {
        title: 'Veterinarios',
        href: veterinariosIndex(),
        icon: UserRound,
    },
    {
        title: 'Medicamentos',
        href: medicamentosIndex(),
        icon: Pill,
    },
    {
        title: 'Vacunas',
        href: vacunasIndex(),
        icon: Syringe,
    },
    {
        title: 'Alimentos',
        href: alimentosIndex(),
        icon: Apple,
    },
];

/**
 * Lo que va en la barra inferior del celular. Se mantiene corto a propósito:
 * más de cinco destinos no entran sin achicar el área táctil por debajo de 44px
 * ni cortar las etiquetas.
 *
 * Los datos recurrentes quedan afuera y no es un olvido: se cargan una vez al
 * principio y después casi nunca. En el celular están en el menú lateral, que
 * abre el botón del encabezado; en escritorio, en la sidebar.
 *
 * Visitas y Seguimiento también quedan afuera, por la misma cuenta de cinco:
 * sumarlas haría siete y habría que achicar el área táctil o cortar las
 * etiquetas. Están en el menú, y el camino corto sigue siendo la ficha de la
 * mascota que estás mirando.
 */
export const destinosBarraInferior: NavItem[] = [
    ...destinosPrincipales.filter(
        (destino) => !['Visitas', 'Seguimiento'].includes(destino.title),
    ),
    {
        title: 'Perfil',
        href: editProfile(),
        icon: Settings,
    },
];
