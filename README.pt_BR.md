# Plugin Review Reminder

Outros idiomas: [English](README.md) · [Español](README.es.md)

[![Compatibilidade com OJS](https://img.shields.io/badge/OJS-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![Versão no GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licença](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Este README descreve a versão para **OJS 3.5.0.x**. O plugin adiciona dois recursos para os avaliadores:

| Recurso | Quando | O que o avaliador recebe |
| --- | --- | --- |
| Anexo de calendário | Convite para avaliação (inclusive em rodada posterior) ou lembrete manual enviado pelo editor | O arquivo `invite.ics` anexado ao próprio email do OJS |
| Lembrete semanal | Uma vez por semana, a partir de segunda-feira 00:00 | Um email por revista com as avaliações pendentes |

Os dois recursos são ativados, por revista, ao ativar o plugin. O lembrete semanal pode ser desligado nas **Configurações** do plugin, em **Website > Plugins > Plugins instalados**, sem afetar o anexo de calendário. O dia e o horário do lembrete semanal não podem ser alterados.

## Lembrete semanal

Exemplo do email, enviado no idioma principal da revista e tendo o contato principal da revista como remetente:

> **Assunto:** Lembrete de Avaliações Pendentes
>
> Prezado(a) Maria Silva,
>
> Os manuscritos abaixo aguardam a sua moderação.
>
> [Título da submissão A](#) - Data limite: 2026-11-25<br>
> [Título da submissão B](#) - Data limite: 2026-12-02
>
> Este é um email automático. Caso você já tenha enviado a sua avaliação para estes manuscritos, por favor desconsidere este email.

- Cada título leva à página de avaliação, que pede login ao avaliador. O email não tem anexo de calendário.
- O texto do email vem do plugin e não aparece nos modelos de email da revista, por isso não pode ser editado ali.
- Entram as avaliações da rodada mais recente, em submissões ativas no fluxo editorial, para as quais o avaliador já foi notificado e que não foram concluídas, recusadas ou canceladas. Isso inclui convites ainda sem resposta e avaliações atrasadas.
- Avaliadores sem pendências e contas desativadas não recebem o email. Quem tem pendências em várias revistas recebe um email de cada revista.
- Cada envio é registrado no *Histórico de atividades* da submissão e aparece no *Histórico* da avaliação, em um bloco separado dos lembretes do OJS. A data de lembrete da avaliação não é alterada.

O lembrete semanal é independente dos lembretes automáticos do OJS, que continuam funcionando conforme configurados. Um avaliador pode receber os dois.

### Agendamento

O envio depende do agendador de tarefas do OJS 3.5. Cada revista recebe o lembrete uma vez por semana, na primeira execução do agendador a partir de segunda-feira 00:00, no fuso definido em `time_zone` no `config.inc.php`. Configure no servidor a tarefa cron recomendada pelo OJS:

```
* * * * * cd /caminho/do/ojs && php lib/pkp/tools/scheduler.php run >> /dev/null 2>&1
```

Sem o cron, o OJS usa o executor integrado (`task_runner` em `config.inc.php`), que só roda ao final de um acesso ao site. Nesse caso, o lembrete é enviado no primeiro acesso a partir de segunda-feira, então, em sites com poucas visitas, ele pode chegar mais tarde na semana. Depois que o plugin é ativado ou atualizado, o primeiro lembrete sai a partir da segunda-feira seguinte.

## Anexo de calendário

O arquivo `invite.ics` contém um evento com o título "Prazo de avaliação: <título da submissão> (<nome da revista>)" e, na descrição, o link para a página de avaliação. O evento ocupa o dia inteiro do **prazo de conclusão da avaliação**. O link pede login ao avaliador mesmo com o **Acesso do avaliador com um clique** habilitado, porque eventos de calendário costumam ser sincronizados e compartilhados.

Importar o evento é opcional e não aceita o convite. O plugin não define alarmes e não envia nada quando o prazo muda. Todos os anexos de uma avaliação descrevem o mesmo evento, então importar um anexo posterior, como o de um lembrete manual, atualiza o evento existente nos aplicativos de calendário que oferecem isso. O anexo não é adicionado aos lembretes automáticos de atraso do OJS.

## Instalação

> [!IMPORTANT]
> A versão para OJS 3.5 ainda não está na Galeria de Plugins.

1. Baixe o pacote `.tar.gz` para OJS 3.5 na [página de versões](https://github.com/lepidus/reviewReminder/releases).
2. Em **Website > Plugins > Plugins instalados**, use **Enviar um novo plugin** e envie o pacote.
3. Ative o **Review Reminder** em cada revista que precisar dele.
4. Confirme que o OJS envia emails e que o agendador está configurado.

Ao desativar o plugin em uma revista, os próximos emails deixam de levar o anexo e a revista sai dos próximos lembretes semanais. Avaliações, prazos e eventos já importados não são alterados.

## Créditos

Patrocinado pela [South African Medical Association](http://samedical.org/). Desenvolvido pela [Lepidus Tecnologia](https://lepidus.com.br/).

## Licença

__Este plugin é licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
