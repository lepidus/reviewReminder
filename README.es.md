# Plugin Review Reminder

Otros idiomas: [English](README.md) · [Português do Brasil](README.pt_BR.md)

[![Compatibilidad con OJS](https://img.shields.io/badge/OJS-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![Versión en GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licencia](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Este README describe la versión para **OJS 3.5.0.x**. El plugin añade dos funciones para los revisores:

| Función | Cuándo | Qué recibe el revisor |
| --- | --- | --- |
| Archivo adjunto de calendario | Invitación de revisión (incluida una ronda posterior) o recordatorio manual enviado por un editor | Un archivo `invite.ics` adjunto al propio correo de OJS |
| Recordatorio semanal | Una vez por semana, desde el lunes 00:00 | Un correo por revista con las revisiones pendientes |

Ambas funciones se habilitan, por revista, al activar el plugin. El recordatorio semanal puede desactivarse en los **Ajustes** del plugin, en **Sitio web > Plugins > Plugins instalados**, sin afectar el adjunto de calendario. El día y la hora del recordatorio semanal no se pueden cambiar.

## Recordatorio semanal

Ejemplo del correo, enviado en el idioma principal de la revista y con el contacto principal de la revista como remitente:

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

- Cada título enlaza a la página de revisión, que pide al revisor iniciar sesión. El correo no lleva adjunto de calendario.
- El texto del correo viene del plugin y no aparece en las plantillas de correo de la revista, por lo que no se puede editar allí.
- Incluye las revisiones de la ronda más reciente, en envíos activos en el flujo editorial, para las que ya se notificó al revisor y que no se completaron, rechazaron ni cancelaron. Esto incluye invitaciones aún sin respuesta y revisiones atrasadas.
- Los revisores sin pendientes y las cuentas desactivadas no reciben el correo. Quien tiene pendientes en varias revistas recibe un correo de cada revista.
- Cada envío se registra en el *Registro de actividad* del envío y aparece en el *Historial* de la revisión, en un bloque separado de los recordatorios de OJS. La fecha de recordatorio de la revisión no se modifica.

El recordatorio semanal es independiente de los recordatorios automáticos de OJS, que siguen funcionando según su configuración. Un revisor puede recibir ambos.

### Programación

El envío depende del programador de tareas de OJS 3.5. Cada revista recibe el recordatorio una vez por semana, en la primera ejecución del programador desde el lunes 00:00, en la zona horaria definida en `time_zone` del `config.inc.php`. Configure en el servidor la tarea cron recomendada por OJS:

```
* * * * * cd /ruta/de/ojs && php lib/pkp/tools/scheduler.php run >> /dev/null 2>&1
```

Sin cron, OJS usa el ejecutor integrado (`task_runner` en `config.inc.php`), que solo se ejecuta al final de un acceso al sitio. En ese caso, el recordatorio se envía en el primer acceso desde el lunes, por lo que en sitios con pocas visitas puede llegar más tarde en la semana. Después de activar o actualizar el plugin, el primer recordatorio se envía desde el lunes siguiente.

## Archivo adjunto de calendario

El archivo `invite.ics` contiene un evento con el nombre de la revista, el título del envío y el enlace a la página de revisión. El evento va desde el envío del correo hasta las 23:59:59 del **plazo para completar la revisión**, en la zona horaria de la aplicación de calendario del revisor.

Importar el evento es opcional y no acepta la invitación. El plugin no define alarmas ni actualiza el evento si el plazo cambia. El adjunto no se añade a los recordatorios automáticos de retraso de OJS.

## Instalación

> [!IMPORTANT]
> La versión para OJS 3.5 aún no está en la Galería de plugins.

1. Descargue el paquete `.tar.gz` para OJS 3.5 en la [página de versiones](https://github.com/lepidus/reviewReminder/releases).
2. En **Sitio web > Plugins > Plugins instalados**, use **Subir un nuevo plugin** para cargar el paquete.
3. Active **Review Reminder** en cada revista que lo necesite.
4. Compruebe que OJS envía correos y que el programador está configurado.

Opcionalmente, habilite el **Acceso del revisor con un clic** en **Flujo de trabajo > Revisión > Configuración**. Así, el adjunto usa el mismo enlace de un clic del correo de OJS y abre la revisión sin iniciar sesión. Los enlaces del recordatorio semanal siempre piden al revisor iniciar sesión.

![Cómo habilitar el acceso del revisor con un clic](https://i.imgur.com/cHjoXsI.gif)

Al desactivar el plugin en una revista, los próximos correos dejan de llevar el adjunto y la revista sale de los próximos recordatorios semanales. Las revisiones, los plazos y los eventos ya importados no se modifican.

## Créditos

Patrocinado por la [South African Medical Association](http://samedical.org/). Desarrollado por [Lepidus Tecnologia](https://lepidus.com.br/).

## Licencia

__Este plugin está licenciado bajo la GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
