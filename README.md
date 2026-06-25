# Arquidesk PHP

Versao inicial em PHP puro + MySQL/MariaDB para hospedagem compartilhada.

## Rodar localmente

1. Crie um banco MySQL/MariaDB.
2. Importe `database/schema.sql`.
3. Copie `app/config/config.example.php` para `app/config/config.php`.
4. Ajuste os dados do banco em `config.php`.
5. Rode:

```bash
php -S 127.0.0.1:8080 -t public
```

6. Acesse `http://127.0.0.1:8080/setup.php` para criar o primeiro administrador.

## Hostinger

Guia completo de deploy (ZIP, FTP, arquivos a enviar): **[DEPLOY-HOSTINGER.md](DEPLOY-HOSTINGER.md)**

Resumo:

1. Envie `app/`, `database/`, `public/` e `.htaccess` para a raiz do site.
2. Crie `app/config/config.local.php` a partir de `config.local.example.php` com os dados do MySQL da Hostinger.
3. Acesse `/install-database.php` (primeira vez) e depois `/setup.php`.

Se usar `public_html`, copie o conteudo da pasta `public` para `public_html` e mantenha `app`,
`database` e `uploads` fora do acesso publico quando possivel.
