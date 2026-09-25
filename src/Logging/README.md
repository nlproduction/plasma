# Optional logging

The ORM does not require a logger. Prefer your application's logging system through a `DatabaseEventListener`.

The optional utilities retained in this package are:

- `Logger`: static Clockwork/file logging helpers; Clockwork must be installed and initialized separately.
- `ImprovedLogger`: instance-based callable handlers for custom, file, console, or Clockwork output. It is not an implementation of the PSR-3 interface.
- `ClockworkListener`: forwards database events after `Logger::init(['logToClockwork' => true])`.

Install `itsgoingd/clockwork` explicitly when using it. Do not enable query logging with sensitive payloads in production without redaction and access controls.
