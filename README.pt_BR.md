# Plugin Review Reminder

Outros idiomas: [English](README.md) · [Español](README.es.md)

[![Compatibilidade com OJS](https://img.shields.io/badge/OJS-3.4.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_4_0)
[![Versão no GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licença](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

Este README descreve a versão para **OJS 3.4.0.x**. O plugin envia dois emails aos avaliadores:

| Email | Quando | Conteúdo |
| --- | --- | --- |
| Lembrete de avaliação | Quando o avaliador aceita uma solicitação de avaliação | Prazo da avaliação, link para a avaliação e um arquivo de calendário `invite.ics` |
| Lembrete semanal | Segunda-feira, por meio do plugin Acron | Um email por revista com as avaliações pendentes |

Os dois emails são ativados, por revista, ao ativar o plugin. O lembrete semanal pode ser desligado nas **Configurações** do plugin, em **Website > Plugins > Plugins instalados**, sem afetar o lembrete enviado no aceite. O dia do lembrete semanal não pode ser alterado.

Os dois emails têm o contato principal da revista como remetente. O lembrete enviado no aceite usa o idioma em que o avaliador está usando o OJS, e o lembrete semanal usa o idioma principal da revista. A revista pode editar e traduzir o lembrete semanal em **Fluxo de Trabalho > E-mails**, no modelo *Lembrete de Avaliações Pendentes*. O texto do lembrete enviado no aceite vem do plugin e não pode ser editado. Cada envio é registrado no *Histórico de atividades* da submissão e aparece no *Histórico* da avaliação, em um bloco separado dos lembretes do OJS.

## Lembrete de avaliação

Exemplo do email enviado quando o avaliador aceita a solicitação:

> **Assunto:** Lembrete de Avaliação
>
> Prezado(a) Maria Silva,
>
> Você possui uma avaliação pendente na revista Revista Exemplo, para o artigo Título da submissão.
>
> O prazo para avaliação é em 2026-11-25. Você pode prosseguir com a avaliação através deste link: [Link para avaliação](#)
>
> Você também pode utilizar o lembrete em anexo para adicionar esse evento ao calendário de sua preferência.

O arquivo `invite.ics` contém um evento com o título "Prazo de avaliação: <título da submissão> (<nome da revista>)", que ocupa o dia inteiro do **prazo de conclusão da avaliação**. A descrição do evento traz o link para a avaliação. O link pede login ao avaliador mesmo com o **Acesso do avaliador com um clique** habilitado, porque eventos de calendário costumam ser sincronizados e compartilhados. O plugin não define alarmes nem atualiza o evento se o prazo mudar.

## Lembrete semanal

Exemplo do email semanal:

> **Assunto:** Lembrete de Avaliações Pendentes - Revista Exemplo
>
> Prezado(a) Maria Silva,
>
> Os manuscritos abaixo aguardam a sua moderação.
>
> [Título da submissão A](#) - Data limite: 2026-11-25<br>
> [Título da submissão B](#) - Data limite: 2026-12-02
>
> Este é um email automático. Caso você já tenha enviado a sua avaliação para estes manuscritos, por favor desconsidere este email.

- Cada título leva à página de avaliação, que pode exigir login. O email não tem anexo de calendário.
- Entram as avaliações da rodada mais recente, em submissões ativas no fluxo editorial, para as quais o avaliador já foi notificado e que não foram concluídas, recusadas ou canceladas. Isso inclui convites ainda sem resposta e avaliações atrasadas.
- Avaliadores sem pendências e contas desativadas não recebem o email. Quem tem pendências em várias revistas recebe um email de cada revista.

O lembrete semanal é independente dos lembretes automáticos do OJS, que continuam funcionando conforme configurados. Um avaliador pode receber os dois.

### Agendamento

A tarefa semanal é registrada por meio do plugin **Acron**, que precisa estar ativado em **Website > Plugins > Plugins instalados**. O Acron executa tarefas durante os acessos ao site: o email é enviado no primeiro acesso da segunda-feira, no fuso definido em `time_zone` no `config.inc.php`. Se passar uma semana sem que a tarefa rode, ela roda no próximo acesso, em qualquer dia. O primeiro envio acontece no primeiro acesso depois que o plugin é ativado, em qualquer dia.

Se o servidor executa as tarefas agendadas por cron em vez do Acron, inclua explicitamente o arquivo de tarefas do plugin, por exemplo:

```
0 * * * * cd /caminho/do/ojs && php tools/runScheduledTasks.php plugins/generic/reviewReminder/scheduledTasks.xml
```

## Instalação

Instale o **Review Reminder** pela Galeria de Plugins em **Website > Plugins**, ou baixe o pacote `.tar.gz` para OJS 3.4 na [página de versões](https://github.com/lepidus/reviewReminder/releases) e envie-o em **Enviar um novo plugin**. Depois, ative-o em cada revista que precisar dele e confirme que o OJS envia emails.

Ao desativar o plugin em uma revista, os dois emails deixam de ser enviados para ela. Avaliações, prazos e eventos já importados não são alterados.

## Créditos

Patrocinado pela [South African Medical Association](http://samedical.org/). Desenvolvido pela [Lepidus Tecnologia](https://lepidus.com.br/).

## Licença

__Este plugin é licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
