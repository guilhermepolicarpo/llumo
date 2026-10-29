<p align="center">
  <img src="art/logo-wordmark.svg" width="180" alt="llumo">
</p>

<p align="center">
  Gestão para centros espíritas — livre, self-hosted e feito com carinho.
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

O llumo é um sistema de gestão para centros espíritas: cadastro de assistidos, agendamento e registro de atendimentos, controle da biblioteca — o dia a dia da casa em um só lugar.

O nome vem do esperanto *lumo* (luz), língua com ligação histórica com o movimento espírita. É grafado sempre em minúsculas.

Duas premissas guiam o projeto:

- **Os dados da casa pertencem à casa.** O llumo é self-hosted: você o instala no seu próprio servidor e mantém o controle total das informações dos assistidos.
- **Software livre.** Código aberto sob licença MIT, sem custo de licença e sem dependência de um fornecedor.

> [!NOTE]
> O llumo está em desenvolvimento ativo. Os módulos do dia a dia da casa — assistidos, agendamentos, atendimentos e biblioteca — já funcionam e são cobertos por testes, mas ainda não há uma versão estável lançada. Veja o [roadmap](#roadmap) para o que vem a seguir.

<p align="center">
  <img src="art/screenshots/agendamentos.png" alt="Tela de agendamentos do llumo: agenda do dia com busca, filtros, aviso de atendimentos pendentes e a lista de assistidos com as ações Receber e Atender">
</p>

<p align="center"><em>Agendamentos: a agenda do dia, com a recepção e o atendimento dos assistidos.</em></p>

## Funcionalidades

| Módulo | Descrição | Status |
|---|---|---|
| Casas (multi-tenancy) | Uma instância do llumo hospeda várias casas, cada uma com dados isolados, membros e papéis (Proprietário/Administrador/Membro). Convites por e-mail com expiração. Em **Configurações do centro**, os administradores cuidam dos dados da casa (nome, logotipo, telefone, endereço preenchido pelo CEP), dos membros e dos cadastros. | ✅ Pronto |
| Contas e acesso | Cadastro, login, verificação de e-mail, recuperação de senha, 2FA (TOTP + códigos de recuperação) e passkeys/WebAuthn. | ✅ Pronto |
| Assistidos | Cadastro das pessoas atendidas, com contato e endereço (preenchido pelo CEP), busca e o histórico de atendimentos e empréstimos de cada uma. | ✅ Pronto |
| Agendamentos | Agenda por dia, atendimentos presenciais ou a distância, recepção da chegada e fila de espera, filtros por status, tipo e atendente, e faltas marcadas automaticamente. Tipos de atendimento com dias da semana e limite diário. | ✅ Pronto |
| Atendimentos | Registro do atendimento: mentor, fluídicos (quantidade e posologia), passes, orientações recomendadas, retirada de infiltração, retorno agendado e observações, com rascunho salvo enquanto se preenche. Impressão das fichas de atendimento do dia em PDF. | ✅ Pronto |
| Cadastros | Tipos de atendimento, mentores, fluídicos, orientações e tipos de passe; na biblioteca, autores, editoras, categorias e coleções. | ✅ Pronto |
| Biblioteca | Acervo da casa com busca pelo ISBN nos catálogos públicos (BrasilAPI e Open Library, com capa), exemplares com tombo, empréstimos, renovações e devoluções, controle de atrasos e regras de empréstimo definidas por cada casa. | ✅ Pronto |
| Início | Visão geral da casa ao entrar: agendamentos do dia por situação, atendimentos pendentes de dias anteriores, empréstimos atrasados ou vencendo e os agendamentos dos próximos dias. | ✅ Pronto |
| Financeiro | Controle de receitas e despesas da casa. | 🚧 Planejado |
| Assistência social | Gestão de doações e ações assistenciais aos assistidos. | 🚧 Planejado |

O fluxo central do domínio é: **assistido → agendamento → atendimento**. O assistido é cadastrado, tem um atendimento agendado e, no registro desse atendimento, anotam-se o mentor espiritual que atuou através do médium e os fluídicos prescritos por ele. Os mentores são espíritos protetores e guias — espíritos mais evoluídos que orientam, protegem e auxiliam na evolução moral e espiritual, realizando o tratamento espiritual e de cura durante o atendimento. Os fluídicos ficam vinculados ao atendimento em que foram prescritos, formando o histórico do assistido.

## Stack

PHP 8.3+ · Laravel 13 · Livewire 4 (single-file components) · Flux UI 2 · Tailwind CSS 4 · Laravel Fortify · Laravel PDF (DomPDF) · Pest 5 · PHPStan level 7 · Pint · SQLite (padrão) · Vite 8 / vite-plus

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

### Dados de demonstração

Para explorar o llumo com dados de exemplo, rode `php artisan db:seed`. Ele cria o usuário `test@example.com` (senha `password`) com uma casa já preenchida: assistidos, agendamentos, atendimentos, cadastros e a biblioteca com livros e empréstimos.

### Em produção

- **Agendador:** configure o cron do Laravel (`* * * * * php artisan schedule:run`). Ele marca como falta os agendamentos de dias anteriores que não foram atendidos e apaga os convites expirados.
- **Filas:** mantenha um worker rodando (`php artisan queue:work`).
- **Serviços externos:** a busca de endereço pelo CEP e de livros pelo ISBN usa as APIs públicas e gratuitas da [BrasilAPI](https://brasilapi.com.br) e da [Open Library](https://openlibrary.org). Os endereços podem ser trocados por `BRASIL_API_URL` e `OPEN_LIBRARY_URL` no `.env`.
- **Cache de views:** rode `php artisan view:cache` (ou `view:clear`) a cada deploy, para que as traduções dos componentes do Flux entrem nas views compiladas.

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

Já prontos: assistidos, agendamentos, atendimentos, mentores e fluídicos, biblioteca, a tela de início e a tradução da interface para pt-BR.

A seguir:

1. **Financeiro** — controle de receitas e despesas da casa
2. **Assistência social** — gestão de doações e ações assistenciais aos assistidos

Além dos módulos, no radar:

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
