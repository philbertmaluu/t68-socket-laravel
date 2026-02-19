# Deploy Electron/Next.js Frontend as Web on AlmaLinux

This guide explains how to run your **Electron frontend (Next.js)** as a **web application** on an AlmaLinux server—not as a desktop app. You use only the Next.js build and run it with Node.js behind Nginx.

---

## Prerequisites

- AlmaLinux server with the frontend at **`/web/qms/frontend`**.
- Node.js **20.x LTS** installed (see [ALMALINUX_DEPLOYMENT_GUIDE.md](./ALMALINUX_DEPLOYMENT_GUIDE.md) for install steps).
- Nginx installed.
- (Optional) PM2 for process management: `sudo npm install -g pm2`.

---

## 1. Build the frontend for web (no Electron)

On the server, in the **frontend** directory:

```bash
cd /web/qms/frontend

# Install dependencies
npm ci --omit=dev     # production deps only (or use npm install if no package-lock)

# Build Next.js (produces standalone output)
npm run build
```

Your Next.js config uses `output: 'standalone'`, so the build creates:

- `.next/standalone/` – Node server + app (what you run).
- `.next/static/` – static assets (must be available next to standalone).

---

## 2. Prepare the standalone deploy folder

Next.js standalone expects `static` and (if you use it) `public` to be next to the standalone app:

```bash
# Still in /web/qms/frontend

# Copy static assets into standalone
cp -r .next/static .next/standalone/.next/static

# If you have a public folder (images, etc.), copy it too
if [ -d public ]; then
  cp -r public .next/standalone/public
fi
```

Your runnable app is now under `/web/qms/frontend/.next/standalone/`.

---

## 3. Run the Next.js server

### Option A: Run directly (quick test)

```bash
cd /web/qms/frontend/.next/standalone
PORT=3000 node server.js
```

Visit `http://YOUR_SERVER_IP:3000`. Default port is 3000; set `PORT` if you want another.

### Option B: Run with PM2 (recommended for production)

From the **frontend** directory (so paths are correct):

```bash
cd /web/qms/frontend

# Start Next.js using the standalone server
PORT=3000 pm2 start .next/standalone/server.js --name "queueing-web"

# Save PM2 process list so it survives reboot
pm2 save
pm2 startup   # run the command it prints to enable on boot
```

Or use an `ecosystem.config.cjs` in `/web/qms/frontend`:

```javascript
// /web/qms/frontend/ecosystem.config.cjs
module.exports = {
  apps: [{
    name: 'queueing-web',
    script: '.next/standalone/server.js',
    cwd: '/web/qms/frontend',
    instances: 1,
    exec_mode: 'fork',
    env: {
      NODE_ENV: 'production',
      PORT: 3000,
    },
  }],
};
```

Then:

```bash
cd /web/qms/frontend
pm2 start ecosystem.config.cjs
pm2 save && pm2 startup
```

---

## 4. Put Nginx in front (reverse proxy)

So the app is served on port 80/443 and you can add SSL later.

Create a vhost (e.g. `/etc/nginx/conf.d/queueing-web.conf`):

```nginx
server {
    listen 80;
    server_name your-domain.com;   # or your server IP for testing

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

Then:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Visit `http://your-domain.com` (or `http://YOUR_SERVER_IP`). The Next.js app is now served as a website.

---

## 5. Environment variables (API, Reverb, etc.)

If the frontend talks to your Laravel API or Reverb, set env vars before starting the server.

**Option A – `.env.production` in `/web/qms/frontend`**

```bash
cd /web/qms/frontend
nano .env.production
```

Example:

```env
NEXT_PUBLIC_API_URL=https://api.your-domain.com
NEXT_PUBLIC_WS_HOST=your-domain.com
NEXT_PUBLIC_WS_PORT=8080
NEXT_PUBLIC_WS_SCHEME=wss
```

Rebuild after changing `NEXT_PUBLIC_*` (they are baked in at build time):

```bash
npm run build
# Then copy static/public again and restart PM2
cp -r .next/static .next/standalone/.next/static
[ -d public ] && cp -r public .next/standalone/public
pm2 restart queueing-web
```

**Option B – PM2 env**

In `ecosystem.config.cjs`:

```javascript
env: {
  NODE_ENV: 'production',
  PORT: 3000,
  NEXT_PUBLIC_API_URL: 'https://api.your-domain.com',
  // ...
},
```

Only server-side env vars can be set at runtime; `NEXT_PUBLIC_*` must be present at build time.

---

## 6. SSL (HTTPS) with Let’s Encrypt

```bash
sudo dnf install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

Follow prompts. Nginx will be updated to use HTTPS.

---

## 7. Firewall

Allow HTTP/HTTPS and (if needed) Reverb port:

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
# If Reverb runs on 8080:
# sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```

---

## 8. Summary checklist

| Step | Action |
|------|--------|
| 1 | `cd /web/qms/frontend` → `npm ci` → `npm run build` |
| 2 | Copy `.next/static` and `public` into `.next/standalone` |
| 3 | Run with PM2: `node .next/standalone/server.js` (PORT=3000) |
| 4 | Nginx reverse proxy to `http://127.0.0.1:3000` |
| 5 | Set `NEXT_PUBLIC_*` in `.env.production`, rebuild if changed |
| 6 | (Optional) SSL with certbot, open firewall |

---

## 9. Useful commands

```bash
# Rebuild after code/config change
cd /web/qms/frontend
npm ci && npm run build
cp -r .next/static .next/standalone/.next/static
[ -d public ] && cp -r public .next/standalone/public
pm2 restart queueing-web

# Logs
pm2 logs queueing-web

# Status
pm2 status
```

---

## 10. Troubleshooting

- **Blank or 404 on refresh**  
  Ensure Nginx uses `proxy_pass` to the Next.js app and that `try_files` is not overwriting SPA-style routes; the config above is suitable for Next.js.

- **502 Bad Gateway**  
  Next.js not running or wrong port. Check `pm2 status` and `curl http://127.0.0.1:3000`.

- **API/WebSocket errors**  
  Check `NEXT_PUBLIC_*` and Reverb/API URLs; rebuild if you changed `NEXT_PUBLIC_*`.

- **Permission denied**  
  Ensure the user running PM2/Node can read `.next/standalone` and that Nginx can connect to `127.0.0.1:3000`.

You now have the Electron/Next.js frontend running as a **web app** on AlmaLinux, without building or running the Electron desktop app.
