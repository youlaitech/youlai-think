import type { BaseQueryParams } from "@/api/common";

/** 列表对象 */
export interface {{entityName}}Item {
{{listFieldsTs}}
}

/** 表单对象 */
export interface {{entityName}}Form {
{{formFieldsTs}}
}

/** 查询参数 */
export interface {{entityName}}QueryParams extends BaseQueryParams {
{{queryFieldsTs}}
}
