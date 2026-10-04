# Production deployment

Only `public/` may be exposed by the web server. Never point a document root at the project directory. Keep `.env`, `runtime/`, `src/`, `database/`, and `tests/` outside the public document root.

Before deployment:

```bash
aml install --production
aml env:set APP_ENV production
aml env:set APP_DEBUG false
aml env:set APP_URL https://example.com
aml doctor --production
```

Set a unique `APP_KEY` of at least 32 random characters, enable HTTPS, make `runtime/storage` and `runtime/cache` writable only by the application user, and back up the database before migrations. `aml serve` is development-only.

## Apache

Set `DocumentRoot` to `/path/to/project/public`, allow overrides for that directory, and enable `mod_rewrite`. The included `public/.htaccess` sends non-file requests to `public/index.php` and blocks internal extensions.

## Nginx

```nginx
root /path/to/project/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}

location ~ /\. { deny all; }
```

Adjust the PHP-FPM socket for the host and terminate TLS at Nginx or a trusted proxy.

## Shared hosting

Configure the domain document root as the project's `public/` directory. If the provider forces `public_html`, place only the contents of `public/` there and keep the rest of the project one level above; update `public/index.php` paths for that layout. Confirm that `.env`, SQLite files, source PHP, Composer files, tests, and archives return 404 or 403.
