# Projetos de Estagiários Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir criação, atribuição, acompanhamento e movimentação limitada de projetos por estagiários.

**Architecture:** `designer_id` continua identificando o projetista responsável; `intern_user_id` identifica a execução delegada. A autorização combina papel, empresa, atribuição e etapa.

**Tech Stack:** PHP 8.3, PDO e MySQL/MariaDB.

## Global Constraints

- Somente o proprietário administra contas de estagiários.
- Estagiários acessam apenas projetos explicitamente atribuídos.
- Estagiários não avançam além de Negociação.

---

### Task 1: Modelo e autorização

**Files:** `database/schema.sql`, `database/2026_08_04_intern_projects.sql`, `app/includes/intern-permissions.php`, `tests/intern-project-access-test.php`

- [ ] Testar atribuição e limite de etapas.
- [ ] Adicionar a coluna de atribuição.
- [ ] Aplicar autorização por projeto e etapa.

### Task 2: Criação e listagem

**Files:** `public/project-form.php`, `public/project-save.php`, `public/projects.php`

- [ ] Adicionar o botão Criar projeto para estagiária.
- [ ] Validar estagiária e projetista no servidor.
- [ ] Adicionar Projetos Estagiários e filtros por papel.

### Task 3: Gestão e Meu Dia

**Files:** `public/interns.php`, `app/includes/functions.php`, `public/my-day.php`

- [ ] Restringir a gestão de contas ao proprietário.
- [ ] Fixar as três abas do estagiário.
- [ ] Permitir ao projetista acompanhar e atribuir tarefas às suas estagiárias.

