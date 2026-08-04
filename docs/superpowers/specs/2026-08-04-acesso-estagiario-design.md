# Acesso de estagiários

## Objetivo

Permitir que projetistas criem estagiários e escolham, por aba, se cada um pode visualizar, editar ou excluir. O administrador da empresa pode administrar todos os estagiários.

## Regras

- Estagiários contam no limite de usuários do plano.
- Cada estagiário pertence a um projetista responsável.
- O responsável escolhe entre dados próprios e dados de toda a empresa.
- Cada aba recebe um nível: sem acesso, visualizar, editar ou excluir.
- Excluir inclui editar e visualizar; editar inclui visualizar.
- O menu mostra somente abas autorizadas e as rotas validam a mesma permissão no servidor.
- Projetistas administram apenas seus estagiários; administradores administram todos.
- No alcance restrito, projetos e clientes futuros são filtrados pelo projetista responsável.

## Persistência

Adicionar `supervisor_user_id` e `intern_data_scope` em `users`, o papel `ESTAGIARIO` e a tabela `intern_permissions`, com uma linha por aba autorizada.

## Segurança e validação

Todas as alterações usam CSRF existente, consultas limitadas à empresa e validação de hierarquia. Acesso direto não autorizado retorna HTTP 403.

