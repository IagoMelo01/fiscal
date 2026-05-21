# Homologacao Focus NFe e Go-live

Este checklist deve ser executado por cliente/banco Dolibarr, usando token Focus do mesmo ambiente e empresa Focus ativa.

## Pre-requisitos

- Ambiente Focus em homologacao.
- Token Focus configurado no setup do modulo.
- Empresa Focus ativa no Dolibarr com `habilita_nfe=1` e `habilita_manifestacao=1`.
- Certificado A1 valido do mesmo CNPJ da empresa ativa.
- Serie e numeracao conferidas com contador.
- Permissoes Dolibarr revisadas para emissao, recebidas e manifestacao.

## Certificados

| Caso | Procedimento | Resultado esperado |
| --- | --- | --- |
| Valido | Enviar A1 valido do CNPJ ativo pelo assistente Focus. | Dry-run aprovado, envio efetivo aprovado, metadados salvos, arquivo/senha nao persistidos. |
| Vencido | Enviar A1 vencido com senha correta. | Validacao local bloqueia ou Focus rejeita; erro claro; somente metadados tecnicos seguros quando aplicavel. |
| Invalido | Enviar arquivo nao PFX/P12 ou senha incorreta. | Validacao local rejeita; nenhum arquivo/senha/base64 salvo. |
| CNPJ divergente | Enviar A1 valido de outro CNPJ. | Validacao local rejeita divergencia antes do envio efetivo. |

## NF-e emitida

| Caso | Procedimento | Resultado esperado |
| --- | --- | --- |
| Payload valido | Criar NF-e modelo 55 com destinatario, itens, CFOP, NCM, CST/CSOSN e totais completos. | Botao "Validar payload" sem erros. |
| Rejeicao controlada | Enviar nota com regra fiscal propositalmente invalida em homologacao. | Status local rejeitado, mensagem Focus/SEFAZ visivel, log tecnico sem segredo. |
| Autorizacao | Enviar nota fiscal valida em homologacao. | Status autorizado, chave/protocolo salvos, XML/DANFe/JSON baixados e vinculados. |
| Consulta cron | Deixar nota transmitida/processando. | Cron atualiza status e baixa documentos quando Focus liberar caminhos. |

## NF-es recebidas

| Caso | Procedimento | Resultado esperado |
| --- | --- | --- |
| Importacao incremental | Sincronizar recebidas com cursor zerado e repetir sincronizacao. | Primeira chamada grava notas e cursor; segunda busca usa `versao` e nao duplica. |
| Downloads | Abrir recebida e baixar XML/PDF/JSON. | Arquivos salvos em `fiscal/received/{chave}` e links exibidos no card. |
| Pendencias | Filtrar pendentes na listagem. | Notas sem manifestacao aparecem como pendentes. |

## Manifestacao

| Tipo | Procedimento | Resultado esperado |
| --- | --- | --- |
| Ciencia | Enviar `ciencia`. | Protocolo/status SEFAZ salvos no historico. |
| Confirmacao | Enviar `confirmacao`. | Manifestacao local atualizada e historico gravado. |
| Desconhecimento | Enviar `desconhecimento`. | Manifestacao local atualizada e historico gravado. |
| Operacao nao realizada | Enviar `nao_realizada` com justificativa entre 15 e 255 caracteres. | Justificativa enviada, protocolo/status salvos, historico preservado. |

## Go-live

- Confirmar backup do banco e documentos Dolibarr.
- Confirmar que homologacao teve ao menos uma NF-e autorizada e uma rejeitada tratada.
- Confirmar importacao incremental e manifestacao de recebidas.
- Confirmar token de producao da Focus e certificado de producao.
- Ativar producao no setup somente com confirmacao administrativa.
- Conferir serie, numeracao, regime tributario e perfis fiscais com contador.
- Executar primeira emissao em producao acompanhada e salvar protocolo/XML/DANFe.
