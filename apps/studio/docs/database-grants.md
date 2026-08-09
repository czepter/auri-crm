# Database grants

The application connects as a role that cannot rewrite history. Run once per
environment, as a superuser, after the first migration:

```sql
REVOKE UPDATE, DELETE, TRUNCATE ON TABLE audit_logs FROM studio_app;
GRANT INSERT, SELECT ON TABLE audit_logs TO studio_app;
```

Migrations run as a separate, more privileged role (`studio_migrator`) so that
schema changes remain possible while the request-path role stays constrained.

This is intentionally not applied by a migration: a migration running as the
app role could simply grant the privileges back, which would make the control
decorative.
