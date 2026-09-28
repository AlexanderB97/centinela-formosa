import jsQR from 'jsqr';

const LADO_MAXIMO_IMAGEN = 1024;

// En vivo alcanza con un cuadro más chico: decodifica más rápido y gasta menos batería.
const LADO_MAXIMO_VIDEO = 640;

// Unas 10 lecturas por segundo, no una por cada cuadro a 60 fps.
const INTERVALO_LECTURA_MS = 100;

// Si en un minuto no aparece ningún QR, apagamos la cámara para cuidar la batería.
const TIEMPO_LIMITE_MS = 60_000;

/**
 * Dibuja `fuente` achicada en `canvas` y busca un QR con jsQR.
 * Es el único lugar que decodifica: lo usan la subida de imagen y la cámara.
 *
 * @param {CanvasImageSource} fuente
 * @param {number} anchoFuente
 * @param {number} altoFuente
 * @param {{ladoMaximo: number, canvas: HTMLCanvasElement, inversion: 'attemptBoth'|'dontInvert'}} opciones
 * @returns {string|null}
 */
function decodificar(fuente, anchoFuente, altoFuente, { ladoMaximo, canvas, inversion }) {
    const escala = Math.min(1, ladoMaximo / Math.max(anchoFuente, altoFuente));
    const ancho = Math.round(anchoFuente * escala);
    const alto = Math.round(altoFuente * escala);

    if (canvas.width !== ancho || canvas.height !== alto) {
        canvas.width = ancho;
        canvas.height = alto;
    }

    const contexto = canvas.getContext('2d', { willReadFrequently: true });
    contexto.drawImage(fuente, 0, 0, ancho, alto);

    const { data } = contexto.getImageData(0, 0, ancho, alto);

    return jsQR(data, ancho, alto, { inversionAttempts: inversion })?.data || null;
}

/**
 * Decodifica un código QR desde una imagen, 100% en el navegador.
 * La imagen nunca se envía al servidor: solo se devuelve el texto leído.
 *
 * @param {File} archivo
 * @returns {Promise<string|null>} El contenido del QR, o null si no se encontró ninguno.
 */
window.leerQrDeArchivo = async function (archivo) {
    const bitmap = await createImageBitmap(archivo);

    try {
        return decodificar(bitmap, bitmap.width, bitmap.height, {
            ladoMaximo: LADO_MAXIMO_IMAGEN,
            canvas: document.createElement('canvas'),
            inversion: 'attemptBoth',
        });
    } finally {
        bitmap.close();
    }
};

function errorDeCamara(codigo) {
    const error = new Error(codigo);
    error.codigo = codigo;

    return error;
}

/**
 * Traduce los errores de getUserMedia a los códigos que muestra la vista.
 * Incluye los nombres viejos que todavía usan algunos navegadores.
 */
function codigoDeError(error) {
    if (error?.codigo) {
        return error.codigo;
    }

    switch (error?.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
        case 'SecurityError':
            return 'permiso';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return 'sin-camara';
        case 'NotReadableError':
        case 'TrackStartError':
        case 'AbortError':
            return 'ocupada';
        default:
            return 'error';
    }
}

async function abrirCamara() {
    // isSecureContext ya contempla https, localhost y 127.0.0.1.
    if (! window.isSecureContext) {
        throw errorDeCamara('inseguro');
    }

    if (! navigator.mediaDevices?.getUserMedia) {
        throw errorDeCamara('sin-soporte');
    }

    try {
        // Cámara trasera en el celular; "ideal" nunca es obligatorio, así que en una notebook usa la que haya.
        return await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } },
        });
    } catch (error) {
        if (error?.name !== 'OverconstrainedError') {
            throw error;
        }

        // Un solo reintento sin preferencias.
        return navigator.mediaDevices.getUserMedia({ audio: false, video: true });
    }
}

/**
 * Abre la cámara en `video` y busca un QR en vivo. Todo pasa en el navegador:
 * ningún cuadro del video sale del dispositivo, solo el texto que devuelve alDetectar.
 *
 * La cámara se apaga sola al detectar un QR, al agotar el tiempo o ante un error;
 * en cualquier otro caso hay que llamar a detener() (cambio de modo o de pestaña, navegación, etc.).
 *
 * @param {HTMLVideoElement} video
 * @param {{alIniciar?: Function, alDetectar: (texto: string) => void, alError: (error: Error & {codigo: string}) => void, alAgotarTiempo?: Function}} callbacks
 * @returns {{detener: () => void}}
 */
window.escanearQrConCamara = function (video, { alIniciar, alDetectar, alError, alAgotarTiempo }) {
    const canvas = document.createElement('canvas');
    let stream = null;
    let cuadro = null;
    let detenido = false;
    let inicio = 0;
    let ultimaLectura = 0;

    const detener = () => {
        detenido = true;

        if (cuadro !== null) {
            cancelAnimationFrame(cuadro);
            cuadro = null;
        }

        stream?.getTracks().forEach((pista) => pista.stop());
        stream = null;
        video.pause();
        video.srcObject = null;
    };

    const leer = (ahora) => {
        if (detenido) {
            return;
        }

        cuadro = requestAnimationFrame(leer);

        if (ahora - inicio > TIEMPO_LIMITE_MS) {
            detener();
            alAgotarTiempo?.();

            return;
        }

        if (ahora - ultimaLectura < INTERVALO_LECTURA_MS || video.readyState < video.HAVE_ENOUGH_DATA || ! video.videoWidth) {
            return;
        }

        ultimaLectura = ahora;

        // En vivo no probamos la versión invertida (cuesta el doble); la subida de imagen sí la prueba.
        const texto = decodificar(video, video.videoWidth, video.videoHeight, {
            ladoMaximo: LADO_MAXIMO_VIDEO,
            canvas,
            inversion: 'dontInvert',
        });

        if (texto) {
            detener();
            alDetectar(texto);
        }
    };

    (async () => {
        try {
            const abierto = await abrirCamara();

            // Se detuvo mientras el navegador pedía permiso: soltamos la cámara enseguida.
            if (detenido) {
                abierto.getTracks().forEach((pista) => pista.stop());

                return;
            }

            stream = abierto;
            video.srcObject = stream;
            await video.play();

            if (detenido) {
                return;
            }

            inicio = performance.now();
            alIniciar?.();
            cuadro = requestAnimationFrame(leer);
        } catch (error) {
            if (detenido) {
                return;
            }

            detener();
            alError(errorDeCamara(codigoDeError(error)));
        }
    })();

    return { detener };
};

window.dispatchEvent(new CustomEvent('qr:listo'));
