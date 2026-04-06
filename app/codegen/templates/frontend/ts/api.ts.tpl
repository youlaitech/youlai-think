import request from "@/utils/request";
import type { {$entityName}QueryParams, {$entityName}Item, {$entityName}Form } from "./types";
import type { PageResult } from "@/api/common";

const BASE_URL = "/api/v1/{$entityKebab}";

const {$entityKebab}API = {
  /** 分页 */
  getPage(queryParams: {$entityName}QueryParams) {
    return request<any, PageResult<{$entityName}Item>>({
      url: `${BASE_URL}`,
      method: "get",
      params: queryParams,
    });
  },

  /** 表单 */
  getForm(id: string) {
    return request<any, {$entityName}Form>({
      url: `${BASE_URL}/${id}/form`,
      method: "get",
    });
  },

  /** 新增 */
  create(data: {$entityName}Form) {
    return request({
      url: `${BASE_URL}`,
      method: "post",
      data,
    });
  },

  /** 修改 */
  update(id: string, data: {$entityName}Form) {
    return request({
      url: `${BASE_URL}/${id}`,
      method: "put",
      data,
    });
  },

  /** 删除 */
  deleteByIds(ids: string) {
    return request({
      url: `${BASE_URL}/${ids}`,
      method: "delete",
    });
  },
};

export default {$entityKebab}API;

// 重导出类型
export * from "./types";
