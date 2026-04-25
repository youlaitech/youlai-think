<?php declare(strict_types=1);

namespace app\system\controller;

use app\controller\BaseController;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\service\DictService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="06.字典接口")
 */
final class DictController extends BaseController
{
    // 字典
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
        [$list, $total] = $this->service(DictService::class)->getDictPage($this->getAllParams());
        return $this->success($list, $total);
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
        $list = $this->service(DictService::class)->getDictList();
        return $this->success($list);
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
        $data = $this->service(DictService::class)->getDictForm($id);
        return $this->success($data);
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
    #[Log(actionType: ActionType::DICT_CREATE)]
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        $this->service(DictService::class)->saveDict($data);
        return $this->success();
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
    #[Log(actionType: ActionType::DICT_UPDATE)]
    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        $this->service(DictService::class)->updateDict($id, $data);
        return $this->success();
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
    #[Log(actionType: ActionType::DICT_DELETE)]
    public function delete(string $ids): \think\Response
    {
        $this->service(DictService::class)->deleteDictByIds($ids);
        return $this->success();
    }

    // 字典项
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
        [$list, $total] = $this->service(DictService::class)->getDictItemPage($dictCode, $this->getAllParams());
        return $this->success($list, $total);
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
        $list = $this->service(DictService::class)->getDictItems($dictCode);
        return $this->success($list);
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
        $data = $this->service(DictService::class)->getDictItemForm($itemId);
        $data['dict_code'] = $dictCode;
        return $this->success($data);
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
    #[Log(actionType: ActionType::DICT_CREATE)]
    public function createItem(string $dictCode): \think\Response
    {
        $data = $this->mergeJsonParams();
        $this->service(DictService::class)->saveDictItem($dictCode, $data);
        return $this->success();
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
    #[Log(actionType: ActionType::DICT_UPDATE)]
    public function updateItem(string $dictCode, int $itemId): \think\Response
    {
        $data = $this->mergeJsonParams();
        $this->service(DictService::class)->updateDictItem($dictCode, $itemId, $data);
        return $this->success();
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
    #[Log(actionType: ActionType::DICT_DELETE)]
    public function deleteItems(string $dictCode, string $itemIds): \think\Response
    {
        $this->service(DictService::class)->deleteDictItems($dictCode, $itemIds);
        return $this->success();
    }
}
