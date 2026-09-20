# cPanel Shared Hosting Episode Button Portal & Shortlink Engine

A secure, private, high-performance web portal built specifically for standard cPanel shared hosting (Apache / LiteSpeed, PHP 7.4 - 8.3+, and MySQL / MariaDB).

---

## 🚀 Key Architecture & Features

### 1. Private Administrator Authentication & Isolation
- **No Public Hooks**: Absolutely no links, buttons, clues, or hooks for admin login, account creation, or private functions exist on any public-facing pages.
- **One-Time Account Setup (`setup.php`)**: When the system is first installed, the admin creates the primary superadmin account. Once created, the setup page **self-locks completely**, blocking any future registration attempts.
- **Private Sign-In (`login.php`)**: Dedicated private login portal backed by MySQL and PHP `password_hash()` (BCrypt).
- **Private Functions**: The link resolver, Blogspot scraper, and button extractor are private APIs restricted to authenticated admins only.

### 2. Streamlined Administrator Workflow
- **Automated Flow**: Admin pastes a shortened link (e.g., `https://shrt.sohojgyan.com/Ij03ndJ` or `AAhg`).
- **Resolver**: Automatically hops through Sohojgyan / AdLinkFly gateways until reaching the target Blogspot destination post.
- **Extractor**: Automatically parses download server buttons, qualities (720p/1080p), and episode numbers.
- **Instant Publishing**: Publishes a clean button page and provides a 1-click copyable clean public link (`/p/{slug}`).

### 3. Dedicated Admin Pages

#### `/pages` (Pages Management - `pages.php`):
- View all created episode button pages in an organized dashboard.
- **Public / Private Visibility Toggle**: Instantly switch any page between `Public` and `Private` with 1 click. Private pages immediately return 404 to public visitors.
- **Full Editor**: Edit page titles, URLs/slugs, descriptions, theme colors, and view/add/edit/delete individual episode buttons.
- **Analytics & Actions**: Track view counts, copy clean links, and permanently delete pages.

#### `/settings` (Portal Settings - `settings.php`):
- **Public Navigation Menu Manager**:
  - Pre-loaded with requested defaults:
    1. **Home** (`https://moviehubhq.com/`)
    2. **Korean Drama** (`https://moviehubhq.com/catagory/korean/`)
    3. **Chinese Drama** (`https://moviehubhq.com/catagory/chinese/`)
  - Create **unlimited** menu items with custom button names, destination links, and open-in-new-tab options.
  - Edit and delete menu items dynamically.
- **Public Footer Copyright Editor (HTML Allowed)**:
  - Full HTML text editor for copyright notices, DMCA links, or site descriptions.
  - Includes real-time live preview before saving.

### 4. Public Non-Indexable Button Pages (`/p/{slug}` or `view.php`)
- **Strictly Non-Indexable**:
  - `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex` HTTP headers.
  - `<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">`.
  - Googlebot and Bingbot directives preventing indexing or caching.
- **Mobile Hamburger Menu & Desktop Navigation**:
  - Displays dynamic navigation buttons on desktop.
  - Responsive mobile hamburger menu drawer with smooth toggle on smartphones.
- **Zero Admin Footprint**: No admin references, login buttons, or administrative links are visible to public visitors.

---

## 🛠️ cPanel Deployment Instructions

### Step 1: Create a MySQL Database in cPanel
1. Log into your **cPanel** dashboard.
2. Go to **MySQL Database Wizard**.
3. Create a new database (e.g., `user_moviehub`).
4. Create a database user with a strong password and assign **ALL PRIVILEGES** to the database.

### Step 2: Upload Files
1. Open **cPanel File Manager**.
2. Navigate to your target directory (e.g., `public_html` or a subdomain directory).
3. Upload all files from the `cpanel-package/` directory.

### Step 3: Configure Database in `config.php`
Open `config.php` in the cPanel File Editor and set your MySQL credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'user_moviehub');
define('DB_USER', 'user_dbuser');
define('DB_PASS', 'your_mysql_password_here');
```
*(Note: Tables `users`, `pages`, and `settings` are created automatically by `SLEA_DB` on first launch!)*

### Step 4: Run One-Time Admin Setup
1. Visit `https://yourdomain.com/setup.php` in your browser.
2. Enter your desired **Admin Username**, **Email**, and **Secure Password**.
3. Click **Create Administrator Account**.
4. The page will immediately lock itself, preventing any future account registrations.

### Step 5: Start Creating Button Pages
1. Visit `https://yourdomain.com/login.php` to sign into your private dashboard.
2. Paste any shortened URL into the generator box to create episode pages.
3. Access `/pages` to manage public/private visibility and edit pages.
4. Access `/settings` to manage site branding, logo, navigation menu buttons, and footer copyright.

---

## 🔄 Updating / Re-uploading New Files (`update.php`)

Whenever you upload updated PHP script files to your cPanel hosting:
1. Log in as an administrator.
2. Click the **Migrate** icon button in the left sidebar, or visit `https://yourdomain.com/update.php`.
3. Click **Run Database Migration Now**.
4. The migration script will automatically:
   - Check tables & add any new schema columns safely without touching existing data.
   - Synchronize site branding and default configurations.
   - Verify the Blogspot structured destination resolution pipeline (`https://mydverse02.blogspot.com/p/*.html`).

