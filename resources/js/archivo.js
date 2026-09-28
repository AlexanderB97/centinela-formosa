// Escáner de archivos por huella: todo pasa en el navegador.
// El archivo nunca se sube: solo se calcula su SHA-256 y se manda ese texto.

export const TAMANO_MAXIMO = 10 * 1024 * 1024;

// Firmas (primeros bytes) de cada formato aceptado, para validar el contenido real y no solo la extensión.
const FIRMAS = {
    pdf: [[0x25, 0x50, 0x44, 0x46]], // %PDF
    png: [[0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]],
    jpeg: [[0xff, 0xd8, 0xff]],
    zip: [[0x50, 0x4b, 0x03, 0x04]], // .docx y .xlsx son ZIP
    ole: [[0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1]], // .doc y .xls
};

const FORMATO_POR_EXTENSION = {
    pdf: 'pdf',
    png: 'png',
    jpg: 'jpeg',
    jpeg: 'jpeg',
    docx: 'zip',
    xlsx: 'zip',
    doc: 'ole',
    xls: 'ole',
};

export const EXTENSIONES = Object.keys(FORMATO_POR_EXTENSION);

function empiezaCon(bytes, firma) {
    return firma.every((byte, i) => bytes[i] === byte);
}

class ErrorDeArchivo extends Error {
    constructor(codigo) {
        super(codigo);
        this.codigo = codigo;
    }
}

/**
 * Valida el archivo (extensión, tamaño y contenido real) y calcula su SHA-256 en el navegador.
 *
 * @param {File} archivo
 * @returns {Promise<string>} La huella en hexadecimal (64 caracteres en minúscula).
 * @throws {ErrorDeArchivo} con código 'sin-soporte' | 'tipo' | 'vacio' | 'tamano' | 'contenido'.
 */
window.calcularHuellaDeArchivo = async function (archivo) {
    // Web Crypto solo existe en contextos seguros (https o localhost).
    if (!window.crypto?.subtle) {
        throw new ErrorDeArchivo('sin-soporte');
    }

    const extension = archivo.name.split('.').pop().toLowerCase();
    const formato = FORMATO_POR_EXTENSION[extension];

    if (!formato) {
        throw new ErrorDeArchivo('tipo');
    }

    if (archivo.size === 0) {
        throw new ErrorDeArchivo('vacio');
    }

    if (archivo.size > TAMANO_MAXIMO) {
        throw new ErrorDeArchivo('tamano');
    }

    const bytes = new Uint8Array(await archivo.arrayBuffer());

    if (!FIRMAS[formato].some((firma) => empiezaCon(bytes, firma))) {
        throw new ErrorDeArchivo('contenido');
    }

    const huella = await window.crypto.subtle.digest('SHA-256', bytes);

    return Array.from(new Uint8Array(huella), (byte) => byte.toString(16).padStart(2, '0')).join('');
};

window.dispatchEvent(new CustomEvent('archivo:listo'));
