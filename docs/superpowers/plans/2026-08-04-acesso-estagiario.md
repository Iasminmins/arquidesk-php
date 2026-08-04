# Acesso de Estagiários Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar contas de estagiário administradas por projetistas, com permissões por aba e ação.

**Architecture:** Uma camada central traduz rotas em abas e compara níveis de acesso. A persistência guarda responsável, alcance dos dados e permissões normalizadas por usuário.

**Tech Stack:** PHP 8.3, PDO, MySQL/MariaDB, HTML e Tailwind CSS.

## Global Constraints

- Preservar os perfis atuais.
- Estagiários contam no limite do plano.
- Projetistas administram somente seus próprios estagiários; administradores administram todos.

---

### Task 1: Modelo central de autorização

**Files:**
- Create: `app/includes/intern-permissions.php`
- Create: `tests/intern-permissions-test.php`

- [ ] Criar testes para hierarquia de níveis, catálogo de abas e filtragem do menu.
- [ ] Executar os testes e confirmar falha pela ausência da implementação.
- [ ] Implementar funções puras de autorização.
- [ ] Executar os testes e confirmar aprovação.

### Task 2: Persistência e cadastro

**Files:**
- Create: `database/2026_08_04_intern_permissions.sql`
- Modify: `database/schema.sql`
- Create: `public/interns.php`

- [ ] Adicionar papel, vínculo, alcance e tabela de permissões.
- [ ] Criar formulário e operações de criação, edição, ativação e exclusão.
- [ ] Restringir a administração pela empresa e pelo projetista responsável.

### Task 3: Aplicação das permissões

**Files:**
- Modify: `app/includes/auth.php`
- Modify: `app/includes/functions.php`
- Modify: `app/includes/sidebar.php`
- Modify: `public/projects.php`
- Modify: `public/future-clients.php`

- [ ] Bloquear no servidor rotas sem nível suficiente.
- [ ] Filtrar o menu pelo catálogo autorizado.
- [ ] Aplicar o alcance escolhido às listagens de projetos e clientes futuros.
- [ ] Validar sintaxe de todos os arquivos PHP e executar os testes.

