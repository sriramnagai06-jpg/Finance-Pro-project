# API & Functions Documentation

FinancePro uses a set of global helper functions located in `includes/functions.php`. These functions centralize core logic, security, and repetitive tasks.

## Security & Authentication

### `is_logged_in(): bool`
Checks if the current session has a valid `user_id`.

### `require_login(): void`
Enforces authentication. If `is_logged_in()` is false, redirects to `login.php`.

### `is_admin(): bool`
Checks if the current authenticated user has the `role = 'admin'`.

### `require_admin(): void`
Enforces admin access. Redirects non-admin users to the dashboard.

### `clean_input(string $data): string`
Sanitizes raw user input by trimming whitespace and escaping HTML entities to prevent basic XSS during insertion.

### `sanitize_output($data): mixed`
Recursively sanitizes variables or arrays before echoing them in HTML views to prevent XSS.

### `generate_csrf_token(): string`
Creates a secure random CSRF token, stores it in the session, and returns it for form injection.

### `verify_csrf_token(?string $token): bool`
Validates a submitted CSRF token against the session token.

---

## Utility & Formatting

### `set_flash(string $type, string $message): void`
Sets a flash message in the session to be displayed on the next page load. `$type` can be `success`, `danger`, `warning`, or `info`.

### `e($string): string`
A shorthand alias for `htmlspecialchars()` to safely escape strings in views.

### `format_currency(float $amount): string`
Formats an amount using the global system currency defined in `config.php`.

### `format_currency_custom(float $amount, array $settings): string`
Formats an amount based on the user's specific currency symbol and position preferences.

### `format_date(string $date): string`
Converts a MySQL date string (YYYY-MM-DD) to a readable format (e.g., "15 Jun 2026").

### `generate_invoice_number(mysqli $conn): string`
Auto-generates the next sequential invoice number for the current year (e.g., `INV-2026-0004`).

---

## Notifications & Logging (Phase 2/3)

### `audit_log($conn, int $user_id, string $action, string $table, int $record_id, string $desc): void`
Records critical actions (create, update, delete, login_failed) to the `audit_log` table for security and history tracking.

### `add_notification($conn, int $user_id, string $type, string $title, string $message): void`
Creates a new notification for a specific user. Triggers UI alerts on the dashboard.

### `get_unread_notifications_count($conn, int $user_id): int`
Returns the total count of unread notifications to render the red badge on the topbar bell icon.

---

## Settings (Phase 4)

### `get_user_settings($conn, int $user_id): array`
Fetches a user's custom settings (theme, tax rates, large expense limits). If none exist, returns a standard default array to prevent null reference errors.
