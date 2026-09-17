# Plugin Review Reminder

Otros idiomas:

- [English](README.md)
- [Português do Brasil](README.pt_BR.md)

[![Compatibilidad con OJS](https://img.shields.io/badge/OJS-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![Versión en GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licencia](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Review Reminder añade dos funciones a **OJS 3.5.0.x**:

| Función | Cuándo ocurre | Qué recibe el revisor |
| --- | --- | --- |
| Archivo adjunto de calendario | OJS envía una invitación de revisión (incluida una ronda posterior) o un editor envía un recordatorio manual de revisión | Un archivo `invite.ics` adjunto al correo de OJS |
| Recordatorio semanal | Cada lunes, a través del programador de tareas de OJS | Un correo por revista con la lista de revisiones pendientes del revisor, sus fechas límite y enlaces |

Este README describe la versión para OJS 3.5. Las versiones anteriores pueden enviar un correo separado con el recordatorio de calendario y utilizar otro mecanismo de programación.

## Archivo adjunto de calendario

El plugin adjunta un archivo iCalendar (`.ics`) al correo de invitación o recordatorio manual existente. No envía un correo separado para este archivo adjunto, y el revisor no necesita aceptar la invitación antes de recibirlo.

El evento de calendario contiene:

- El nombre de la revista.
- El título del envío y un enlace a su página de revisión en la descripción del evento.
- Un período que comienza cuando se genera el archivo adjunto y termina en la fecha límite de revisión, a las 23:59:59.

El evento utiliza el **plazo para completar la revisión**, no el plazo para aceptar o rechazar la invitación. Estos detalles se guardan en el archivo adjunto; el plugin no añade una explicación del período de revisión al cuerpo del correo.

Para utilizarlo, abra o importe `invite.ics` en una aplicación de calendario compatible con iCalendar. Importar el evento es opcional y no acepta la invitación ni envía una revisión. Las notificaciones del calendario dependen de la configuración de la aplicación del revisor; el plugin no define una alarma anticipada ni sincroniza los cambios posteriores de plazo con los eventos importados.

### Zona horaria

El plugin utiliza la zona horaria de OJS definida en la sección `[general]`, en la opción `time_zone` del archivo `config.inc.php`. Las aplicaciones de calendario pueden mostrar el evento según su propia configuración de zona horaria.

### Enlaces y acceso del revisor

El evento de calendario incluye un enlace a la página de revisión incluso cuando el acceso del revisor con un clic está desactivado. El revisor puede necesitar iniciar sesión en OJS para abrir esa página.

El acceso con un clic es una configuración de OJS, no un requisito de este plugin. El plugin utiliza la URL de revisión proporcionada por OJS y, como alternativa, una URL normal de la página de revisión. No crea tokens de acceso ni omite la autenticación de OJS. El correo semanal también utiliza enlaces normales a las páginas de revisión, que pueden requerir iniciar sesión.

## Recordatorio semanal

Cada correo semanal incluye los títulos de los envíos, las fechas límite de revisión y los enlaces a las páginas de revisión de **un revisor en una revista**. Los revisores con revisiones pendientes en varias revistas con el plugin activado reciben un correo separado de cada revista. Los revisores sin revisiones pendientes que cumplan los criterios no reciben el correo semanal.

Se incluyen las revisiones para las que ya se ha notificado al revisor, en la ronda más reciente y en la etapa actual de revisión, que no se hayan completado, rechazado o cancelado. El envío debe seguir activo en el flujo editorial. Por lo tanto, una invitación que aún espera la respuesta del revisor puede aparecer en la lista.

La lista incluye tanto las revisiones atrasadas como aquellas cuyos plazos aún no han vencido. El envío es semanal; no se activa un número fijo de días antes del plazo de la invitación o de la revisión. El correo semanal utiliza la plantilla `PENDING_REVIEWS_REMINDER` y no incluye un archivo adjunto de calendario.

## Relación con los recordatorios de OJS

OJS tiene sus propios recordatorios automáticos para las respuestas a invitaciones y las revisiones atrasadas. Su configuración y envío siguen bajo el control de OJS. Este plugin añade el resumen semanal de forma independiente, por lo que un revisor puede recibir tanto un recordatorio automático de OJS como un correo semanal del plugin.

El archivo adjunto de calendario del plugin se aplica a las invitaciones y a los recordatorios **manuales** de revisión. No se añade a los recordatorios automáticos de retraso de OJS.

## Instalación y activación

> [!IMPORTANT]
> La versión de Review Reminder para **OJS 3.5 aún no está disponible en la Galería de plugins**. Instálela manualmente utilizando un paquete de una versión compatible.

1. Visite la [página de versiones](https://github.com/lepidus/reviewReminder/releases) y descargue el paquete `.tar.gz` del plugin para **OJS 3.5**. Compruebe la información de compatibilidad de la versión antes de descargarlo.
2. En el panel de la revista, abra **Sitio web > Plugins > Plugins instalados**, seleccione **Subir un nuevo plugin** y cargue el paquete descargado.
3. Localice **Review Reminder** en **Plugins instalados** y actívelo para la revista.
4. Compruebe que el envío de correos de OJS funciona y que su programador de tareas está en funcionamiento si desea recibir los recordatorios semanales.

En una instalación con varias revistas, active el plugin por separado en cada revista que lo necesite. La activación del plugin habilita ambas funciones; no dispone de opciones separadas para habilitar solo los archivos adjuntos de calendario o solo los correos semanales.

## Configure el acceso del revisor con un clic

Para que los revisores puedan acceder a sus revisiones asignadas mediante un enlace seguro en el correo de invitación de OJS, vaya a **Flujo de trabajo > Revisión > Configuración** y habilite el **Acceso del revisor con un clic**, si aún no está habilitado.

Esta configuración es opcional. El plugin puede adjuntar eventos de calendario y enviar recordatorios semanales sin ella. Habilitarla no convierte todos los enlaces de los archivos adjuntos de calendario o de los recordatorios semanales en enlaces de acceso con un clic; los enlaces normales a las páginas de revisión pueden seguir requiriendo iniciar sesión.

![Tutorial sobre cómo habilitar el acceso del revisor con un clic](https://i.imgur.com/cHjoXsI.gif)

## ¿Qué ocurre si se desactiva el plugin?

Desactivar Review Reminder para una revista detiene la inclusión de archivos adjuntos de calendario en los próximos correos de invitación y recordatorio manual, y excluye a esa revista de los próximos resúmenes semanales.

OJS sigue gestionando las asignaciones de revisión, los plazos, las invitaciones, los recordatorios manuales y sus propios recordatorios automáticos configurados. Desactivar el plugin no elimina envíos ni revisiones, no cambia los plazos ni elimina los eventos que los revisores ya hayan importado a sus calendarios.

## Créditos

Este plugin fue patrocinado por la [South African Medical Association](http://samedical.org/).

Desarrollado por [Lepidus Tecnologia](https://lepidus.com.br/).

## Licencia

__Este plugin está licenciado bajo la GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
