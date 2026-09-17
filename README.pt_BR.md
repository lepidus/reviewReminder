# Plugin Review Reminder

Outros idiomas:

- [English](README.md)
- [Español](README.es.md)

[![Compatibilidade com OJS](https://img.shields.io/badge/OJS-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![Versão no GitHub](https://img.shields.io/github/v/release/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/releases)
[![Licença](https://img.shields.io/github/license/lepidus/reviewReminder)](https://github.com/lepidus/reviewReminder/blob/main/LICENSE)

O Review Reminder adiciona dois recursos ao **OJS 3.5.0.x**:

| Recurso | Quando ocorre | O que o avaliador recebe |
| --- | --- | --- |
| Anexo de calendário | O OJS envia um convite para avaliação (inclusive em uma rodada posterior) ou um editor envia um lembrete manual de avaliação | Um arquivo `invite.ics` anexado ao email do OJS |
| Lembrete semanal | Toda segunda-feira, por meio do agendador de tarefas do OJS | Um email por revista com a lista de avaliações pendentes do avaliador, seus prazos e links |

Este README descreve a versão para OJS 3.5. Versões anteriores podem enviar um email separado com o lembrete de calendário e usar outro mecanismo de agendamento.

## Anexo de calendário

O plugin anexa um arquivo iCalendar (`.ics`) ao email de convite ou lembrete manual já existente. Ele não envia um email separado para esse anexo, e o avaliador não precisa aceitar o convite antes de recebê-lo.

O evento de calendário contém:

- O nome da revista.
- O título da submissão e um link para a página de avaliação na descrição do evento.
- Um período que começa quando o anexo é gerado e termina na data limite da avaliação, às 23:59:59.

O evento usa o **prazo de conclusão da avaliação**, não o prazo para aceitar ou recusar o convite. Esses detalhes ficam no anexo; o plugin não adiciona uma explicação sobre o período de avaliação ao corpo do email.

Para utilizá-lo, abra ou importe o arquivo `invite.ics` em um aplicativo de calendário compatível com iCalendar. A importação do evento é opcional e não aceita o convite nem envia uma avaliação. As notificações do calendário dependem das configurações do aplicativo do avaliador; o plugin não define um alarme antecipado nem sincroniza alterações posteriores de prazo com os eventos importados.

### Fuso horário

O plugin usa o fuso horário do OJS definido na seção `[general]`, na configuração `time_zone` do arquivo `config.inc.php`. Os aplicativos de calendário podem exibir o evento de acordo com suas próprias configurações de fuso horário.

### Links e acesso do avaliador

O evento de calendário inclui um link para a página de avaliação mesmo quando o acesso do avaliador com um clique está desativado. O avaliador pode precisar entrar no OJS para abrir essa página.

O acesso com um clique é uma configuração do OJS, não um requisito deste plugin. O plugin usa a URL de avaliação fornecida pelo OJS e, como alternativa, uma URL comum da página de avaliação. Ele não cria tokens de acesso nem ignora a autenticação do OJS. O email semanal também usa links comuns para as páginas de avaliação, que podem exigir autenticação.

## Lembrete semanal

Cada email semanal lista os títulos das submissões, os prazos de avaliação e os links das páginas de avaliação de **um avaliador em uma revista**. Avaliadores com avaliações pendentes em várias revistas com o plugin ativado recebem um email separado de cada revista. Avaliadores sem avaliações pendentes elegíveis não recebem o email semanal.

São elegíveis as avaliações para as quais o avaliador já foi notificado, na rodada mais recente e na etapa atual de avaliação, que não tenham sido concluídas, recusadas ou canceladas. A submissão deve continuar ativa no fluxo editorial. Portanto, um convite que ainda aguarda a resposta do avaliador pode aparecer na lista.

A lista inclui tanto avaliações atrasadas quanto aquelas cujos prazos ainda não venceram. O envio é semanal; ele não é acionado um número fixo de dias antes do prazo do convite ou da avaliação. O email semanal usa o modelo `PENDING_REVIEWS_REMINDER` e não inclui um anexo de calendário.

## Relação com os lembretes do OJS

O OJS possui seus próprios lembretes automáticos para respostas a convites e avaliações em atraso. As configurações e o envio desses lembretes continuam sob o controle do OJS. Este plugin adiciona o resumo semanal de forma independente, de modo que um avaliador pode receber tanto um lembrete automático do OJS quanto um email semanal do plugin.

O anexo de calendário do plugin se aplica aos convites e aos lembretes **manuais** de avaliação. Ele não é adicionado aos lembretes automáticos de atraso do OJS.

## Instalação e ativação

> [!IMPORTANT]
> A versão do Review Reminder para **OJS 3.5 ainda não está disponível na Galeria de Plugins**. Instale-a manualmente usando um pacote de versão compatível.

1. Acesse a [página de versões](https://github.com/lepidus/reviewReminder/releases) e baixe o pacote `.tar.gz` do plugin para **OJS 3.5**. Confira as informações de compatibilidade da versão antes de baixar.
2. No painel da revista, acesse **Website > Plugins > Plugins instalados**, selecione **Enviar um novo plugin** e envie o pacote baixado.
3. Localize **Review Reminder** em **Plugins instalados** e ative-o para a revista.
4. Verifique se o envio de emails do OJS funciona e se o agendador de tarefas está em execução, caso queira receber os lembretes semanais.

Em uma instalação com várias revistas, ative o plugin separadamente em cada revista que precisar dele. A ativação do plugin habilita os dois recursos; não há configurações separadas para habilitar apenas os anexos de calendário ou apenas os emails semanais.

## Configure o acesso do avaliador com um clique

Para permitir que os avaliadores acessem suas avaliações por meio de um link seguro no email de convite do OJS, acesse **Fluxo de trabalho > Avaliação > Configuração** e habilite o **Acesso do avaliador com um clique**, caso ainda não esteja habilitado.

Essa configuração é opcional. O plugin pode anexar eventos de calendário e enviar lembretes semanais sem ela. Habilitá-la não transforma todos os links dos anexos de calendário ou dos lembretes semanais em links de acesso com um clique; os links comuns das páginas de avaliação ainda podem exigir autenticação.

![Tutorial de como habilitar o acesso do avaliador com um clique](https://i.imgur.com/cHjoXsI.gif)

## O que acontece se o plugin for desativado?

Desativar o Review Reminder para uma revista interrompe a inclusão de anexos de calendário nos próximos emails de convite e lembrete manual, além de excluir essa revista dos próximos resumos semanais.

O OJS continua gerenciando as designações de avaliação, os prazos, os convites, os lembretes manuais e seus próprios lembretes automáticos configurados. Desativar o plugin não exclui submissões ou avaliações, não altera prazos e não remove eventos que os avaliadores já tenham importado para seus calendários.

## Créditos

Este plugin foi patrocinado pela [South African Medical Association](http://samedical.org/).

Desenvolvido pela [Lepidus Tecnologia](https://lepidus.com.br/).

## Licença

__Este plugin é licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2024-2026 Lepidus Tecnologia__
