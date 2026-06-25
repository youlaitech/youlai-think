import type { BaseQueryParams } from "@/api/common";

/** 列表对象 */
export interface {$entityName}Item {
  id?: string;
{$listFieldsTs}
}

/** 表单对象 */
export interface {$entityName}Form {
  id?: string;
{$formFieldsTs}
}

/** 查询参数 */
export interface {$entityName}QueryParams extends BaseQueryParams {
{$queryFieldsTs}
}
