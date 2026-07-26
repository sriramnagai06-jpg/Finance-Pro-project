# Project Structure

FinancePro uses a simplified MVC-inspired directory structure to separate logic, presentation, and assets.

```text
FinancePro/
│
├── admin/                  # Admin-specific pages
│   ├── dashboard.php       # Platform overview and stats
│   └── users.php           # User management (block, delete)
│
├── assets/                 # Static assets (CSS, JS, Fonts, Images)
│   ├── css/
│   │   ├── style.css             # Main styling, typography, colors
│   │   ├── style-dashboard.css   # Layout, cards, sidebar styles
│   │   └── dark-mode.css         # Dark theme overrides
│   └── js/
│       └── app.js                # UI interactions (toasts, toggles)
│
├── database/               # SQL files for schema creation & updates
│   ├── financepro.sql            # Base schema and seed data
│   └── migrations/               # Phase 2 advanced schema updates
│
├── docs/                   # Documentation files (README, ER Diagrams)
│
├── includes/               # Reusable PHP components
│   ├── header.php          # Top navigation bar
│   ├── sidebar.php         # Left sidebar navigation
│   ├── functions.php       # Core business logic and helper functions
│   └── alerts.php          # Flash message rendering component
│
├── user/                   # User-facing features and modules
│   ├── dashboard.php       # Main user dashboard and charts
│   ├── income.php          # Income tracking (CRUD)
│   ├── expenses.php        # Expense tracking (CRUD)
│   ├── budget.php          # Budget planning (CRUD)
│   ├── reports.php         # Monthly/Yearly financial reports
│   ├── invoices.php        # Invoice generator list
│   ├── invoice_view.php    # Printable invoice view
│   ├── gst_calculator.php  # Tax calculator tool
│   ├── settings.php        # User preferences and company info
│   ├── profile.php         # User profile management
│   ├── change_password.php # Password update form
│   └── notifications.php   # System alerts and notifications
│
├── uploads/                # User uploaded content (ignored in git)
│   ├── profile/            # Profile avatars
│   └── logos/              # Company logos for invoices
│
├── config.php              # Global configuration and DB connection
├── login.php               # Authentication entry point
├── register.php            # User registration
├── logout.php              # Session destruction
└── README.md               # Main project overview
```
