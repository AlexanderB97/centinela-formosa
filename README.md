# centinela-formosa

🗂️ Escáner de archivos por huella: PDF, Word, Excel e imágenes de hasta 10 MB. Calculamos la huella SHA-256 **en tu dispositivo, sin subir el archivo a ningún lado**, y consultamos solo esa huella en VirusTotal. Si VirusTotal no la conoce, te lo decimos, porque eso no significa que sea seguro.

📰 Noticias: cualquier persona del staff escribe noticias (título, texto plano y una imagen de portada opcional), que quedan como borrador hasta que se publican. Las publicadas se ven en `/noticias` y las últimas 3 en la portada.

## Requisitos del servidor para las noticias

- **Extensión GD de PHP con soporte WebP (obligatoria).** Toda imagen de portada se vuelve a codificar a WebP en el servidor: así se borran los metadatos EXIF (incluida la ubicación GPS de la foto) y cualquier cosa escondida dentro del archivo. En XAMPP se activa descomentando `extension=gd` en `php.ini` y reiniciando Apache. **Hay que activarla también en producción.**
- **No hace falta `storage:link`.** Las imágenes se guardan en el disco privado (`storage/app/private/noticias`) y las sirve la ruta `/noticias/{id}/imagen`, que devuelve 404 si la noticia no está publicada.
- **Límite de 2 MB por imagen**, que entra en el `upload_max_filesize` por defecto de PHP: no hay que cambiarlo.
- **Al elegir dónde desplegar:** las imágenes viven en el disco del servidor. En un hosting con disco efímero (que se borra en cada despliegue o reinicio) se perderían: hay que usar un disco persistente o migrar a un almacenamiento externo.
