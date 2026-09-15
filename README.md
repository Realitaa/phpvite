# Vite + Plain PHP Starter Template

A modern, lightweight starter template combining **Vite 8** with **Plain PHP**.

Follows the standard Vite Vanilla JavaScript project structure, replacing `index.html` with `index.php` as the server-rendered entry point — without framework bloat (no Laravel, no `public/index.php` rewrite complexity).

---

## Features

- ⚡ **Vite 8 + HMR**: Instant server start and blazing-fast Hot Module Replacement.
- 🐘 **Plain PHP Entry**: Use `index.php` as your main template with full access to PHP runtime and environment variables.
- 🎯 **Vanilla JavaScript**: Clean, standard ES module architecture (`src/main.js`, `src/style.css`, `src/counter.js`).
- 📦 **Automated Production Pipeline**: Production builds output hashed assets and a manifest (`dist/.vite/manifest.json`), loaded automatically by PHP.
- 🛡️ **Zero Runtime Overhead in Dev**: PHP automatically switches between Vite dev server and production manifest using an ephemeral hot-file.
- 🧪 **PHPUnit Test Suite**: Fully tested PHP adapter with isolated unit tests.

---

## Project Structure

```text
phpvite/
├── .env                  # Environment configuration
├── .env.example          # Environment configuration example
├── .gitignore            # Standard Vite + PHP ignores
├── composer.json         # PHP dependencies & PSR-4 autoloading
├── package.json          # Node dependencies & Vite scripts
├── phpunit.xml           # PHPUnit test configuration
├── vite.config.js        # Vite build & hot-file plugin configuration
├── README.md             # Project documentation
│
├── index.php             # Main application entry point (replaces index.html)
│
├── app/                  # PHP application backend & adapter
│   └── Vite/             # Lightweight PHP adapter
│       ├── DevServer.php # Concurrent dev server runner
│       ├── Vite.php      # Manifest & dev server resolver class
│       └── helpers.php   # Global vite() helper function
│
├── public/               # Static public assets (copied directly to dist)
│   ├── favicon.svg
│   └── icons.svg
│
├── src/                  # Clean Vite frontend source code
│   ├── assets/           # Bundled assets (images, SVGs)
│   ├── counter.js        # Interactive counter component
│   ├── main.js           # Main JavaScript entry point
│   └── style.css         # Main stylesheet
│
├── storage/              # Runtime directory
│   └── vite.hot          # Hot-file indicator (created while Vite dev runs)
│
├── dist/                 # Vite build output (generated on build)
│   ├── .vite/
│   │   └── manifest.json
│   └── assets/
│
└── tests/                # PHP unit tests
    └── ViteTest.php
```

---

## Getting Started

### Prerequisites

- **PHP** 8.2 or higher
- **Composer** 2.x
- **Node.js** 20 or higher
- **pnpm** (or npm / yarn)

### Installation

1. Clone or use this repository as a GitHub template:
   ```bash
   git clone https://github.com/Realitaa/phpvite.git
   cd phpvite
   ```

2. Install dependencies:
   ```bash
   pnpm install
   composer install
   ```

   > **Tip (Package Manager):** This repository uses **pnpm** by default. However, if you prefer **npm**, **yarn**, or **bun**, simply delete the `pnpm-lock.yaml` file and install using your preferred package manager (e.g., `npm install` or `bun install`). The `composer dev` command will detect and use it automatically.

3. Setup environment file:
   ```bash
   cp .env.example .env
   ```

---

## Development Workflow

Start both the Vite development server and the PHP built-in server concurrently with a single command powered by `spatie/fork`:

```bash
composer run dev
# or: composer dev
```

Open your browser at **`http://localhost:8000`**.

### How Development Mode Works

1. `composer run dev` forks two concurrent processes: Vite dev server (`http://localhost:5173`) and PHP built-in server (`http://localhost:8000`).
2. Vite writes its active address to `storage/vite.hot`.
3. When `index.php` is rendered, `vite()->tags('src/main.js')` detects `storage/vite.hot`.
4. It emits the Vite HMR client script followed by the entry script:
   ```html
   <script type="module" src="http://localhost:5173/@vite/client"></script>
   <script type="module" src="http://localhost:5173/src/main.js"></script>
   ```
5. JavaScript and CSS changes update instantly in the browser via HMR.
6. When you stop the server (`Ctrl+C`), both processes terminate gracefully and `storage/vite.hot` is automatically cleaned up.

---

## Production Workflow

To build and serve production assets:

```bash
# 1. Build optimized assets with Vite
pnpm build

# 2. Start your PHP web server
php -S localhost:8000
```

### How Production Mode Works

1. Vite bundles and hashes your assets into `dist/` and writes `dist/.vite/manifest.json`.
2. With no `storage/vite.hot` present, PHP operates in production mode.
3. PHP reads the manifest, extracts required CSS `<link>` tags and the hashed JavaScript `<script type="module">` tag:
   ```html
   <link rel="stylesheet" href="/dist/assets/main-CsUDhMuy.css">
   <script type="module" src="/dist/assets/main-ChP9mZAA.js"></script>
   ```
4. Manifest content is cached in memory per request for maximum performance.

---

## PHP Helper API

### `vite()->tags(string $entry): string`

Generates the required `<script>` and `<link>` tags for an entry file:

```php
<?= vite()->tags('src/main.js') ?>
```

- **JS Entries:** Emits `<script type="module">` and associated CSS links.
- **CSS Entries:** Emits `<link rel="stylesheet">`.
- **Security:** All generated URLs are safely escaped against XSS.

### `vite()->asset(string $entry): string`

Resolves the raw public URL for an asset entry:

```php
<img src="<?= vite()->asset('src/assets/logo.png') ?>">
```

### `vite()->isRunning(): bool`

Returns `true` when the Vite development server is actively running.

---

## Testing

Run unit tests using Composer:

```bash
composer run test
# or: composer test
```

---

## License

MIT License. Feel free to use this template for your personal or commercial projects.
