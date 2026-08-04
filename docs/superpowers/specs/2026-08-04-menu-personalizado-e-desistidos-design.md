# Menu personalizado e projetos desistidos

## Objetivo

Permitir que projetistas simplifiquem o próprio menu lateral e tornar os projetos desistidos acessíveis em uma aba própria para projetistas e estagiários.

## Projetos desistidos

- A listagem em tabela terá a aba **Desistidos** ao lado de **Projetos Estagiários**.
- A aba ficará disponível nas etapas Projeto e Negociação.
- O projetista verá os projetos desistidos da empresa que ele tem autorização para acompanhar, incluindo projetos atribuídos a estagiários.
- O estagiário verá somente projetos desistidos cujo `intern_user_id` seja o seu usuário.
- Projetos desistidos não aparecerão nas listas de projetos ativos.
- A reativação continuará levando o projeto para Negociação.

## Personalização do menu

- O menu do projetista terá uma opção **Configurações do menu**, sempre visível.
- Cada projetista poderá mostrar ou ocultar todos os demais atalhos do próprio menu.
- A escolha será individual: não altera o menu do proprietário, de outros projetistas ou de estagiários.
- Ocultar um atalho não altera permissões, não apaga dados e não interrompe o fluxo do projeto.
- As preferências serão armazenadas em uma tabela própria, vinculada à empresa e ao usuário.
- Na ausência de preferências salvas, todas as abas originais permanecerão visíveis.

## Explicação das abas

A tela de configuração separará as opções em dois grupos e exibirá uma mensagem explicativa.

### Fluxo em sequência

Projeto → Negociação → Conferência → Montagem → Assistência → Finalizados.

Essas abas são apresentadas como uma “escadinha”: cada uma representa uma etapa do mesmo fluxo. Ocultar uma etapa remove somente seu atalho lateral; os projetos continuam avançando normalmente e a etapa pode ser reativada nas configurações.

### Abas independentes

Dashboard, Meu Dia, Agendamentos, Clientes Futuros, Financeiro, Contratos, Arquivos de Projetos, Minha Meta e Minhas Exportações.

Essas abas não representam etapas sequenciais do projeto e podem ser exibidas ou ocultadas individualmente.

## Dados e segurança

Criar `user_nav_preferences` com `company_id`, `user_id`, `nav_key`, `visible` e chave única por usuário e item. O backend aceitará somente chaves existentes no menu de projetista. A página exigirá usuário autenticado, assinatura ativa e papel `PROJETISTA`.

## Testes

- Testar a classificação das abas entre fluxo e independentes.
- Testar que Configurações nunca pode ser ocultada.
- Testar o comportamento padrão sem preferências.
- Testar o filtro de desistidos para projetista e estagiário.
- Executar lint PHP, testes existentes e verificação do diff.
