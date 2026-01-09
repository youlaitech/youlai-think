export interface {{entityName}} {
{{fieldsTs}}
}

/** 查询参数 */
export interface {{entityName}}Query extends PageQuery {
  keywords?: string;
}
