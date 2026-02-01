<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Dict;
use app\model\DictItem;
use think\facade\Db;

final class DictService
{
    public function getDictPage(array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $keywords = trim((string) ($queryParams['keywords'] ?? ''));
        $status = $queryParams['status'] ?? null;

        $q = Dict::where('is_deleted', 0);

        if ($keywords !== '') {
            $kw = '%' . $keywords . '%';
            $q = $q->whereLike('name|dict_code', $kw);
        }

        if ($status !== null && $status !== '') {
            $q = $q->where('status', (int) $status);
        }

        $total = (int) (clone $q)->count();

        $rows = $q
            ->order('id', 'desc')
            ->page($pageNum, $pageSize)
            ->field('id,name,dict_code,status')
            ->select();

        $list = [];
        foreach ($rows as $row) {
            $r = $row->toArray();
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'name' => (string) ($r['name'] ?? ''),
                'dictCode' => (string) ($r['dict_code'] ?? ''),
                'status' => isset($r['status']) ? (int) $r['status'] : 0,
            ];
        }

        return [$list, $total];
    }

    public function getDictList(): array
    {
        $rows = Db::name('sys_dict')
            ->where('is_deleted', 0)
            ->order('id', 'asc')
            ->field('dict_code,name')
            ->select()
            ->toArray();
        $list = [];
        foreach ($rows as $row) {
            $dictCode = (string) ($row['dict_code'] ?? '');
            $name = (string) ($row['name'] ?? '');
            if ($dictCode === '' || $name === '') {
                continue;
            }

            $list[] = [
                'label' => $name,
                'value' => $dictCode,
            ];
        }

        return $list;
    }

    public function getDictForm(int $id): array
    {
        $dict = Dict::where('id', $id)->where('is_deleted', 0)->find();
        if ($dict === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '字典不存在');
        }

        $d = $dict->toArray();

        return [
            'id' => (string) ($d['id'] ?? ''),
            'name' => $d['name'] ?? null,
            'dictCode' => $d['dict_code'] ?? null,
            'status' => isset($d['status']) ? (int) $d['status'] : null,
            'remark' => $d['remark'] ?? null,
        ];
    }

    public function saveDict(array $data): bool
    {
        $name = trim((string) ($data['name'] ?? ''));
        $dictCode = trim((string) ($data['dictCode'] ?? $data['dict_code'] ?? ''));

        if ($name === '' || $dictCode === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $exists = Dict::where('is_deleted', 0)->where('dict_code', $dictCode)->count();
        if ($exists > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '字典编码已存在');
        }

        $now = date('Y-m-d H:i:s');
        $dict = new Dict();
        $dict->save([
            'name' => $name,
            'dict_code' => $dictCode,
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
            'remark' => $data['remark'] ?? null,
            'create_time' => $now,
            'update_time' => $now,
            'is_deleted' => 0,
        ]);

        return true;
    }

    public function updateDict(int $id, array $data): bool
    {
        $dict = Dict::where('id', $id)->where('is_deleted', 0)->find();
        if ($dict === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '字典不存在');
        }

        $name = trim((string) ($data['name'] ?? ''));
        $dictCode = trim((string) ($data['dictCode'] ?? $data['dict_code'] ?? ''));

        if ($name === '' || $dictCode === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $exists = Dict::where('is_deleted', 0)
            ->where('dict_code', $dictCode)
            ->where('id', '<>', $id)
            ->count();
        if ($exists > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '字典编码已存在');
        }

        $old = $dict->toArray();
        $oldCode = (string) ($old['dict_code'] ?? '');

        // 关键点：如果字典编码发生变化，需要同步更新 sys_dict_item.dict_code
        Db::transaction(function () use ($dict, $id, $data, $name, $dictCode, $oldCode) {
            $dict->save([
                'name' => $name,
                'dict_code' => $dictCode,
                'status' => isset($data['status']) ? (int) $data['status'] : 1,
                'remark' => $data['remark'] ?? null,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            if ($oldCode !== '' && $oldCode !== $dictCode) {
                Db::name('sys_dict_item')
                    ->where('dict_code', $oldCode)
                    ->update(['dict_code' => $dictCode]);
            }
        });

        return true;
    }

    public function deleteDictByIds(string $ids): bool
    {
        $ids = trim($ids);
        if ($ids === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), fn($v) => $v !== ''));
        $idList = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
            }
            $idList[] = (int) $p;
        }

        // 关键点：字典删除需要同时清理字典项（sys_dict_item 无 is_deleted 字段）
        Db::transaction(function () use ($idList) {
            $dictCodes = Db::name('sys_dict')
                ->whereIn('id', $idList)
                ->where('is_deleted', 0)
                ->column('dict_code');

            Db::name('sys_dict')->whereIn('id', $idList)->update([
                'is_deleted' => 1,
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $dictCodes = array_values(array_unique(array_filter($dictCodes, fn($v) => $v !== null && $v !== '')));
            if (!empty($dictCodes)) {
                Db::name('sys_dict_item')->whereIn('dict_code', $dictCodes)->delete();
            }
        });

        return true;
    }

    public function getDictItemPage(string $dictCode, array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $keywords = trim((string) ($queryParams['keywords'] ?? ''));

        $q = DictItem::where('dict_code', $dictCode);

        if ($keywords !== '') {
            $kw = '%' . $keywords . '%';
            $q = $q->whereLike('label|value', $kw);
        }

        $total = (int) (clone $q)->count();

        $rows = $q
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->page($pageNum, $pageSize)
            ->field('id,dict_code,label,value,status,sort')
            ->select();

        $list = [];
        foreach ($rows as $row) {
            $r = $row->toArray();
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'dictCode' => (string) ($r['dict_code'] ?? ''),
                'label' => (string) ($r['label'] ?? ''),
                'value' => (string) ($r['value'] ?? ''),
                'status' => isset($r['status']) ? (int) $r['status'] : 0,
                'sort' => isset($r['sort']) ? (int) $r['sort'] : null,
            ];
        }

        return [$list, $total];
    }

    public function getDictItems(string $dictCode): array
    {
        $rows = DictItem::where('dict_code', $dictCode)
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->field('value,label,tag_type')
            ->select();

        $list = [];
        foreach ($rows as $row) {
            $r = $row->toArray();
            $list[] = [
                'value' => (string) ($r['value'] ?? ''),
                'label' => (string) ($r['label'] ?? ''),
                'tagType' => (string) ($r['tag_type'] ?? ''),
            ];
        }

        return $list;
    }

    public function getDictItemForm(int $itemId): array
    {
        $item = DictItem::where('id', $itemId)->find();
        if ($item === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '字典项不存在');
        }

        $i = $item->toArray();
        return [
            'id' => (string) ($i['id'] ?? ''),
            'dictCode' => $i['dict_code'] ?? null,
            'label' => $i['label'] ?? null,
            'value' => $i['value'] ?? null,
            'status' => isset($i['status']) ? (int) $i['status'] : null,
            'sort' => isset($i['sort']) ? (int) $i['sort'] : null,
            'tagType' => $i['tag_type'] ?? '',
        ];
    }

    public function saveDictItem(string $dictCode, array $data): bool
    {
        $label = trim((string) ($data['label'] ?? ''));
        $value = trim((string) ($data['value'] ?? ''));

        if ($label === '' || $value === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $now = date('Y-m-d H:i:s');
        $item = new DictItem();
        $item->save([
            'dict_code' => $dictCode,
            'label' => $label,
            'value' => $value,
            'tag_type' => $data['tagType'] ?? $data['tag_type'] ?? null,
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 0,
            'remark' => $data['remark'] ?? null,
            'create_time' => $now,
            'update_time' => $now,
        ]);

        return true;
    }

    public function updateDictItem(string $dictCode, int $itemId, array $data): bool
    {
        $item = DictItem::where('id', $itemId)->find();
        if ($item === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '字典项不存在');
        }

        $label = trim((string) ($data['label'] ?? ''));
        $value = trim((string) ($data['value'] ?? ''));

        if ($label === '' || $value === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $item->save([
            'dict_code' => $dictCode,
            'label' => $label,
            'value' => $value,
            'tag_type' => $data['tagType'] ?? $data['tag_type'] ?? null,
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 0,
            'remark' => $data['remark'] ?? null,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function deleteDictItems(string $dictCode, string $itemIds): bool
    {
        $itemIds = trim($itemIds);
        if ($itemIds === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $itemIds)), fn($v) => $v !== ''));
        $idList = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
            }
            $idList[] = (int) $p;
        }

        DictItem::where('dict_code', $dictCode)->whereIn('id', $idList)->delete();
        return true;
    }
}
