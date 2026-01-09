import request from "@/utils/request";

const BASE_URL = "/api/v1/{{entityKebab}}";

const {{entityKebab}}API = {
  /** 分页 */
  getPage(params: any) {
    return request({
      url: `${BASE_URL}/page`,
      method: "get",
      params,
    });
  },

  /** 表单 */
  getForm(id: string) {
    return request({
      url: `${BASE_URL}/${id}/form`,
      method: "get",
    });
  },

  /** 新增 */
  create(data: any) {
    return request({
      url: `${BASE_URL}`,
      method: "post",
      data,
    });
  },

  /** 修改 */
  update(id: string, data: any) {
    return request({
      url: `${BASE_URL}/${id}`,
      method: "put",
      data,
    });
  },

  /** 删除 */
  delete(ids: string) {
    return request({
      url: `${BASE_URL}/${ids}`,
      method: "delete",
    });
  },
};

export default {{entityKebab}}API;
