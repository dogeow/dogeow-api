# 数据库迁移说明

## 两级分类功能迁移

为了支持两级分类功能，需要运行以下数据库迁移：

### 1. 运行迁移命令

```bash
cd dogeow-api
php artisan migrate
```

这将执行以下迁移：
- `2025_01_23_000000_add_parent_id_to_thing_item_categories_table.php`

### 2. 迁移内容

该迁移将为 `thing_item_categories` 表添加：
- `parent_id` 字段（可为空的外键，引用同表的 id）
- 外键约束，支持级联删除

### 3. 数据结构变化

迁移后的表结构：
```sql
thing_item_categories:
- id (主键)
- name (分类名称)
- parent_id (父分类ID，可为空)
- user_id (用户ID)
- created_at
- updated_at
```

### 4. 功能说明

- `parent_id` 为 `null` 的记录是主分类
- `parent_id` 不为 `null` 的记录是子分类
- 支持最多两级分类（主分类 -> 子分类）
- 删除主分类时会级联删除其下的子分类

### 5. API 变化

- 创建分类时可以传递 `parent_id` 参数
- 获取分类列表时会包含父子关系信息
- 删除分类时会检查是否有子分类

### 6. 验证规则

- `parent_id` 必须是已存在的分类ID
- 不能在子分类下再创建子分类（防止三级分类）
- 不能删除有子分类的主分类