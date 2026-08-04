# Menu personalizado e desistidos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar uma aba segura de projetos desistidos e permitir que cada projetista personalize os atalhos do próprio menu lateral.

**Architecture:** Centralizar os filtros de projetos desistidos em funções puras testáveis e aplicar o escopo existente de projetista/estagiário. Armazenar preferências de navegação numa tabela por usuário e filtrar `role_nav()` apenas na renderização do sidebar, mantendo Configurações sempre visível.

**Tech Stack:** PHP 8.3, PDO, MySQL, HTML/Tailwind existente e testes PHP executáveis por CLI.

## Global Constraints

- Ocultar atalhos não altera permissões nem dados.
- Configurações do menu fica sempre visível.
- Sem preferências salvas, o menu original aparece completo.
- Estagiário acessa somente seus projetos atribuídos.
- Não adicionar dependências externas.

---

### Task 1: Aba Desistidos

**Files:**
- Modify: `app/includes/functions.php`
- Modify: `public/projects.php`
- Modify: `tests/project-abandon-test.php`

**Interfaces:**
- Produces: `project_is_abandoned(array $project): bool` e filtro de listagem por `view=desistidas`.

- [ ] **Step 1: Write the failing test**

Adicionar casos que confirmem `negotiation_status=Desistida` como desistido e garantam que outro status não seja classificado assim.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/project-abandon-test.php`
Expected: FAIL por função ainda inexistente.

- [ ] **Step 3: Write minimal implementation**

Criar a função pura, ampliar a consulta e o contador de desistidos para Projeto e Negociação, adicionar a aba ao lado de Projetos Estagiários e preservar `assignment=intern` para estagiário.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/project-abandon-test.php`
Expected: `project-abandon-test: OK`.

### Task 2: Preferências individuais de navegação

**Files:**
- Create: `database/2026_08_04_user_nav_preferences.sql`
- Create: `app/includes/nav-preferences.php`
- Create: `public/menu-settings.php`
- Modify: `database/schema.sql`
- Modify: `app/includes/functions.php`
- Modify: `app/includes/sidebar.php`
- Create: `tests/nav-preferences-test.php`

**Interfaces:**
- Produces: `designer_nav_groups(): array`, `filter_nav_by_preferences(array $nav, array $hiddenKeys): array`, `ensure_nav_preferences_schema(): void` e `nav_preferences_for_user(int $userId): array`.

- [ ] **Step 1: Write the failing test**

Testar menu completo sem preferências, ocultação individual, Configurações obrigatória e classificação das abas em fluxo e independentes.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/nav-preferences-test.php`
Expected: FAIL por funções ainda inexistentes.

- [ ] **Step 3: Write minimal implementation**

Criar tabela com chave única `(user_id, nav_key)`, funções puras de filtro, carregamento PDO, página restrita a projetista com checkboxes e mensagem da escadinha, e aplicar preferências no sidebar.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/nav-preferences-test.php`
Expected: `nav-preferences-test: OK`.

### Task 3: Verificação integrada

**Files:**
- Verify: todos os arquivos PHP modificados
- Verify: todos os testes em `tests/*.php`

- [ ] **Step 1: Run PHP lint**

Executar `php -l` nos arquivos tocados e exigir zero erros.

- [ ] **Step 2: Run all focused tests**

Executar os testes de desistidos, navegação, estagiários, permissões e limites de plano.

- [ ] **Step 3: Verify database migration and diff**

Revisar a migração, executar `git diff --check` e confirmar que nenhuma alteração alheia foi sobrescrita.
