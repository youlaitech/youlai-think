<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\util\TemplateRenderer;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * 代码生成服务
 */
final class CodegenService
{
    private const TEMPLATE_BASE_DIR = '/codegen/templates';

    private const DEFAULT_MODULE_NAME = 'system';
    private const DEFAULT_PACKAGE_NAME = 'app';
    private const DEFAULT_AUTHOR = 'youlai';
    private const DEFAULT_FRONTEND_APP_NAME = 'vue3-element-admin';
    private const DEFAULT_BACKEND_APP_NAME = 'youlai-think';
    private const DEFAULT_REMOVE_TABLE_PREFIX = 'sys_';

    private TemplateRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new TemplateRenderer();
    }

    /**
     * 数据表分页
     */
    public function getTablePage(array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;
        $keywords = trim((string) ($queryParams['keywords'] ?? ''));

        $where = "t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME NOT IN ('gen_table','gen_table_column')";
        $bind = [];
        if ($keywords !== '') {
            $where .= ' AND t.TABLE_NAME LIKE ?';
            $bind[] = '%' . $keywords . '%';
        }

        $totalSql = "SELECT COUNT(1) AS total FROM information_schema.TABLES t WHERE {$where}";
        $totalRows = Db::query($totalSql, $bind);
        $total = (int) (($totalRows[0]['total'] ?? 0));

        $offset = ($pageNum - 1) * $pageSize;
        $listSql = "
SELECT
  t.TABLE_NAME AS tableName,
  t.TABLE_COMMENT AS tableComment,
  t.ENGINE AS engine,
  t.TABLE_COLLATION AS tableCollation,
  SUBSTRING_INDEX(t.TABLE_COLLATION, '_', 1) AS charset,
  DATE_FORMAT(t.CREATE_TIME, '%Y-%m-%d %H:%i:%s') AS createTime,
  IF(gt.id IS NULL, 0, 1) AS isConfigured
FROM information_schema.TABLES t
LEFT JOIN gen_table gt
  ON gt.table_name = t.TABLE_NAME AND gt.is_deleted = 0
WHERE {$where}
ORDER BY t.CREATE_TIME DESC
LIMIT ? OFFSET ?
";

        $listRows = Db::query($listSql, array_merge($bind, [$pageSize, $offset]));
        $list = [];
        foreach ($listRows as $r) {
            $list[] = [
                'tableName' => (string) ($r['tableName'] ?? ''),
                'tableComment' => (string) ($r['tableComment'] ?? ''),
                'engine' => (string) ($r['engine'] ?? ''),
                'tableCollation' => (string) ($r['tableCollation'] ?? ''),
                'charset' => (string) ($r['charset'] ?? ''),
                'createTime' => (string) ($r['createTime'] ?? ''),
                'isConfigured' => isset($r['isConfigured']) ? (int) $r['isConfigured'] : 0,
            ];
        }

        return [$list, $total];
    }

    /**
     * 获取代码生成配置
     */
    public function getGenConfigFormData(string $tableName): array
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $cfg = Db::name('gen_table')
            ->where('table_name', $tableName)
            ->where('is_deleted', 0)
            ->find();

        if ($cfg) {
            $tableId = (int) ($cfg['id'] ?? 0);
            $fieldRows = [];
            if ($tableId > 0) {
                $fieldRows = Db::name('gen_table_column')
                    ->where('table_id', $tableId)
                    ->order('field_sort', 'asc')
                    ->select()
                    ->toArray();
            }

            $fieldConfigs = [];
            foreach ($fieldRows as $r) {
                $fieldConfigs[] = [
                    'id' => isset($r['id']) ? (string) $r['id'] : null,
                    'columnName' => $r['column_name'] ?? null,
                    'columnType' => $r['column_type'] ?? null,
                    'fieldName' => $r['field_name'] ?? null,
                    'fieldType' => $r['field_type'] ?? null,
                    'fieldComment' => $r['field_comment'] ?? null,
                    'isShowInList' => isset($r['is_show_in_list']) ? (int) $r['is_show_in_list'] : 0,
                    'isShowInForm' => isset($r['is_show_in_form']) ? (int) $r['is_show_in_form'] : 0,
                    'isShowInQuery' => isset($r['is_show_in_query']) ? (int) $r['is_show_in_query'] : 0,
                    'isRequired' => isset($r['is_required']) ? (int) $r['is_required'] : 0,
                    'formType' => isset($r['form_type']) ? (int) $r['form_type'] : null,
                    'queryType' => isset($r['query_type']) ? (int) $r['query_type'] : null,
                    'maxLength' => isset($r['max_length']) ? (int) $r['max_length'] : null,
                    'fieldSort' => isset($r['field_sort']) ? (int) $r['field_sort'] : null,
                    'dictType' => $r['dict_type'] ?? null,
                ];
            }

            return [
                'id' => isset($cfg['id']) ? (string) $cfg['id'] : null,
                'tableName' => $cfg['table_name'] ?? $tableName,
                'businessName' => $cfg['business_name'] ?? null,
                'moduleName' => $cfg['module_name'] ?? self::DEFAULT_MODULE_NAME,
                'packageName' => $cfg['package_name'] ?? self::DEFAULT_PACKAGE_NAME,
                'entityName' => $cfg['entity_name'] ?? null,
                'author' => $cfg['author'] ?? self::DEFAULT_AUTHOR,
                'parentMenuId' => isset($cfg['parent_menu_id']) && $cfg['parent_menu_id'] !== null ? (string) $cfg['parent_menu_id'] : null,
                'backendAppName' => self::DEFAULT_BACKEND_APP_NAME,
                'frontendAppName' => self::DEFAULT_FRONTEND_APP_NAME,
                'fieldConfigs' => $fieldConfigs,
                'pageType' => $cfg['page_type'] ?? 'classic',
                'removeTablePrefix' => $cfg['remove_table_prefix'] ?? self::DEFAULT_REMOVE_TABLE_PREFIX,
            ];
        }

        $meta = Db::query(
            "SELECT TABLE_COMMENT AS tableComment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$tableName]
        );
        $tableComment = (string) (($meta[0]['tableComment'] ?? ''));

        $businessName = $tableComment !== '' ? trim(str_replace('表', '', $tableComment)) : $tableName;
        $removePrefix = self::DEFAULT_REMOVE_TABLE_PREFIX;
        $processed = $removePrefix !== '' && str_starts_with($tableName, $removePrefix)
            ? substr($tableName, strlen($removePrefix))
            : $tableName;
        $entityName = $this->toPascalCase($processed);

        $columns = Db::query(
            "
SELECT
  COLUMN_NAME AS columnName,
  COLUMN_TYPE AS columnType,
  COLUMN_COMMENT AS columnComment,
  IS_NULLABLE AS isNullable,
  CHARACTER_MAXIMUM_LENGTH AS maxLength,
  ORDINAL_POSITION AS ordinalPosition,
  COLUMN_KEY AS columnKey
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
ORDER BY ORDINAL_POSITION ASC
",
            [$tableName]
        );

        $fieldConfigs = [];
        foreach ($columns as $idx => $col) {
            $columnName = (string) ($col['columnName'] ?? '');
            $columnType = (string) ($col['columnType'] ?? '');
            $comment = (string) ($col['columnComment'] ?? '');
            $nullable = strtoupper((string) ($col['isNullable'] ?? 'YES')) === 'YES';
            $maxLength = $col['maxLength'] ?? null;
            $isPk = ((string) ($col['columnKey'] ?? '')) === 'PRI';

            $fieldType = $this->phpTypeByColumnType($columnType);
            $formType = $this->defaultFormTypeByColumnType($columnType, $columnName);

            $isShowInForm = 1;
            $isShowInList = 1;
            $isShowInQuery = 0;

            if ($isPk) {
                $formType = 10;
                $isShowInQuery = 0;
                $isShowInForm = 0;
            }

            $fieldConfigs[] = [
                'columnName' => $columnName,
                'columnType' => $columnType,
                'fieldName' => $this->toCamelCase($columnName),
                'fieldType' => $fieldType,
                'fieldComment' => $comment,
                'isRequired' => $nullable ? 0 : 1,
                'formType' => $formType,
                'queryType' => 1,
                'maxLength' => is_numeric($maxLength) ? (int) $maxLength : null,
                'fieldSort' => $idx + 1,
                'isShowInList' => $isShowInList,
                'isShowInForm' => $isShowInForm,
                'isShowInQuery' => $isShowInQuery,
            ];
        }

        return [
            'tableName' => $tableName,
            'businessName' => $businessName,
            'moduleName' => self::DEFAULT_MODULE_NAME,
            'packageName' => self::DEFAULT_PACKAGE_NAME,
            'entityName' => $entityName,
            'author' => self::DEFAULT_AUTHOR,
            'parentMenuId' => null,
            'backendAppName' => self::DEFAULT_BACKEND_APP_NAME,
            'frontendAppName' => self::DEFAULT_FRONTEND_APP_NAME,
            'removeTablePrefix' => $removePrefix,
            'pageType' => 'classic',
            'fieldConfigs' => $fieldConfigs,
        ];
    }

    /**
     * 保存代码生成配置
     */
    public function saveGenConfig(string $tableName, array $formData): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $moduleName = trim((string) ($formData['moduleName'] ?? ''));
        $moduleName = $moduleName !== '' ? $moduleName : self::DEFAULT_MODULE_NAME;

        $packageName = trim((string) ($formData['packageName'] ?? ''));
        $packageName = $packageName !== '' ? $packageName : self::DEFAULT_PACKAGE_NAME;

        $businessName = trim((string) ($formData['businessName'] ?? ''));
        $businessName = $businessName !== '' ? $businessName : $tableName;

        $entityName = trim((string) ($formData['entityName'] ?? ''));
        $entityName = $entityName !== '' ? $entityName : $this->toPascalCase($tableName);

        $author = trim((string) ($formData['author'] ?? ''));
        $author = $author !== '' ? $author : self::DEFAULT_AUTHOR;

        $parentMenuId = $formData['parentMenuId'] ?? null;
        $parentMenuId = $parentMenuId === null || $parentMenuId === '' ? null : (int) $parentMenuId;

        $removeTablePrefix = (string) ($formData['removeTablePrefix'] ?? self::DEFAULT_REMOVE_TABLE_PREFIX);
        $pageType = (string) ($formData['pageType'] ?? 'classic');
        $pageType = $pageType === 'curd' ? 'curd' : 'classic';

        $fieldConfigs = $formData['fieldConfigs'] ?? null;
        if (!is_array($fieldConfigs)) {
            $fieldConfigs = [];
        }

        Db::transaction(function () use (
            $tableName,
            $moduleName,
            $packageName,
            $businessName,
            $entityName,
            $author,
            $parentMenuId,
            $removeTablePrefix,
            $pageType,
            $fieldConfigs
        ) {
            $now = date('Y-m-d H:i:s');

            $row = Db::name('gen_table')->where('table_name', $tableName)->find();
            if ($row) {
                $tableId = (int) ($row['id'] ?? 0);
                Db::name('gen_table')->where('id', $tableId)->update([
                    'module_name' => $moduleName,
                    'package_name' => $packageName,
                    'business_name' => $businessName,
                    'entity_name' => $entityName,
                    'author' => $author,
                    'parent_menu_id' => $parentMenuId,
                    'remove_table_prefix' => $removeTablePrefix,
                    'page_type' => $pageType,
                    'update_time' => $now,
                    'is_deleted' => 0,
                ]);
            } else {
                $tableId = (int) Db::name('gen_table')->insertGetId([
                    'table_name' => $tableName,
                    'module_name' => $moduleName,
                    'package_name' => $packageName,
                    'business_name' => $businessName,
                    'entity_name' => $entityName,
                    'author' => $author,
                    'parent_menu_id' => $parentMenuId,
                    'remove_table_prefix' => $removeTablePrefix,
                    'page_type' => $pageType,
                    'create_time' => $now,
                    'update_time' => $now,
                    'is_deleted' => 0,
                ]);
            }

            if ($tableId <= 0) {
                throw new BusinessException(ResultCode::DATABASE_SERVICE_ERROR, '生成配置保存失败');
            }

            Db::name('gen_table_column')->where('table_id', $tableId)->delete();

            $rows = [];
            $sort = 1;
            foreach ($fieldConfigs as $fc) {
                if (!is_array($fc)) {
                    continue;
                }

                $columnName = isset($fc['columnName']) ? trim((string) $fc['columnName']) : '';
                if ($columnName === '') {
                    continue;
                }

                $fieldSort = isset($fc['fieldSort']) && is_numeric($fc['fieldSort']) ? (int) $fc['fieldSort'] : $sort;

                $rows[] = [
                    'table_id' => $tableId,
                    'column_name' => $columnName,
                    'column_type' => $fc['columnType'] ?? null,
                    'field_name' => $fc['fieldName'] ?? $this->toCamelCase($columnName),
                    'field_type' => $fc['fieldType'] ?? null,
                    'field_sort' => $fieldSort,
                    'field_comment' => $fc['fieldComment'] ?? null,
                    'max_length' => isset($fc['maxLength']) && is_numeric($fc['maxLength']) ? (int) $fc['maxLength'] : null,
                    'is_required' => isset($fc['isRequired']) ? (int) $fc['isRequired'] : 0,
                    'is_show_in_list' => isset($fc['isShowInList']) ? (int) $fc['isShowInList'] : 0,
                    'is_show_in_form' => isset($fc['isShowInForm']) ? (int) $fc['isShowInForm'] : 0,
                    'is_show_in_query' => isset($fc['isShowInQuery']) ? (int) $fc['isShowInQuery'] : 0,
                    'query_type' => isset($fc['queryType']) ? (int) $fc['queryType'] : null,
                    'form_type' => isset($fc['formType']) ? (int) $fc['formType'] : null,
                    'dict_type' => $fc['dictType'] ?? null,
                    'create_time' => $now,
                    'update_time' => $now,
                ];
                $sort++;
            }

            if (!empty($rows)) {
                Db::name('gen_table_column')->insertAll($rows);
            }
        });

        return true;
    }

    /**
     * 删除代码生成配置
     */
    public function deleteGenConfig(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $row = Db::name('gen_table')->where('table_name', $tableName)->where('is_deleted', 0)->find();
        if (!$row) {
            return true;
        }

        $tableId = (int) ($row['id'] ?? 0);

        Db::transaction(function () use ($tableId) {
            $now = date('Y-m-d H:i:s');
            Db::name('gen_table')->where('id', $tableId)->update([
                'is_deleted' => 1,
                'update_time' => $now,
            ]);
            Db::name('gen_table_column')->where('table_id', $tableId)->delete();
        });

        return true;
    }

    /**
     * 代码预览
     */
    public function getCodegenPreviewData(string $tableName, string $pageType = 'classic'): array
    {
        $config = $this->getGenConfigFormData($tableName);
        $finalPageType = (string) ($config['pageType'] ?? 'classic');
        if ($finalPageType === '') {
            $finalPageType = $pageType;
        }
        $finalPageType = $finalPageType === 'curd' ? 'curd' : 'classic';

        $entityName = (string) ($config['entityName'] ?? $this->toPascalCase($tableName));
        $moduleName = (string) ($config['moduleName'] ?? self::DEFAULT_MODULE_NAME);
        $businessName = (string) ($config['businessName'] ?? $tableName);
        $entityKebab = $this->toKebabCase($entityName);

        $fieldConfigs = $config['fieldConfigs'] ?? [];
        if (!is_array($fieldConfigs)) {
            $fieldConfigs = [];
        }

        $previews = [];

        $tableNameFinal = (string) ($config['tableName'] ?? $tableName);
        $fieldSql = $this->buildFieldSql($fieldConfigs);
        $fieldsTs = $this->buildFieldsTs($fieldConfigs);
        $vars = [
            'tableName' => $tableNameFinal,
            'entityName' => $entityName,
            'entityKebab' => $entityKebab,
            'businessName' => $businessName,
            'moduleName' => $moduleName,
            'pageType' => $finalPageType,
            'fieldSql' => $fieldSql,
            'fieldsTs' => $fieldsTs,
        ];

        $previews[] = [
            'path' => self::DEFAULT_BACKEND_APP_NAME . '/app/model',
            'fileName' => $entityName . '.php',
            'content' => $this->renderFromTemplate('backend/model.php.tpl', $vars),
        ];

        $previews[] = [
            'path' => self::DEFAULT_BACKEND_APP_NAME . '/app/service',
            'fileName' => $entityName . 'Service.php',
            'content' => $this->renderFromTemplate('backend/service.php.tpl', $vars),
        ];

        $previews[] = [
            'path' => self::DEFAULT_BACKEND_APP_NAME . '/app/controller',
            'fileName' => $entityName . 'Controller.php',
            'content' => $this->renderFromTemplate('backend/controller.php.tpl', $vars),
        ];

        $previews[] = [
            'path' => self::DEFAULT_BACKEND_APP_NAME . '/route',
            'fileName' => $entityKebab . '.php',
            'content' => $this->renderFromTemplate('backend/route.php.tpl', $vars),
        ];

        $previews[] = [
            'path' => self::DEFAULT_FRONTEND_APP_NAME . '/src/api/' . $moduleName,
            'fileName' => $entityKebab . '.ts',
            'content' => $this->renderFromTemplate('frontend/api.ts.tpl', $vars),
        ];

        $previews[] = [
            'path' => self::DEFAULT_FRONTEND_APP_NAME . '/src/types/api',
            'fileName' => $entityKebab . '.ts',
            'content' => $this->renderFromTemplate('frontend/types.ts.tpl', $vars),
        ];

        $previews[] = [
            'path' => self::DEFAULT_FRONTEND_APP_NAME . '/src/views/' . $moduleName . '/' . $entityKebab,
            'fileName' => 'index.vue',
            'content' => $this->renderFromTemplate('frontend/index.vue.tpl', $vars),
        ];

        return $previews;
    }

    /**
     * 下载代码 zip
     */
    public function downloadZip(array $tableNames, string $pageType = 'classic'): array
    {
        $tableNames = array_values(array_filter(array_map('trim', $tableNames), fn($v) => $v !== ''));
        if (empty($tableNames)) {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        if (!class_exists('ZipArchive')) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, 'ZipArchive 扩展未启用');
        }

        $zipPath = (string) tempnam(sys_get_temp_dir(), 'codegen_');
        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, '创建 zip 失败');
        }

        foreach ($tableNames as $tableName) {
            $list = $this->getCodegenPreviewData($tableName, $pageType);
            foreach ($list as $item) {
                $path = (string) ($item['path'] ?? '');
                $fileName = (string) ($item['fileName'] ?? '');
                $content = (string) ($item['content'] ?? '');
                if ($path === '' || $fileName === '') {
                    continue;
                }

                $entryName = str_replace('\\', '/', rtrim($path, '/\\') . '/' . $fileName);
                $zip->addFromString($entryName, $content);
            }
        }

        $zip->close();
        $bin = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return [
            'fileName' => self::DEFAULT_BACKEND_APP_NAME . '-code.zip',
            'bin' => $bin,
        ];
    }

    private function renderFromTemplate(string $relativePath, array $vars): string
    {
        $base = dirname(__DIR__) . self::TEMPLATE_BASE_DIR;
        $file = $base . '/' . ltrim($relativePath, '/');
        if (!is_file($file)) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, '模板不存在: ' . $relativePath);
        }

        $tpl = (string) file_get_contents($file);
        return $this->renderer->render($tpl, $vars);
    }

    private function buildFieldSql(array $fieldConfigs): string
    {
        $selectFields = [];
        foreach ($fieldConfigs as $fc) {
            if (!is_array($fc)) {
                continue;
            }
            if (($fc['isShowInList'] ?? 0) != 1) {
                continue;
            }
            $col = trim((string) ($fc['columnName'] ?? ''));
            if ($col !== '' && !in_array($col, $selectFields, true)) {
                $selectFields[] = $col;
            }
        }
        return !empty($selectFields) ? implode(',', $selectFields) : '*';
    }

    private function buildFieldsTs(array $fieldConfigs): string
    {
        $lines = [];
        foreach ($fieldConfigs as $fc) {
            if (!is_array($fc)) {
                continue;
            }
            $name = trim((string) ($fc['fieldName'] ?? ''));
            if ($name === '') {
                continue;
            }
            $comment = trim((string) ($fc['fieldComment'] ?? ''));
            $type = (string) ($fc['fieldType'] ?? 'string');
            $tsType = $this->tsTypeByPhpType($type);
            if ($comment !== '') {
                $lines[] = "  /** {$comment} */";
            }
            $lines[] = "  {$name}?: {$tsType};";
        }

        return !empty($lines) ? implode("\n", $lines) : '  id?: string;';
    }

    private function tsTypeByPhpType(string $phpType): string
    {
        $t = strtolower(trim($phpType));
        if ($t === 'int' || $t === 'float') {
            return 'number';
        }
        if ($t === 'bool' || $t === 'boolean') {
            return 'boolean';
        }
        return 'string';
    }

    private function defaultFormTypeByColumnType(string $columnType, string $columnName): int
    {
        $t = $this->normalizeColumnType($columnType);
        $name = strtolower($columnName);

        if ($t === 'date') {
            return 8;
        }
        if (in_array($t, ['datetime', 'timestamp'], true)) {
            return 9;
        }
        if (str_contains($t, 'text') || str_contains($t, 'json')) {
            return 7;
        }
        if (str_contains($t, 'decimal') || str_contains($t, 'numeric') || str_contains($t, 'float') || str_contains($t, 'double')) {
            return 5;
        }
        if (str_contains($t, 'int') || str_contains($t, 'bigint') || str_contains($t, 'smallint') || str_contains($t, 'mediumint')) {
            if (str_contains($t, 'tinyint') && (str_starts_with($name, 'is_') || str_starts_with($name, 'has_'))) {
                return 6;
            }
            return 5;
        }

        return 1;
    }

    private function normalizeColumnType(string $columnType): string
    {
        $t = strtolower(trim($columnType));
        $t = str_replace(['unsigned', 'zerofill'], '', $t);
        $t = trim($t);
        if (str_contains($t, '(')) {
            $t = trim(explode('(', $t, 2)[0]);
        }
        return $t;
    }

    private function lowerFirst(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        return strtolower($s[0]) . substr($s, 1);
    }

    private function toKebabCase(string $s): string
    {
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            $isUpper = $ch >= 'A' && $ch <= 'Z';
            if ($i > 0 && $isUpper) {
                $out .= '-';
            }
            $out .= $ch;
        }
        $out = str_replace('_', '-', $out);
        return strtolower($out);
    }

    private function toCamelCase(string $s): string
    {
        $s = strtolower(trim($s));
        $parts = preg_split('/[_-]+/', $s) ?: [];
        $parts = array_values(array_filter($parts, fn($v) => $v !== ''));
        if (empty($parts)) {
            return '';
        }

        $first = array_shift($parts);
        $rest = array_map(fn($p) => strtoupper($p[0]) . substr($p, 1), $parts);
        return $first . implode('', $rest);
    }

    private function toPascalCase(string $s): string
    {
        $camel = $this->toCamelCase($s);
        if ($camel === '') {
            return '';
        }
        return strtoupper($camel[0]) . substr($camel, 1);
    }
}
