# Plugin Review Reminder

Otros idiomas: [English](README.md) · [Português do Brasil](README.pt_BR.md)

[![Compatibilidad con OJS](https://img.shields.io/badge/OJS-3.4.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_4_0)
[![Versión en GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licencia](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Este README describe la versión para **OJS 3.4.0.x**. El plugin envía dos correos a los revisores:

| Correo | Cuándo | Contenido |
| --- | --- | --- |
| Recordatorio de revisión | Cuando el revisor acepta una solicitud de revisión | Plazo de la revisión, enlace a la revisión y un archivo de calendario `invite.ics` |
| Recordatorio semanal | Lunes, a través del plugin Acron | Un correo por revista con las revisiones pendientes |

Ambos correos se habilitan, por revista, al activar el plugin. El recordatorio semanal puede desactivarse en los **Ajustes** del plugin, en **Sitio web > Plugins > Plugins instalados**, sin afectar el recordatorio enviado al aceptar. El día del recordatorio semanal no se puede cambiar.

Ambos correos tienen como remitente al contacto principal de la revista. El recordatorio enviado al aceptar usa el idioma en que el revisor está usando OJS, y el recordatorio semanal usa el idioma principal de la revista. Los textos vienen del plugin y no aparecen en las plantillas de correo de la revista, por lo que no se pueden editar allí. Cada envío se registra en el *Registro de actividad* del envío y aparece en el *Historial* de la revisión, en un bloque separado de los recordatorios de OJS.

## Recordatorio de revisión

Ejemplo del correo enviado cuando el revisor acepta la solicitud:

> **Asunto:** Recordatorio de Revisión
>
> Estimado/a Maria Silva,
>
> Tiene una revisión pendiente en la revista Revista Ejemplo, para el artículo Título del envío.
>
> La fecha límite para la revisión es el 2026-11-25. Puede continuar con la revisión a través de este enlace: [Enlace para la revisión](#)
>
> También puede utilizar el recordatorio adjunto para añadir este evento al calendario que prefiera.

El archivo `invite.ics` contiene un evento titulado "Plazo de revisión: <título del envío> (<nombre de la revista>)", que ocupa todo el día del **plazo para completar la revisión**. La descripción del evento enlaza a la revisión. Cuando el **Acceso del revisor con un clic** está habilitado, ese enlace abre la revisión sin iniciar sesión. El plugin no define alarmas ni actualiza el evento si el plazo cambia.

## Recordatorio semanal

Ejemplo del correo semanal:

> **Asunto:** Recordatorio de Revisiones Pendientes
>
> Estimado/a Maria Silva,
>
> Los manuscritos abajo están pendientes de su revisión.
>
> [Título del envío A](#) - Fecha límite: 2026-11-25<br>
> [Título del envío B](#) - Fecha límite: 2026-12-02
>
> Este es un correo electrónico automático. Si ya ha enviado su revisión para estos manuscritos, por favor ignore este correo.

- Cada título enlaza a la página de revisión, que puede requerir iniciar sesión. El correo no lleva adjunto de calendario.
- Incluye las revisiones de la ronda más reciente, en envíos activos en el flujo editorial, para las que ya se notificó al revisor y que no se completaron, rechazaron ni cancelaron. Esto incluye invitaciones aún sin respuesta y revisiones atrasadas.
- Los revisores sin pendientes y las cuentas desactivadas no reciben el correo. Quien tiene pendientes en varias revistas recibe un correo de cada revista.

El recordatorio semanal es independiente de los recordatorios automáticos de OJS, que siguen funcionando según su configuración. Un revisor puede recibir ambos.

### Programación

La tarea semanal se registra a través del plugin **Acron**, que debe estar activado en **Sitio web > Plugins > Plugins instalados**. Acron ejecuta las tareas durante los accesos al sitio: el correo se envía en el primer acceso del lunes, en la zona horaria definida en `time_zone` del `config.inc.php`. Si pasa una semana sin que la tarea se ejecute, se ejecuta en el siguiente acceso, sea cual sea el día. El primer envío ocurre en el primer acceso después de activar el plugin, sea cual sea el día.

Si el servidor ejecuta las tareas programadas por cron en lugar de Acron, incluya explícitamente el archivo de tareas del plugin, por ejemplo:

```
0 * * * * cd /ruta/de/ojs && php tools/runScheduledTasks.php plugins/generic/reviewReminder/scheduledTasks.xml
```

## Instalación

Instale **Review Reminder** desde la Galería de plugins en **Sitio web > Plugins**, o descargue el paquete `.tar.gz` para OJS 3.4 en la [página de versiones](https://github.com/lepidus/reviewReminder/releases) y cárguelo con **Subir un nuevo plugin**. Después, actívelo en cada revista que lo necesite y compruebe que OJS envía correos.

Opcionalmente, habilite el **Acceso del revisor con un clic** en **Flujo de trabajo > Revisión > Configuración**.

![Cómo habilitar el acceso del revisor con un clic](https://i.imgur.com/cHjoXsI.gif)

Al desactivar el plugin en una revista, ambos correos dejan de enviarse para ella. Las revisiones, los plazos y los eventos ya importados no se modifican.

## Créditos

Patrocinado por la [South African Medical Association](http://samedical.org/). Desarrollado por [Lepidus Tecnologia](https://lepidus.com.br/).

## Licencia

__Este plugin está licenciado bajo la GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
