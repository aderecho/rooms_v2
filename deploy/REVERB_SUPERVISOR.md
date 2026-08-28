# Reverb, queued mail, and Supervisor

The committed Supervisor file assumes the production checkout is
`/var/www/cebu_rooms2` and PHP is `/usr/bin/php`. Update those paths in
`deploy/supervisor/cebu-rooms.conf` if the server differs.

Configure production `.env` without committing credentials:

```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-account@up.edu.ph
MAIL_PASSWORD=your-google-app-password
MAIL_FROM_ADDRESS=your-account@up.edu.ph
MAIL_FROM_NAME="${APP_NAME}"

REVERB_APP_ID=cebu-rooms
REVERB_APP_KEY=generate-a-long-random-value
REVERB_APP_SECRET=generate-another-long-random-value
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=rooms.example.edu
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=rooms.example.edu

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Install and start the managed processes:

```bash
cd /var/www/cebu_rooms2
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
npm ci
npm run build

sudo cp deploy/supervisor/cebu-rooms.conf /etc/supervisor/conf.d/cebu-rooms.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart cebu-rooms:*
sudo supervisorctl status cebu-rooms:*
```

Proxy Reverb from the public HTTPS virtual host:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}

location /apps {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_pass http://127.0.0.1:8080;
}
```

After each deployment, restart long-running processes so they load the new code:

```bash
php artisan queue:restart
php artisan reverb:restart
sudo supervisorctl status cebu-rooms:*
```

`MAIL_MAILER=log` writes email content to the Laravel log and never delivers it.
For Gmail SMTP, use an App Password rather than the normal account password.
