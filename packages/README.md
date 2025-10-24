# Section Packages Directory Structure

This directory manages section packages for import, export, and sharing.

## Directory Structure

```
packages/
├── local/          # User-created packages (exported from this installation)
├── imported/       # Packages imported from other installations
├── marketplace/    # Downloaded from marketplace/repository
└── temp/          # Temporary files during import/export (auto-cleaned)
```

## Package Format

Section packages are stored as `.hubpkg` files (JSON format):

```json
{
  "format_version": "1.0.0",
  "package": {
    "id": "organization.section-name",
    "name": "section-name",
    "display_name": "Section Display Name",
    "version": "1.0.0",
    ...
  },
  "fields": [...],
  "permissions": {...}
}
```

## Usage

### Exporting a Section
1. Admin Dashboard → Section Builder
2. Select section to export
3. Click "Export Package"
4. File saved to `packages/local/`

### Importing a Section
1. Admin Dashboard → Section Builder
2. Click "Import Package"
3. Upload `.hubpkg` file
4. Package extracted to `packages/imported/`
5. Review and install

## File Permissions

- Owner: `rsullivan` (or web user)
- Group: `www-data` (web server)
- Permissions: `775` (rwxrwxr-x)

This allows both CLI scripts and web server to read/write packages.

## Security Notes

- `.hubpkg` files are validated before import
- Malicious code checks performed
- SQL injection prevention in field definitions
- File size limits enforced (default: 10MB per package)

## Cleanup

Temp files older than 24 hours are automatically deleted by cron job:
```bash
# Add to crontab
0 2 * * * find /var/www/woodson/thehub/packages/temp -type f -mtime +1 -delete
```
