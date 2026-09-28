# Centinela Formosa

> Proyecto desarrollado para **Formosa Hack 2026**, hackathon "ultra" de 24 horas organizada por el Instituto Politécnico Formosa "Dr. Alberto Marcelo Zorrilla".

[![Estado](https://img.shields.io/badge/estado-en%20desarrollo-yellow)]()
[![Licencia](https://img.shields.io/badge/licencia-MIT-blue)]()

## 📋 Tabla de contenidos

- [Problemática](#-problemática)
- [Solución propuesta](#-solución-propuesta)
- [Equipo](#-equipo)
- [Stack tecnológico](#-stack-tecnológico)
- [Diseño del sistema](#-diseño-del-sistema)
- [Instalación](#-instalación)
- [Requisitos del servidor](#-requisitos-del-servidor)
- [Organización del trabajo en equipo](#-organización-del-trabajo-en-equipo)
- [Testing](#-testing)
- [Demo](#-demo)
- [Impacto esperado](#-impacto-esperado)
- [Roadmap / Próximos pasos](#-roadmap--próximos-pasos)
- [Licencia](#-licencia)

## 🎯 Problemática

**Área temática:** Seguridad

**Desafío:** Reconocer riesgos y estafas digitales

**Descripción de la problemática**

Las personas interactúan diariamente con mensajes, sitios web, redes sociales y otros servicios digitales
donde pueden encontrarse con intentos de fraude, suplantaciones de identidad, información engañosa u
otras situaciones de riesgo. Reconocer estas situaciones a tiempo continúa siendo un desafío y
puede afectar la seguridad de las personas y de su información.

El phishing (mensajes, links o imágenes que simulan ser de una entidad confiable — bancos, organismos públicos, servicios conocidos — para robar datos o dinero) es uno de los engaños digitales más frecuentes y de menor barrera técnica para detectar a tiempo, si la persona sabe qué mirar. La mayoría de las víctimas no tiene forma rápida y confiable de chequear un mensaje sospechoso antes de actuar sobre él.

## 💡 Solución propuesta

**En una frase:** Centinela Formosa es un detector de phishing público y anónimo: pegás un texto, un link, escaneás un QR o elegís un archivo, y en segundos recibís un veredicto en semáforo con una explicación en lenguaje simple.

**Usuario objetivo:** cualquier persona que reciba un mensaje, link, código QR o archivo sospechoso, sin conocimientos técnicos y sin ganas de crear una cuenta para algo que quiere resolver en el momento.

**¿Qué hace la solución?**

- Analiza texto/mensaje, link o código QR, sin necesidad de cuenta. El QR se lee con la cámara en vivo o desde una foto, y en los dos casos se decodifica en el propio navegador: al servidor solo llega el texto.
- 🗂️ Escáner de archivos por huella: PDF, Word, Excel e imágenes de hasta 10 MB. Calculamos la huella SHA-256 **en tu dispositivo, sin subir el archivo a ningún lado**, y consultamos solo esa huella en VirusTotal. Si VirusTotal no la conoce, te lo decimos, porque eso no significa que sea seguro.
- Calcula un nivel de riesgo determinístico (🟢 seguro · 🟡 dudoso · 🔴 riesgo) combinando reglas propias, casos ya confirmados por el equipo y consulta a VirusTotal.
- Genera una explicación en lenguaje simple con IA (Gemini API, capa gratuita) sobre por qué el contenido es o no es sospechoso — con una plantilla de respaldo fija si la IA no responde a tiempo.
- Permite reportar un mensaje sospechoso de forma anónima para que el equipo lo revise, con contexto opcional (departamento, barrio en Formosa Capital y por dónde llegó) que nunca identifica a quien reporta.
- Panel interno (`/staff/login`, solo equipo) para moderar reportes: confirmar o descartar, retroalimentando futuros análisis.
- Dashboard público de impacto: reportes comunitarios y casos confirmados, sin exponer datos sensibles.
- Ranking público de lo más consultado (`/rankings`): contenidos sospechosos analizados al menos 3 veces, con emails, teléfonos y números largos ocultos.
- Mapa público de barrios afectados de Formosa Capital (`/mapa`): solo muestra un barrio cuando tiene al menos 5 reportes, para que ningún punto identifique a una persona.
- 📰 Noticias: cualquier persona del staff escribe noticias (título, texto plano y una imagen de portada opcional), que quedan como borrador hasta que se publican. Las publicadas se ven en `/noticias` y las últimas 3 en la portada.

**¿Qué queda fuera de alcance (por ahora)?**

- Cuentas de usuario público o historial personal de análisis.
- Extensión de navegador e integración con WhatsApp Business API.
- Analizar el contenido de archivos o ejecutables (el foco es específicamente phishing vía texto/link/imagen). Los archivos solo se consultan por su huella SHA-256, sin subirlos ni abrirlos.

## 👥 Equipo

| Nombre | Rol | GitHub |
|---|---|---|
| Benítez José Alexander | Backend | [@AlexanderB97](https://github.com/AlexanderB97) |
| Arce Ángel Gabriel | Frontend |  |
| Sixto Servián | Backend |  |
| Fabián Castillo | QA / Testing |  |

## 🛠️ Stack tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Lenguaje | PHP | 8.4.25 |
| Framework | Laravel | 13.33.0 |
| Frontend | Livewire (componentes de un solo archivo) | Livewire 4.4.6 |
| Autenticación | Starter kit oficial de Laravel (Livewire) + Fortify |  |
| Estilos | Tailwind CSS | v4 |
| Build tool | Vite (vite-plus) | 8 |
| Base de datos | MariaDB | 11.8.6 |
| Gestor de dependencias | Composer + npm | Composer 2.10.2 |
| Runtime JS | Node.js | v22.23.3 (npm 10.9.9) |
| Testing | Pest | ^5.2 (+ pest-plugin-laravel ^5.0) |
| Servicios externos | VirusTotal API, Gemini API (capa gratuita) |  |
| Lectura de QR | jsQR (client-side, foto y cámara en vivo) |  |
| Mapa | Leaflet + OpenStreetMap |  |
| Imágenes de noticias | Extensión GD de PHP (reencodeo a WebP) |  |
| CI | GitHub Actions: Pint, phpstan y Pest (`composer ci:check`) |  |
| Control de versiones | Git | `feature/*` → `integracion` → `main` |

<!--
Ya confirmado: PHP 8.4.25, Laravel 13.33.0, Livewire, starter kit + Fortify, Tailwind, Vite, MariaDB, Composer 2.10.2, Node v22.23.3, npm 10.9.9, Pest ^5.2, CI con GitHub Actions.
Todavía falta saber:
  - Versión del plan/API key de VirusTotal (gratuita vs paga)
  - Modelo exacto de Gemini a usar (recomendado: Gemini 2.5 Flash o Flash-Lite, capa gratuita)
  - Versión de la librería jsQR que instalen
-->

<!-- Confirmar si el stack final coincide con este (es el mismo que venimos usando en el equipo) -->

## 🎨 Diseño del sistema

1. **RBAC / Roles** — Sitio público sin ningún tipo de cuenta ni registro. Un único punto de acceso interno oculto (`/staff/login`) con dos roles: `moderador` (revisa y confirma/descarta reportes, y escribe y publica noticias) y `admin` (lo mismo, más la gestión de cuentas de staff).

2. **Diagrama ER** — Separa datos públicos (`analisis`, `reportes`, `noticias` publicadas) de datos internos de staff (`usuarios_staff`, `casos_confirmados`). `analisis` admite tipo `texto`, `link`, `qr` o `archivo` (en ese caso guarda solo la huella SHA-256, nunca el nombre, la extensión ni el tamaño), y separa `razones` (señales determinísticas) de `explicacion` (texto generado por IA o de respaldo). `reportes` guarda solo el contexto opcional del mensaje (departamento, barrio, medio), nada del visitante. `noticias` guarda título, texto plano, la ruta de la imagen, el estado (borrador/publicada), la fecha de publicación y el autor.

3. **User flow:**
   - Visitante pega texto/link, escanea o sube la foto de un QR, o elige un archivo → sistema evalúa (reglas + casos confirmados + VirusTotal) → visitante ve el veredicto con explicación de IA → puede reportar (opcional, anónimo) → moderador revisa la cola en el panel interno → confirma o descarta → un caso confirmado retroalimenta futuros análisis.

4. **Wireframes** — Sitio público (inicio, resultado, reportar, impacto, rankings, mapa, noticias) y panel interno de staff (login, cola de reportes pendientes, noticias, gestión de staff).

5. **Arquitectura en 3 capas:**
   - Frontend: navegador del visitante — acepta texto, link, QR (foto o cámara) o archivo. La decodificación del QR y la huella del archivo se calculan 100% client-side.
   - Backend: rutas públicas + rutas internas de staff; `RiskAnalyzer` calcula el nivel (reglas + VirusTotal) y delega solo la redacción de la explicación a la API de Gemini — la IA nunca decide el color del semáforo.
   - Datos: MariaDB, con fallback ante fallos/timeouts de VirusTotal y de la API de Gemini (misma estrategia de resiliencia para ambos servicios externos).

## 🚀 Instalación

Antes de empezar, revisá los [requisitos del servidor](#-requisitos-del-servidor): la extensión GD de PHP es obligatoria.

```bash
# Clonar el repositorio
git clone <URL_DEL_REPO>
cd centinela-formosa

# Instalar dependencias
composer install
npm install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Configurar la base de datos y las APIs externas en .env
# DB_CONNECTION=mysql
# DB_HOST=
# DB_DATABASE=
# DB_USERNAME=
# DB_PASSWORD=
# VIRUSTOTAL_API_KEY=
# GEMINI_API_KEY=

# Migrar y sembrar datos de prueba
php artisan migrate --seed

# Compilar assets
npm run build

# Levantar el servidor
php artisan serve
```

### Usuarios de prueba (seeder)

> ⚠️ **Solo para desarrollo.** Estas cuentas las crea `UsuarioStaffSeeder` con una contraseña conocida. No sembrarlas en producción.

| Rol | Email | Password |
|---|---|---|
| admin | `admin@centinela-formosa.test` | `centinela2026` |
| moderador | `moderador@centinela-formosa.test` | `centinela2026` |

## 🖥️ Requisitos del servidor

- **Extensión GD de PHP con soporte WebP (obligatoria).** Toda imagen de portada de una noticia se vuelve a codificar a WebP en el servidor: así se borran los metadatos EXIF (incluida la ubicación GPS de la foto) y cualquier cosa escondida dentro del archivo. En XAMPP se activa descomentando `extension=gd` en `php.ini` y reiniciando Apache. **Hay que activarla también en producción.**
- **HTTPS en producción.** La cámara para leer QR y el cálculo de la huella de archivos solo funcionan en un contexto seguro: `https` o `localhost`. Sin eso, el sitio ofrece subir una foto del QR y avisa por qué la cámara no está disponible.
- **No hace falta `storage:link`.** Las imágenes de noticias se guardan en el disco privado (`storage/app/private/noticias`) y las sirve la ruta `/noticias/{id}/imagen`, que devuelve 404 si la noticia no está publicada.
- **Límite de 2 MB por imagen de noticia**, que entra en el `upload_max_filesize` por defecto de PHP: no hay que cambiarlo.
- **Al elegir dónde desplegar:** las imágenes viven en el disco del servidor. En un hosting con disco efímero (que se borra en cada despliegue o reinicio) se perderían: hay que usar un disco persistente o migrar a un almacenamiento externo.

## 👥 Organización del trabajo en equipo

### Ramas de Git

Flujo de trabajo:

1. Cada feature se desarrolla en una rama propia `feature/*`, creada a partir de `integracion`.
2. Cuando la feature está lista y `composer ci:check` pasa, se mergea a `integracion`, que es donde se valida que todo funcione en conjunto.
3. `integracion` se lleva a `main` con un PR; `main` se actualiza solo con versiones confirmadas para la entrega/demo.

### Épicas (Milestones de GitHub)

| # | Épica | Prioridad |
|---|---|---|
| 1 | Analizador de riesgo (texto/link/QR + IA) | Alta |
| 2 | Reportes y moderación interna | Alta |
| 3 | Dashboard público de impacto | Media |

### Historias de usuario

| HU | Descripción | Prioridad | Estado |
|---|---|---|---|
| HU1.1 | Login interno de staff (`/staff/login`) con roles | Alta | Hecha |
| HU1.2 | Gestión de cuentas de staff (solo admin) | Alta | Hecha |
| HU2.1 | Analizador de riesgo con texto, link, foto/QR y explicación por IA | Alta | Hecha |
| HU2.2 | Reportar mensaje sospechoso (anónimo) | Alta | Hecha |
| HU2.3 | Panel de staff — revisar, confirmar o descartar reportes | Alta | Hecha |
| HU3.1 | Dashboard público de estadísticas de impacto | Media | Hecha |
| — | Ranking público de lo más consultado | Media | Hecha |
| — | Contexto opcional del reporte (departamento, barrio, medio) y mapa de barrios afectados | Media | Hecha |
| — | Escáner de archivos por huella SHA-256 | Media | Hecha |
| — | Sección de noticias administrable por el staff | Media | Hecha |
| — | Lectura de QR con la cámara en vivo | Media | Hecha |

## 🧪 Testing

```bash
# Solo los tests
php artisan test

# Lo mismo que corre el CI: Pint, phpstan y los tests
composer ci:check
```

**Resultado actual: 381/381 tests pasando.**

> Nota para el equipo: si `php artisan test` te tira 404 en casi todos los tests después de tocar `.env` o `phpunit.xml`, corré `php artisan config:clear` — la config queda cacheada y no toma los cambios nuevos hasta limpiarla.

## 🎥 Demo

**Video / GIF:**

**Link al prototipo desplegado:**

**Capturas de pantalla:**

## 📈 Impacto esperado

**¿A quién ayuda?** A cualquier persona en Formosa (y más allá) que reciba un mensaje, link o QR sospechoso y no tenga forma rápida de verificarlo antes de actuar.

**¿Qué problema real resuelve?** Reduce el tiempo entre recibir un intento de phishing y poder confirmar si es real o no, sin depender de conocimientos técnicos ni de crear una cuenta — y construye una base de casos confirmados localmente relevante (con reportes de la propia comunidad).

**¿Cómo se mide el impacto?** Cantidad de análisis realizados, reportes comunitarios recibidos, y casos confirmados por el equipo — visibles en el dashboard público.

## 🗺️ Roadmap / Próximos pasos

- [x] Analizador con texto/link + veredicto en semáforo
- [x] Lectura de QR (client-side) como método de entrada adicional, desde una foto o con la cámara en vivo
- [x] Explicación del veredicto generada por IA, con fallback ante fallos
- [x] Dashboard público de estadísticas de impacto
- [x] Ranking de lo más consultado y mapa de barrios afectados
- [x] Escáner de archivos por huella SHA-256
- [x] Sección de noticias
- [ ] Banco de ejemplos reales de phishing en Formosa/Argentina
- [ ] Quiz educativo para reconocer phishing

## 📄 Licencia

Este proyecto fue desarrollado con fines educativos para Formosa Hack 2026.
