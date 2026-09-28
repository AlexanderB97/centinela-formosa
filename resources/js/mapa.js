import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const RADIO_MINIMO = 10;
const RADIO_MAXIMO = 32;

// Escala roja del proyecto ("riesgo"): de red-300 a red-800.
const COLOR_CLARO = [0xfc, 0xa5, 0xa5];
const COLOR_OSCURO = [0x99, 0x1b, 0x1b];

function color(proporcion) {
    const canales = COLOR_CLARO.map((claro, i) => Math.round(claro + (COLOR_OSCURO[i] - claro) * proporcion));

    return `rgb(${canales.join(', ')})`;
}

function texto(zona) {
    const reportes = zona.total === 1 ? 'reporte' : 'reportes';

    return `Barrio: ${zona.etiqueta} — ${zona.total} ${reportes}`;
}

/**
 * Crea el mapa de barrios afectados de Formosa Capital dentro de `elemento`.
 * El radio crece con la raíz cuadrada del total (el área es proporcional a la cantidad)
 * y el color se oscurece con la intensidad relativa al barrio con más reportes.
 *
 * @param {HTMLElement} elemento
 * @param {Array<{barrio: string, etiqueta: string, lat: number, lng: number, total: number}>} zonas
 * @param {[[number, number], [number, number]]} limites  Encuadre inicial que calcula el backend a partir de todos los barrios.
 * @returns {L.Map}
 */
window.crearMapa = function (elemento, zonas, limites) {
    const mapa = L.map(elemento, { scrollWheelZoom: false });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(mapa);

    mapa.fitBounds(limites);

    const maximo = Math.max(1, ...zonas.map((zona) => zona.total));

    for (const zona of zonas) {
        const proporcion = zona.total / maximo;
        const radio = RADIO_MINIMO + (RADIO_MAXIMO - RADIO_MINIMO) * Math.sqrt(proporcion);

        // Contenido siempre como nodo de texto nuevo, nunca como HTML.
        const contenido = () => document.createTextNode(texto(zona));

        L.circleMarker([zona.lat, zona.lng], {
            radius: radio,
            color: color(1),
            weight: 1,
            fillColor: color(proporcion),
            fillOpacity: 0.75,
        })
            .bindTooltip(contenido)
            .bindPopup(contenido)
            .addTo(mapa);
    }

    return mapa;
};

window.dispatchEvent(new CustomEvent('mapa:listo'));
