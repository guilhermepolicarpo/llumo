<p align="center">
  <img src="art/logo.svg" width="120" alt="llumo">
</p>

<h1 align="center">llumo</h1>

<p align="center">
  Gestão para centros e casas espíritas — livre, self-hosted e feito com carinho.
</p>

<p align="center">
  <a href="README.en.md">Read this in English</a>
</p>

<p align="center">
  <a href="https://github.com/guilhermepolicarpo/llumo/actions/workflows/tests.yml"><img src="https://github.com/guilhermepolicarpo/llumo/actions/workflows/tests.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="Licença MIT"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20" alt="Laravel 13">
</p>

## Sobre

O llumo é um sistema de gestão para centros e casas espíritas: cadastro de assistidos, agendamento e registro de atendimentos, controle da biblioteca — o dia a dia da casa em um só lugar.

O nome vem do esperanto *lumo* (luz), língua com ligação histórica com o movimento espírita. É grafado sempre em minúsculas.

Duas premissas guiam o projeto:

- **Os dados da casa pertencem à casa.** O llumo é self-hosted: você o instala no seu próprio servidor e mantém o controle total das informações dos assistidos.
- **Software livre, sem mensalidade.** Código aberto sob licença MIT, sem custo de licença e sem dependência de um fornecedor.

> [!WARNING]
> O llumo está em desenvolvimento inicial. A base técnica — autenticação e multi-tenancy — já está pronta e testada, mas **nenhum módulo de domínio foi implementado ainda**. Consulte a tabela abaixo e o [roadmap](#roadmap) para ver o que existe hoje e o que está planejado.

## Funcionalidades

| Módulo | Descrição | Status |
|---|---|---|
| Casas (multi-tenancy) | Uma instância do llumo hospeda várias casas, cada uma com dados isolados, membros e papéis (Owner/Admin/Member). Convite por e-mail com expiração. | ✅ Pronto |
| Contas e acesso | Cadastro, login, verificação de e-mail, recuperação de senha, 2FA (TOTP + códigos de recuperação) e passkeys/WebAuthn. | ✅ Pronto |
| Assistidos | Cadastro das pessoas atendidas, com histórico de atendimentos. | 🚧 Planejado |
| Agendamento | Agenda de atendimentos, com controle de horários e presença. | 🚧 Planejado |
| Atendimentos | Registro do atendimento, vinculando o mentor que atuou e os fluídicos prescritos. | 🚧 Planejado |
| Mentores | Cadastro dos mentores espirituais, vinculados aos atendimentos. | 🚧 Planejado |
| Fluídicos | Cadastro de fluídicos (nome e descrição), prescritos pelo mentor durante o atendimento. | 🚧 Planejado |
| Biblioteca | Catálogo de livros da casa, com controle de empréstimos e devoluções. | 🚧 Planejado |

O fluxo central do domínio é: **assistido → agendamento → atendimento**. O assistido é cadastrado, tem um atendimento agendado e, no registro desse atendimento, anotam-se o mentor espiritual que atuou através do médium e os fluídicos prescritos por ele. Os mentores são espíritos protetores e guias — espíritos mais evoluídos que orientam, protegem e auxiliam na evolução moral e espiritual, realizando o tratamento espiritual e de cura durante o atendimento. Os fluídicos ficam vinculados ao atendimento em que foram prescritos, formando o histórico do assistido.

## Screenshots

<!-- TODO: adicionar capturas de tela -->

Serão adicionadas conforme os módulos de domínio ficarem prontos.

## Stack

PHP 8.3+ · Laravel 13 · Livewire 4 (single-file components) · Flux UI 2 · Tailwind CSS 4 · Laravel Fortify · Pest 5 · PHPStan level 7 · Pint · SQLite (padrão) · Vite 8 / vite-plus

## Requisitos

- PHP 8.3 ou superior (a CI roda em 8.5)
- Composer
- Node.js 22 ou superior
- Um banco de dados — SQLite por padrão, sem serviço externo necessário

MySQL e PostgreSQL também funcionam: basta ajustar `DB_CONNECTION` e as credenciais no `.env`.

## Instalação

```bash
git clone https://github.com/guilhermepolicarpo/llumo.git
cd llumo
composer setup
composer dev
```

O `composer setup` cuida de tudo: instala as dependências PHP, cria o `.env` a partir do `.env.example`, gera a chave da aplicação, roda as migrations, instala as dependências JS e compila os assets.

O `composer dev` sobe servidor, filas, logs e Vite juntos (via `php artisan dev`). A aplicação fica disponível em `http://localhost:8000`.

Se você usa [Herd](https://herd.laravel.com), pode dispensar o servidor embutido: aponte o Herd para o diretório do projeto, ajuste o `APP_URL` no `.env` e rode apenas o Vite.

## Desenvolvimento

| Comando | O que faz |
|---|---|
| `composer dev` | Servidor, filas, logs e Vite |
| `composer test` | Suíte completa: Pint (check), PHPStan e Pest |
| `composer lint` | Formata o código com Pint |
| `composer types:check` | Análise estática (PHPStan level 7) |
| `php artisan test --compact` | Só os testes |

O `composer test` é exatamente o que a CI executa em cada push e pull request. Rode-o antes de abrir um PR.

## Roadmap

Na ordem em que devem ser desenvolvidos:

1. **Assistidos** — cadastro das pessoas atendidas
2. **Agendamento** — agenda de atendimentos
3. **Atendimentos** — registro do atendimento
4. **Mentores e fluídicos** — cadastros e vínculo com o atendimento
5. **Biblioteca** — catálogo, empréstimos e devoluções
6. **Financeiro** — controle de receitas e despesas da casa
7. **Assistência social** — gestão de doações e ações assistenciais aos assistidos

Além dos módulos, no radar:

- **Tradução para pt-BR** — as strings da interface já estão em `__()`; falta o arquivo `lang/pt_BR.json` e trocar o `APP_LOCALE`
- **Imagem Docker e guia de deploy** — para tornar o self-host o mais simples possível

## Contribuindo

Contribuições são muito bem-vindas. Veja o [CONTRIBUTING.md](CONTRIBUTING.md) para o setup local, os padrões de código e o fluxo de pull request.

Ao participar do projeto, você concorda em seguir o nosso [Código de Conduta](CODE_OF_CONDUCT.md).

## Segurança

Encontrou uma vulnerabilidade? Não abra uma issue pública. Reporte pelo [GitHub Security Advisories](https://github.com/guilhermepolicarpo/llumo/security/advisories/new) do repositório.

## Comunidade

- [Issues](https://github.com/guilhermepolicarpo/llumo/issues) — bugs e sugestões de funcionalidades
- [Discussions](https://github.com/guilhermepolicarpo/llumo/discussions) — dúvidas, ideias e conversa em geral

## Licença

O llumo é software livre sob a licença [MIT](LICENSE).
