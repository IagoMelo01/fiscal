# AGENTS.md

Diretivas para agentes e desenvolvedores que trabalharem neste módulo Fiscal do Dolibarr.

## Contexto do projeto

Este repositório é um módulo customizado Dolibarr 23 em `htdocs/custom/fiscal`, com foco fiscal para o agro brasileiro. O primeiro objetivo é entregar emissão de NF-e modelo 55 e importação de NF-es emitidas contra o CNPJ da empresa usando a API Focus NFe.

## Prioridades

1. Segurança fiscal e rastreabilidade antes de velocidade.
2. Compatibilidade com padrões Dolibarr antes de abstrações próprias.
3. Homologação Focus antes de produção.
4. Parametrização fiscal explícita antes de regra tributária hardcoded.
5. Código simples, auditável e com logs úteis para suporte.

## Regras de implementação

- Siga os padrões do Dolibarr: `CommonObject`, permissões em `modFiscal.class.php`, SQL em `sql/`, traduções em `langs/`, páginas com `main.inc.php`, tokens CSRF e helpers nativos.
- Não grave tokens Focus em logs, HTML, mensagens de erro ou arquivos exportáveis.
- Não grave arquivo de certificado digital, senha de certificado, base64 do certificado ou chave privada no Dolibarr.
- Use HTTP Basic Auth com token Focus como usuário e senha vazia.
- Use ambiente de homologação por padrão. Produção deve exigir configuração explícita de admin.
- O cadastro/atualização da empresa Focus deve ser feito pelo módulo com `dry_run=1` antes de efetivar `POST /v2/empresas` ou `PUT /v2/empresas/{id}`.
- O upload de certificado deve ser transitório: validar em memória, enviar para Focus e descartar arquivo/senha da memória ao final da requisição.
- A listagem local de certificados enviados deve conter apenas metadados: hash, titular, emissor, serial, CNPJ extraído, validade, usuário, data, ambiente, status e mensagem Focus.
- Não envie NF-e se houver validação local pendente em CNPJ/CPF, IE, endereço, itens, NCM, CFOP, CST/CSOSN, totais ou configuração Focus.
- Persistir request/response resumidos, HTTP code, status Focus, status SEFAZ, protocolo e mensagens.
- Bloquear edição fiscal sensível após transmissão, mantendo apenas campos administrativos permitidos.
- Usar `ref` idempotente e único para cada emissão Focus.
- Separar nota emitida (`NFe`) de nota recebida contra CNPJ (`NFeReceived`).
- Criar migrations SQL reversíveis na prática: novas tabelas e colunas devem ter nomes claros, índices e defaults seguros.

## Organização esperada

- `class/focusnfeclient.class.php`: cliente HTTP Focus, sem dependência de UI.
- `class/focuscompany.class.php`: empresa cadastrada/sincronizada com a Focus.
- `class/focuscompanyservice.class.php`: orquestra criação/atualização de empresa, `dry_run`, validação transitória de certificado e seleção de empresa ativa.
- `class/focuscertificatesubmission.class.php`: metadados dos certificados enviados, sem arquivo nem senha.
- `class/focusnfepayloadbuilder.class.php`: mapeamento Dolibarr para payload Focus.
- `class/fiscalvalidator.class.php`: validações funcionais antes de transmitir.
- `class/nfe.class.php`: domínio da NF-e emitida.
- `class/nfereceived.class.php`: domínio das NF-es recebidas.
- `class/fiscaloperationprofile.class.php`: perfis fiscais configuráveis.
- `admin/setup.php`: ambiente, token, emitente, série, cron e políticas.
- `received_nfe_list.php` e `received_nfe_card.php`: consulta e manifestação de recebidas.

## Qualidade

- Teste builder e client com fixtures de payload/response.
- Toda chamada Focus deve ter tratamento para 400, 401, 403, 404, 415, 422, 429 e 500.
- Erros para usuário devem explicar a correção provável; logs técnicos devem conter contexto sem segredo.
- Antes de finalizar uma mudança, executar ao menos lint PHP nos arquivos alterados quando o ambiente permitir.
- Não refatore telas geradas pelo ModuleBuilder fora do escopo da tarefa se isso não for necessário para a entrega.

## Fiscal e agro

- CFOP, CST, CSOSN, NCM, CEST, benefícios, diferimentos e regras por UF devem ser configuráveis e revisáveis pelo contador.
- Operações agro comuns devem ser representadas como perfis: venda de produção, compra de insumos, remessa, retorno, devolução, transferência e beneficiamento/armazenagem.
- Campos de safra, lote, peso, romaneio e produtor rural devem ser planejados para integração gradual, sem bloquear o MVP de NF-e.

## Documentação

- Atualize `plan.html` quando mudar arquitetura, endpoints, roadmap ou decisões de escopo.
- Cite documentação oficial da Focus NFe ao adicionar ou alterar endpoints.
- Registre decisões fiscais relevantes em documentação, não apenas no código.
