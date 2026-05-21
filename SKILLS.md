# SKILLS.md

Playbook local de habilidades para trabalhar no módulo Fiscal Agro. Este arquivo orienta a execução do projeto; não é uma skill instalada do Codex.

## Skill: Dolibarr Module Engineering

Use quando criar ou alterar estrutura do módulo.

- Ler primeiro `core/modules/modFiscal.class.php`, `class/*.class.php`, `sql/*.sql`, `admin/setup.php` e a página envolvida.
- Preferir APIs nativas Dolibarr para permissões, constantes, formulários, documentos, logs, hooks e cron.
- Manter compatibilidade com multi-entidade sempre que a tabela representar dado da empresa.
- Adicionar permissões antes de expor ações sensíveis na UI.
- Para novos objetos, criar classe, SQL, chaves, lista/card e traduções mínimas.

## Skill: Focus NFe Integration

Use quando implementar cadastro de empresa, emissão, consulta, cancelamento, manifestação ou importação.

- Consultar a documentação oficial Focus NFe antes de implementar endpoint.
- Usar `https://homologacao.focusnfe.com.br` no desenvolvimento e `https://api.focusnfe.com.br` apenas em produção configurada.
- Autenticar por Basic Auth com token como usuário e senha vazia.
- Nunca registrar o token em banco, logs ou tela.
- Para empresa Focus, consultar primeiro `GET /v2/empresas?cnpj=CNPJ`.
- Criar empresa com `POST /v2/empresas` e atualizar com `PUT /v2/empresas/{id}`.
- Executar `dry_run=1` antes de efetivar criação ou atualização de empresa.
- Enviar `arquivo_certificado_base64` e `senha_certificado` somente na requisição para a Focus.
- Nunca salvar arquivo PFX/P12, senha, base64 ou chave privada no Dolibarr.
- Salvar somente metadados do certificado enviado: hash, titular, emissor, serial, CNPJ, validade, usuário, data, ambiente, status e mensagem Focus.
- Para habilitar o MVP, configurar `habilita_nfe=true` e `habilita_manifestacao=true` quando houver importação de recebidas.
- Normalizar respostas HTTP e respostas de negócio da Focus separadamente.
- Persistir status Focus, status SEFAZ, mensagem SEFAZ, protocolo, caminhos de XML/DANFe e payload relevante.
- Tratar `ref` como chave idempotente de emissão; não gerar nova referência automaticamente em retentativa.

## Skill: NF-e Modelo 55

Use quando mexer em payload, validação ou tela de emissão.

- Validar emitente, destinatário, endereço, IE, regime tributário, natureza da operação e finalidade antes da transmissão.
- Validar cada item: produto, descrição, NCM, CFOP, unidade, quantidade, valor, origem ICMS, CST/CSOSN, PIS e COFINS.
- Garantir consistência dos totais antes de enviar para Focus.
- Após transmissão, bloquear alterações que mudem o XML fiscal.
- Armazenar XML e DANFe autorizados como documentos do objeto.

## Skill: NFe Recebidas e MDe

Use quando implementar notas emitidas contra o CNPJ.

- Sincronizar com `GET /v2/nfes_recebidas?cnpj=CNPJ&versao=N`.
- Guardar `X-Max-Version` por CNPJ e buscar somente versões maiores na próxima execução.
- Fazer upsert por `chave_nfe + cnpj_destinatario`.
- Baixar XML, PDF e JSON completo sob demanda ou quando `nfe_completa=true`.
- Manifestar com `POST /v2/nfes_recebidas/CHAVE/manifesto`.
- Tipos de manifesto: `ciencia`, `confirmacao`, `desconhecimento`, `nao_realizada`.
- Exigir justificativa de 15 a 255 caracteres para `nao_realizada`.

## Skill: Fiscal Agro Brasileiro

Use quando modelar perfis fiscais e dados de operação agro.

- Não codificar regra tributária específica sem fonte e validação funcional.
- Representar operações como perfis configuráveis por UF, regime, finalidade e tipo de parceiro.
- Considerar produtor rural PF/PJ, IE, endereço rural, unidade comercial/tributável, peso, lote, safra e romaneio.
- Mapear produtos com NCM, CEST quando aplicável, unidade, origem ICMS e configurações de PIS/COFINS.
- Preparar extensão para estoque, lotes e documentos de transporte, mas manter o MVP independente.

## Skill: QA Fiscal

Use antes de encerrar qualquer entrega.

- Rodar lint PHP nos arquivos alterados.
- Validar que ações sensíveis exigem permissão e token CSRF.
- Testar erro de configuração, erro de autenticação, rejeição Focus e sucesso.
- Conferir que logs não expõem token.
- Conferir que o documento não duplica em retentativa.
- Atualizar documentação quando comportamento fiscal ou endpoint mudar.
