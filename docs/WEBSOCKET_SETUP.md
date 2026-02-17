# WebSocket Setup Guide

## Backend Configuration

### 1. Set Environment Variables

Add the following to your `.env` file:

```env
# Enable Reverb broadcasting
BROADCAST_CONNECTION=reverb

# Reverb Application Credentials
# Generate these by running: php artisan reverb:install
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret

# Reverb Server Configuration
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb Server Host (for the WebSocket server itself)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

### 2. Generate Reverb Keys

Run this command to generate the app credentials:

```bash
php artisan reverb:install
```

This will:
- Generate `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`
- Update your `.env` file with these values

### 3. Start Reverb Server

Start the Reverb WebSocket server:

```bash
php artisan reverb:start
```

The server will start on `0.0.0.0:8080` (or your configured port).

### 4. Verify Broadcasting is Enabled

Check that `BROADCAST_CONNECTION=reverb` is set in your `.env` file. If it's set to `null`, broadcasting will be disabled and events won't be sent to Reverb.

### 5. Test the Connection

1. Open the WebSocket Test page in your frontend
2. Enter the `REVERB_APP_KEY` from your Laravel `.env` file
3. Click "Connect"
4. Check the browser console (F12) for connection logs

## Troubleshooting

### Connection Error: "Connection unavailable"
- **Solution**: Ensure Reverb server is running (`php artisan reverb:start`)
- Check that the port (8080) is not blocked by firewall

### Connection Error: "Connection failed"
- **Solution**: Verify `REVERB_APP_KEY` matches between frontend and backend
- Check that `BROADCAST_CONNECTION=reverb` is set in `.env`
- Restart Reverb server after changing `.env` values

### Events Not Received
- **Solution**: 
  - Verify events implement `ShouldBroadcast` interface
  - Check that `BROADCAST_CONNECTION=reverb` is set
  - Ensure you're subscribed to the correct channels
  - Check browser console for subscription logs

### Broadcasting Still Disabled
- **Solution**: 
  - Verify `BROADCAST_CONNECTION=reverb` in `.env`
  - Run `php artisan config:clear` to clear cached config
  - Restart Laravel application

## Frontend Configuration

In your frontend, set the environment variable or configure in the test page:

```env
NEXT_PUBLIC_REVERB_APP_KEY=your-app-key-from-laravel-env
```

Or enter it directly in the WebSocket Test page interface.

## Common Issues

1. **"Cannot connect"**: Reverb server not running
2. **"Invalid credentials"**: App key mismatch between frontend and backend
3. **"Broadcasting disabled"**: `BROADCAST_CONNECTION` not set to `reverb`
4. **"Port already in use"**: Another process is using port 8080
