# 项目约定

- 本项目是 Laravel / PHP API，对应 Next.js 前端在 `../dogeow`。生产数据库使用 PostgreSQL，本地测试可显式使用 SQLite 内存库。
- 认证使用 Sanctum；受保护接口使用 `auth:sanctum`，资源权限由 Policy 或用户范围查询控制。
- 数据库不使用外键，关联完整性由应用层维护。
- 业务代码按模块组织在 `app/Http/Controllers/Api/`、`app/Http/Requests/`、`app/Services/`；响应沿用 `ApiResponse` 或 API Resource。
- 验证：`vendor/bin/pint --dirty`、`php artisan test --filter=<相关测试>`；测试使用工厂及隔离数据库。
- 中文沟通与注释。API 契约变更需检查对应前端。
