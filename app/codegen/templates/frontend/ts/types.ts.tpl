import type { PageQuery } from "@/api/common";

/** 列表对象 */
export interface {$entityName}Item {
  /** 稳定主键，不受列表显示配置影响 */
  id: string;
{$listFieldsTs}
}

/** 表单对象 */
export interface {$entityName}Form {
  id?: string;
{$formFieldsTs}
}

/** 查询参数 */
export interface {$entityName}QueryParams extends PageQuery {
{$queryFieldsTs}
}
