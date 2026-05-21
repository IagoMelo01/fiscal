# Fiscal para Dolibarr 23

Modulo customizado para operacoes fiscais brasileiras no Dolibarr, com foco inicial em NF-e modelo 55 e importacao de NF-es recebidas via Focus NFe.

## Escopo atual

- Base do modulo limpa para ativacao e configuracao.
- Tabelas e objetos fiscais para empresa Focus, certificados enviados, NF-e emitida, itens, NF-e recebida, logs e cursor de sincronizacao.
- Cliente HTTP isolado para endpoints Focus NFe v2 usados no MVP.

## Regras de seguranca

- Homologacao e o ambiente padrao.
- Producao exige confirmacao explicita de administrador.
- Token Focus nao e impresso na tela nem gravado em logs.
- Certificado digital, senha, base64 e chave privada nao sao persistidos no Dolibarr.
- Logs Focus sao resumidos e sanitizados.

Consulte `plan.html` e `AGENTS.md` para arquitetura, roadmap e decisoes fiscais do projeto.
