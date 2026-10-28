# Plugin Review Reminder

Otros idiomas: [English](README.md) · [Português do Brasil](README.pt_BR.md)

[![Compatibilidad con OJS](https://img.shields.io/badge/OJS-3.5.0--4%2B-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![Versión en GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licencia](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Este README describe la versión para **OJS 3.5.0-4 o posterior**. El plugin añade dos funciones para los revisores:

| Función | Cuándo | Qué recibe el revisor |
| --- | --- | --- |
| Archivo adjunto de calendario | Invitación de revisión (incluida una ronda posterior) o recordatorio manual enviado por un editor | Un archivo `invite.ics` adjunto al propio correo de OJS |
| Recordatorio semanal | Una vez por semana, desde el lunes 00:00 | Un correo por revista con las revisiones pendientes |

Ambas funciones se habilitan, por revista, al activar el plugin. El recordatorio semanal puede desactivarse en **Flujo de trabajo > Revisión > Configuración**, debajo de los recordatorios para la revisión, sin afectar el adjunto de calendario. La opción aparece mientras el plugin está activado. El día y la hora del recordatorio semanal no se pueden cambiar.

## Recordatorio semanal

Ejemplo del correo, enviado en el idioma principal de la revista y con el contacto principal de la revista como remitente:

> **Asunto:** Recordatorio de Revisiones Pendientes - Revista de Ejemplo
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
- La revista puede editar y traducir el correo en **Flujo de trabajo > Correos electrónicos**, plantilla *Recordatorio de Revisiones Pendientes*. La variable `{$submissionsList}` contiene la lista de revisiones pendientes.
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

El archivo `invite.ics` contiene un evento titulado "Plazo de revisión: <título del envío> (<nombre de la revista>)" y, en la descripción, el enlace a la página de revisión. El evento ocupa todo el día del **plazo para completar la revisión**. El enlace pide al revisor iniciar sesión incluso con el **Acceso del revisor con un clic** habilitado, porque los eventos de calendario suelen sincronizarse y compartirse.

Importar el evento es opcional y no acepta la invitación. El plugin no define alarmas y no envía nada cuando cambia el plazo. Todos los adjuntos de una revisión describen el mismo evento, así que importar uno posterior, como el de un recordatorio manual, actualiza el evento existente en las aplicaciones de calendario que lo permiten. El adjunto no se añade a los recordatorios automáticos de retraso de OJS.

## Instalación

Instale **Review Reminder** desde la Galería de plugins en **Sitio web > Plugins**, o descargue el paquete `.tar.gz` para OJS 3.5 en la [página de versiones](https://github.com/lepidus/reviewReminder/releases) y cárguelo con **Subir un nuevo plugin**. Después, actívelo en cada revista que lo necesite y compruebe que OJS envía correos y que el programador está configurado.

El plugin requiere OJS 3.5.0-4 o posterior. Las versiones 3.5.0 anteriores no tienen el hook que el plugin usa para adjuntar la invitación de calendario, y los correos salen sin ella.

Al desactivar el plugin en una revista, los próximos correos dejan de llevar el adjunto y la revista sale de los próximos recordatorios semanales. Las revisiones, los plazos y los eventos ya importados no se modifican.

## Créditos

Patrocinado por la [South African Medical Association](http://samedical.org/). Desarrollado por [Lepidus Tecnologia](https://lepidus.com.br/).

## Licencia

__Este plugin está licenciado bajo la GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
