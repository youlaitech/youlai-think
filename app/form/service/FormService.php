<?php declare(strict_types=1);

namespace app\form\service;

use app\common\exception\BusinessException;
use app\common\util\AiService;
use app\form\model\FormData;
use app\form\model\FormDefinition;
use app\form\model\FormSnapshot;
use app\system\model\Menu;
use think\facade\Cache;
use think\facade\Db;

/**
 * 动态表单服务（表单定义、渲染、数据、访问菜单生成、AI 生成）
 */
final class FormService
{
    private const STATUS_DRAFT = 0;
    private const STATUS_PUBLISHED = 1;
    private const STATUS_DISABLED = -1;
    private const DEFAULT_CATALOG_NAME = '表单中心';
    private const FORM_ADMIN_CATALOG_NAME = '动态表单';
    private const PUBLIC_SUBMIT_LIMIT = 10;
    private const RENDER_CACHE_TTL = 1800;
    private const PROMPT_FILE = 'form/system.md';

    public function __construct(private readonly AiService $aiService) {}

    // ── 规则校验 ────────────────────────────────────────────────

    /**
     * 校验表单规则结构
     */
    public static function validateRule(mixed $rules): void
    {
        if (!is_array($rules) || $rules === []) {
            throw new BusinessException('表单规则格式非法：应为非空数组');
        }
        self::checkNodes($rules);
    }

