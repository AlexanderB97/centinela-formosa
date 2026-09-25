import jsQR from 'jsqr';

const LADO_MAXIMO = 1024;

/**
 * Decodifica un código QR desde una imagen, 100% en el navegador.
 * La imagen nunca se envía al servidor: solo se devuelve el texto leído.
 *
 * @param {File} archivo
 * @returns {Promise<string|null>} El contenido del QR, o null si no se encontró ninguno.
 */
window.leerQrDeArchivo = async function (archivo) {
    const bitmap = await createImageBitmap(archivo);
    const escala = Math.min(1, LADO_MAXIMO / Math.max(bitmap.width, bitmap.height));
    const ancho = Math.round(bitmap.width * escala);
    const alto = Math.round(bitmap.height * escala);

    const canvas = document.createElement('canvas');
    canvas.width = ancho;
    canvas.height = alto;

    const contexto = canvas.getContext('2d', { willReadFrequently: true });
    contexto.drawImage(bitmap, 0, 0, ancho, alto);
    bitmap.close();

    const { data } = contexto.getImageData(0, 0, ancho, alto);
    const codigo = jsQR(data, ancho, alto, { inversionAttempts: 'attemptBoth' });

    return codigo?.data || null;
};
