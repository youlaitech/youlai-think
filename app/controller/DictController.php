<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\DictService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="06.字典接口")
 */
final class DictController extends ApiController
{
    //---------------------------------------------------
    // Dict
    //---------------------------------------------------
    /**
     * @OA\Get(
     *     path="/api/v1/dicts",
     *     summary="字典分页列表",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function page(): \think\Response
    {
        [$list, $total] = (new DictService())->getDictPage($this->request->param());
        return $this->okPage($list, $total);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dicts/options",
     *     summary="字典列表",
     *     tags={"06.字典接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(): \think\Response
    {
        $list = (new DictService())->getDictList();
        return $this->ok($list);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dicts/{id}/form",
     *     summary="获取字典表单数据",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="id", in="path", description="字典ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function form(int $id): \think\Response
    {
        $data = (new DictService())->getDictForm($id);
        return $this->ok($data);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dicts",
     *     summary="新增字典",
     *     tags={"06.字典接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->saveDict($data);
        return $this->ok();
    }

    /**
     * @OA\Put(
     *     path="/api/v1/dicts/{id}",
     *     summary="修改字典",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="id", in="path", description="字典ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->updateDict($id, $data);
        return $this->ok();
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/dicts/{ids}",
     *     summary="删除字典",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="ids", in="path", description="字典ID，多个以英文逗号(,)分割", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function delete(string $ids): \think\Response
    {
        (new DictService())->deleteDictByIds($ids);
        return $this->ok();
    }

    //---------------------------------------------------
    // Dict Items
    //---------------------------------------------------
    /**
     * @OA\Get(
     *     path="/api/v1/dicts/{dictCode}/items",
     *     summary="字典项分页列表",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="dictCode", in="path", description="字典编码", required=true),
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function itemPage(string $dictCode): \think\Response
    {
        [$list, $total] = (new DictService())->getDictItemPage($dictCode, $this->request->param());
        return $this->okPage($list, $total);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dicts/{dictCode}/items/options",
     *     summary="字典项下拉列表",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="dictCode", in="path", description="字典编码", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function items(string $dictCode): \think\Response
    {
        $list = (new DictService())->getDictItems($dictCode);
        return $this->ok($list);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dicts/{dictCode}/items/{itemId}/form",
     *     summary="获取字典项表单数据",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="dictCode", in="path", description="字典编码", required=true),
     *     @OA\Parameter(name="itemId", in="path", description="字典项ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function itemForm(string $dictCode, int $itemId): \think\Response
    {
        $data = (new DictService())->getDictItemForm($itemId);
        $data['dictCode'] = $dictCode;
        return $this->ok($data);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dicts/{dictCode}/items",
     *     summary="新增字典项",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="dictCode", in="path", description="字典编码", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function createItem(string $dictCode): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->saveDictItem($dictCode, $data);
        return $this->ok();
    }

    /**
     * @OA\Put(
     *     path="/api/v1/dicts/{dictCode}/items/{itemId}",
     *     summary="修改字典项",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="dictCode", in="path", description="字典编码", required=true),
     *     @OA\Parameter(name="itemId", in="path", description="字典项ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function updateItem(string $dictCode, int $itemId): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new DictService())->updateDictItem($dictCode, $itemId, $data);
        return $this->ok();
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/dicts/{dictCode}/items/{itemIds}",
     *     summary="删除字典项",
     *     tags={"06.字典接口"},
     *     @OA\Parameter(name="dictCode", in="path", description="字典编码", required=true),
     *     @OA\Parameter(name="itemIds", in="path", description="字典项ID，多个以英文逗号(,)分割", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function deleteItems(string $dictCode, string $itemIds): \think\Response
    {
        (new DictService())->deleteDictItems($dictCode, $itemIds);
        return $this->ok();
    }
}