    private static function checkNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                throw new BusinessException('表单规则格式非法：节点必须为对象');
            }
            if (!isset($node['type']) || !is_string($node['type'])) {
                throw new BusinessException('表单规则格式非法：节点缺少 type');
            }
            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                self::checkNodes($children);
            } elseif (!isset($node['field']) || !is_string($node['field'])) {
                throw new BusinessException('表单规则格式非法：字段节点缺少 field');
            }
        }
    }

    /**
     * 按规则白名单过滤提交数据并校验必填与类型
     */
    public static function validateAndFilter(array $rules, array $data): array
    {
        $filtered = [];
        $errors = [];
        self::walkNodes($rules, $data, $filtered, $errors);

        if ($errors !== []) {
            throw new BusinessException(implode('；', $errors));
        }
        return $filtered;
    }

    private static function walkNodes(array $nodes, array $data, array &$filtered, array &$errors): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $field = $node['field'] ?? null;
            if (is_string($field) && $field !== '') {
                // 字段白名单：规则未声明的 field 直接丢弃
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = $data[$field];
                }
                $title = $node['title'] ?? $field;
                $value = $data[$field] ?? null;
                foreach ($node['validate'] ?? [] as $rule) {
                    if (!is_array($rule)) {
                        continue;
                    }
                    if (($rule['required'] ?? false) === true && self::isEmptyValue($value)) {
                        $errors[] = $rule['message'] ?? "「{$title}」不能为空";
                    }
                    if (!empty($rule['type']) && !self::isEmptyValue($value)
                        && !self::matchType((string) $rule['type'], $value)) {
                        $errors[] = "「{$title}」数据类型非法";
                    }
                }
            }
            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                self::walkNodes($children, $data, $filtered, $errors);
            }
        }
    }

    private static function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return $value === [];
        }
        return false;
    }

    private static function matchType(string $kind, mixed $value): bool
    {
        return match ($kind) {
            'number', 'integer', 'float' => is_numeric($value),
            'array' => is_array($value),
            default => true,
        };
    }

    // ── 表单定义 ────────────────────────────────────────────────

    /**
     * 表单定义分页列表
     */
    public function getPage(array $params): array
    {
        $pageNum = max(1, (int) ($params['page_num'] ?? 1));
        $pageSize = max(1, (int) ($params['page_size'] ?? 10));

        $query = FormDefinition::order('id', 'desc');
        if (!empty($params['keywords'])) {
            $kw = '%' . $params['keywords'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->whereLike('form_name', $kw)->whereOr('form_key', 'like', $kw);
            });
        }
        if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) {
            $query->where('status', (int) $params['status']);
        }
        if (!empty($params['category'])) {
            $query->where('category', $params['category']);
        }

        $total = (clone $query)->count();
        $rows = $query->page($pageNum, $pageSize)->select()->toArray();

        $list = array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'form_key' => $row['form_key'],
            'form_name' => $row['form_name'],
            'description' => $row['description'] ?? null,
            'status' => (int) $row['status'],
            'is_public' => (int) ($row['is_public'] ?? 0),
            'category' => $row['category'] ?? 'normal',
            'version' => (int) ($row['version'] ?? 1),
            'create_time' => $row['create_time'] ?? null,
        ], $rows);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 表单设计数据回显
     */
    public function getForm(int $id): array
    {
        $entity = $this->findDefinition($id);
        return [
            'id' => (string) $entity->id,
            'form_key' => $entity->form_key,
            'form_name' => $entity->form_name,
            'description' => $entity->description,
            'form_json' => $entity->form_json,
            'options_json' => $entity->options_json,
            'is_public' => (int) $entity->is_public,
            'category' => $entity->category,
        ];
    }

    /**
     * 新增表单定义
     */
    public function create(array $data, int $userId): int
    {
        $formKey = trim((string) ($data['form_key'] ?? ''));
        if ($formKey === '') {
            throw new BusinessException('表单标识不能为空');
        }
        if (FormDefinition::where('form_key', $formKey)->count() > 0) {
            throw new BusinessException('表单标识已存在');
        }

        $now = date('Y-m-d H:i:s');
        $id = (int) FormDefinition::insertGetId([
            'form_key' => $formKey,
            'form_name' => trim((string) ($data['form_name'] ?? '')),
            'description' => $data['description'] ?? null,
            'form_json' => json_encode($data['form_json'] ?? [], JSON_UNESCAPED_UNICODE),
            'options_json' => isset($data['options_json']) ? json_encode($data['options_json'], JSON_UNESCAPED_UNICODE) : null,
            'status' => self::STATUS_DRAFT,
            'is_public' => (int) ($data['is_public'] ?? 0),
            'category' => $data['category'] ?: 'normal',
            'version' => 1,
            'create_by' => $userId,
            'create_time' => $now,
            'update_time' => $now,
            'is_deleted' => 0,
        ]);

        return $id;
    }

    /**
     * 修改表单定义
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $entity = $this->findDefinition($id);

        $formKey = trim((string) ($data['form_key'] ?? ''));
        if ($formKey !== '' && $formKey !== $entity->form_key) {
            throw new BusinessException('表单标识创建后不可修改');
        }

        $patch = [
            'form_name' => trim((string) ($data['form_name'] ?? $entity->form_name)),
            'description' => $data['description'] ?? $entity->description,
            'update_by' => $userId,
            'update_time' => date('Y-m-d H:i:s'),
        ];
        if (array_key_exists('form_json', $data)) {
            $patch['form_json'] = json_encode($data['form_json'], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('options_json', $data)) {
            $patch['options_json'] = $data['options_json'] === null
                ? null
                : json_encode($data['options_json'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['is_public'])) {
            $patch['is_public'] = (int) $data['is_public'];
        }
        if ((int) $entity->status === self::STATUS_DRAFT && !empty($data['category'])) {
            $patch['category'] = $data['category'];
        }

        FormDefinition::where('id', $id)->update($patch);

        // 已发布表单规则变更后清渲染缓存，保证渲染端即时生效
        if ((int) $entity->status === self::STATUS_PUBLISHED) {
            $this->evictRenderCache((string) $entity->form_key);
        }
        return true;
    }

    /**
     * 删除表单（级联逻辑删除数据与快照，并清理生成的菜单）
     */
    public function remove(string $ids): bool
    {
        $idList = $this->parseIds($ids);
        if ($idList === []) {
            throw new BusinessException('删除的表单数据为空');
        }

        $forms = FormDefinition::whereIn('id', $idList)->select();
        $now = date('Y-m-d H:i:s');
        foreach ($forms as $form) {
            if (!empty($form->menu_id)) {
                Db::name('sys_role_menu')->where('menu_id', (int) $form->menu_id)->delete();
                Menu::where('id', (int) $form->menu_id)->delete();
            }
            $this->evictRenderCache((string) $form->form_key);
        }

        FormDefinition::whereIn('id', $idList)->update(['is_deleted' => 1, 'update_time' => $now]);
        FormData::whereIn('form_id', $idList)->update(['is_deleted' => 1, 'update_time' => $now]);
        FormSnapshot::whereIn('form_id', $idList)->update(['is_deleted' => 1, 'update_time' => $now]);
        return true;
    }

    /**
     * 发布表单（版本递增并固化快照）
     */
    public function publish(int $id): bool
    {
        $entity = $this->findDefinition($id);
        $version = (int) $entity->version + 1;

        FormDefinition::where('id', $id)->update([
            'status' => self::STATUS_PUBLISHED,
            'version' => $version,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        // 固化版本快照：数据回显按提交时版本加载，防止规则变更导致历史数据漂移
        FormSnapshot::insert([
            'form_id' => $id,
            'version' => $version,
            'form_json' => json_encode($entity->form_json ?? [], JSON_UNESCAPED_UNICODE),
            'options_json' => $entity->options_json === null
                ? null
                : json_encode($entity->options_json, JSON_UNESCAPED_UNICODE),
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'is_deleted' => 0,
        ]);

        $this->evictRenderCache((string) $entity->form_key);
        return true;
    }

    /**
     * 停用表单
     */
    public function disable(int $id): bool
    {
        $this->findDefinition($id);
        FormDefinition::where('id', $id)->update([
            'status' => self::STATUS_DISABLED,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    // ── 渲染 ────────────────────────────────────────────────────

    /**
     * 已发布表单渲染规则（缓存兜底 30 分钟）
     */
    public function render(string $formKey): array
    {
        $cacheKey = 'form:render:' . $formKey;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $entity = FormDefinition::where('form_key', $formKey)
            ->where('status', self::STATUS_PUBLISHED)
            ->find();
        if ($entity === null) {
            throw new BusinessException('表单不存在或未发布');
        }

        $payload = $this->renderPayload($entity);
        Cache::set($cacheKey, $payload, self::RENDER_CACHE_TTL);
        return $payload;
    }

    /**
     * 公开表单渲染规则（直查库校验 is_public，不走缓存）
     */
    public function publicRender(string $formKey): array
    {
        $entity = FormDefinition::where('form_key', $formKey)
            ->where('status', self::STATUS_PUBLISHED)
            ->where('is_public', 1)
            ->find();
        if ($entity === null) {
            throw new BusinessException('表单不存在或未开放公开访问');
        }
        return $this->renderPayload($entity);
    }

    /**
     * 审批表单下拉选项
     */
    public function workflowOptions(): array
    {
        $rows = FormDefinition::where('category', 'workflow')
            ->where('status', self::STATUS_PUBLISHED)
            ->order('id', 'asc')
            ->select();

        $options = [];
        foreach ($rows as $row) {
            $options[] = ['value' => $row->form_key, 'label' => $row->form_name];
        }
        return $options;
    }

    // ── 访问菜单 ────────────────────────────────────────────────

    /**
     * 表单访问菜单回显
     */
    public function getMenu(int $id): ?array
    {
        $entity = $this->findDefinition($id);
        if (empty($entity->menu_id)) {
            return null;
        }

        $menu = Menu::find((int) $entity->menu_id);
        if ($menu === null) {
            return null;
        }

        $roleIds = Db::name('sys_role_menu')->where('menu_id', (int) $menu->id)->column('role_id');
        return [
            'menu_id' => (string) $menu->id,
            'menu_name' => $menu->name,
            'parent_id' => (string) $menu->parent_id,
            'role_ids' => array_map('strval', $roleIds),
        ];
    }

    /**
     * 生成或更新表单访问菜单，并按需授权角色（幂等）
     */
    public function saveMenu(int $id, array $data, int $userId): bool
    {
        $entity = $this->findDefinition($id);

        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = Menu::find($parentId);
            if ($parent === null) {
                throw new BusinessException('上级菜单不存在');
            }
            if ($parent->type !== 'C') {
                throw new BusinessException('上级菜单必须为目录类型');
            }
        } else {
            $parent = $this->getOrCreateCatalog();
            $parentId = (int) $parent->id;
        }

        $routeName = 'FormRender' . $this->toUpperCamel((string) $entity->form_key);
        $treePath = ($parent->tree_path ?? '') !== ''
            ? $parent->tree_path . ',' . $parentId
            : (string) $parentId;
        $params = json_encode(['formKey' => (string) $entity->form_key], JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');

        $menuId = (int) ($entity->menu_id ?? 0);
        if ($menuId > 0 && Menu::find($menuId) !== null) {
            Menu::where('id', $menuId)->update([
                'parent_id' => $parentId,
                'name' => trim((string) $data['menu_name']),
                'route_name' => $routeName,
                'route_path' => (string) $entity->form_key,
                'component' => 'dynamic-form/render',
                'params' => $params,
                'tree_path' => $treePath,
                'update_time' => $now,
            ]);
        } else {
            $menuId = (int) Menu::insertGetId([
                'parent_id' => $parentId,
                'tree_path' => $treePath . ',' . 0,
                'name' => trim((string) $data['menu_name']),
                'type' => 'M',
                'route_name' => $routeName,
                'route_path' => (string) $entity->form_key,
                'component' => 'dynamic-form/render',
                'params' => $params,
                'visible' => 1,
                'sort' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
            Menu::where('id', $menuId)->update(['tree_path' => $treePath . ',' . $menuId]);
        }

        // 回写 menu_id：下次生成走更新语义，防重复生成菜单
        FormDefinition::where('id', $id)->update([
            'menu_id' => $menuId,
            'update_by' => $userId,
            'update_time' => $now,
        ]);

        $roleIds = array_values(array_filter(array_map('intval', $data['role_ids'] ?? [])));
        if ($roleIds !== []) {
            $this->assignMenuRoles($menuId, $treePath . ',' . $menuId, $roleIds);
        }
        return true;
    }

    // ── 表单数据 ────────────────────────────────────────────────

    /**
     * 提交表单数据（需已发布）
     */
    public function submit(string $formKey, array $data, int $userId): bool
    {
        $entity = FormDefinition::where('form_key', $formKey)->find();
        if ($entity === null) {
            throw new BusinessException('表单不存在');
        }
        if ((int) $entity->status !== self::STATUS_PUBLISHED) {
            throw new BusinessException('表单未发布或已停用');
        }
        return $this->persist($entity, $data, $userId);
    }

    /**
     * 匿名提交公开表单
     */
    public function submitPublic(string $formKey, array $data, string $ip): bool
    {
        $entity = FormDefinition::where('form_key', $formKey)->find();
        if ($entity === null || (int) $entity->is_public !== 1
            || (int) $entity->status !== self::STATUS_PUBLISHED) {
            throw new BusinessException('表单不存在或未开放公开访问');
        }
        if (!$this->checkPublicLimit($formKey, $ip)) {
            throw new BusinessException('提交过于频繁，请稍后再试');
        }
        return $this->persist($entity, $data);
    }

    /**
     * 表单数据分页
     */
    public function getDataPage(string $formKey, int $pageNum, int $pageSize): array
    {
        $entity = FormDefinition::where('form_key', $formKey)->find();
        if ($entity === null) {
            throw new BusinessException('表单不存在');
        }

        $pageNum = max(1, $pageNum);
        $pageSize = max(1, $pageSize);

        $query = FormData::where('form_id', (int) $entity->id)->order('id', 'desc');
        $total = (clone $query)->count();
        $rows = $query->page($pageNum, $pageSize)->select()->toArray();

        $list = array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'form_version' => (int) $row['form_version'],
            'data_json' => $row['data_json'],
            'create_by' => $row['create_by'] ?? null,
            'create_time' => $row['create_time'] ?? null,
        ], $rows);

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 表单数据详情（按提交时版本快照回显规则）
     */
    public function getDataDetail(int $dataId): array
    {
        $row = FormData::find($dataId);
        if ($row === null) {
            throw new BusinessException('表单数据不存在');
        }

        // 命中版本快照用快照规则，否则回退当前定义（兼容快照表启用前提交的历史数据）
        $snapshot = FormSnapshot::where('form_id', (int) $row->form_id)
            ->where('version', (int) $row->form_version)
            ->find();

        $formJson = $snapshot?->form_json;
        $optionsJson = $snapshot?->options_json;
        if ($snapshot === null) {
            $definition = FormDefinition::find((int) $row->form_id);
            $formJson = $definition?->form_json;
            $optionsJson = $definition?->options_json;
        }

        return [
            'id' => (string) $row->id,
            'form_version' => (int) $row->form_version,
            'data_json' => $row->data_json,
            'create_by' => $row->create_by ?? null,
            'create_time' => $row->create_time ?? null,
            'form_json' => $formJson,
            'options_json' => $optionsJson,
        ];
    }

    /**
     * 逻辑删除表单数据
     */
    public function deleteData(string $ids): bool
    {
        $idList = $this->parseIds($ids);
        if ($idList === []) {
            throw new BusinessException('删除的表单数据为空');
        }

        FormData::whereIn('id', $idList)->update([
            'is_deleted' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    // ── AI ──────────────────────────────────────────────────────

    /**
     * 用 AI 把需求描述转成 form-create 规则，产出经结构校验后才返回
     */
    public function aiGenerateRule(string $description): array
    {
        $rules = $this->aiService->chatArray(
            $this->aiService->loadPrompt(self::PROMPT_FILE),
            "需求描述：\n" . $description
        );

        self::validateRule($rules);
        return $rules;
    }

    // ── 私有方法 ────────────────────────────────────────────────

    private function findDefinition(int $id): FormDefinition
    {
        $entity = FormDefinition::find($id);
        if ($entity === null) {
            throw new BusinessException('表单不存在');
        }
        return $entity;
    }

    private function renderPayload(FormDefinition $entity): array
    {
        return [
            'form_key' => $entity->form_key,
            'form_name' => $entity->form_name,
            'version' => (int) $entity->version,
            'form_json' => $entity->form_json,
            'options_json' => $entity->options_json,
        ];
    }

    private function evictRenderCache(string $formKey): void
    {
        Cache::delete('form:render:' . $formKey);
    }

    private function persist(FormDefinition $entity, array $data, ?int $userId = null): bool
    {
        $filtered = self::validateAndFilter(is_array($entity->form_json) ? $entity->form_json : [], $data);
        $now = date('Y-m-d H:i:s');

        FormData::insert([
            'form_id' => (int) $entity->id,
            'form_version' => (int) $entity->version,
            'data_json' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
            'create_by' => $userId,
            'create_time' => $now,
            'update_time' => $now,
            'is_deleted' => 0,
        ]);
        return true;
    }

    /**
     * 公开表单按 formKey + IP 限流（固定窗口计数）
     */
    private function checkPublicLimit(string $formKey, string $ip): bool
    {
        $key = 'form:public:submit:' . $formKey . ':' . $ip;
        $count = (int) Cache::get($key, 0);
        if ($count >= self::PUBLIC_SUBMIT_LIMIT) {
            return false;
        }
        Cache::set($key, $count + 1, 60);
        return true;
    }

    /**
     * 确保默认挂载目录"表单中心"存在，不存在则创建
     */
    private function getOrCreateCatalog(): Menu
    {
        $catalog = Menu::where('name', self::DEFAULT_CATALOG_NAME)->where('type', 'C')->find();
        if ($catalog !== null) {
            return $catalog;
        }

        $adminCatalog = Menu::where('name', self::FORM_ADMIN_CATALOG_NAME)->where('type', 'C')->find();
        $parentId = $adminCatalog !== null ? (int) $adminCatalog->id : 0;
        $treePath = $adminCatalog !== null
            ? (($adminCatalog->tree_path ?? '') !== ''
                ? $adminCatalog->tree_path . ',' . $parentId
                : (string) $parentId)
            : '0';
        $routePath = $adminCatalog !== null ? 'center' : '/form-center';
        $now = date('Y-m-d H:i:s');

        $id = (int) Menu::insertGetId([
            'parent_id' => $parentId,
            'tree_path' => $treePath,
            'name' => self::DEFAULT_CATALOG_NAME,
            'type' => 'C',
            'route_path' => $routePath,
            'component' => 'Layout',
            'visible' => 1,
            'sort' => 5,
            'icon' => 'el-icon-EditPen',
            'create_time' => $now,
            'update_time' => $now,
        ]);

        return Menu::find($id);
    }

    /**
     * 给角色授权菜单（含 tree_path 祖先目录，幂等）
     */
    private function assignMenuRoles(int $menuId, string $treePath, array $roleIds): void
    {
        $grantIds = [(string) $menuId];
        foreach (explode(',', $treePath) as $part) {
            $part = trim($part);
            if ($part !== '' && $part !== '0') {
                $grantIds[] = $part;
            }
        }
        $grantIds = array_values(array_unique($grantIds));

        $existing = Db::name('sys_role_menu')
            ->whereIn('menu_id', array_map('intval', $grantIds))
            ->column('role_id,menu_id');

        foreach ($roleIds as $roleId) {
            foreach ($grantIds as $grantId) {
                if (in_array($roleId . ',' . $grantId, $existing, true)) {
                    continue;
                }
                Db::name('sys_role_menu')->insert([
                    'role_id' => $roleId,
                    'menu_id' => (int) $grantId,
                ]);
            }
        }
    }

    private function toUpperCamel(string $value): string
    {
        return implode('', array_map(
            static fn(string $part): string => $part === '' ? '' : ucfirst($part),
            explode('_', $value)
        ));
    }

    private function parseIds(string $ids): array
    {
        $result = [];
        foreach (explode(',', $ids) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $result[] = (int) $part;
            }
        }
        return $result;
    }
}
