# Contribuindo com o llumo

Obrigado pelo interesse em contribuir. O llumo é um projeto voltado a centros e casas espíritas, e toda ajuda — código, tradução, documentação, relato de bug ou simplesmente contar como sua casa funciona — é bem-vinda.

Ao participar do projeto, você concorda em seguir nosso [Código de Conduta](CODE_OF_CONDUCT.md).

## Antes de começar

O projeto está em desenvolvimento inicial e segue um [roadmap definido](README.md#roadmap). Antes de investir tempo em código:

- **Para um bug**, abra uma [issue](https://github.com/guilhermepolicarpo/llumo/issues/new/choose) com passos de reprodução.
- **Para uma funcionalidade nova**, abra uma [discussion](https://github.com/guilhermepolicarpo/llumo/discussions) primeiro. Conversar antes evita que você escreva algo que não caiba na direção do projeto — o que é frustrante para todo mundo.
- **Para um bug pequeno e óbvio** (typo, link quebrado, texto errado), pode mandar o pull request direto.

## Ambiente de desenvolvimento

Requisitos: **PHP 8.3+** (a CI roda em 8.5), **Composer** e **Node.js 22+**. O banco padrão é SQLite, então não é preciso subir nenhum serviço externo.

```bash
git clone https://github.com/guilhermepolicarpo/llumo.git
cd llumo
composer setup
composer dev
```

O `composer setup` instala as dependências, cria o `.env`, gera a chave, roda as migrations e compila os assets. O `composer dev` sobe servidor, filas, logs e Vite de uma vez.

## Padrões de código

O projeto é rigoroso com estilo e análise estática, porque a CI barra o merge se algo falhar.

| Comando | O que faz |
| --- | --- |
| `composer lint` | Formata o código com Pint (preset `laravel`) |
| `composer types:check` | Análise estática com PHPStan **level 7** |
| `composer test` | Roda os três: Pint, PHPStan e Pest |

Convenções que valem a pena conhecer antes do primeiro PR:

- **Código, nomes e commits em inglês.** A interface é traduzida, o código não.
- **O nome do projeto é `llumo`**, sempre em minúsculas — inclusive em início de frase.
- **Tipos explícitos sempre**: tipo de retorno e type hint em todo parâmetro.
- **Chaves em toda estrutura de controle**, mesmo de uma linha só.
- **Constructor property promotion** do PHP 8 em vez de atribuição manual.
- **PHPDoc em vez de comentário inline.** Comentário inline só para lógica realmente difícil.
- **Componentes Livewire 4 são single-file**, criados em `resources/views/` com o prefixo `⚡` no nome do arquivo — por exemplo `resources/views/pages/settings/⚡profile.blade.php`. Só crie classe em `app/Livewire/` se houver um motivo concreto.
- **Toda string de interface dentro de `__()`.** O projeto ainda roda em inglês, mas a tradução para português depende disso.
- **Use os geradores do Artisan** (`php artisan make:...`) em vez de criar arquivos na mão.

O arquivo [CLAUDE.md](CLAUDE.md) detalha essas convenções e é a referência usada por agentes de IA que trabalham no repositório.

## Testes

Todo comportamento novo precisa de teste. O projeto usa **Pest 5**, com preferência forte por testes de feature:

```bash
php artisan make:test --pest NomeDoTeste     # feature
php artisan make:test --pest --unit NomeDoTeste

php artisan test --filter=nomeDoTeste        # rode o mais estreito possível
php artisan test --compact                   # suíte completa
```

Use as factories existentes (`UserFactory`, `TeamFactory`, `TeamInvitationFactory`) e confira se já há um *state* que atenda antes de montar o model na mão.

## Terminologia do domínio

O llumo trata de conceitos da doutrina espírita, e usar o termo errado no código ou na interface gera confusão real para quem usa o sistema. Um resumo do vocabulário:

- **Assistido** — a pessoa que recebe atendimento na casa.
- **Atendimento** — o encontro em que o assistido é atendido.
- **Mentor** — o mentor espiritual (espírito protetor ou guia) que orienta e realiza o tratamento espiritual através do médium. **Não** é um voluntário encarnado.
- **Fluídico** — a prescrição passada pelo mentor durante o atendimento, registrada com nome e descrição e vinculada ao atendimento.

Se não tiver familiaridade com esses conceitos, tudo bem — pergunte na issue ou na discussion. É melhor perguntar do que assumir.

## Abrindo o pull request

1. Crie um branch a partir da `main`.
2. **Um assunto por PR.** Refatoração e funcionalidade nova no mesmo PR tornam a revisão muito mais difícil.
3. Rode `composer test` antes de enviar — é exatamente o que a CI executa, então o que passa aqui passa lá.
4. Descreva **o que** muda e **por quê**. Se for mudança visual, anexe uma captura de tela.
5. Se o PR resolve uma issue, referencie com `Closes #123`.

Reviews podem demorar alguns dias. Se ninguém responder em uma semana, pode dar um toque no próprio PR.

## Segurança

Encontrou uma vulnerabilidade? **Não abra uma issue pública.** Reporte pelo [GitHub Security Advisories](https://github.com/guilhermepolicarpo/llumo/security/advisories/new) do repositório.

## Licença

Ao contribuir, você concorda que sua contribuição será licenciada sob a [licença MIT](LICENSE) do projeto.
