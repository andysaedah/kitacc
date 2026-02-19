# KiTAcc Database Migrations

Timestamped SQL migration files for applying incremental schema changes to existing databases.

## How to Use

### For fresh installs
Run the base schema and seed first:
```sql
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```
Then apply the migration tracker:
```sql
mysql -u root -p < migrationdb/000_init_migration_table.sql
```

### For existing databases
1. Run the migration tracker (if not done yet):
```sql
mysql -u root -p < migrationdb/000_init_migration_table.sql
```

2. Apply pending migrations in order:
```sql
mysql -u root -p < migrationdb/001_add_branch_id_to_funds.sql
```

3. Check which migrations have been applied:
```sql
SELECT * FROM migrations ORDER BY id;
```

## Naming Convention

```
NNN_short_description.sql
```
- `NNN` — Sequential 3-digit number (001, 002, 003…)
- `short_description` — Lowercase, underscored summary

## Rules
- Each migration records itself in the `migrations` table on success
- **Never modify** a migration that has already been applied to production
- Always test against a backup first
