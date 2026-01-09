<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\DictService;

final class DictController extends ApiController
{
    //---------------------------------------------------
    // Dict
    //---------------------------------------------------
    public function page(): \think\Response
    {
        [$list, $total] = (new DictService())->getDictPage($this->request->param());
        return $this->okPage($list, $total);
    }

    public function index(): \think\Response
    {
        $list = (new DictService())->getDictList();
        return $this->ok($list);
    }

    public function form(int $id): \think\Response
    {
        $data = (new DictService())->getDictForm($id);
        return $this->ok($data);
    }

    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->saveDict($data);
        return $this->ok();
    }

    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->updateDict($id, $data);
        return $this->ok();
    }

    public function delete(string $ids): \think\Response
    {
        (new DictService())->deleteDictByIds($ids);
        return $this->ok();
    }

    //---------------------------------------------------
    // Dict Items
    //---------------------------------------------------
    public function itemPage(string $dictCode): \think\Response
    {
        [$list, $total] = (new DictService())->getDictItemPage($dictCode, $this->request->param());
        return $this->okPage($list, $total);
    }

    public function items(string $dictCode): \think\Response
    {
        $list = (new DictService())->getDictItems($dictCode);
        return $this->ok($list);
    }

    public function itemForm(string $dictCode, int $itemId): \think\Response
    {
        $data = (new DictService())->getDictItemForm($itemId);
        $data['dictCode'] = $dictCode;
        return $this->ok($data);
    }

    public function createItem(string $dictCode): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->saveDictItem($dictCode, $data);
        return $this->ok();
    }

    public function updateItem(string $dictCode, int $itemId): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->updateDictItem($dictCode, $itemId, $data);
        return $this->ok();
    }

    public function deleteItems(string $dictCode, string $itemIds): \think\Response
    {
        (new DictService())->deleteDictItems($dictCode, $itemIds);
        return $this->ok();
    }
}
