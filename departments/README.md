# Department Code Workspace

Use each folder below as a workspace for that department's code:

| Department | Folder |
|---|---|
| Front Office (`FRONT_OFFICE`) | `front-office/` |
| Management (`MANAGEMENT`) | `management/` |
| Housekeeping (`HOUSEKEEPING`) | `housekeeping/` |
| Food and Beverage (`FNB`) | `food-and-beverage/` |
| Kitchen and Food Production (`KITCHEN`) | `kitchen/` |
| Maintenance (`MAINTENANCE`) | `maintenance/` |
| Accounts and Finance (`FINANCE`) | `finance/` |
| Security (`SECURITY`) | `security/` |

These folders are department workspaces and are not loaded by the PHP app
automatically. The Security department is integrated into the IHMS PHP
application; its pages, services, and API live in the shared application
structure. Follow `docs/department-handoff.md` for the shared database and
integration boundaries.

Do not put passwords, API keys, or other secrets in these folders.
