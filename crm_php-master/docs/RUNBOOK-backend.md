RUNBOOK - Backend (crm_php-master)

Entry and Routing
- Entry file: `index.php` in project root
- Route config list: `config/config.php` -> `route_config_file`
  - Files: `config/route_admin.php`, `config/route_crm.php`, `config/route_oa.php`,
    `config/route_bi.php`, `config/route_work.php`

Nginx (example)
server {
    listen 80;
    server_name 192.168.10.15;
    root E:/code/workspace/crm/crm_php-master;
    index index.php index.html;
    location / {
        try_files $uri $uri/ /index.php?s=$uri&$args;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
    }
}

PHP Extensions
- Required by composer: ext-pdo, ext-json
- Typically needed by this app:
  - pdo_mysql (MySQL), mbstring, curl, openssl, gd
  - redis (cache config defaults to redis in `config/config.php`)

Logs
- App logs: `runtime/log`
- Temp files: `runtime/temp`

Minimal Health Check
- GET `http://192.168.10.15/index.php/admin/base/getVerify`
  - Should return a captcha image (verifies routing + PHP-FPM)
