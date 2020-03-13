# Erika

Simple custom structured data collecting service based on ClickHouse

## Requirements

- HTTP web server
- A CGI for communicating between web server and PHP (use PHP-FPM for Nginx)
- PHP `^7.4`
- Following PHP plugins: `php-curl` `php-json` `php-mbstring` `php-xml` `php-redis`
- composer (Debian/Ubuntu: `apt install composer`)
- Redis server (Debian/Ubuntu: `apt install redis-server`)
- ClickHouse server

## Installation

```bash
git pull git@git.oott123.com:tundra/erika.git
mv erika /srv/www/erika
cd /srv/www/erika
composer install
```

## Configuration

### Application Config

Copy `.env.example` to `.env` , and edit the items in it.

If you want to deploy it on an online environment, you should generate a new `APP_KEY` and replace the one given.

Fields start with `CLICKHOUSE_` should match the configuration of your local redis server.

Fields start with `REDIS_` should match the configuration of your local redis server.

Fields start with `USER_` are used for authorizing as admin user.

Do not delete any items.

### Web Server Config

Configuration of your web server should be same with apps using [Laravel Framework](https://laravel.com/docs/6.x#web-server-configuration).

For people using Nginx & PHP-FPM, here's a sample site config clip:

```nginx
server {

    listen 443;

    server_name erika.api.com;

    ssl on;
    ssl_certificate cert/star_api_com.crt;
    ssl_certificate_key cert/star_api_com.key;
    ssl_protocols TLSv1.2;
    ssl_ciphers 'CHACHA20:-DHE:AESGCM:AESCCM+ECDH:-SHA256:AES:+AES256:+DHE:+AESCCM8:+SHA256:-SHA384:+SHA:-RSA:-ECDH+ECDSA+SHA:!DSS:!PSK:!aNULL:!SRP:!aECDH';
    ssl_prefer_server_ciphers on;
    root /srv/www/erika/public;

  location / {
    index index.html index.php;
    if (!-e $request_filename) {
      rewrite ^/(.*)$ /index.php/$1 last;
      break;
    }
  }

# use socket to call php-fpm
  location ~ .+\.php($|/) {
    add_header Cache-Control private;
    fastcgi_pass unix:/run/php/php7.4-fpm.sock;
    include snippets/fastcgi-php.conf;
  }

}
```

## License

This application is open-sourced software licensed under [Apache 2.0](http://www.apache.org/licenses/LICENSE-2.0.html).
