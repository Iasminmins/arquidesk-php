# Arquidesk

<p align="center">
  <img src="https://arquidesk.com.br/favicon.ico" width="100" alt="Arquidesk">
</p>

<p align="center">
  <strong>Gestão inteligente para empresas de arquitetura, interiores, marcenaria e móveis planejados.</strong>
</p>

<p align="center">
  <a href="https://arquidesk.com.br">🌐 Site Oficial</a>
</p>

---

# Sobre o projeto

O **Arquidesk** é uma plataforma SaaS desenvolvida para empresas de arquitetura, design de interiores, marcenarias e móveis planejados que desejam substituir planilhas por uma plataforma moderna e centralizada.

A aplicação acompanha todo o fluxo operacional da empresa, desde o primeiro contato com o cliente até a finalização do projeto, incluindo financeiro, agenda, metas, comissões, histórico completo e gestão da equipe.

O sistema foi desenvolvido em **PHP puro**, utilizando **MySQL**, priorizando desempenho, simplicidade de implantação e compatibilidade com hospedagens compartilhadas como Hostinger.

---

# Principais funcionalidades

### Gestão de Projetos

- Cadastro de clientes
- Cadastro de projetos
- Pipeline completo
- Histórico de movimentações
- Status personalizados
- Controle por etapas

### Etapas do projeto

- Projeto
- Negociação
- Conferência
- Montagem
- Assistência
- Finalizado

### Gestão Financeira

- Registro de vendas
- Controle de pagamentos
- Parcelamentos
- Comissões
- Metas por vendedor/projetista

### Agenda

- Agenda manual
- Checklist diário
- Compromissos
- Lembretes

### CRM

- Clientes futuros
- Histórico de contatos
- Próximos retornos
- Conversão em projeto

### Administração

- Multiempresa
- Multiusuário
- Controle de permissões
- Personalização da empresa
- Gestão de assinaturas

---

# Perfis de acesso

- SUPER_ADMIN
- ADMIN_EMPRESA
- PROJETISTA
- CONFERENTE

Cada usuário possui permissões específicas dentro da plataforma.

---

# Tecnologias utilizadas

## Backend

- PHP 8+
- MySQL
- PDO

## Front-end

- HTML5
- CSS3
- Tailwind CSS
- JavaScript

## Hospedagem

- Hostinger
- Apache
- MariaDB

---

# Estrutura do projeto

```text
arquidesk-php/

app/
    config/
    includes/
    services/
    views/

database/
    schema.sql

public/
    index.php
    login.php
    setup.php
    dashboard.php

uploads/

README.md
```

---

# Como executar localmente

## Clone o projeto

```bash
git clone https://github.com/Iasminmins/arquidesk-php.git
```

Entre na pasta

```bash
cd arquidesk-php
```

---

## Banco de dados

Crie um banco MySQL chamado

```text
arquidesk
```

Depois importe

```text
database/schema.sql
```

---

## Configuração

Copie

```text
app/config/config.example.php
```

para

```text
app/config/config.php
```

Configure

```php
'db' => [
    'host' => '127.0.0.1',
    'name' => 'arquidesk',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
]
```

---

## Executando

```bash
php -S localhost:8080 -t public
```

Acesse

```
http://localhost:8080
```

Para criar a primeira empresa

```
http://localhost:8080/setup.php
```

---

# Deploy

O Arquidesk pode ser hospedado em qualquer servidor PHP compatível.

Processo de implantação:

- Upload dos arquivos
- Criação do banco MySQL
- Importação do schema
- Configuração do arquivo `config.local.php`
- Configuração do domínio

Projeto atualmente em produção:

## https://arquidesk.com.br

---

# Banco de dados

O projeto possui estrutura completa para:

- Empresas
- Usuários
- Projetos
- Histórico
- Financeiro
- Pagamentos
- Comissões
- Metas
- Agenda
- Checklist
- CRM
- Clientes futuros
- Assinaturas
- Recuperação de senha
- Importações
- Exportações

---

# Segurança

O sistema utiliza diversas práticas de segurança:

- Password Hash (`password_hash`)
- Password Verify
- CSRF Token
- PDO Prepared Statements
- Controle de Sessão
- Controle de Permissões
- Isolamento entre empresas (Multi-tenant)

---

# Objetivo

O Arquidesk foi criado para eliminar planilhas e centralizar toda a gestão operacional de empresas que trabalham com projetos personalizados, oferecendo mais organização, produtividade e controle sobre cada etapa do processo.

---

# Roadmap

Funcionalidades planejadas:

- Integração com WhatsApp
- Emissão de contratos
- Assinatura digital
- Integração com ERP
- Aplicativo Mobile
- Dashboard BI
- Notificações em tempo real
- Integração com calendário
- Relatórios avançados
- API pública

---

# Desenvolvedora

**Iasmin Oliveira**

Desenvolvedora Full Stack e fundadora do Arquidesk.

GitHub:
https://github.com/Iasminmins

Site:
https://arquidesk.com.br

---

⭐ Se este projeto foi útil para você, deixe uma estrela no repositório.