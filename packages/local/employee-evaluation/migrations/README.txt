# Migrations

Place database migration files here for version upgrades.

Naming convention: NNNN_description.sql

Example:
- 0001_init.sql (created automatically from manifest)
- 0002_add_priority_field.sql
- 0003_create_comments_table.sql

Also create rollback scripts in rollback/ directory:
- 0002_rollback.sql
- 0003_rollback.sql

These are executed when upgrading or downgrading package versions.