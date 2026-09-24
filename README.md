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

**En una frase:** Centinela Formosa es un detector de phishing público y anónimo: pegás un texto, un link, o subís una foto/QR, y en segundos recibís un veredicto en semáforo con una explicación en lenguaje simple.

**Usuario objetivo:** cualquier persona que reciba un mensaje, link o código QR sospechoso, sin conocimientos técnicos y sin ganas de crear una cuenta para algo que quiere resolver en el momento.

**¿Qué hace la solución?**

- Analiza texto/mensaje, link, o una foto/QR (decodificado en el propio navegador), sin necesidad de cuenta.
- Calcula un nivel de riesgo determinístico (🟢 seguro · 🟡 dudoso · 🔴 riesgo) combinando reglas propias, casos ya confirmados por el equipo y consulta a VirusTotal.
- Genera una explicación en lenguaje simple con IA (Gemini API, capa gratuita) sobre por qué el contenido es o no es sospechoso — con una plantilla de respaldo fija si la IA no responde a tiempo.
- Permite reportar un mensaje sospechoso de forma anónima para que el equipo lo revise.
- Panel interno (`/staff/login`, solo equipo) para moderar reportes: confirmar o descartar, retroalimentando futuros análisis.
- Dashboard público de impacto: reportes comunitarios y casos confirmados, sin exponer datos sensibles.

**¿Qué queda fuera de alcance (por ahora)?**

- Cuentas de usuario público o historial personal de análisis.
- Extensión de navegador e integración con WhatsApp Business API.
- Analizar archivos ejecutables u otros formatos de malware (el foco es específicamente phishing vía texto/link/imagen).

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
| Frontend | Livewire + Livewire Volt | Livewire 4.4.6 · Volt 1.11.2 |
| Autenticación | Laravel Breeze (stack Livewire Volt) + Fortify |  |
| Estilos | Tailwind CSS | v4 |
| Build tool | Vite | 8 |
| Base de datos | MariaDB | 11.8.6 |
| Gestor de dependencias | Composer + npm | Composer 2.10.2 |
| Runtime JS | Node.js | v22.23.3 (npm 10.9.9) |
| Testing | Pest | ^5.2 (+ pest-plugin-laravel ^5.0) |
| Servicios externos | VirusTotal API, Gemini API (capa gratuita) |  |
| Lectura de QR | jsQR (client-side) |  |
| CI |  |  |
| Control de versiones | Git | 4 ramas: `main`, `backend`, `frontend`, `testing` |

<!--
Ya confirmado: PHP 8.4.25, Laravel 13.33.0, Livewire/Volt, Breeze+Fortify, Tailwind, Vite, MariaDB, Composer 2.10.2, Node v22.23.3, npm 10.9.9, Pest ^5.2.
Todavía falta saber:
  - Si van a configurar algún CI (GitHub Actions, etc.) o queda manual por ahora
  - Versión del plan/API key de VirusTotal (gratuita vs paga)
  - Modelo exacto de Gemini a usar (recomendado: Gemini 2.5 Flash o Flash-Lite, capa gratuita)
  - Versión de la librería jsQR que instalen
-->

<!-- Confirmar si el stack final coincide con este (es el mismo que venimos usando en el equipo) -->

## 🎨 Diseño del sistema

1. **RBAC / Roles** — Sitio público sin ningún tipo de cuenta ni registro. Un único punto de acceso interno oculto (`/staff/login`) con dos roles: `moderador` (revisa y confirma/descarta reportes) y `admin`.

2. **Diagrama ER** — Separa datos públicos (`analisis`, `reportes`) de datos internos de staff (`usuarios_staff`, `casos_confirmados`). `analisis` admite tipo `texto`, `link` o `qr`, y separa `razones` (señales determinísticas) de `explicacion` (texto generado por IA o de respaldo).

3. **User flow:**
   - Visitante pega texto/link o sube foto/QR → sistema evalúa (reglas + casos confirmados + VirusTotal) → visitante ve el veredicto con explicación de IA → puede reportar (opcional, anónimo) → moderador revisa la cola en el panel interno → confirma o descarta → un caso confirmado retroalimenta futuros análisis.

4. **Wireframes** — Sitio público (inicio, resultado, reportar) y panel interno de staff (login, cola de reportes pendientes).

5. **Arquitectura en 3 capas:**
   - Frontend: navegador del visitante — acepta texto, link o QR/foto (decodificación de QR 100% client-side).
   - Backend: rutas públicas + rutas internas de staff; `RiskAnalyzer` calcula el nivel (reglas + VirusTotal) y delega solo la redacción de la explicación a la API de Gemini — la IA nunca decide el color del semáforo.
   - Datos: MariaDB, con fallback ante fallos/timeouts de VirusTotal y de la API de Gemini (misma estrategia de resiliencia para ambos servicios externos).

## 🚀 Instalación

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

| Rol | Email | Password |
|---|---|---|
| admin |  |  |
| moderador |  |  |

## 👥 Organización del trabajo en equipo

### Ramas de Git

Repo con 4 ramas: `main`, `testing`, `backend`, `frontend`.

Flujo de trabajo:

1. Cada feature se desarrolla en una rama propia a partir de `backend` o `frontend` según corresponda.
2. Se abre un PR hacia `backend` o `frontend` para revisión del equipo.
3. Una vez aprobado y mergeado, se integra a `testing` para validar que todo funcione en conjunto.
4. `testing` es la rama estable de referencia durante el hackathon; `main` se actualiza solo con versiones confirmadas para la entrega/demo.

### Épicas (Milestones de GitHub)

| # | Épica | Prioridad |
|---|---|---|
| 1 | Analizador de riesgo (texto/link/QR + IA) | Alta |
| 2 | Reportes y moderación interna | Alta |
| 3 | Dashboard público de impacto | Media |

### Historias de usuario

| HU | Descripción | Prioridad | Estado |
|---|---|---|---|
| HU1.1 | Login interno de staff (`/staff/login`) con roles | Alta |  |
| HU2.1 | Analizador de riesgo con texto, link, foto/QR y explicación por IA | Alta |  |
| HU2.2 | Reportar mensaje sospechoso (anónimo) | Alta |  |
| HU2.3 | Panel de staff — revisar, confirmar o descartar reportes | Alta |  |
| HU3.1 | Dashboard público de estadísticas de impacto | Media |  |

## 🧪 Testing

```bash
php artisan test
```

**Resultado actual: 34/34 tests pasando.**

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
- [x] Lectura de QR (client-side) como método de entrada adicional
- [x] Explicación del veredicto generada por IA, con fallback ante fallos
- [ ] Banco de ejemplos reales de phishing en Formosa/Argentina
- [ ] Quiz educativo para reconocer phishing
- [ ] Dashboard público de estadísticas de impacto (en curso)

## 📄 Licencia

Este proyecto fue desarrollado con fines educativos para Formosa Hack 2026.
