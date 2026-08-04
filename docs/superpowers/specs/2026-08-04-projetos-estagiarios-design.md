# Projetos atribuídos a estagiários

## Objetivo

O proprietário cria as contas de estagiário. Proprietário e projetistas criam projetos específicos para um estagiário, mantendo o projetista como responsável principal.

## Regras

- Um projeto pode ter uma estagiária atribuída por vez.
- A estagiária vê somente Meu Dia, Projeto e Negociação.
- A estagiária vê e edita somente projetos atribuídos a ela.
- A estagiária pode mover Projeto para Negociação, mas não avançar para Conferência.
- O proprietário vê todos os projetos de estagiários.
- O projetista vê os projetos de estagiários sob sua responsabilidade.
- A listagem de projetos ganha a opção Projetos Estagiários.
- O botão Criar projeto para estagiária fica disponível ao proprietário e aos projetistas.
- O histórico registra normalmente as alterações e movimentações feitas pela estagiária.

## Dados

Adicionar `intern_user_id` em `client_projects`, preservando `designer_id` como responsável comercial.

