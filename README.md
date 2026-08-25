# Faruk Laravel Starter

This repository is a fully configured, ready-to-use Laravel boilerplate designed to kickstart new API projects quickly. It includes essential features like authentication, role management, and an automated setup process so you can start building features immediately instead of configuring the basics.

## 🚀 Features Included

- **Laravel Framework** (v11.x)
- **Sanctum Authentication**: Ready for SPA or API token authentication.
- **Role & Permission Management**: Powered by `spatie/laravel-permission`.
- **Automated Setup Script**: One command to install dependencies, generate keys, and run migrations.
- **Socialite Integration**: Scaffolding for OAuth (Google, Facebook, etc.).

## 📦 Quickstart & Installation

To use this boilerplate for a new project, follow these steps:

1. **Clone the repository** to your local machine:
   ```bash
   git clone <repository-url> your-new-project-name
   cd your-new-project-name
   ```

2. **Run the setup script**:
   We've configured a single command to handle `composer install`, `.env` creation, key generation, and migrations.
   ```bash
   composer setup
   ```
   *(Note: The setup script will attempt to run migrations. If your database doesn't exist yet, it will prompt you to create it (if using MySQL/PostgreSQL) or automatically create an SQLite file).*

3. **Configure your Database** (if not using SQLite):
   Open your `.env` file and update your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```
   Then manually run `php artisan migrate` if it failed during step 2.

4. **Start the development server**:
   ```bash
   composer dev
   ```

## 🔒 Authentication & Roles

This boilerplate uses **Laravel Sanctum** for API authentication.
- To protect your routes, wrap them in the `auth:sanctum` middleware in `routes/api.php`.
- Users and roles can be managed using Spatie's permission package. See the [Spatie Documentation](https://spatie.be/docs/laravel-permission/v6/introduction) for advanced usage.

## 🛠 Usage & Best Practices

- **Keep it minimal**: This repository is meant to be a base. Do not add heavy dependencies unless 90% of your future projects will use them.
- **AI/Agent rules**: If using AI tools (like Cursor, Windsurf, or Copilot), consider adding an `agents.md` file to this repo to instruct the AI on your preferred coding style.

## 📝 License

This boilerplate is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
