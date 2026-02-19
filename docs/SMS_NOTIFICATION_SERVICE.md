# SMS Notification Service Documentation

## Overview

The SMS Notification Service integrates with the ICTMS API to send SMS notifications to customers when tickets are created and completed. The service is event-driven and automatically sends notifications when specific ticket events occur.

## Features

- ✅ Automatic SMS notifications on ticket creation
- ✅ Automatic SMS notifications on ticket completion
- ✅ Configurable via environment variables
- ✅ Comprehensive error handling and logging
- ✅ Phone number validation and cleaning
- ✅ Retry mechanism for failed requests
- ✅ Swahili message templates

## Configuration

Add the following to your `.env` file:

```env
# ICTMS SMS Notification Service
ICTMS_API_ENDPOINT=https://ictms-api.nssf.go.tz/api/send-notification
ICTMS_SYSTEM=ICTMS
ICTMS_SMS_ENABLED=true
```

### Configuration Options

- `ICTMS_API_ENDPOINT`: The API endpoint for sending notifications (default: provided endpoint)
- `ICTMS_SYSTEM`: The system identifier (default: 'ICTMS')
- `ICTMS_SMS_ENABLED`: Enable/disable SMS notifications (default: true)

## How It Works

### Event-Driven Architecture

The service uses Laravel's event system:

1. **TicketCreated Event**: Fired when a new ticket is created
   - Listener: `SendTicketCreatedSms`
   - Sends SMS with ticket number and service type

2. **TicketCompleted Event**: Fired when a ticket is completed
   - Listener: `SendTicketCompletedSms`
   - Sends SMS confirming service completion

### Message Templates

#### Ticket Created Message
```
Ndugu {Member Name},
Tiketi yako namba {Ticket Number} imeundwa kwa ajili ya huduma ya {Service Type}.
Tafadhali subiri kuitwa kwenye counter.
Asante - NSSF
```

#### Ticket Completed Message
```
Ndugu {Member Name},
Huduma yako kwa tiketi namba {Ticket Number} ({Service Type}) imekamilika.
Muda uliotumika: {Duration} dakika.
Asante kwa kutumia huduma za NSSF.
Karibu tena!
```

## Service Class

### NotificationService

Located at: `app/Services/NotificationService.php`

#### Methods

##### `sendSms()`
Send a generic SMS notification.

```php
$notificationService = app(NotificationService::class);

$result = $notificationService->sendSms(
    recipient: '0718206671',
    message: 'Your message here',
    process: 'MEMBER SMS',
    expiryHours: 4,
    attachment: null
);

if ($result['success']) {
    // SMS sent successfully
} else {
    // Handle error
    Log::error($result['message']);
}
```

##### `sendTicketCreatedNotification()`
Send SMS when a ticket is created.

```php
$result = $notificationService->sendTicketCreatedNotification($ticket);
```

##### `sendTicketCompletedNotification()`
Send SMS when a ticket is completed.

```php
$result = $notificationService->sendTicketCompletedNotification($ticket);
```

## Listeners

### SendTicketCreatedSms

- **Event**: `TicketCreated`
- **Location**: `app/Listeners/SendTicketCreatedSms.php`
- **Behavior**: 
  - Checks if phone number exists
  - Checks if SMS is enabled
  - Sends notification via NotificationService
  - Logs success/failure

### SendTicketCompletedSms

- **Event**: `TicketCompleted`
- **Location**: `app/Listeners/SendTicketCompletedSms.php`
- **Behavior**: 
  - Checks if phone number exists
  - Checks if SMS is enabled
  - Sends notification via NotificationService
  - Logs success/failure

## Error Handling

The service includes comprehensive error handling:

1. **Validation**: Phone number and message validation
2. **HTTP Errors**: Handles API response errors
3. **Exceptions**: Catches and logs all exceptions
4. **Retry Logic**: Automatic retry (3 attempts with 1 second delay)
5. **Logging**: All operations are logged for debugging

## Logging

All SMS operations are logged:

- **Info**: Successful SMS sends
- **Warning**: Failed SMS sends (with error details)
- **Error**: Exceptions and critical errors
- **Debug**: Skipped notifications (no phone number, disabled)

View logs:
```bash
tail -f storage/logs/laravel.log | grep SMS
```

## Testing

### Manual Testing

You can test the service directly:

```php
use App\Services\NotificationService;
use App\Domains\Ticket\Models\Ticket;

$notificationService = app(NotificationService::class);
$ticket = Ticket::find(1);

// Test ticket created notification
$result = $notificationService->sendTicketCreatedNotification($ticket);

// Test ticket completed notification
$result = $notificationService->sendTicketCompletedNotification($ticket);
```

### Disable for Testing

Set in `.env`:
```env
ICTMS_SMS_ENABLED=false
```

## Requirements

- Ticket must have a `phone_number` field populated
- ICTMS API must be accessible
- Valid API endpoint configured

## Troubleshooting

### SMS Not Sending

1. Check if SMS is enabled: `ICTMS_SMS_ENABLED=true`
2. Verify phone number exists on ticket
3. Check API endpoint is correct
4. Review logs: `storage/logs/laravel.log`
5. Verify network connectivity to ICTMS API

### Common Issues

**Issue**: "No phone number available"
- **Solution**: Ensure ticket has `phone_number` field populated

**Issue**: "ICTMS API endpoint is not configured"
- **Solution**: Add `ICTMS_API_ENDPOINT` to `.env`

**Issue**: API timeout
- **Solution**: Check network connectivity, API may be down

## Future Enhancements

- Queue SMS sending for better performance
- Support for multiple languages
- Custom message templates per service type
- SMS delivery status tracking
- Rate limiting and throttling
