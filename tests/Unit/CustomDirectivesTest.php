<?php

declare(strict_types=1);

use Onhost\Domain\Services\Web\CustomDirectives;

/*
 * What a customer may write into their own vhost. The old check was a deny-list anchored at the start of a line; nginx
 * is not line-oriented, so one line with two blocks gave a reverse proxy into the management network and the node's /etc.
 */

it('accepts ordinary tuning of a site', function (string $kind, string $content) {
    expect(CustomDirectives::firstRefused($kind, $content))->toBeNull();
})->with([
    'nginx rewrites and headers' => ['nginx', "# pretty URLs\nlocation / {\n    try_files \$uri \$uri/ /index.php?\$args;\n}\nlocation ~* \\.(jpg|css|js)\$ { expires 30d; add_header Cache-Control \"public\"; }\nif (\$host = 'old.cz') { return 301 https://new.cz\$request_uri; }\nclient_max_body_size 64m;"],
    'aaPanel rewrite file' => ['rewrite', "rewrite ^/clanek/(\\d+)\$ /index.php?id=\$1 last;\nerror_page 404 /404.html;"],
    'apache headers and redirects' => ['apache', "# zabezpečení\nHeader set X-Frame-Options SAMEORIGIN\r\nRedirect 301 /old /new\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^blog/(.*)\$ /index.php?p=\$1 [L,QSA]\n</IfModule>\n<FilesMatch \"\\.(bak|sql)\$\">\nRequire all denied\n</FilesMatch>"],
]);

it('refuses whatever reaches outside the site, however it is laid out', function (string $kind, string $content) {
    expect(CustomDirectives::firstRefused($kind, $content))->not->toBeNull();
})->with([
    'nginx: the one-line bypass of the old check' => ['nginx', 'location /x { proxy_pass http://10.0.0.5:8888/; } location /y { root /etc; }'],
    'nginx: proxy after a block on the same line' => ['nginx', 'location /a { return 200; } proxy_pass http://127.0.0.1:8006;'],
    'nginx: a file served from outside' => ['nginx', 'location /etc { alias /etc/; }'],
    'nginx: a log written as root' => ['nginx', 'access_log /etc/cron.d/owned;'],
    'nginx: include' => ['rewrite', 'include /www/server/panel/vhost/nginx/*.conf;'],
    'nginx: a script engine' => ['nginx', 'location /l { content_by_lua_block { os.execute("id") } }'],
    'nginx: fastcgi to somebody else\'s pool' => ['nginx', "location ~ \\.php\$ {\n  fastcgi_pass unix:/tmp/php-cgi-other.sock;\n}"],
    'nginx: a directive hidden behind a # inside quotes' => ['nginx', 'add_header X-Note "a # b"; proxy_pass http://10.0.0.5:8888/;'],
    'nginx: a quoted directive name' => ['nginx', '"proxy_pass" http://10.0.0.5/;'],
    'nginx: a directive name on its own line' => ['nginx', "location /p {\nproxy_pass\n  http://10.0.0.5/;\n}"],
    'apache: include' => ['apache', 'Include /etc/apache2/other.conf'],
    'apache: indented, lower case' => ['apache', '   includeoptional /etc/passwd'],
    'apache: a directive split by a line continuation' => ['apache', "Inclu\\\nde /etc/shadow"],
    'apache: a handler' => ['apache', "<FilesMatch \"\\.jpg\$\">\nSetHandler application/x-httpd-php\n</FilesMatch>"],
    'apache: a proxy' => ['apache', 'ProxyPass /panel http://10.0.0.5:8888/'],
    'apache: a proxy through a rewrite flag' => ['apache', 'RewriteRule ^panel/(.*)$ http://10.0.0.5:8888/$1 [P,L]'],
    'apache: a rewrite map running a program' => ['apache', 'RewriteMap x prg:/usr/bin/id'],
    'apache: a log written as root' => ['apache', 'CustomLog /etc/cron.d/owned combined'],
    'apache: an alias out of the site' => ['apache', 'Alias /etc /etc'],
    'apache: php admin values' => ['apache', 'php_admin_value open_basedir /'],
    'apache: a CGI alias' => ['apache', 'ScriptAlias /cgi /tmp'],
    'control characters' => ['nginx', "return 200;\x00proxy_pass http://10.0.0.5;"],
]);
